<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Bulk-register 3D models and diagrams in the enrichment asset registry.
 *
 * Idempotent: an asset is matched by name, so re-running after filling in a
 * missing URL updates that row rather than creating a duplicate. This is the
 * point -- a catalogue of models is assembled over time, and the URLs arrive
 * later than the names do.
 *
 * An asset with no URL is registered but left DISABLED, so it appears in the
 * admin list as outstanding work without ever being offered to an editor as
 * something they can insert.
 *
 * Usage:
 *   php cli/import_assets.php --file=assets.csv
 *   php cli/import_assets.php --file=assets.csv --dry-run
 *
 * CSV columns (header row required):
 *   name, assettype, viewer, url, posterurl, licence, attribution, description
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(is_file(__DIR__ . '/../../../../config.php')
    ? __DIR__ . '/../../../../config.php'
    : (getenv('MOODLE_ROOT')
        ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php'
        : '/var/www/moodle/config.php'));
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'file' => '',
    'dry-run' => false,
    'help' => false,
], ['f' => 'file', 'h' => 'help']);

if ($options['help'] || $options['file'] === '') {
    cli_writeln("Bulk-register enrichment assets from a CSV.

Options:
  -f, --file=PATH   CSV to import (required)
      --dry-run     Report what would change without writing
  -h, --help        This help

CSV columns: name, assettype, viewer, url, posterurl, licence, attribution, description
");
    exit(0);
}

if (!is_readable($options['file'])) {
    cli_error('Cannot read ' . $options['file']);
}

/**
 * Turn a Sketchfab page URL into its embeddable form.
 *
 * Editors copy the address bar, which is the model page, not the embed. Doing
 * the conversion here means nobody has to know the difference.
 *
 * @param string $url Whatever was pasted.
 * @return string An embeddable URL.
 */
function local_contentchecker_embed_url(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    // https://sketchfab.com/3d-models/some-name-<32-hex-uid>  ->  /models/<uid>/embed
    if (preg_match('#^https?://(?:www\.)?sketchfab\.com/3d-models/[^/]*?([0-9a-f]{32})/?$#i',
            $url, $m)) {
        return 'https://sketchfab.com/models/' . strtolower($m[1]) . '/embed';
    }
    // Already an embed URL, or a bare UID.
    if (preg_match('#^https?://(?:www\.)?sketchfab\.com/models/([0-9a-f]{32})#i', $url, $m)) {
        return 'https://sketchfab.com/models/' . strtolower($m[1]) . '/embed';
    }
    if (preg_match('#^[0-9a-f]{32}$#i', $url)) {
        return 'https://sketchfab.com/models/' . strtolower($url) . '/embed';
    }

    return $url;
}

$handle = fopen($options['file'], 'r');
$header = fgetcsv($handle, 0, ',', '"', '');
if (!$header) {
    cli_error('Empty CSV.');
}
$header = array_map(fn($h) => strtolower(trim($h)), $header);

$created = $updated = $skipped = $disabled = 0;
$now = time();
$adminid = get_admin()->id;

while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    if (count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) {
        continue;
    }
    $row = array_combine($header, array_pad($line, count($header), ''));
    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        continue;
    }

    $url = local_contentchecker_embed_url((string) ($row['url'] ?? ''));

    // No URL means the model is catalogued but not yet usable. Registering it
    // disabled keeps it visible as outstanding without offering an editor a
    // broken embed.
    $enabled = $url !== '' ? 1 : 0;
    if (!$enabled) {
        $disabled++;
    }

    $record = (object) [
        'name' => $name,
        'assettype' => trim((string) ($row['assettype'] ?? 'model3d')) ?: 'model3d',
        'viewer' => trim((string) ($row['viewer'] ?? 'iframe')) ?: 'iframe',
        'url' => $url,
        'posterurl' => trim((string) ($row['posterurl'] ?? '')),
        'body' => '',
        'description' => trim((string) ($row['description'] ?? '')),
        'licence' => trim((string) ($row['licence'] ?? '')),
        'attribution' => trim((string) ($row['attribution'] ?? '')),
        'enabled' => $enabled,
        'sortorder' => 0,
        'usermodified' => $adminid,
        'timemodified' => $now,
    ];

    $existing = $DB->get_record('local_cchecker_assets', ['name' => $name]);

    if ($options['dry-run']) {
        cli_writeln(sprintf('  %-8s %-58s %s',
            $existing ? 'update' : 'create',
            \core_text::substr($name, 0, 58),
            $url !== '' ? 'url ok' : 'NO URL -> disabled'));
        $existing ? $updated++ : $created++;
        continue;
    }

    if ($existing) {
        $record->id = $existing->id;
        // Never downgrade a working asset to disabled just because this run
        // had no URL for it.
        if ($url === '' && trim((string) $existing->url) !== '') {
            $record->url = $existing->url;
            $record->enabled = $existing->enabled;
            $skipped++;
        }
        $DB->update_record('local_cchecker_assets', $record);
        $updated++;
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_cchecker_assets', $record);
        $created++;
    }
}
fclose($handle);

cli_writeln('');
cli_writeln("created:  {$created}");
cli_writeln("updated:  {$updated}");
cli_writeln("no URL (registered but disabled): {$disabled}");
if ($skipped) {
    cli_writeln("kept existing URL for {$skipped} row(s) that had none in the CSV");
}

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
 * Seed and ingest the evidence corpus.
 *
 * Admin bootstrap only. Nobody needs CLI access to USE the verifier -- this
 * exists so a site administrator can populate the corpus once.
 *
 * Usage:
 *   sudo -u www-data php cli/seed_corpus.php --list
 *   sudo -u www-data php cli/seed_corpus.php --add=Anatomical_plane --type=wikipedia --tier=3
 *   sudo -u www-data php cli/seed_corpus.php --ingest
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_emdverify\local\corpus;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'list' => false,
    'add' => '',
    'title' => '',
    'type' => 'wikipedia',
    'tier' => 3,
    'ingest' => false,
], ['h' => 'help', 'l' => 'list', 'i' => 'ingest']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode("\n  ", $unrecognised)));
}

if ($options['help'] || (!$options['list'] && !$options['add'] && !$options['ingest'])) {
    cli_writeln("Seed and ingest the local_emdverify evidence corpus.

Options:
  -l, --list        List configured sources.
      --add=REF     Add a source. For type=wikipedia, REF is the page title.
      --title=NAME  Display title (defaults to REF).
      --type=TYPE   wikipedia or url. Default wikipedia.
      --tier=N      1 licensed textbook, 2 guideline body, 3 open reference.
  -i, --ingest      Fetch, chunk and embed every enabled source.
  -h, --help        Show this help.

A tier-3-only corpus is adequate for checking that the pipeline behaves. It is
not an authoritative medical corpus, and the UI says so wherever findings are
shown.");
    exit(0);
}

if ($options['list']) {
    $sources = $DB->get_records('local_emdverify_source', null, 'tier, title');
    if (!$sources) {
        cli_writeln('No sources configured.');
        exit(0);
    }
    foreach ($sources as $source) {
        cli_writeln(sprintf("  [%d] tier %d  %-40s %s (%d chunks)",
            $source->id, $source->tier, $source->title, $source->sourcetype,
            $source->numchunks));
    }
    [$numsources, $numchunks] = corpus::status();
    cli_writeln(sprintf("\n%d enabled sources, %d passages embedded.",
        $numsources, $numchunks));
    exit(0);
}

if ($options['add']) {
    $ref = trim($options['add']);
    $tier = max(1, min(3, (int) $options['tier']));

    if ($DB->record_exists('local_emdverify_source', ['ref' => $ref])) {
        cli_writeln("Already present: {$ref}");
        exit(0);
    }

    $id = $DB->insert_record('local_emdverify_source', (object) [
        'sourcetype' => $options['type'],
        'ref' => $ref,
        'title' => $options['title'] ?: str_replace('_', ' ', $ref),
        'tier' => $tier,
        'enabled' => 1,
        'numchunks' => 0,
        'timemodified' => time(),
    ]);
    cli_writeln("Added source {$id}: {$ref} (tier {$tier})");
    exit(0);
}

if ($options['ingest']) {
    $corpus = new corpus();
    $sources = $DB->get_records('local_emdverify_source', ['enabled' => 1], 'id');
    if (!$sources) {
        cli_error('No enabled sources. Add some with --add first.');
    }

    $total = 0;
    foreach ($sources as $source) {
        cli_write(sprintf("  %-40s ", \core_text::substr($source->title, 0, 40)));
        try {
            $count = $corpus->ingest($source);
            $total += $count;
            cli_writeln("{$count} chunks");
        } catch (\Throwable $e) {
            cli_writeln('FAILED: ' . $e->getMessage());
        }
    }
    cli_writeln("\n{$total} passages embedded.");
    exit(0);
}

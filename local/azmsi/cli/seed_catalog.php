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
 * CLI: seed the AZMSI program catalog (Year -> Quarter -> 48 courses).
 *
 * Idempotent: creates the custom fields + category tree + courses on first run,
 * and updates names/status/category on subsequent runs. Safe to re-run.
 *
 *   php public/local/azmsi/cli/seed_catalog.php          # seed/update
 *   php public/local/azmsi/cli/seed_catalog.php --dry-run # show what it would do
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// This plugin is deployed by symlink, so __DIR__ resolves to the real repo path
// (outside Moodle) — the conventional relative require would miss config.php.
// Resolve it robustly: MOODLE_ROOT env, then the in-tree relative path, then the
// known public/-layout root.
$azmsiconfigcandidates = [
    getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : null,
    __DIR__ . '/../../../config.php',
    '/var/www/moodle/config.php',
];
foreach ($azmsiconfigcandidates as $azmsiconfig) {
    if ($azmsiconfig && is_file($azmsiconfig)) {
        require($azmsiconfig);
        break;
    }
}
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'dry-run' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Seed the AZMSI program catalog (idempotent).\n");
    cli_writeln("Options:");
    cli_writeln("  --dry-run   Print the catalog that would be seeded, change nothing.");
    cli_writeln("  -h, --help  This help.");
    exit(0);
}

if ($options['dry-run']) {
    $total = 0;
    foreach (\local_azmsi\local\program::catalog() as $quarter => $info) {
        foreach ($info['courses'] as $entry) {
            cli_writeln(sprintf('Y%d Q%-2d  %-9s  %s', $info['year'], $quarter, $entry[0], $entry[1]));
            $total++;
        }
    }
    cli_writeln("\n$total courses would be seeded/updated. (dry run — no changes made)");
    exit(0);
}

cli_writeln('Seeding AZMSI catalog…');
$result = \local_azmsi\local\program::seed();
cli_writeln(sprintf('Done. Created %d, updated %d course(s).', $result['created'], $result['updated']));
exit(0);

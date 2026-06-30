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
 * CLI: make the AZMSI role dashboard the default /my/ content for every user.
 *
 * Ensures the system default Dashboard contains exactly one full-width
 * block_azmsi_dashboard (which renders the admin console / faculty dashboard /
 * student dashboard from the viewer's capabilities), then resets every user's
 * Dashboard to that default so existing customisations are replaced.
 *
 * Idempotent and safe to re-run.
 *
 * Usage:
 *   php local/azmsi/cli/setup_dashboard.php             # configure + reset all users
 *   php local/azmsi/cli/setup_dashboard.php --no-reset  # configure default only
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/my/lib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'no-reset' => false],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Make the AZMSI role dashboard the default /my/ content.\n");
    cli_writeln("Options:");
    cli_writeln("  --no-reset   Configure the default page only; do not reset existing users.");
    cli_writeln("  -h, --help");
    exit(0);
}

if (!$DB->record_exists('block', ['name' => 'azmsi_dashboard'])) {
    cli_error('block_azmsi_dashboard is not installed. Run the upgrade first.');
}

$systemcontext = context_system::instance();
$systempage = my_get_page(null, MY_PAGE_PRIVATE);
if (!$systempage) {
    cli_error('Could not load the system default Dashboard page (my_pages).');
}

// Make the default page contain exactly one azmsi_dashboard block in 'content'.
$existing = $DB->get_records('block_instances', [
    'parentcontextid' => $systemcontext->id,
    'pagetypepattern' => 'my-index',
    'subpagepattern'  => $systempage->id,
]);
$keptazmsi = false;
foreach ($existing as $instance) {
    if ($instance->blockname === 'azmsi_dashboard' && !$keptazmsi && $instance->defaultregion === 'content') {
        $keptazmsi = true; // Keep the first correctly-placed AZMSI block.
        continue;
    }
    blocks_delete_instance($instance);
    cli_writeln("Removed block '{$instance->blockname}' from the default Dashboard.");
}

if (!$keptazmsi) {
    $block = new stdClass();
    $block->blockname        = 'azmsi_dashboard';
    $block->parentcontextid  = $systemcontext->id;
    $block->showinsubcontexts = 0;
    $block->requiredbytheme  = 0;
    $block->pagetypepattern  = 'my-index';
    $block->subpagepattern   = $systempage->id;
    $block->defaultregion    = 'content';
    $block->defaultweight    = 0;
    $block->configdata       = '';
    $block->timecreated      = time();
    $block->timemodified     = time();
    $block->id = $DB->insert_record('block_instances', $block);
    context_block::instance($block->id);
    cli_writeln("Added block_azmsi_dashboard (instance {$block->id}) to the 'content' region.");
} else {
    cli_writeln('Default Dashboard already carries the AZMSI dashboard block.');
}

// Make sure the Dashboard is enabled and is the site home for users.
set_config('enabledashboard', 1);
cli_writeln('Dashboard enabled (enabledashboard = 1).');

if ($options['no-reset']) {
    purge_all_caches();
    cli_writeln('Configured default only (--no-reset). Caches purged. Done.');
    exit(0);
}

// Reset every user's Dashboard to the default so all roles pick up the role
// dashboard (replaces any prior per-user customisation).
cli_writeln('Resetting all user Dashboards to the default...');
my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index');
cli_writeln('All user Dashboards reset.');

purge_all_caches();
cli_writeln('Caches purged. Done — /my/ now shows the AZMSI role dashboard for every user.');

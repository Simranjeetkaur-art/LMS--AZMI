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

namespace theme_azmsi\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use core\context\system as context_system;

/**
 * "Switch portal" sidebar component.
 *
 * Capability-gated cross-portal links (03_SCREEN_SPECS S4, 01_ARCHITECTURE §6).
 * Only links the current user is permitted to follow are exported, so the UI
 * never advertises a portal the user cannot reach. The capability checks are
 * guarded so the component degrades to "no links" if local_azmsi is not yet
 * installed (Agent 03) rather than throwing.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class switch_portal implements renderable, templatable {
    /**
     * Export the capability-permitted portal links for the template.
     *
     * @param renderer_base $output
     * @return array template context ('haslinks' => bool, 'links' => array)
     */
    public function export_for_template(renderer_base $output): array {
        $context = context_system::instance();
        $links = [];

        // Faculty portal (teal accent) — gated by the faculty-view capability.
        if ($this->can('local/azmsi:viewfacultyportal', $context)) {
            $links[] = [
                'url' => (new moodle_url('/local/azmsi/faculty.php'))->out(false),
                'label' => get_string('faculty', 'theme_azmsi'),
                'accent' => 'faculty',
            ];
        }

        // Admin console (gold accent) — gated by the admin-console capability.
        if ($this->can('local/azmsi:viewadminconsole', $context)) {
            $links[] = [
                'url' => (new moodle_url('/local/azmsi/admin.php'))->out(false),
                'label' => get_string('adminconsole', 'theme_azmsi'),
                'accent' => 'admin',
            ];
        }

        return [
            'label' => get_string('switchportal', 'theme_azmsi'),
            'haslinks' => !empty($links),
            'links' => $links,
        ];
    }

    /**
     * Capability check guarded against the capability not being installed yet.
     *
     * get_capability_info() returns null (no exception) for an unknown
     * capability, so this is safe before local_azmsi is installed.
     *
     * @param string $capability
     * @param \context $context
     * @return bool
     */
    protected function can(string $capability, \context $context): bool {
        if (get_capability_info($capability) === null) {
            return false;
        }
        return has_capability($capability, $context);
    }
}

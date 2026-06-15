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

/**
 * AZMSI core renderer.
 *
 * Extends Moove's renderer so all Moove output overrides are preserved; adds
 * only the AZMSI-specific output methods consumed by the (additively overridden)
 * drawers template via the `{{{ output.* }}}` pattern.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_moove\output\core_renderer {
    /**
     * Render the capability-gated "Switch portal" sidebar component.
     *
     * Returns an empty string when the current user has no permitted portal
     * links, so the markup simply does not appear. Called from the drawers
     * template as {{{ output.azmsi_switch_portal }}}.
     *
     * @return string HTML
     */
    public function azmsi_switch_portal(): string {
        $portal = new switch_portal();
        $data = $portal->export_for_template($this);
        if (empty($data['haslinks'])) {
            return '';
        }
        return $this->render_from_template('theme_azmsi/switchportal', $data);
    }
}

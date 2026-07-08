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

namespace mod_flashdeck\output;

/**
 * Renderer for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the student study page.
     *
     * @param study_page $page the renderable
     * @return string HTML
     */
    protected function render_study_page(study_page $page): string {
        return $this->render_from_template('mod_flashdeck/study_page', $page->export_for_template($this));
    }

    /**
     * Render the teacher card-management page.
     *
     * @param manage_page $page the renderable
     * @return string HTML
     */
    protected function render_manage_page(manage_page $page): string {
        return $this->render_from_template('mod_flashdeck/manage_cards', $page->export_for_template($this));
    }
}

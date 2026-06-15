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
 * AZMSI student dashboard block.
 *
 * Renders the student overview (continue-card, per-course %, due-this-week,
 * program map) from local_azmsi_get_student_overview. No hardcoded data.
 *
 * @package    block_azmsi_dashboard
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Student dashboard block class.
 *
 * @package    block_azmsi_dashboard
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_azmsi_dashboard extends block_base {
    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_azmsi_dashboard');
    }

    /**
     * The block has no instance-level config body.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Where the block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'my'             => true,
            'course-view'    => false,
            'site'           => false,
        ];
    }

    /**
     * Build the block content.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        global $USER;
        $renderer = $this->page->get_renderer('block_azmsi_dashboard');
        $dashboard = new \block_azmsi_dashboard\output\dashboard((int) $USER->id);
        $this->content->text = $renderer->render($dashboard);
        return $this->content;
    }

    /**
     * No global config.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }
}

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
     * Render this block full-bleed with no title bar — it is a whole dashboard,
     * not a sidebar card.
     *
     * @return bool
     */
    public function hide_header() {
        return true;
    }

    /**
     * Mark the block wrapper so the theme can remove card chrome.
     *
     * @return array
     */
    public function html_attributes() {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_azmsi_dashboard--bleed';
        return $attributes;
    }

    /**
     * Build the block content — the AZMSI dashboard for the viewer's role.
     *
     * Admins/managers see the admin console, faculty see the faculty dashboard,
     * and everyone else sees the student dashboard. Each is a live, cron/event
     * backed renderable; nothing is hardcoded.
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
        $systemcontext = context_system::instance();
        $renderable = self::role_dashboard((int) $USER->id, $systemcontext);

        // A single renderer can render any named_templatable regardless of the
        // owning component, so this works for both local_azmsi and this block.
        $renderer = $this->page->get_renderer('local_azmsi');
        $this->content->text = $renderer->render($renderable);
        return $this->content;
    }

    /**
     * Pick the dashboard renderable for the viewer's capabilities.
     *
     * @param int $userid
     * @param \context $systemcontext
     * @return \renderable
     */
    protected static function role_dashboard(int $userid, \context $systemcontext): \renderable {
        if (has_capability('local/azmsi:viewadminconsole', $systemcontext)) {
            $canmanage = has_capability('local/azmsi:managepipeline', $systemcontext);
            return new \local_azmsi\output\admin_console($canmanage);
        }
        if (has_capability('local/azmsi:viewfacultyportal', $systemcontext)) {
            return new \local_azmsi\output\faculty_dashboard($userid);
        }
        return new \block_azmsi_dashboard\output\dashboard($userid);
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

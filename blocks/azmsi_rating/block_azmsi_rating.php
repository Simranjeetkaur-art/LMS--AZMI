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
 * Course + instructor rating block (Phase 3).
 *
 * @package    block_azmsi_rating
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_azmsi\local\reviews;
use local_azmsi\output\rating_form;

/**
 * Lets an enrolled learner rate the course and its instructor (1-5 stars + review).
 */
class block_azmsi_rating extends block_base {

    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_azmsi_rating');
    }

    /**
     * Only on course pages.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course-view' => true, 'mod' => false, 'my' => false, 'site' => false];
    }

    /**
     * One per course.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Render the rating form for enrolled learners.
     *
     * @return \stdClass
     */
    public function get_content() {
        global $USER, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($COURSE->id) || $COURSE->id == SITEID) {
            return $this->content;
        }
        $context = context_course::instance($COURSE->id);
        // Only enrolled learners (submitreview capability) see the form.
        if (!has_capability('local/azmsi:submitreview', $context)) {
            return $this->content;
        }

        $this->content->text = $OUTPUT->render(new rating_form($COURSE->id));
        return $this->content;
    }
}

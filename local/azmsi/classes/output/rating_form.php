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

namespace local_azmsi\output;

use core\output\named_templatable;
use local_azmsi\local\reviews;
use moodle_url;
use renderable;
use renderer_base;

/**
 * Course + instructor rating form for enrolled learners.
 *
 * Shared by block_azmsi_rating and the eMD course-preview sidebar.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_form implements named_templatable, renderable {

    /** @var int */
    protected $courseid;

    /**
     * @param int $courseid
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'block_azmsi_rating/form';
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $context = \context_course::instance($this->courseid);
        $existing = reviews::get_user_review($this->courseid, (int) $USER->id);
        $statusmsg = '';
        if ($existing) {
            $statusmsg = get_string('rating' . $existing->status, 'local_azmsi');
        }

        return [
            'actionurl'        => (new moodle_url('/local/azmsi/rate.php'))->out(false),
            'courseid'         => $this->courseid,
            'sesskey'          => sesskey(),
            'intro'            => get_string('ratingblockintro', 'local_azmsi'),
            'hasstatus'        => $statusmsg !== '',
            'statusmsg'        => $statusmsg,
            'coursestars'      => self::star_options((int) ($existing->coursestars ?? 0)),
            'instructorstars'  => self::star_options((int) ($existing->instructorstars ?? 0)),
            'coursereview'     => $existing->coursereview ?? '',
            'instructorreview' => $existing->instructorreview ?? '',
        ];
    }

    /**
     * Build the 1-5 radio options with the current value checked.
     *
     * @param int $selected
     * @return array
     */
    protected static function star_options(int $selected): array {
        $out = [];
        for ($i = 1; $i <= 5; $i++) {
            $out[] = ['value' => $i, 'checked' => ($i === $selected)];
        }
        return $out;
    }
}

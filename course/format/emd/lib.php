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
 * eMD course format definition.
 *
 * Behaves like topics/weeks (numbered sections, one week per section) but will
 * render the AGENT_04 "master template": Course Introduction -> Weekly Modules
 * -> Final Exam, with the standard weekly activity sequence.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Main class for the eMD course format.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_emd extends core_courseformat\base {
    /**
     * Sections are used (each section is one week/module).
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Course index is supported (left navigation tree).
     *
     * @return bool
     */
    public function uses_course_index() {
        return true;
    }

    /**
     * Use the reactive (component) course editor, like the core numbered formats.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * Enable the course-editor AJAX (drag-and-drop, inline move/delete/edit) like the
     * core numbered formats. include_course_ajax() gates the editor JS on this, so
     * without it activities cannot be dragged between weeks.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Output the eMD master-template header (S5) above the course content.
     *
     * @return \renderable|null
     */
    public function course_content_header() {
        global $PAGE;
        // With Edit mode off the full preview page (format.php) is shown, which
        // carries its own hero header — so suppress this one to avoid duplication.
        // With Edit mode on (editors), show the S5 header above the editing UI.
        if (!$PAGE->user_is_editing()) {
            return null;
        }
        return new \format_emd\output\course_header($this->get_course());
    }

    /**
     * Allow stealth activities like the standard numbered formats.
     *
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        return true;
    }

    /**
     * Returns the display name of the given section.
     *
     * @param int|stdClass|section_info $section
     * @return string
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => context_course::instance($this->get_courseid())]
            );
        }
        return $this->get_default_section_name($section);
    }

    /**
     * Default section name: "Course Introduction" for section 0, else "Week N".
     *
     * @param stdClass|section_info $section
     * @return string
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            return get_string('section0name', 'format_emd');
        }
        return get_string('weekn', 'format_emd', $section->section);
    }

    /**
     * Definition of the format's per-course options.
     *
     * @param bool $foreditform
     * @return array
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => 1,
                    'type'    => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => COURSE_DISPLAY_MULTIPAGE,
                    'type'    => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $hiddensectionslist = new core\output\choicelist();
            $hiddensectionslist->set_allow_empty(false);
            $hiddensectionslist->add_option(1, new lang_string('hiddensectionsinvisible'), [
                'description' => new lang_string('hiddensectionsinvisible_description'),
            ]);
            $hiddensectionslist->add_option(0, new lang_string('hiddensectionscollapsed'), [
                'description' => new lang_string('hiddensectionscollapsed_description'),
            ]);

            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label'              => new lang_string('hiddensections'),
                    'element_type'       => 'choicedropdown',
                    'element_attributes' => [$hiddensectionslist],
                ],
                'coursedisplay' => [
                    'label'              => new lang_string('coursedisplay'),
                    'element_type'       => 'select',
                    'element_attributes' => [[
                        COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                        COURSE_DISPLAY_MULTIPAGE  => new lang_string('coursedisplay_multi'),
                    ]],
                    'help'           => 'coursedisplay',
                    'help_component' => 'moodle',
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }
}

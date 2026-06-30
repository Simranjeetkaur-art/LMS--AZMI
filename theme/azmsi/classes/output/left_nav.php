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
 * AZMSI persistent left-navigation sidebar.
 *
 * Renders a role-aware app menu (admin console / faculty / student) that matches
 * the AZMSI prototype. Every item is capability-gated: a link only appears if the
 * current user can actually reach its target, so the menu never advertises a page
 * the user cannot open. The active item is derived live from the current page URL.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class left_nav implements renderable, templatable {
    /**
     * Build the sidebar context for the current user.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE, $DB;

        $sys = context_system::instance();
        $current = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';

        $isadmin = $this->can('local/azmsi:viewadminconsole', $sys);
        $isfaculty = !$isadmin && $this->can('local/azmsi:viewfacultyportal', $sys);

        if ($isadmin) {
            $brand = get_string('brand_admin', 'theme_azmsi');
            $defs = $this->admin_items();
        } else if ($isfaculty) {
            $brand = get_string('brand_faculty', 'theme_azmsi');
            $defs = $this->faculty_items();
        } else {
            $brand = get_string('brand_student', 'theme_azmsi');
            $defs = $this->student_items((int) $GLOBALS['USER']->id);
        }

        $items = [];
        foreach ($defs as $def) {
            if (!empty($def['cap']) && !$this->can($def['cap'], $sys)) {
                continue;
            }
            $url = new moodle_url($def['path'], $def['params'] ?? []);
            if (!empty($def['fragment'])) {
                $url->set_anchor($def['fragment']);
            }
            $item = [
                'label'  => $def['label'],
                'url'    => $url->out(false),
                'active' => $this->is_active($current, $url, $def['path']),
            ];
            if (!empty($def['children'])) {
                $item['children'] = [];
                foreach ($def['children'] as $child) {
                    $curl = new moodle_url($child['path'], $child['params'] ?? []);
                    $item['children'][] = [
                        'label'  => $child['label'],
                        'url'    => $curl->out(false),
                        'active' => $this->is_active($current, $curl, $child['path']),
                    ];
                }
                $item['haschildren'] = !empty($item['children']);
            }
            $items[] = $item;
        }

        // Switch portal is shown for admin/faculty only — never on the student sidebar.
        $switchdata = ['label' => '', 'links' => [], 'haslinks' => false];
        if ($isadmin || $isfaculty) {
            $switch = new switch_portal();
            $switchdata = $switch->export_for_template($output);
        }

        return [
            'brand'       => $brand,
            'brandsub'    => $isadmin ? get_string('brand_admin_sub', 'theme_azmsi')
                : ($isfaculty ? get_string('brand_faculty_sub', 'theme_azmsi')
                : get_string('brand_student_sub', 'theme_azmsi')),
            'items'       => $items,
            'haslinks'    => !empty($items),
            'switchlabel' => $switchdata['label'],
            'switchlinks' => $switchdata['links'],
            'hasswitch'   => ($isadmin || $isfaculty) && !empty($switchdata['haslinks']),
        ];
    }

    /**
     * Admin menu definitions (mirrors the AZMSI prototype sidebar).
     *
     * @return array
     */
    protected function admin_items(): array {
        $items = [
            ['label' => get_string('nav_overview', 'theme_azmsi'), 'path' => '/my/'],
            ['label' => get_string('nav_users', 'theme_azmsi'), 'path' => '/admin/user.php',
                'cap' => 'moodle/site:config'],
            ['label' => get_string('nav_catalog', 'theme_azmsi'), 'path' => '/course/management.php',
                'cap' => 'moodle/category:manage'],
            ['label' => get_string('nav_admissions', 'theme_azmsi'), 'path' => '/local/azmsi/admin.php',
                'cap' => 'local/azmsi:viewadminconsole'],
            ['label' => get_string('nav_faculty', 'theme_azmsi'), 'path' => '/local/azmsi/faculty.php',
                'cap' => 'local/azmsi:viewfacultyportal'],
            ['label' => get_string('nav_reports', 'theme_azmsi'), 'path' => '/admin/category.php',
                'params' => ['category' => 'reports'], 'cap' => 'moodle/site:config'],
        ];

        // Announcements -> the site news forum, only if one exists.
        if ($forum = $this->news_forum()) {
            $items[] = ['label' => get_string('nav_announcements', 'theme_azmsi'),
                'path' => '/mod/forum/view.php', 'params' => ['f' => $forum]];
        }

        $items[] = ['label' => get_string('nav_system', 'theme_azmsi'), 'path' => '/admin/search.php',
            'cap' => 'moodle/site:config'];
        return $items;
    }

    /**
     * Faculty menu definitions.
     *
     * @return array
     */
    protected function faculty_items(): array {
        $userid = (int) $GLOBALS['USER']->id;
        $children = [];
        $firstcourseid = 0;

        if (class_exists('\local_azmsi\local\faculty')) {
            foreach (\local_azmsi\local\faculty::taught_courses($userid) as $course) {
                if (!$firstcourseid) {
                    $firstcourseid = (int) $course->id;
                }
                $label = trim($course->idnumber . ' ' . format_string($course->shortname ?: $course->fullname, true));
                $children[] = [
                    'label'  => $label,
                    'path'   => '/course/view.php',
                    'params' => ['id' => $course->id],
                ];
            }
        }

        $items = [
            ['label' => get_string('nav_teachingdashboard', 'theme_azmsi'), 'path' => '/my/'],
            ['label' => get_string('nav_mycourses', 'theme_azmsi'), 'path' => '/my/courses.php', 'children' => $children],
            ['label' => get_string('nav_gradingqueue', 'theme_azmsi'), 'path' => '/local/azmsi/faculty.php', 'fragment' => 'az-grading-queue'],
        ];

        if ($firstcourseid) {
            $items[] = ['label' => get_string('nav_discussions', 'theme_azmsi'), 'path' => '/mod/forum/index.php', 'params' => ['id' => $firstcourseid]];
            $items[] = ['label' => get_string('nav_questionbank', 'theme_azmsi'), 'path' => '/question/edit.php', 'params' => ['courseid' => $firstcourseid]];
            $items[] = ['label' => get_string('nav_coursebuilder', 'theme_azmsi'), 'path' => '/course/view.php', 'params' => ['id' => $firstcourseid]];
        }

        $items[] = ['label' => get_string('nav_researchmentees', 'theme_azmsi'), 'path' => '/local/azmsi/research.php', 'cap' => 'local/azmsi:mentorresearch'];
        $items[] = ['label' => get_string('nav_liveclasses', 'theme_azmsi'), 'path' => '/calendar/view.php'];
        $items[] = ['label' => get_string('nav_calendar', 'theme_azmsi'), 'path' => '/calendar/view.php'];

        return $items;
    }

    /**
     * Student menu definitions (matches the student portal prototype).
     *
     * @param int $userid
     * @return array
     */
    protected function student_items(int $userid): array {
        $children = [];
        foreach (enrol_get_all_users_courses($userid, true, 'id, idnumber, shortname, fullname, sortorder') as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $label = trim($course->idnumber . ' ' . format_string($course->shortname ?: $course->fullname, true));
            $children[] = [
                'label'  => $label,
                'path'   => '/course/view.php',
                'params' => ['id' => $course->id],
            ];
        }

        $items = [
            ['label' => get_string('nav_mydashboard', 'theme_azmsi'), 'path' => '/my/'],
            ['label' => get_string('nav_mycourses', 'theme_azmsi'), 'path' => '/my/courses.php', 'children' => $children],
            ['label' => get_string('nav_calendar', 'theme_azmsi'), 'path' => '/calendar/view.php'],
            ['label' => get_string('nav_grades', 'theme_azmsi'), 'path' => '/grade/report/overview/index.php'],
            ['label' => get_string('nav_research', 'theme_azmsi'), 'path' => '/local/azmsi/research.php'],
            ['label' => get_string('nav_library', 'theme_azmsi'), 'path' => '/search/index.php'],
            ['label' => get_string('nav_forums', 'theme_azmsi'), 'path' => '/mod/forum/index.php', 'params' => ['id' => SITEID]],
            ['label' => get_string('nav_liveclasses', 'theme_azmsi'), 'path' => '/calendar/view.php'],
        ];
        return $items;
    }

    /**
     * The site news forum id, or 0 if there isn't one.
     *
     * @return int
     */
    protected function news_forum(): int {
        global $DB;
        try {
            $forum = $DB->get_record('forum', ['course' => SITEID, 'type' => 'news'], 'id', IGNORE_MULTIPLE);
            return $forum ? (int) $forum->id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Whether a menu item is the current page.
     *
     * @param string $current current page url (no query string)
     * @param moodle_url $url the item url
     * @param string $path the item path
     * @return bool
     */
    protected function is_active(string $current, moodle_url $url, string $path): bool {
        if ($current === '') {
            return false;
        }
        $target = $url->out_omit_querystring();
        // Dashboard: /my/ resolves to /my/index.php in the page url.
        if ($path === '/my/') {
            return (bool) preg_match('#/my/(index\.php)?$#', $current);
        }
        return $current === $target;
    }

    /**
     * Capability check guarded against the capability not being installed yet.
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

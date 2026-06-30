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

use core\context\system as context_system;
use core_course\customfield\course_handler;
use moodle_url;

/**
 * Portal chrome (top bar + footer) for AZMSI role dashboards.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class portal_chrome {

    /** @var string Role key used for admin console users. */
    public const ROLE_ADMIN = 'admin';

    /** @var string Role key used for faculty portal users. */
    public const ROLE_FACULTY = 'faculty';

    /** @var string Role key used for student portal users. */
    public const ROLE_STUDENT = 'student';

    /** @var string Role key used for guests / anonymous viewers. */
    public const ROLE_GUEST = 'guest';

    /**
     * Faculty portal top bar + teal footer context (legacy dashboard block wrapper).
     *
     * @param int $userid
     * @return array template context with hasportalchrome, topbar, footer
     */
    public static function faculty(int $userid): array {
        $topbar = self::faculty_topbar($userid);
        if (empty($topbar['hasportalchrome'])) {
            return $topbar;
        }
        return $topbar + ['footer' => self::footer_context($userid, self::ROLE_FACULTY)];
    }

    /**
     * Faculty portal top bar only (footer is rendered site-wide in drawers).
     *
     * @param int $userid
     * @return array
     */
    public static function faculty_topbar(int $userid): array {
        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        if (!$user) {
            return ['hasportalchrome' => false];
        }

        $searchurl = new moodle_url('/search/index.php');

        return [
            'hasportalchrome' => true,
            'topbar' => [
                'searchplaceholder' => get_string('portalsearchplaceholder', 'theme_azmsi'),
                'searchurl'         => $searchurl->out(false),
                'termlabel'         => self::term_label($userid),
                'initials'          => self::initials($user),
                'fullname'          => fullname($user),
                'rolesubtitle'      => self::faculty_subtitle($userid),
            ],
        ];
    }

    /**
     * Site footer context for the current user's portal role.
     *
     * @param int $userid
     * @return array flat template context for theme_azmsi/footer
     */
    public static function footer_for_user(int $userid): array {
        $role = self::detect_role($userid);
        return self::footer_context($userid, $role);
    }

    /**
     * Merge portal chrome into a renderable context when the theme is active.
     *
     * @param int $userid
     * @param array $context
     * @return array
     */
    public static function merge_faculty(int $userid, array $context): array {
        return array_merge($context, self::faculty_topbar($userid));
    }

    /**
     * Resolve the AZMSI portal role for a user (matches left_nav.php).
     *
     * @param int $userid
     * @return string one of the ROLE_* constants
     */
    public static function detect_role(int $userid): string {
        if ($userid <= 0 || isguestuser($userid)) {
            return self::ROLE_GUEST;
        }

        $sys = context_system::instance();
        if (has_capability('local/azmsi:viewadminconsole', $sys, $userid)) {
            return self::ROLE_ADMIN;
        }
        if (has_capability('local/azmsi:viewfacultyportal', $sys, $userid)) {
            return self::ROLE_FACULTY;
        }
        return self::ROLE_STUDENT;
    }

    /**
     * Build the flat footer template context for a portal role.
     *
     * @param int $userid
     * @param string $role
     * @return array
     */
    protected static function footer_context(int $userid, string $role): array {
        switch ($role) {
            case self::ROLE_ADMIN:
                $portalpath = self::portal_path('/local/azmsi/admin.php');
                $title = get_string('adminportalfooter', 'theme_azmsi', $portalpath);
                $contacts = get_string('adminportalcontacts', 'theme_azmsi');
                $links = self::admin_footer_links();
                break;
            case self::ROLE_FACULTY:
                $portalpath = self::portal_path('/local/azmsi/faculty.php');
                $title = get_string('facultyportalfooter', 'theme_azmsi', $portalpath);
                $contacts = get_string('facultyportalcontacts', 'theme_azmsi');
                $links = self::faculty_footer_links();
                break;
            case self::ROLE_GUEST:
                $portalpath = self::portal_path('');
                $title = get_string('guestportalfooter', 'theme_azmsi', $portalpath);
                $contacts = get_string('studentportalcontacts', 'theme_azmsi');
                $links = self::student_footer_links();
                $role = self::ROLE_STUDENT;
                break;
            default:
                $portalpath = self::portal_path('');
                $title = get_string('studentportalfooter', 'theme_azmsi', $portalpath);
                $contacts = get_string('studentportalcontacts', 'theme_azmsi');
                $links = self::student_footer_links();
                $role = self::ROLE_STUDENT;
                break;
        }

        return [
            'hasfooter' => true,
            'variant'   => $role,
            'title'     => $title,
            'contacts'  => $contacts,
            'haslinks'  => !empty($links),
            'links'     => $links,
        ];
    }

    /**
     * User initials for the avatar chip.
     *
     * @param \stdClass $user
     * @return string
     */
    protected static function initials(\stdClass $user): string {
        $first = \core_text::strtoupper(\core_text::substr($user->firstname, 0, 1));
        $last = \core_text::strtoupper(\core_text::substr($user->lastname, 0, 1));
        return trim($first . $last) ?: '?';
    }

    /**
     * Quarter + season label (e.g. QUARTER 1 · WINTER 2026).
     *
     * @param int $userid
     * @return string
     */
    protected static function term_label(int $userid): string {
        $quarter = self::current_quarter($userid);
        $season = self::season_label();
        return get_string('portaltermlabel', 'theme_azmsi', (object) [
            'quarter' => $quarter,
            'season'  => $season,
        ]);
    }

    /**
     * Current academic quarter from assigned course custom fields, else calendar quarter.
     *
     * @param int $userid
     * @return int
     */
    protected static function current_quarter(int $userid): int {
        if (class_exists('\local_azmsi\local\faculty')) {
            $handler = course_handler::create();
            foreach (\local_azmsi\local\faculty::taught_courses($userid) as $course) {
                foreach ($handler->get_instance_data($course->id, true) as $data) {
                    $shortname = $data->get_field()->get('shortname');
                    if ($shortname === 'quarter' && is_numeric($data->export_value())) {
                        return max(1, min(12, (int) $data->export_value()));
                    }
                }
            }
        }
        return max(1, min(4, (int) ceil((int) userdate(time(), '%m') / 3)));
    }

    /**
     * Season + year label from the current date.
     *
     * @return string
     */
    protected static function season_label(): string {
        $month = (int) userdate(time(), '%m');
        $year = (int) userdate(time(), '%Y');
        if ($month === 12 || $month <= 2) {
            $key = 'seasonwinter';
        } else if ($month <= 5) {
            $key = 'seasonspring';
        } else if ($month <= 8) {
            $key = 'seasonsummer';
        } else {
            $key = 'seasonfall';
        }
        return get_string($key, 'theme_azmsi', $year);
    }

    /**
     * Faculty role subtitle under the user name.
     *
     * @param int $userid
     * @return string
     */
    protected static function faculty_subtitle(int $userid): string {
        $focus = '';
        if (class_exists('\local_azmsi\local\faculty')) {
            $courses = \local_azmsi\local\faculty::taught_courses($userid);
            if (!empty($courses)) {
                $course = reset($courses);
                $focus = $course->idnumber ?: $course->shortname;
            }
        }
        if ($focus === '') {
            return get_string('facultyrolesubtitle_default', 'theme_azmsi');
        }
        return get_string('facultyrolesubtitle', 'theme_azmsi', $focus);
    }

    /**
     * Display path for the portal footer title (hostname + optional portal slug).
     *
     * Never exposes internal PHP script names — those are implementation details.
     *
     * @param string $suffix Moodle path such as /local/azmsi/admin.php
     * @return string e.g. azmsi.unicornfortunes.com/admin
     */
    protected static function portal_path(string $suffix): string {
        global $CFG;
        $parts = parse_url($CFG->wwwroot);
        $host = $parts['host'] ?? 'lms.azmsi.com';

        static $slugs = [
            '/local/azmsi/admin.php'   => 'admin',
            '/local/azmsi/faculty.php' => 'faculty',
        ];
        $slug = $slugs[$suffix] ?? '';
        if ($slug === '') {
            return $host;
        }
        return $host . '/' . $slug;
    }

    /**
     * Student footer quick links.
     *
     * @return array
     */
    protected static function student_footer_links(): array {
        return self::link_set([
            ['label' => 'footeracademiccalendar', 'url' => 'footeracademiccalendarurl'],
            ['label' => 'footerstudenthandbook', 'url' => 'footerstudenthandbookurl'],
            ['label' => 'footertechnicalsupport', 'url' => 'footertechnicalsupporturl'],
        ]);
    }

    /**
     * Faculty footer quick links.
     *
     * @return array
     */
    protected static function faculty_footer_links(): array {
        return self::link_set([
            ['label' => 'footerinstructionaldesign', 'url' => 'footerinstructionaldesignurl'],
            ['label' => 'footerfacultyhandbook', 'url' => 'footerfacultyhandbookurl'],
            ['label' => 'footersupport', 'url' => 'footersupporturl'],
        ]);
    }

    /**
     * Admin footer quick links.
     *
     * @return array
     */
    protected static function admin_footer_links(): array {
        return self::link_set([
            ['label' => 'footerauditlog', 'url' => 'footerauditlogurl'],
            ['label' => 'footeraccreditationprep', 'url' => 'footeraccreditationprepurl'],
            ['label' => 'footersystemsettings', 'url' => 'footersystemsettingsurl'],
        ]);
    }

    /**
     * Build footer link rows from lang string keys.
     *
     * @param array $keys
     * @return array
     */
    protected static function link_set(array $keys): array {
        $links = [];
        foreach ($keys as $item) {
            $links[] = [
                'label' => get_string($item['label'], 'theme_azmsi'),
                'url'   => get_string($item['url'], 'theme_azmsi'),
            ];
        }
        return $links;
    }
}

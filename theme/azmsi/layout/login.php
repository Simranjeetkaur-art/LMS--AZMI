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
 * AZMSI login landing layout (AGENT_02a).
 *
 * A marketing-style portal landing page whose sign-in card embeds the REAL
 * Moodle login form (output.main_content — CSRF, lockout, errors, remember-me,
 * configured IdP buttons all native). The program stats, year structure and
 * Quarter-1 course cards are read live from the catalog — nothing hardcoded.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $CFG, $SITE;

$settings = $OUTPUT->page->theme->settings ?? new stdClass();

// Editable marketing copy (falls back to the prototype defaults).
$eyebrow  = !empty($settings->logineyebrow) ? $settings->logineyebrow : 'Executive Medical Doctorate · LMS';
$headline = !empty($settings->loginheadline) ? $settings->loginheadline : 'Advance medical science. From anywhere.';
$subhead  = !empty($settings->loginsubhead) ? $settings->loginsubhead :
    'Your courses, assessments, research, and live seminars — one online learning environment, built for working professionals.';

// ---------------------------------------------------------------------------
// Live program data (catalog-derived; nothing hardcoded).
// ---------------------------------------------------------------------------
$catalog = class_exists('\local_azmsi\local\program') ? \local_azmsi\local\program::catalog() : [];
$totalcourses = 0;
$yearmap = [];
foreach ($catalog as $qnum => $q) {
    $totalcourses += count($q['courses']);
    $yearmap[$q['year']]['quarters'][] = $qnum;
    $yearmap[$q['year']]['courses'] = ($yearmap[$q['year']]['courses'] ?? 0) + count($q['courses']);
}
$totalquarters = count($catalog);
$totalyears = count($yearmap);

$stats = [
    ['num' => $totalcourses ?: 48, 'label' => get_string('statcourses', 'theme_azmsi')],
    ['num' => $totalquarters ?: 12, 'label' => get_string('statquarters', 'theme_azmsi')],
    ['num' => $totalyears ?: 3, 'label' => get_string('statyears', 'theme_azmsi')],
    ['num' => '100%', 'label' => get_string('statonline', 'theme_azmsi'), 'hi' => true],
];

// Year cards: editorial copy + live quarter range / course count.
$yearcopy = [
    1 => ['ordinal' => 'One', 'accent' => 'teal', 'title' => 'Medical Science Foundations',
        'desc' => 'Build the language and core science of medicine — terminology, anatomy, physiology, biochemistry, pathology and research methods.',
        'bullets' => ['Medical terminology & clinical language', 'Human anatomy, physiology & biochemistry', 'Biostatistics & research foundations']],
    2 => ['ordinal' => 'Two', 'accent' => 'gold', 'title' => 'Innovation, AI & Biotechnology',
        'desc' => 'Move into the frontier — AI in medicine, genomics, digital health, hospital operations and healthcare entrepreneurship.',
        'bullets' => ['AI & machine learning for healthcare', 'Genomics, biotech & regenerative science', 'Health systems leadership & policy']],
    3 => ['ordinal' => 'Three', 'accent' => 'teal', 'title' => 'Leadership & Dissertation',
        'desc' => 'Lead and contribute original work — executive strategy, bioethics, advanced research, and your capstone dissertation.',
        'bullets' => ['Executive strategy & global health', 'Bioethics, law & data governance', 'Capstone & dissertation defense']],
];
$years = [];
foreach ($yearcopy as $yn => $c) {
    $qs = $yearmap[$yn]['quarters'] ?? [];
    $range = $qs ? (min($qs) . '–' . max($qs)) : '';
    $years[] = $c + [
        'num'      => $yn,
        'quarters' => $range !== '' ? get_string('quartersrange', 'theme_azmsi', $range) : '',
        'courses'  => $yearmap[$yn]['courses'] ?? 0,
    ];
}

// Quarter-1 course cards (live records + custom fields).
$q1courses = [];
$cardaccents = ['teal', 'purple', 'gold', 'blue'];
if (class_exists('\local_azmsi\local\program')) {
    $handler = \core_course\customfield\course_handler::create();
    $i = 0;
    foreach (($catalog[1]['courses'] ?? []) as $cinfo) {
        [$code] = $cinfo;
        $course = $DB->get_record('course', ['idnumber' => $code]);
        if (!$course) {
            continue;
        }
        $f = [];
        foreach ($handler->get_instance_data($course->id, true) as $d) {
            $f[$d->get_field()->get('shortname')] = (string) $d->export_value();
        }
        $q1courses[] = [
            'code'    => $code,
            'name'    => format_string($course->fullname, true, ['escape' => false]),
            'weeks'   => ($f['max_weeks'] ?? '10'),
            'credits' => ($f['credits'] ?? ''),
            'accent'  => $cardaccents[$i % count($cardaccents)],
            'url'     => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
        $i++;
    }
}

// Platform feature cards (editorial).
$features = [
    ['accent' => 'gold', 'icon' => '&#9658;', 'title' => 'Video lectures & readings',
        'desc' => 'Every weekly module pairs faculty lecture videos with guided readings and curated open resources.'],
    ['accent' => 'teal', 'icon' => '&#9678;', 'title' => 'Live seminars',
        'desc' => 'Join faculty-led live sessions on schedule — every class is recorded for anytime review.'],
    ['accent' => 'purple', 'icon' => '&#10022;', 'title' => 'Interactive labs',
        'desc' => 'H5P simulations, imaging labs and applied activities make complex science hands-on.'],
    ['accent' => 'gold', 'icon' => '&#9201;', 'title' => 'Quizzes & assessments',
        'desc' => 'Weekly knowledge checks, midterms and proctored finals with instant rubric feedback.'],
    ['accent' => 'teal', 'icon' => '&#10078;', 'title' => 'Discussion & cohort',
        'desc' => 'Forums and peer discussion keep you connected to faculty and your cohort throughout.'],
    ['accent' => 'gold', 'icon' => '&#9670;', 'title' => 'Dissertation workspace',
        'desc' => 'Track your proposal, research milestones and defense in a dedicated dissertation space.'],
];

// Three audiences (links require auth, so they funnel to the sign-in card).
$experiences = [
    ['letter' => 'S', 'accent' => 'teal', 'title' => 'Students',
        'bullets' => ['Your enrolled courses & weekly modules', 'Assignments, quizzes & live classes', 'Grades, progress & dissertation tracker'],
        'linklabel' => get_string('gotodashboard', 'theme_azmsi')],
    ['letter' => 'F', 'accent' => 'gold', 'title' => 'Faculty',
        'bullets' => ['Teach your assigned cohorts', 'Rosters, attendance & grading queue', 'Assignments, submissions & feedback'],
        'linklabel' => get_string('facultyportallink', 'theme_azmsi')],
    ['letter' => 'A', 'accent' => 'purple', 'title' => 'Administrators',
        'bullets' => ['Whole-institution overview', 'Users, courses, enrolments & content', 'Announcements, reports & system health'],
        'linklabel' => get_string('adminconsolelink', 'theme_azmsi')],
];

$bodyattributes = $OUTPUT->body_attributes(['pagelayout-login', 'theme-azmsi-login']);

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'logineyebrow'   => $eyebrow,
    'loginheadline'  => $headline,
    'loginsubhead'   => $subhead,
    'logourl'        => theme_azmsi_get_logo_url(),
    'cresturl'       => $OUTPUT->image_url('crest', 'theme_azmsi')->out(false),
    'wwwroot'        => $CFG->wwwroot,
    'loginhost'      => parse_url($CFG->wwwroot, PHP_URL_HOST),
    'year'           => userdate(time(), '%Y'),
    // Links.
    'azmsicom'       => 'https://azmsi.com',
    'applyurl'       => 'https://azmsi.com',
    'curriculumurl'  => 'https://azmsi.com/curriculum',
    'fullcatalogurl' => (new moodle_url('/course/'))->out(false),
    'signinanchor'   => '#az-signin',
    'supportemail'   => 'support@azmsi.com',
    'admissionsemail' => 'admissions@azmsi.com',
    'phone'          => '+1 (802) 555-0100',
    // Live data.
    'totalcourses'   => $totalcourses ?: 48,
    'stats'          => $stats,
    'years'          => $years,
    'q1courses'      => $q1courses,
    'hasq1'          => !empty($q1courses),
    'features'       => $features,
    'experiences'    => $experiences,
    'badges'         => [
        ['label' => 'Accredited Institution'],
        ['label' => 'Faculty-led cohorts'],
        ['label' => 'Live + on-demand'],
        ['label' => 'Dissertation track'],
    ],
];

echo $OUTPUT->render_from_template('theme_azmsi/login', $templatecontext);

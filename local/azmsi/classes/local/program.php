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

namespace local_azmsi\local;

use core_customfield\category_controller;
use core_customfield\field_controller;
use core_course\customfield\course_handler;
use core_course_category;

/**
 * AZMSI program domain model: course custom fields, catalog seeding, and the
 * Year -> Quarter -> Course tree read back from Moodle (the live source of truth).
 *
 * "Nothing static": the catalog data below is only the initial SEED. Once seeded,
 * academics rename courses / flip status in Moodle and {@see self::get_catalog_tree()}
 * (hence the catalog WS) reflects it with no code change.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program {
    /** @var string Program code. */
    public const PROGRAM = 'eMD';

    /** @var int Credits per course (constant across the program). */
    public const CREDITS = 4;

    /** @var string Custom field category name. */
    public const CF_CATEGORY = 'AZMSI program metadata';

    /**
     * Custom field definitions applied to every course.
     *
     * @return array<string,array{name:string,type:string}>
     */
    public static function field_defs(): array {
        return [
            'program'           => ['name' => 'Program', 'type' => 'text'],
            'year'              => ['name' => 'Year', 'type' => 'text'],
            'quarter'           => ['name' => 'Quarter', 'type' => 'text'],
            'course_code'       => ['name' => 'Course code', 'type' => 'text'],
            'credits'           => ['name' => 'Credits', 'type' => 'text'],
            'status'            => ['name' => 'Status', 'type' => 'select'],
            'faculty_name'      => ['name' => 'Faculty name', 'type' => 'text'],
            'welcome_video_url' => ['name' => 'Welcome video URL', 'type' => 'text'],
        ];
    }

    /**
     * The 48-course seed catalog (03_SCREEN_SPECS §Catalog).
     *
     * Quarter => [year, [ [code, name], ... ]]. Year 1 = Q1-4, Year 2 = Q5-8,
     * Year 3 = Q9-12. Only Q1 ships live; the rest are planned shells.
     *
     * @return array<int,array{year:int,courses:array<int,array{0:string,1:string}>}>
     */
    public static function catalog(): array {
        return [
            1  => ['year' => 1, 'courses' => [
                ['EMD-101', 'Medical Terminology & Clinical Language'],
                ['EMD-102', 'Human Anatomy I'],
                ['EMD-103', 'Cellular Biology & Genetics'],
                ['EMD-104', 'Healthcare Systems & Global Medicine'],
            ]],
            2  => ['year' => 1, 'courses' => [
                ['EMD-201', 'Human Anatomy II'],
                ['EMD-202', 'Human Physiology'],
                ['EMD-203', 'Biochemistry & Molecular Medicine'],
                ['EMD-204', 'Health Informatics Foundations'],
            ]],
            3  => ['year' => 1, 'courses' => [
                ['EMD-301', 'Pathophysiology'],
                ['EMD-302', 'Pharmacology Principles'],
                ['EMD-303', 'Microbiology & Immunology'],
                ['EMD-304', 'Biostatistics & Epidemiology'],
            ]],
            4  => ['year' => 1, 'courses' => [
                ['EMD-401', 'Medical Imaging & Diagnostics'],
                ['EMD-402', 'Clinical Data Science'],
                ['EMD-403', 'Healthcare Economics & Finance'],
                ['EMD-404', 'Research Methods I'],
            ]],
            5  => ['year' => 2, 'courses' => [
                ['EMD-501', 'AI in Medicine'],
                ['EMD-502', 'Machine Learning for Healthcare'],
                ['EMD-503', 'Predictive & Precision Medicine'],
                ['EMD-504', 'Digital Health Systems'],
            ]],
            6  => ['year' => 2, 'courses' => [
                ['EMD-601', 'Genomics & Biotechnology'],
                ['EMD-602', 'Regenerative & Longevity Sciences'],
                ['EMD-603', 'Medical Robotics & Automation'],
                ['EMD-604', 'Digital Therapeutics'],
            ]],
            7  => ['year' => 2, 'courses' => [
                ['EMD-701', 'Hospital Operations & Quality'],
                ['EMD-702', 'Healthcare Policy & Regulation'],
                ['EMD-703', 'Health Systems Leadership'],
                ['EMD-704', 'Research Methods II'],
            ]],
            8  => ['year' => 2, 'courses' => [
                ['EMD-801', 'Healthcare Entrepreneurship'],
                ['EMD-802', 'Innovation Ecosystems'],
                ['EMD-803', 'Medical Product & EMR Systems'],
                ['EMD-804', 'Translational Medicine'],
            ]],
            9  => ['year' => 3, 'courses' => [
                ['EMD-901', 'Executive Strategy in Healthcare'],
                ['EMD-902', 'Global & Public Health Systems'],
                ['EMD-903', 'Bioethics & Medical Law'],
                ['EMD-904', 'Healthcare Analytics & Outcomes'],
            ]],
            10 => ['year' => 3, 'courses' => [
                ['EMD-1001', 'AI Diagnostics Practicum'],
                ['EMD-1002', 'Cybersecurity & Data Governance'],
                ['EMD-1003', 'Organizational Leadership'],
                ['EMD-1004', 'Dissertation Proposal'],
            ]],
            11 => ['year' => 3, 'courses' => [
                ['EMD-1101', 'Advanced Research Seminar'],
                ['EMD-1102', 'Scientific Publication & Communication'],
                ['EMD-1103', 'Innovation Capstone I'],
                ['EMD-1104', 'Dissertation Research I'],
            ]],
            12 => ['year' => 3, 'courses' => [
                ['EMD-1201', 'Innovation Capstone II'],
                ['EMD-1202', 'Executive Leadership Capstone'],
                ['EMD-1203', 'Dissertation Research II'],
                ['EMD-1204', 'Dissertation Defense'],
            ]],
        ];
    }

    /**
     * Quarter 1 is the only live quarter at launch.
     *
     * @param int $quarter
     * @return string in_progress|planned
     */
    protected static function seed_status(int $quarter): string {
        return $quarter === 1 ? 'in_progress' : 'planned';
    }

    // Custom fields.

    /**
     * Ensure the AZMSI course custom-field category and fields exist (idempotent).
     *
     * @return int the custom field category id
     */
    public static function ensure_customfields(): int {
        $handler = course_handler::create();

        // Find or create our category.
        $categoryid = 0;
        foreach ($handler->get_categories_with_fields() as $category) {
            if ($category->get('name') === self::CF_CATEGORY) {
                $categoryid = $category->get('id');
                break;
            }
        }
        if (!$categoryid) {
            $categoryid = $handler->create_category(self::CF_CATEGORY);
        }
        $category = category_controller::load($categoryid);

        // Existing field shortnames in this category.
        $existing = [];
        foreach ($handler->get_fields() as $field) {
            $existing[$field->get('shortname')] = true;
        }

        foreach (self::field_defs() as $shortname => $def) {
            if (isset($existing[$shortname])) {
                continue;
            }
            $field = field_controller::create(0, (object) ['type' => $def['type']], $category);
            $configdata = [
                'required'     => 0,
                'uniquevalues' => 0,
                'locked'       => 0,
                'visibility'   => 2, // Everyone.
                'defaultvalue' => '',
            ];
            if ($def['type'] === 'select') {
                $configdata['options'] = "in_progress\nplanned";
                $configdata['defaultvalue'] = 'planned';
            } else {
                $configdata['displaysize'] = 50;
                $configdata['maxlength'] = 1333;
                $configdata['ispassword'] = 0;
                $configdata['link'] = '';
            }
            $record = (object) [
                'name'              => $def['name'],
                'shortname'         => $shortname,
                'type'              => $def['type'],
                'description'       => '',
                'descriptionformat' => FORMAT_HTML,
                'configdata'        => $configdata,
            ];
            $handler->save_field_configuration($field, $record);
        }

        return $categoryid;
    }

    // Seeding.

    /**
     * Seed (idempotent) the Year -> Quarter category tree + 48 courses with
     * their custom field values. Re-running updates existing rows, never dupes.
     *
     * @return array{created:int,updated:int} counts
     */
    public static function seed(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        self::ensure_customfields();

        $created = 0;
        $updated = 0;

        foreach (self::catalog() as $quarter => $info) {
            $year = $info['year'];
            $yearcatid = self::ensure_category('AZMSI-Y' . $year, 'Year ' . $year, 0);
            $qcatid = self::ensure_category(
                'AZMSI-Y' . $year . '-Q' . $quarter,
                'Quarter ' . $quarter,
                $yearcatid
            );
            $status = self::seed_status($quarter);

            foreach ($info['courses'] as $entry) {
                [$code, $name] = $entry;
                $fields = [
                    'customfield_program'           => self::PROGRAM,
                    'customfield_year'              => (string) $year,
                    'customfield_quarter'           => (string) $quarter,
                    'customfield_course_code'       => $code,
                    'customfield_credits'           => (string) self::CREDITS,
                    'customfield_status'            => $status,
                    'customfield_faculty_name'      => '',
                    'customfield_welcome_video_url' => '',
                ];

                $existing = $DB->get_record('course', ['idnumber' => $code]);
                if ($existing) {
                    $data = (object) array_merge((array) $existing, [
                        'fullname'  => $name,
                        'category'  => $qcatid,
                    ], $fields);
                    update_course($data);
                    $updated++;
                } else {
                    $data = (object) array_merge([
                        'fullname'     => $name,
                        'shortname'    => $code,
                        'idnumber'     => $code,
                        'category'     => $qcatid,
                        'visible'      => 1,
                        'summary'      => '',
                        'summaryformat' => FORMAT_HTML,
                    ], $fields);
                    create_course($data);
                    $created++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Find or create a course category by idnumber (idempotent).
     *
     * @param string $idnumber stable idnumber, e.g. AZMSI-Y1-Q1
     * @param string $name display name
     * @param int $parentid parent category id (0 = top)
     * @return int category id
     */
    protected static function ensure_category(string $idnumber, string $name, int $parentid): int {
        global $DB;
        if ($record = $DB->get_record('course_categories', ['idnumber' => $idnumber])) {
            return (int) $record->id;
        }
        $category = core_course_category::create((object) [
            'name'     => $name,
            'idnumber' => $idnumber,
            'parent'   => $parentid,
        ]);
        return (int) $category->id;
    }

    // Read back — the live catalog tree.

    /**
     * Build the Year -> Quarter -> Course tree from live Moodle data.
     *
     * Reads each AZMSI course's custom fields (the source of truth). A quarter's
     * status is "in_progress" when any of its courses is live, else "planned".
     *
     * @return array{program:string,years:array}
     */
    public static function get_catalog_tree(): array {
        global $DB;

        $handler = course_handler::create();
        $courses = $DB->get_records_select(
            'course',
            $DB->sql_like('idnumber', ':code'),
            ['code' => 'EMD-%'],
            'sortorder ASC'
        );

        // Gather per-course field values keyed by year/quarter.
        $byyear = [];
        foreach ($courses as $course) {
            $values = [];
            foreach ($handler->get_instance_data($course->id, true) as $data) {
                $values[$data->get_field()->get('shortname')] = $data->export_value();
            }
            $year = (int) ($values['year'] ?? 0);
            $quarter = (int) ($values['quarter'] ?? 0);
            if (!$year || !$quarter) {
                continue;
            }
            $status = ($values['status'] ?? 'planned') === 'in_progress' ? 'in_progress' : 'planned';
            $byyear[$year][$quarter][] = [
                'code'    => (string) ($values['course_code'] ?? $course->idnumber),
                'name'    => format_string($course->fullname),
                'credits' => (int) ($values['credits'] ?? self::CREDITS),
                'status'  => $status,
            ];
        }

        ksort($byyear);
        $years = [];
        foreach ($byyear as $yearnum => $quarters) {
            ksort($quarters);
            $quarterout = [];
            foreach ($quarters as $qnum => $courselist) {
                $live = false;
                foreach ($courselist as $c) {
                    if ($c['status'] === 'in_progress') {
                        $live = true;
                        break;
                    }
                }
                $quarterout[] = [
                    'name'    => 'Quarter ' . $qnum,
                    'number'  => $qnum,
                    'status'  => $live ? 'in_progress' : 'planned',
                    'courses' => $courselist,
                ];
            }
            $years[] = [
                'name'     => 'Year ' . $yearnum,
                'number'   => $yearnum,
                'quarters' => $quarterout,
            ];
        }

        return ['program' => self::PROGRAM, 'years' => $years];
    }
}

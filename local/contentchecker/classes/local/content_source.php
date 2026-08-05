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

namespace local_contentchecker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Locates the checkable prose inside a course's activities.
 *
 * The spec's five content kinds map onto Moodle modules as follows, and the map
 * also records which table and column each piece of prose lives in so an
 * approved correction can be written back to exactly the field it came from:
 *
 *   readings / overviews  -> mod_page content, mod_book chapters, mod_resource intro
 *   videos                -> mod_url intro (the description around the embed)
 *   assignments           -> mod_assign intro
 *   discussion forums     -> mod_forum intro (the prompt)
 *   quizzes               -> mod_quiz intro, plus each question's questiontext
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_source {

    /**
     * Shortest passage worth sending to the model.
     *
     * Below this a "reading" is a stub -- a bare video embed, a one-line
     * pointer -- and atomising it costs GPU time to produce nothing.
     *
     * @var int
     */
    const MIN_CHARS = 200;

    /**
     * Modules this plugin knows how to read, and where their prose lives.
     *
     * @return array modname => [table, field, label string key].
     */
    public static function module_map(): array {
        return [
            'page' => ['table' => 'page', 'field' => 'content', 'kind' => 'reading'],
            'book' => ['table' => 'book_chapters', 'field' => 'content', 'kind' => 'reading'],
            'resource' => ['table' => 'resource', 'field' => 'intro', 'kind' => 'reading'],
            'url' => ['table' => 'url', 'field' => 'intro', 'kind' => 'video'],
            'assign' => ['table' => 'assign', 'field' => 'intro', 'kind' => 'assignment'],
            'forum' => ['table' => 'forum', 'field' => 'intro', 'kind' => 'discussion'],
            'quiz' => ['table' => 'quiz', 'field' => 'intro', 'kind' => 'quiz'],
            'label' => ['table' => 'label', 'field' => 'intro', 'kind' => 'reading'],
        ];
    }

    /**
     * Every checkable item in one course section.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return array List of item objects.
     */
    public static function for_section(int $courseid, int $sectionnum): array {
        global $DB;

        $section = $DB->get_record('course_sections',
            ['course' => $courseid, 'section' => $sectionnum]);
        if (!$section) {
            return [];
        }
        return self::collect($courseid, ['section' => $section->id]);
    }

    /**
     * Every checkable item in a whole course.
     *
     * @param int $courseid Course id.
     * @return array List of item objects.
     */
    public static function for_course(int $courseid): array {
        return self::collect($courseid, []);
    }

    /**
     * Every checkable item belonging to one activity.
     *
     * @param int $cmid Course module id.
     * @return array List of item objects; more than one for a book or a quiz.
     */
    public static function for_cm(int $cmid): array {
        global $DB;

        // Tolerates a missing activity rather than throwing: this is a lookup,
        // and callers legitimately ask about an activity that has just been
        // deleted in another session. An empty list is the honest answer.
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return [];
        }
        return self::collect((int) $cm->course, ['cmid' => $cmid]);
    }

    /**
     * Walk the course modules and pull out prose.
     *
     * @param int $courseid Course id.
     * @param array $filter Optional 'section' (section row id) or 'cmid'.
     * @return array List of item objects.
     */
    protected static function collect(int $courseid, array $filter): array {
        global $DB;

        $map = self::module_map();
        $params = ['courseid' => $courseid];
        $where = 'cm.course = :courseid AND cm.deletioninprogress = 0';

        if (!empty($filter['section'])) {
            $where .= ' AND cm.section = :sectionid';
            $params['sectionid'] = $filter['section'];
        }
        if (!empty($filter['cmid'])) {
            $where .= ' AND cm.id = :cmid';
            $params['cmid'] = $filter['cmid'];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($map), SQL_PARAMS_NAMED, 'mod');
        $params += $inparams;

        $sql = "SELECT cm.id AS cmid, cm.instance, cm.section AS sectionid,
                       m.name AS modname, cs.section AS sectionnum
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE {$where} AND m.name {$insql}
              ORDER BY cs.section, cm.id";

        $items = [];
        foreach ($DB->get_records_sql($sql, $params) as $cm) {
            foreach (self::extract($cm, $map[$cm->modname]) as $item) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Pull the prose out of one course module.
     *
     * @param \stdClass $cm Row with cmid, instance, modname, sectionnum.
     * @param array $spec The module_map entry for this module.
     * @return array List of item objects.
     */
    protected static function extract(\stdClass $cm, array $spec): array {
        global $DB;

        $items = [];

        if ($cm->modname === 'book') {
            // A book's prose is per chapter, and a correction has to land in
            // the chapter it came from rather than in some merged blob.
            $chapters = $DB->get_records('book_chapters',
                ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum');
            $bookname = $DB->get_field('book', 'name', ['id' => $cm->instance]);
            foreach ($chapters as $chapter) {
                $items[] = self::make_item($cm, $spec['kind'],
                    $bookname . ': ' . $chapter->title, $chapter->content,
                    'book_chapters', 'content', (int) $chapter->id);
            }
            return $items;
        }

        $record = $DB->get_record($spec['table'], ['id' => $cm->instance]);
        if ($record) {
            $items[] = self::make_item($cm, $spec['kind'], $record->name,
                $record->{$spec['field']}, $spec['table'], $spec['field'],
                (int) $record->id);
        }

        if ($cm->modname === 'quiz') {
            foreach (self::quiz_questions((int) $cm->instance) as $question) {
                $items[] = self::make_item($cm, 'quiz',
                    get_string('question', 'moodle') . ': ' . $question->name,
                    $question->questiontext, 'question', 'questiontext',
                    (int) $question->id);
            }
        }

        return array_values(array_filter($items));
    }

    /**
     * The questions currently used by a quiz.
     *
     * Moodle 4.0+ reaches a question through the reference / version tables
     * rather than directly from the slot. A null reference version means "use
     * whichever version is latest", so that case resolves to the highest.
     *
     * @param int $quizid Quiz instance id.
     * @return array Question rows with id, name, questiontext.
     */
    protected static function quiz_questions(int $quizid): array {
        global $DB;

        $sql = "SELECT DISTINCT q.id, q.name, q.questiontext
                  FROM {quiz_slots} slot
                  JOIN {question_references} qr
                       ON qr.itemid = slot.id
                      AND qr.component = 'mod_quiz'
                      AND qr.questionarea = 'slot'
                  JOIN {question_versions} qv
                       ON qv.questionbankentryid = qr.questionbankentryid
                      AND qv.version = COALESCE(qr.version, (
                              SELECT MAX(v.version)
                                FROM {question_versions} v
                               WHERE v.questionbankentryid = qr.questionbankentryid))
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE slot.quizid = :quizid
              ORDER BY q.id";

        return $DB->get_records_sql($sql, ['quizid' => $quizid]);
    }

    /**
     * Build one item, or null when there is nothing worth checking.
     *
     * @param \stdClass $cm Row with cmid, modname, sectionnum.
     * @param string $kind Content kind label.
     * @param string $name Human-readable item name.
     * @param string|null $html The raw stored HTML.
     * @param string $table Table the prose lives in.
     * @param string $field Column the prose lives in.
     * @param int $recordid Row id in that table.
     * @return \stdClass|null The item, or null if too short to be worth a check.
     */
    protected static function make_item(\stdClass $cm, string $kind, string $name,
            ?string $html, string $table, string $field, int $recordid): ?\stdClass {
        $text = self::to_text((string) $html);
        if (\core_text::strlen($text) < self::MIN_CHARS) {
            return null;
        }

        return (object) [
            'cmid' => (int) $cm->cmid,
            'modname' => $cm->modname,
            'sectionnum' => (int) $cm->sectionnum,
            'kind' => $kind,
            'name' => $name,
            'html' => (string) $html,
            'text' => $text,
            'table' => $table,
            'field' => $field,
            'recordid' => $recordid,
        ];
    }

    /**
     * Where embedded files for an item's field are stored.
     *
     * Needed so an inserted image lands in the file area the field's renderer
     * actually rewrites @@PLUGINFILE@@ against. Getting this wrong produces a
     * broken image that still looks right in the database.
     *
     * @param \stdClass $item An item from this class.
     * @return array {contextid, component, filearea, itemid}.
     */
    public static function file_area(\stdClass $item): array {
        $context = \context_module::instance($item->cmid);

        if ($item->table === 'book_chapters') {
            // A book stores each chapter's files under the chapter's own id.
            return [
                'contextid' => $context->id,
                'component' => 'mod_book',
                'filearea' => 'chapter',
                'itemid' => $item->recordid,
            ];
        }

        if ($item->table === 'question') {
            // Question files live in the question bank's context, not the
            // module's, and the mapping is not one-to-one. Refused rather than
            // guessed.
            throw new \moodle_exception('error:imagetargetunsupported', 'local_contentchecker');
        }

        return [
            'contextid' => $context->id,
            'component' => 'mod_' . $item->modname,
            'filearea' => $item->field === 'content' ? 'content' : 'intro',
            'itemid' => 0,
        ];
    }

    /**
     * Stored HTML as prose the model can read.
     *
     * @param string $html Raw HTML.
     * @return string Plain text.
     */
    public static function to_text(string $html): string {
        return trim(preg_replace('/\n{3,}/', "\n\n",
            html_to_text($html, 0, false)));
    }
}

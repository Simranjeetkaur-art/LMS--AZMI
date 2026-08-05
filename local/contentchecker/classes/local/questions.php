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

use local_contentchecker\api\ai_backend;
use local_contentchecker\api\client_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Ungraded formative follow-up questions.
 *
 * These are deliberately NOT built on the Quiz module. Quiz attempts create
 * grade items, feed course completion and appear in the gradebook, and the
 * requirement here is the opposite: a self-check a learner can get wrong with
 * no consequence and no record. That is why this is a small table and a
 * renderer of its own rather than a thin wrapper over the quiz engine.
 *
 * Nothing generated here reaches a learner until an editor approves it, on the
 * same principle as content suggestions.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions {

    /** @var ai_backend The AI backend. */
    protected $client;

    /**
     * Constructor.
     *
     * @param ai_backend|null $client Optional backend override.
     */
    public function __construct(?ai_backend $client = null) {
        $this->client = $client ?? client_factory::make();
    }

    /**
     * Schema for generated questions.
     *
     * @return array JSON Schema.
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'qtype' => ['type' => 'string',
                                'enum' => ['multichoice', 'checkbox', 'truefalse', 'shorttext']],
                            'qtext' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'answer' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['qtype', 'qtext', 'answer', 'explanation'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    /**
     * Generate draft questions for every block of one activity.
     *
     * Existing questions for a block are left alone; regeneration is an
     * explicit action so an editor's approved set is never silently replaced.
     *
     * @param int $cmid Course module id.
     * @param bool $replace Delete existing drafts for the block first.
     * @return int Number of questions created.
     */
    public function generate_for_cm(int $cmid, bool $replace = false): int {
        global $DB, $USER;

        $items = content_source::for_cm($cmid);
        if (!$items) {
            return 0;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $perblock = max(1, min(10,
            (int) (get_config('local_contentchecker', 'questions_per_block') ?: 4)));
        $allowshort = (bool) get_config('local_contentchecker', 'questions_shorttext');
        $model = get_config('local_contentchecker', 'model_questions') ?: 'qwen3.5:35b';

        $created = 0;
        $now = time();

        foreach ($items as $item) {
            foreach (blocks::split($item->html) as $block) {
                if ($replace) {
                    $DB->delete_records('local_cchecker_questions', [
                        'cmid' => $cmid,
                        'blockref' => $block->ref,
                        'status' => 'draft',
                    ]);
                }

                try {
                    $result = $this->client->generate_json($model,
                        $this->prompt($block, $perblock, $allowshort), self::schema());
                } catch (\Throwable $e) {
                    // A block the model chokes on should not lose the others.
                    continue;
                }

                $sortorder = 0;
                foreach (($result['questions'] ?? []) as $q) {
                    $record = $this->normalise($q, $allowshort);
                    if (!$record) {
                        continue;
                    }

                    $record->courseid = (int) $cm->course;
                    $record->cmid = $cmid;
                    $record->blockref = $block->ref;
                    $record->status = 'draft';
                    $record->sortorder = $sortorder++;
                    $record->generatedby = 0;
                    $record->usermodified = (int) $USER->id;
                    $record->timecreated = $now;
                    $record->timemodified = $now;

                    $DB->insert_record('local_cchecker_questions', $record);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Build the generation prompt for one block.
     *
     * @param \stdClass $block The content block.
     * @param int $count How many questions to ask for.
     * @param bool $allowshort Whether free-text questions are permitted.
     * @return string The prompt.
     */
    protected function prompt(\stdClass $block, int $count, bool $allowshort): string {
        $types = $allowshort
            ? 'multichoice, checkbox, truefalse, shorttext'
            : 'multichoice, checkbox, truefalse';

        return "You are writing formative self-check questions for a university medical "
            . "course. These are NOT graded and NOT an exam; they exist to help a learner "
            . "notice what they have not understood.\n\n"
            . "Write exactly {$count} questions about the PASSAGE.\n"
            . "Use a mix of these types: {$types}.\n\n"
            . "RULES:\n"
            . "- Answer ONLY from the passage. Do not use outside knowledge.\n"
            . "- `answer` is an array of zero-based indexes into `options`.\n"
            . "- multichoice: exactly one correct index, 4 options.\n"
            . "- checkbox: two or more correct indexes, 4 or 5 options.\n"
            . "- truefalse: options MUST be exactly [\"True\", \"False\"].\n"
            . "- shorttext: `options` empty and `answer` empty; put the expected answer in "
            . "`explanation`.\n"
            . "- `explanation` is one or two sentences saying WHY, under 40 words.\n"
            . "- No trick questions and no negatively worded stems.\n\n"
            . "PASSAGE:\n" . $block->text;
    }

    /**
     * Validate and normalise one generated question.
     *
     * A malformed question is dropped rather than shown to an editor to fix:
     * an answer key that indexes past the end of the options list would tell a
     * learner that a correct answer is wrong.
     *
     * @param array $q The raw generated question.
     * @param bool $allowshort Whether free-text questions are permitted.
     * @return \stdClass|null The record, or null when unusable.
     */
    protected function normalise(array $q, bool $allowshort): ?\stdClass {
        $qtype = $q['qtype'] ?? '';
        $qtext = trim((string) ($q['qtext'] ?? ''));
        $options = array_values(array_filter(
            array_map('trim', (array) ($q['options'] ?? [])), fn($o) => $o !== ''));
        $answer = array_values(array_unique(array_map('intval', (array) ($q['answer'] ?? []))));

        if ($qtext === '' || !in_array($qtype, ['multichoice', 'checkbox', 'truefalse', 'shorttext'], true)) {
            return null;
        }
        if ($qtype === 'shorttext' && !$allowshort) {
            return null;
        }

        if ($qtype === 'truefalse') {
            $options = ['True', 'False'];
        }

        if ($qtype === 'shorttext') {
            $options = [];
            $answer = [];
        } else {
            if (count($options) < 2) {
                return null;
            }
            // Every index must actually exist, and there must be at least one.
            $answer = array_values(array_filter($answer,
                fn($i) => $i >= 0 && $i < count($options)));
            if (!$answer) {
                return null;
            }
            if ($qtype === 'multichoice' && count($answer) !== 1) {
                return null;
            }
            if ($qtype === 'checkbox' && count($answer) < 2) {
                return null;
            }
            // A checkbox question where everything is correct teaches nothing.
            if ($qtype === 'checkbox' && count($answer) === count($options)) {
                return null;
            }
        }

        return (object) [
            'qtype' => $qtype,
            'qtext' => $qtext,
            'options' => json_encode($options),
            'answer' => json_encode($answer),
            'explanation' => trim((string) ($q['explanation'] ?? '')),
        ];
    }

    /**
     * Approved questions for a block, ready to render to a learner.
     *
     * The answer key is deliberately included: grading happens in the browser
     * because nothing is recorded anywhere, and a round trip to score an
     * ungraded self-check would be pure latency. Nothing here is secret in the
     * way an exam answer is -- the learner is looking at the passage it came
     * from.
     *
     * @param int $cmid Course module id.
     * @param string|null $blockref Restrict to one block.
     * @return array List of question objects with decoded options.
     */
    public static function approved_for(int $cmid, ?string $blockref = null): array {
        global $DB;

        $params = ['cmid' => $cmid, 'status' => 'approved'];
        if ($blockref !== null) {
            $params['blockref'] = $blockref;
        }

        $rows = $DB->get_records('local_cchecker_questions', $params, 'blockref, sortorder, id');

        return array_values(array_map(fn($row) => (object) [
            'id' => (int) $row->id,
            'blockref' => $row->blockref,
            'qtype' => $row->qtype,
            'qtext' => $row->qtext,
            'options' => json_decode($row->options, true) ?: [],
            'answer' => json_decode($row->answer, true) ?: [],
            'explanation' => $row->explanation,
        ], $rows));
    }

    /**
     * Change a question's status, recording who did it.
     *
     * @param int $questionid The question.
     * @param string $status draft|approved|rejected.
     * @return void
     */
    public static function set_status(int $questionid, string $status): void {
        global $DB, $USER;

        if (!in_array($status, ['draft', 'approved', 'rejected'], true)) {
            throw new \coding_exception('Unknown question status: ' . $status);
        }

        $question = $DB->get_record('local_cchecker_questions',
            ['id' => $questionid], '*', MUST_EXIST);
        $before = $question->status;

        $question->status = $status;
        $question->approvedby = $status === 'approved' ? (int) $USER->id : null;
        $question->usermodified = (int) $USER->id;
        $question->timemodified = time();
        $DB->update_record('local_cchecker_questions', $question);

        audit::log('question', $questionid, $status, [
            'courseid' => (int) $question->courseid,
            'cmid' => (int) $question->cmid,
            'before' => $before,
            'after' => $status,
        ]);
    }
}

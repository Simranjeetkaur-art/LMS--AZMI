<?php
// Read-only Phase 2 smoke test for mod_flashdeck (no DB writes).
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);
// Process-local dummy caches: the site's string cache predates strings
// added since the last run and must not be mutated from here.
define('CACHE_DISABLE_ALL', true);
require('/var/www/moodle/public/config.php');

use mod_flashdeck\local\api;
use mod_flashdeck\scheduler\leitner;
use mod_flashdeck\scheduler\scheduler;
use mod_flashdeck\scheduler\sm2;

global $OUTPUT;

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};

$now = 1750000000;

// --- SM-2 interval maths ---------------------------------------------------
$s = scheduler::create('sm2');
$check('factory sm2', $s instanceof sm2);
$check('factory fallback', scheduler::create('bogus') instanceof sm2);
$check('factory leitner', scheduler::create('leitner') instanceof leitner);

$blank = scheduler::blank_review(1, 1, 1, $now);

$r = $s->grade($blank, scheduler::GRADE_GOOD, $now);
$check('sm2 new+good -> 10 min step', $r->state === 'learning' && $r->duedate === $now + 600);
$r = $s->grade($r, scheduler::GRADE_GOOD, $now + 600);
$check('sm2 graduate -> 1 day', $r->state === 'review' && (int) $r->intervaldays === 1
    && $r->duedate === $now + 600 + DAYSECS);

$t = $r->duedate;
$r = $s->grade($r, scheduler::GRADE_GOOD, $t);
$check('sm2 growth 1 -> 3', (int) $r->intervaldays === 3);
$t = $r->duedate;
$r = $s->grade($r, scheduler::GRADE_GOOD, $t);
$check('sm2 growth 3 -> 8', (int) $r->intervaldays === 8);
$t = $r->duedate;
$r = $s->grade($r, scheduler::GRADE_HARD, $t);
$check('sm2 hard 8 -> 10, ease 2.35', (int) $r->intervaldays === 10
    && abs($r->easefactor - 2.35) < 0.001);

$t = $r->duedate;
$r = $s->grade($r, scheduler::GRADE_AGAIN, $t);
$check('sm2 lapse: relearn +10 min, ease 2.15, lapses 1, interval kept',
    $r->state === 'relearning' && $r->duedate === $t + 600
    && abs($r->easefactor - 2.15) < 0.001 && (int) $r->lapses === 1 && (int) $r->intervaldays === 10);
$r = $s->grade($r, scheduler::GRADE_GOOD, $t + 600);
$check('sm2 relearn graduation halves interval -> 5', $r->state === 'review' && (int) $r->intervaldays === 5);

$capped = $s->grade((object) array_merge((array) $blank,
    ['state' => 'review', 'intervaldays' => 300, 'repetitions' => 9]), scheduler::GRADE_GOOD, $now);
$check('sm2 interval cap 365', (int) $capped->intervaldays === 365);

$previews = $s->preview($blank, $now);
$check('sm2 previews new card = 1m/1m/10m/4d',
    $previews == [0 => 60, 1 => 60, 2 => 600, 3 => 4 * DAYSECS]);

// --- Leitner ----------------------------------------------------------------
$l = scheduler::create('leitner');
$r = $l->grade($blank, scheduler::GRADE_GOOD, $now);
$check('leitner new+good -> box1 1d learning',
    (int) $r->repetitions === 1 && (int) $r->intervaldays === 1 && $r->state === 'learning');
$r = $l->grade($r, scheduler::GRADE_EASY, $r->duedate);
$check('leitner easy jumps to box3 4d review',
    (int) $r->repetitions === 3 && (int) $r->intervaldays === 4 && $r->state === 'review');
$r = $l->grade($r, scheduler::GRADE_AGAIN, $r->duedate);
$check('leitner again -> box1 +10 min, lapse counted',
    (int) $r->repetitions === 1 && (int) $r->lapses === 1 && $r->state === 'learning');

// --- Delay labels -----------------------------------------------------------
$check('format_delay 60s', api::format_delay(60) === '1 min');
$check('format_delay 600s', api::format_delay(600) === '10 min');
$check('format_delay 1 day', api::format_delay(DAYSECS) === '1 day');
$check('format_delay 3 days', api::format_delay(3 * DAYSECS) === '3 days');

// --- Learn page template renders -------------------------------------------
$html = $OUTPUT->render_from_template('mod_flashdeck/learn_page', [
    'uniqid' => 'flashdeck-test2', 'cmid' => 1, 'flashdeckid' => 1, 'intro' => '',
    'hascards' => true, 'hasdissection' => false, 'legendroles' => [],
    'done' => false, 'cardid' => 7,
    'cardhtml' => '<div class="flashdeck-face">x</div>',
    'previews' => ['again' => '1 min', 'hard' => '1 min', 'good' => '10 min', 'easy' => '4 days'],
    'counts' => ['duenow' => 0, 'learning' => 0, 'newremaining' => 16, 'total' => 16],
    'nextdue' => 0, 'nextduelabel' => '',
    'actionurl' => 'http://example.com/view.php', 'sesskey' => 'x',
    'browseurl' => '#', 'canmanage' => false, 'manageurl' => '#',
]);
$check('learn_page: grade form posts (no-JS path)',
    strpos($html, 'method="post"') !== false && substr_count($html, 'name="grade"') === 4);
$check('learn_page: previews on buttons', strpos($html, '4 days') !== false);
$check('learn_page: done panel present but hidden', strpos($html, 'data-region="donepanel" hidden') !== false);

// --- External function metadata --------------------------------------------
foreach (['get_next_due_card', 'submit_review', 'get_deck_progress'] as $fn) {
    $class = "\\mod_flashdeck\\external\\{$fn}";
    $check("external {$fn} class + signatures", class_exists($class)
        && method_exists($class, 'execute')
        && $class::execute_parameters() instanceof \core_external\external_function_parameters
        && $class::execute_returns() instanceof \core_external\external_single_structure);
}

echo $fails ? "\nSMOKE 2: {$fails} FAILURE(S)\n" : "\nSMOKE 2: ALL PASSED\n";
exit($fails ? 1 : 0);

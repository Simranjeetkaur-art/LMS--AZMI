<?php
// Read-only Phase 4 smoke test for mod_flashdeck (no DB writes).
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);
define('CACHE_DISABLE_ALL', true);
require('/var/www/moodle/public/config.php');

use mod_flashdeck\output\match_page;

global $CFG, $OUTPUT;
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};

// Features and lib functions.
$check('FEATURE_GRADE_HAS_GRADE', flashdeck_supports(FEATURE_GRADE_HAS_GRADE) === true);
$check('FEATURE_COMPLETION_HAS_RULES', flashdeck_supports(FEATURE_COMPLETION_HAS_RULES) === true);
foreach (['flashdeck_get_user_grades', 'flashdeck_grade_item_update', 'flashdeck_grade_item_delete',
        'flashdeck_update_grades', 'flashdeck_get_coursemodule_info'] as $fn) {
    $check("function {$fn}", function_exists($fn));
}
$check('custom_completion rules', \mod_flashdeck\completion\custom_completion::get_defined_custom_rules()
    === ['completionstudied', 'completionmastery']);

// Match pair extraction (pure logic).
$cards = [
    (object) ['id' => 1, 'cardtype' => 'matching', 'content' => json_encode([
        'prompt' => 'x', 'pairs' => [['left' => 'brady-', 'right' => 'slow']]])],
    (object) ['id' => 2, 'cardtype' => 'termdissection', 'content' => json_encode([
        'term' => 'hepatitis', 'definition' => 'Inflammation of the liver.', 'parts' => []])],
    (object) ['id' => 3, 'cardtype' => 'basic', 'content' => json_encode([
        'front' => '<p>' . str_repeat('x', 80) . '</p>', 'back' => '<p>short</p>'])],
];
$pairs = match_page::collect_pairs($cards);
$check('match pairs: 2 extracted, long one excluded', count($pairs) === 2
    && $pairs[0] === ['brady-', 'slow']);

// Learn page template with gamification context.
$html = $OUTPUT->render_from_template('mod_flashdeck/learn_page', [
    'uniqid' => 'fd-t1', 'cmid' => 1, 'flashdeckid' => 1, 'intro' => '',
    'hascards' => true, 'hasdissection' => false, 'legendroles' => [],
    'done' => false, 'cardid' => 7, 'cardhtml' => '<div class="flashdeck-face">x</div>',
    'previews' => ['again' => '1 min', 'hard' => '1 min', 'good' => '10 min', 'easy' => '4 days'],
    'counts' => ['duenow' => 0, 'learning' => 0, 'newremaining' => 16, 'total' => 16],
    'mastery' => 44, 'graduated' => 7, 'streak' => 3, 'points' => 210,
    'nextdue' => 0, 'nextduelabel' => '', 'actionurl' => '#', 'sesskey' => 'x',
    'browseurl' => '#', 'canmanage' => false, 'manageurl' => '#',
    'modelinks' => [['url' => '#', 'name' => 'Cram']], 'canviewreports' => true, 'reporturl' => '#',
]);
$check('learn page: mastery ring + streak + points + mode links',
    strpos($html, 'data-region="masteryring"') !== false
    && strpos($html, 'stroke-dasharray="44 100"') !== false
    && strpos($html, 'data-region="streak"') !== false
    && strpos($html, '>210</strong>') !== false
    && strpos($html, '>Cram</a>') !== false
    && strpos($html, 'Study report') !== false);

// Study page in test mode: right/wrong bar and summary present.
$html = $OUTPUT->render_from_template('mod_flashdeck/study_page', [
    'uniqid' => 'fd-t2', 'mode' => 'test', 'istest' => true, 'cmid' => 1, 'intro' => '',
    'hascards' => true, 'cardcount' => 1, 'hasdissection' => false, 'legendroles' => [],
    'cards' => [['cardid' => 1, 'cardtype' => 'basic', 'typename' => 'Basic', 'index' => 1,
        'first' => true, 'cardhtml' => '<div class="flashdeck-face">x</div>']],
    'canmanage' => false, 'manageurl' => '#', 'canstudy' => true, 'learnurl' => '#',
]);
$check('study page test mode: self-mark bar + summary',
    strpos($html, 'data-mode="test"') !== false
    && strpos($html, 'data-action="testright"') !== false
    && strpos($html, 'data-action="testrestart"') !== false);

// Match page template.
$html = $OUTPUT->render_from_template('mod_flashdeck/match_page', [
    'uniqid' => 'fd-t3', 'playable' => true, 'paircount' => 2,
    'tiles' => [['pairid' => 0, 'text' => 'brady-'], ['pairid' => 1, 'text' => 'fast'],
        ['pairid' => 0, 'text' => 'slow'], ['pairid' => 1, 'text' => 'tachy-']],
    'learnurl' => '#',
]);
$check('match page: tiles + timer + done panel',
    substr_count($html, 'flashdeck-tile"') === 4
    && strpos($html, 'data-region="matchtimer"') !== false
    && strpos($html, 'data-action="matchrestart"') !== false);

// Privacy metadata now declares both tables.
$collection = new \core_privacy\local\metadata\collection('mod_flashdeck');
$collection = \mod_flashdeck\privacy\provider::get_metadata($collection);
$check('privacy metadata: review + session tables', count($collection->get_collection()) === 2);

// The upgrade step parses and the new install.xml loads through XMLDB.
$xmldbfile = new xmldb_file($CFG->dirroot . '/mod/flashdeck/db/install.xml');
$check('install.xml XMLDB-valid with 4 tables', $xmldbfile->loadXMLStructure()
    && count($xmldbfile->getStructure()->getTables()) === 4);

echo $fails ? "\nSMOKE 4: {$fails} FAILURE(S)\n" : "\nSMOKE 4: ALL PASSED\n";
exit($fails ? 1 : 0);

<?php
// Read-only Phase 5 smoke test for mod_flashdeck (no DB writes).
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);
define('CACHE_DISABLE_ALL', true);
require('/var/www/moodle/public/config.php');

use mod_flashdeck\local\porter;

global $CFG, $OUTPUT;
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};

// Features and reset callbacks.
$check('FEATURE_BACKUP_MOODLE2 declared', flashdeck_supports(FEATURE_BACKUP_MOODLE2) === true);
foreach (['flashdeck_reset_userdata', 'flashdeck_reset_course_form_definition',
        'flashdeck_reset_course_form_defaults'] as $fn) {
    $check("function {$fn}", function_exists($fn));
}

// Backup classes parse and define the expected API.
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/mod/flashdeck/backup/moodle2/backup_flashdeck_activity_task.class.php');
require_once($CFG->dirroot . '/mod/flashdeck/backup/moodle2/restore_flashdeck_activity_task.class.php');
$check('backup task class', class_exists('backup_flashdeck_activity_task')
    && method_exists('backup_flashdeck_activity_task', 'encode_content_links'));
$check('restore task class + decode rules', class_exists('restore_flashdeck_activity_task')
    && count(restore_flashdeck_activity_task::define_decode_rules()) === 2);
$encoded = backup_flashdeck_activity_task::encode_content_links(
    'See ' . $CFG->wwwroot . '/mod/flashdeck/view.php?id=42 now');
$check('link encoding', strpos($encoded, '$@FLASHDECKVIEWBYID*42@$') !== false);

// GIFT parsing (pure logic, incl. escapes and skipped entries).
$gift = "::T1::What does -itis mean? {=inflammation =swelling}\n\n"
    . "What does brady- mean? {~fast =slow}\n\n"
    . "2 \\= 2 is true {T}\n\n"
    . "The suffix {=logy} means study.\n\n"
    . "Essay to skip {}\n\n"
    . "\$CATEGORY: cat\n\n"
    . "No braces at all";
$defs = porter::parse_gift($gift);
$check('gift: 4 entries parsed, essay/category/plain skipped', count($defs) === 4);
$check('gift: short answer alternatives', $defs[0]['content']['back'] === 'inflammation / swelling');
$check('gift: multichoice keeps correct', $defs[1]['content']['back'] === 'slow');
$check('gift: escaped equals survives', $defs[2]['content']['front'] === '2 = 2 is true');
$check('gift: missing word becomes cloze', $defs[3]['cardtype'] === 'cloze'
    && strpos($defs[3]['content']['text'], '[[logy]]') !== false);

// CSV field mappings round-trip per type (pure logic).
$samples = [
    'basic' => ['front' => 'F', 'frontformat' => FORMAT_HTML, 'back' => 'B', 'backformat' => FORMAT_HTML],
    'qanda' => ['front' => 'F', 'frontformat' => FORMAT_HTML, 'back' => 'B', 'backformat' => FORMAT_HTML,
        'guidance' => 'G'],
    'cloze' => ['text' => 'A [[b|c]] d.', 'casesensitive' => true],
    'termdissection' => ['term' => 'hepatitis', 'definition' => 'Liver inflammation.',
        'parts' => [['text' => 'hepat', 'role' => 'root', 'meaning' => 'liver'],
            ['text' => 'itis', 'role' => 'suffix', 'meaning' => 'inflammation']]],
    'matching' => ['prompt' => 'P', 'pairs' => [['left' => 'a', 'right' => 'b'], ['left' => 'c', 'right' => 'd']]],
    'ordering' => ['prompt' => 'P', 'items' => ['one', 'two', 'three']],
    'comparecontrast' => ['prompt' => 'P', 'columna' => 'A', 'columnb' => 'B',
        'rows' => [['aspect' => 'x', 'a' => '1', 'b' => '2']]],
];
foreach ($samples as $type => $content) {
    $fields = porter::content_to_csv($type, $content);
    $named = ['f1' => $fields[0], 'f2' => $fields[1], 'f3' => $fields[2], 'f4' => $fields[3]];
    $back = porter::content_from_csv($type, $named);
    $ok = true;
    foreach ($content as $key => $value) {
        $ok = $ok && ($back[$key] == $value);
    }
    $check("csv round-trip: {$type}", $ok);
    $problems = \mod_flashdeck\cardtype\manager::get($type)->validate_content($back);
    $check("csv result validates: {$type}", $problems === []);
}
$check('csv: imagelabel has no mapping', porter::content_to_csv('imagelabel', []) === null);

// Sample deck exports to JSON and parses back identically (no DB).
$sample = json_decode(file_get_contents($CFG->dirroot . '/mod/flashdeck/sample/emd101-week1.json'), true);
$fakedeck = (object) ['name' => 'X'];
$fakecards = [];
foreach ($sample['cards'] as $i => $def) {
    $fakecards[] = (object) ['id' => $i + 1, 'cardtype' => $def['cardtype'],
        'tags' => $def['tags'] ?? null, 'content' => json_encode($def['content'])];
}
$reparsed = json_decode(porter::export_json($fakedeck, $fakecards), true);
$check('json export mirrors sample structure', count($reparsed['cards']) === count($sample['cards'])
    && $reparsed['cards'][0]['content'] == $sample['cards'][0]['content']);

// Import/export/copy forms + mobile class resolve.
require_once($CFG->libdir . '/formslib.php');
$check('import_form builds', new \mod_flashdeck\form\import_form('http://example.com') !== null);
$check('copy_form builds', new \mod_flashdeck\form\copy_form('http://example.com',
    ['decks' => [1 => 'Deck A']]) !== null);
$check('mobile class + method', method_exists('\mod_flashdeck\output\mobile', 'mobile_course_view'));

// Mobile template renders with server-side delimiters swapped.
$html = $OUTPUT->render_from_template('mod_flashdeck/mobile_view', [
    'cmid' => 1, 'description' => '<p>d</p>', 'duenow' => 1, 'learning' => 2, 'newremaining' => 3,
    'total' => 4, 'mastery' => 50, 'streak' => 2, 'points' => 30, 'viewurl' => 'http://example.com/v',
]);
$check('mobile template: server values in, Angular {{ }} preserved',
    strpos($html, '<ion-badge slot="end">50%</ion-badge>') !== false
    && strpos($html, "{{ 'plugin.mod_flashdeck.statduenow' | translate }}") !== false
    && strpos($html, 'http://example.com/v') !== false);

echo $fails ? "\nSMOKE 5: {$fails} FAILURE(S)\n" : "\nSMOKE 5: ALL PASSED\n";
exit($fails ? 1 : 0);

<?php
// Read-only smoke test for mod_flashdeck Phase 1 (no DB writes).
define('CLI_SCRIPT', true);
// Process-local component rescan so the not-yet-installed plugin is visible
// without touching the running site's caches.
define('IGNORE_COMPONENT_CACHE', true);
require('/var/www/moodle/public/config.php');

global $CFG, $OUTPUT, $PAGE;

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};

// 1. Component + classloader see the plugin.
$dir = core_component::get_component_directory('mod_flashdeck');
$check('component directory resolved', $dir === $CFG->dirroot . '/mod/flashdeck');

// 2. Registry.
$types = \mod_flashdeck\cardtype\manager::get_types();
$check('registry has basic + termdissection', array_keys($types) === ['basic', 'termdissection']);
$check('exists() rejects unknown', !\mod_flashdeck\cardtype\manager::exists('hologram'));

// 3. Language strings resolve (spot-check + display names).
$sm = get_string_manager();
$check('modulename string', $sm->get_string('modulename', 'mod_flashdeck') === 'Flashcard deck');
foreach ($types as $id => $type) {
    $check("display name for {$id}: " . $type->get_display_name(), $type->get_display_name() !== '');
}

// 4. Sample deck validates through the real card-type code.
$json = json_decode(file_get_contents($CFG->dirroot . '/mod/flashdeck/sample/emd101-week1.json'), true);
$valid = 0;
$dissections = 0;
foreach ($json['cards'] as $i => $def) {
    $type = \mod_flashdeck\cardtype\manager::get($def['cardtype']);
    $problems = $type->validate_content($def['content']);
    if ($problems) {
        echo "  FAIL sample card {$i}: " . implode('; ', $problems) . "\n";
        $fails++;
    } else {
        $valid++;
    }
    $dissections += (int) ($def['cardtype'] === 'termdissection');
}
$check("sample deck: {$valid} cards valid, {$dissections} dissections", $valid === count($json['cards']) && $dissections >= 10);

// 5. Render both card templates through the real Mustache engine.
$syscontext = context_system::instance();
$fakedissection = (object) ['id' => 1, 'cardtype' => 'termdissection',
    'content' => json_encode($json['cards'][0]['content'])];
$type = \mod_flashdeck\cardtype\manager::get('termdissection');
$html = $OUTPUT->render_from_template($type->get_template(), $type->export_for_template($fakedissection, $syscontext));
$check('termdissection template renders term', strpos($html, 'cardiology') !== false);
$check('termdissection template has role classes', strpos($html, 'flashdeck-role-root') !== false
    && strpos($html, 'flashdeck-role-link') !== false && strpos($html, 'flashdeck-role-suffix') !== false);
$check('termdissection summary', $type->get_summary($fakedissection) === 'cardiology');

$fakebasic = (object) ['id' => 2, 'cardtype' => 'basic', 'content' => json_encode([
    'front' => '<p>What does <b>-itis</b> mean?</p>', 'frontformat' => FORMAT_HTML,
    'back' => '<p>Inflammation</p>', 'backformat' => FORMAT_HTML])];
$type = \mod_flashdeck\cardtype\manager::get('basic');
$html = $OUTPUT->render_from_template($type->get_template(), $type->export_for_template($fakebasic, $syscontext));
$check('basic template renders both faces', strpos($html, '-itis') !== false && strpos($html, 'Inflammation') !== false);
$check('basic no-JS reveal is a details element', strpos($html, '<details') !== false && strpos($html, 'Show answer') !== false);

// 6. Render the page templates with representative contexts.
$html = $OUTPUT->render_from_template('mod_flashdeck/study_page', [
    'uniqid' => 'flashdeck-test1', 'cmid' => 1, 'intro' => '', 'hascards' => true, 'cardcount' => 1,
    'hasdissection' => true,
    'legendroles' => [['role' => 'prefix', 'rolename' => 'Prefix'], ['role' => 'root', 'rolename' => 'Root']],
    'cards' => [['cardid' => 1, 'cardtype' => 'basic', 'typename' => 'Basic', 'index' => 1, 'first' => true,
        'cardhtml' => '<div class="flashdeck-face">x</div>']],
    'canmanage' => true, 'manageurl' => 'http://example.com/edit.php',
]);
$check('study_page: legend', strpos($html, 'flashdeck-legend') !== false);
$check('study_page: stage', strpos($html, 'data-region="stage"') !== false);
$check('study_page: flip control', strpos($html, 'data-action="flip"') !== false);
// The {{#js}} block goes to $PAGE->requires, not the returned HTML.
$check('study_page: AMD init queued', strpos($PAGE->requires->get_end_code(), 'mod_flashdeck/study') !== false);

$html = $OUTPUT->render_from_template('mod_flashdeck/manage_cards', [
    'hascards' => true, 'cardcount' => 1,
    'cards' => [['cardid' => 1, 'position' => 1, 'typename' => 'Basic', 'summary' => 'Front text',
        'tags' => 't1', 'editurl' => '#', 'deleteurl' => '#', 'upurl' => '#', 'downurl' => '#',
        'isfirst' => true, 'islast' => true]],
    'addbuttons' => [['url' => '#', 'name' => 'Basic']],
    'seedurl' => '#', 'viewurl' => '#',
]);
$check('manage_cards template renders', strpos($html, 'Front text') !== false && strpos($html, 'Delete') !== false);

// 7. mod_form parses (definition needs course context; just class-load it).
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/flashdeck/mod_form.php');
$check('mod_form class loads', class_exists('mod_flashdeck_mod_form'));

// 8. Privacy provider metadata.
$collection = new \core_privacy\local\metadata\collection('mod_flashdeck');
$collection = \mod_flashdeck\privacy\provider::get_metadata($collection);
$check('privacy metadata declares flashdeck_review', count($collection->get_collection()) === 1);

echo $fails ? "\nSMOKE TEST: {$fails} FAILURE(S)\n" : "\nSMOKE TEST: ALL PASSED\n";
exit($fails ? 1 : 0);

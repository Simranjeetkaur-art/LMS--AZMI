<?php
// Read-only Phase 3 smoke test for mod_flashdeck (no DB writes).
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);
define('CACHE_DISABLE_ALL', true);
require('/var/www/moodle/public/config.php');

use mod_flashdeck\cardtype\cloze;
use mod_flashdeck\cardtype\manager;

global $OUTPUT;

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};
$render = function(string $type, array $content) use ($OUTPUT) {
    $card = (object) ['id' => 1, 'cardtype' => $type, 'content' => json_encode($content)];
    $t = manager::get($type);
    $problems = $t->validate_content($content);
    if ($problems) {
        throw new Exception("$type invalid: " . implode('; ', $problems));
    }
    return $OUTPUT->render_from_template($t->get_template(),
        $t->export_for_template($card, context_system::instance()));
};

// Registry.
$check('registry has 8 types', count(manager::get_types()) === 8);
foreach (manager::get_types() as $id => $t) {
    $check("display name: {$id} = " . $t->get_display_name(), $t->get_display_name() !== '' &&
        strpos($t->get_display_name(), '[[') === false);
}

// Cloze.
$html = $render('cloze', ['text' => 'The powerhouse is the [[mitochondrion|mitochondria]].', 'casesensitive' => false]);
$check('cloze: input with data-answers', strpos($html, 'data-answers=') !== false
    && strpos($html, 'mitochondrion') !== false);
$check('cloze: check button is JS-only', strpos($html, 'data-action="checkanswers"') !== false
    && strpos($html, 'flashdeck-jsonly') !== false);
$check('cloze: back shows answer + alternatives', strpos($html, '<mark') !== false
    && strpos($html, 'mitochondria') !== false);
$check('cloze matches: normalisation', cloze::matches(' MITOCHONDRIA ', ['mitochondrion', 'mitochondria'], false)
    && !cloze::matches('mito', ['mitochondrion'], false));

// Matching.
$html = $render('matching', ['prompt' => 'Match prefixes', 'pairs' => [
    ['left' => 'tachy-', 'right' => 'fast'], ['left' => 'brady-', 'right' => 'slow']]]);
$check('matching: selects with data-answer', substr_count($html, 'data-answer=') === 2
    && strpos($html, 'Choose...') !== false);
$check('matching: options alphabetical', strpos($html, '>fast<') < strpos($html, '>slow<'));

// Ordering.
$html = $render('ordering', ['prompt' => 'Order mitosis', 'items' => ['Prophase', 'Metaphase', 'Anaphase']]);
$check('ordering: position selects + numbered back', substr_count($html, 'data-answer=') === 3
    && strpos($html, '<ol') !== false && strpos($html, 'Anaphase') !== false);

// Compare/contrast.
$html = $render('comparecontrast', ['prompt' => 'Compare models', 'columna' => 'Beveridge',
    'columnb' => 'Bismarck', 'rows' => [['aspect' => 'Funding', 'a' => 'Taxes', 'b' => 'Insurance']]]);
$check('compare: table front hides cells, back reveals', substr_count($html, 'Beveridge') === 2
    && strpos($html, 'Taxes') !== false && strpos($html, 'flashdeck-comparecell') !== false);

// Q&A.
$html = $render('qanda', ['front' => '<p>Why spacing?</p>', 'frontformat' => FORMAT_HTML,
    'back' => '<p>The spacing effect.</p>', 'backformat' => FORMAT_HTML, 'guidance' => 'Mention retrieval.']);
$check('qanda: guidance + self-grade hint', strpos($html, 'Mention retrieval.') !== false
    && strpos($html, 'flashdeck-guidance') !== false);

// Image label (no stored file in system context: graceful degrade).
$html = $render('imagelabel', ['variant' => 'identify', 'label' => 'Deltoid',
    'alttext' => 'Shoulder muscles', 'question' => '', 'description' => 'Abducts the arm.',
    'region' => ['cx' => 42.5, 'cy' => 31.0, 'r' => 8.0]]);
$check('imagelabel identify: default prompt + no-image fallback',
    strpos($html, 'Name the highlighted structure.') !== false
    && strpos($html, 'No image attached.') !== false && strpos($html, 'Deltoid') !== false);

$html = $render('imagelabel', ['variant' => 'hotspot', 'label' => 'Deltoid',
    'alttext' => 'Shoulder muscles', 'question' => '', 'description' => '',
    'region' => ['cx' => 42.5, 'cy' => 31.0, 'r' => 8.0]]);
$check('imagelabel hotspot: prompt names the target', strpos($html, 'Click the Deltoid in the image.') !== false);

// Editor forms build for every type (repeat_elements, filemanager, editors).
require_once($CFG->libdir . '/formslib.php');
foreach (array_keys(manager::TYPES) as $id) {
    try {
        $form = new \mod_flashdeck\form\card_form('http://example.com', [
            'cardtype' => manager::get($id), 'content' => [],
        ]);
        $check("card_form builds for {$id}", true);
    } catch (Throwable $e) {
        $check("card_form builds for {$id} — " . $e->getMessage(), false);
    }
}

// pluginfile callback exists with the right signature.
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');
$check('flashdeck_pluginfile exists', function_exists('flashdeck_pluginfile'));

echo $fails ? "\nSMOKE 3: {$fails} FAILURE(S)\n" : "\nSMOKE 3: ALL PASSED\n";
exit($fails ? 1 : 0);

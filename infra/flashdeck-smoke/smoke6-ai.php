<?php
// Read-only smoke test for mod_flashdeck AI generation (no DB writes).
//
// The live section borrows the qbank_ollama_questiongen server settings
// READ-ONLY via the client's override constructor — nothing is saved to
// mod_flashdeck config. If no server is configured or reachable, the
// live section reports SKIP rather than failing.
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);
define('CACHE_DISABLE_ALL', true);
require('/var/www/moodle/public/config.php');

use mod_flashdeck\local\ai_client;
use mod_flashdeck\local\ai_generator;

$fails = 0;
$check = function(string $label, bool $ok) use (&$fails) {
    echo ($ok ? "  OK   " : "  FAIL ") . $label . "\n";
    if (!$ok) {
        $fails++;
    }
};

// --- Registry-driven contract ------------------------------------------------
$types = ai_generator::get_generatable_types();
$check('7 generatable types, imagelabel excluded',
    count($types) === 7 && !isset($types['imagelabel']));

$messages = ai_generator::build_messages('The heart pumps blood.', ['basic', 'termdissection'], 4);
$check('prompt: system + user roles', $messages[0]['role'] === 'system' && $messages[1]['role'] === 'user');
$check('prompt: dynamic contract lists requested types only',
    strpos($messages[0]['content'], '"termdissection"') !== false
    && strpos($messages[0]['content'], '"matching"') === false
    && strpos($messages[0]['content'], 'exactly 4 cards') !== false);
$check('prompt: source in user message', strpos($messages[1]['content'], 'heart pumps') !== false);
$check('prompt: no hardcoded server or model anywhere',
    strpos(json_encode($messages), '11434') === false);

// --- Response parsing ---------------------------------------------------------
$card = ['cardtype' => 'basic', 'tags' => 'w1', 'content' => ['front' => 'Q', 'back' => 'A']];
$json = json_encode([$card]);
$check('parse: plain array', count(ai_generator::parse_response($json)) === 1);
$check('parse: fenced + commentary + think block',
    count(ai_generator::parse_response("<think>hm</think>Sure! ```json\n{$json}\n``` done")) === 1);
$check('parse: object wrapper', count(ai_generator::parse_response(json_encode(['cards' => [$card]]))) === 1);
$check('parse: garbage rejected', ai_generator::parse_response('no json here') === []);

[$valid, $rejected] = ai_generator::validate_proposals([
    $card,
    ['cardtype' => 'basic', 'tags' => null, 'content' => ['front' => 'broken']],
    ['cardtype' => 'hologram', 'tags' => null, 'content' => []],
], ['basic']);
$check('validate: 1 valid, 2 rejected with reasons', count($valid) === 1 && count($rejected) === 2
    && $rejected[0]['problem'] !== '');

// --- Configuration behaviour --------------------------------------------------
$client = new ai_client();
$check('unconfigured by default (no hardcoded endpoint)', !$client->is_configured());
$check('feature unavailable when unconfigured', !ai_generator::is_available());
$override = new ai_client(['baseurl' => 'http://x/', 'model' => 'm']);
$check('constructor overrides work', $override->is_configured() && $override->get_model() === 'm');

// --- Live end-to-end (borrows the qbank AI server config, read-only) ----------
$qb = 'qbank_ollama_questiongen';
$baseurl = trim((string) get_config($qb, 'baseurl'));
if ($baseurl === '') {
    echo "  SKIP live generation: no site AI server configured\n";
} else {
    $live = new ai_client([
        'provider' => get_config($qb, 'provider_type') ?: 'ollama',
        'baseurl' => $baseurl,
        'bearertoken' => (string) get_config($qb, 'bearer_token'),
        'model' => '',
        'timeout' => (int) get_config($qb, 'timeout') ?: 120,
        'temperature' => 0.3,
    ]);
    $models = $live->get_models();
    if (!$models) {
        echo "  SKIP live generation: AI server unreachable or no models served\n";
    } else {
        echo "  info: server {$baseurl}, model {$models[0]}\n";
        $live = new ai_client([
            'provider' => get_config($qb, 'provider_type') ?: 'ollama',
            'baseurl' => $baseurl,
            'bearertoken' => (string) get_config($qb, 'bearer_token'),
            'model' => $models[0],
            'timeout' => (int) get_config($qb, 'timeout') ?: 120,
            'temperature' => 0.3,
        ]);
        $source = "Medical terminology basics:\n"
            . "The suffix -itis means inflammation, as in hepatitis (inflammation of the liver).\n"
            . "The prefix brady- means slow, as in bradycardia (a slow heart rate).\n"
            . "The root cardi means heart; the combining vowel o joins word parts, as in cardiology.";
        $result = ai_generator::generate($source, ['basic', 'cloze', 'termdissection'], 4, $live);

        $check('live: no transport error', $result['error'] === '');
        $check('live: model produced >= 2 valid cards (' . count($result['cards']) . ' valid, '
            . count($result['rejected']) . ' rejected)', count($result['cards']) >= 2);
        foreach (array_slice($result['cards'], 0, 4) as $i => $def) {
            $summary = \mod_flashdeck\cardtype\manager::get($def['cardtype'])
                ->get_summary((object) ['content' => json_encode($def['content'])]);
            echo "  card {$i}: [{$def['cardtype']}] {$summary}\n";
        }
    }
}

echo $fails ? "\nSMOKE 6: {$fails} FAILURE(S)\n" : "\nSMOKE 6: ALL PASSED\n";
exit($fails ? 1 : 0);

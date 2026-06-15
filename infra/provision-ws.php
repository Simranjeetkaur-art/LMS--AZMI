<?php
// This file is part of the AZMSI platform tooling.
//
// provision-ws.php — finish AGENT_01 §5: create the `ws_consumer` role, the
// `svc_website` / `svc_apply` service accounts, assign the role at system
// context, and mint one permanent token each scoped to the `azmsi_ws` service.
//
// This script MUTATES the running site (roles, users, tokens). It is therefore
// DRY-RUN by default and prints exactly what it would do. Re-run with --commit
// to apply. On PRODUCTION (azmsi.unicornfortunes.com) only run --commit inside
// a maintenance window with a fresh DB + dataroot backup (see infra rules).
//
// It does NOT enable web services, install plugins, or run upgrade.php — those
// are separate, explicit steps. It is idempotent: existing role / users / tokens
// are detected and reused, never duplicated.
//
// Usage:
//   php infra/provision-ws.php                 # dry-run (default) — shows the plan
//   php infra/provision-ws.php --commit         # apply changes
//   php infra/provision-ws.php --commit --write-env=/path/to/infra/.env
//
// Exit codes: 0 = ok (or clean dry-run), non-zero = a precondition failed.

define('CLI_SCRIPT', true);

// Resolve the Moodle root: this script lives at azmsi-plugins/infra/, and the
// live Moodle config.php is at the project root one level above public/.
$moodleroot = getenv('MOODLE_ROOT') ?: '/var/www/moodle';
require($moodleroot . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'commit' => false, 'write-env' => ''],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Provision the azmsi_ws role, service accounts and tokens.");
    cli_writeln("  (no flag)        dry-run, print the plan");
    cli_writeln("  --commit         apply changes");
    cli_writeln("  --write-env=FILE append the minted tokens to FILE (gitignored .env)");
    exit(0);
}

$commit = (bool)$options['commit'];
$mode   = $commit ? 'COMMIT' : 'DRY-RUN';

cli_heading("AZMSI web-service provisioning  [{$mode}]");
cli_writeln("  site: {$CFG->wwwroot}");
cli_writeln("");

// --- Preconditions --------------------------------------------------------
if (!get_config('moodle', 'enablewebservices')) {
    cli_error("enablewebservices is OFF — enable Web Services before provisioning.");
}
if (strpos((string)get_config('moodle', 'webserviceprotocols'), 'rest') === false) {
    cli_error("REST protocol is not enabled — enable it before provisioning.");
}
$service = $DB->get_record('external_services', ['shortname' => 'azmsi_ws']);
if (!$service) {
    cli_error("external service 'azmsi_ws' does not exist — create it first (AGENT_01 §5.3).");
}
cli_writeln("✓ web services on, REST on, azmsi_ws service id={$service->id} (enabled={$service->enabled})");
cli_writeln("");

$syscontext = context_system::instance();

// Capabilities the ws_consumer role carries: REST transport + every local_azmsi
// ws_* capability. Per-function capability checks then gate each call.
$caps = [
    'webservice/rest:use',
    'local/azmsi:ws_catalog',
    'local/azmsi:ws_student',
    'local/azmsi:ws_faculty',
    'local/azmsi:ws_admin',
    'local/azmsi:ws_apply',
];

// --- 1. Role: ws_consumer -------------------------------------------------
$roleid = $DB->get_field('role', 'id', ['shortname' => 'ws_consumer']);
if ($roleid) {
    cli_writeln("• role ws_consumer: exists (id={$roleid})");
} else {
    cli_writeln("• role ws_consumer: MISSING -> create");
    if ($commit) {
        $roleid = create_role('AZMSI WS consumer', 'ws_consumer',
            'External web-service consumer for the azmsi_ws service. System context only.');
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        cli_writeln("  created role id={$roleid}, contextlevel=system");
    }
}

foreach ($caps as $cap) {
    if (!get_capability_info($cap)) {
        cli_writeln("  ! capability {$cap} is not defined yet (install local_azmsi / its upgrade) — skipping");
        continue;
    }
    $has = $roleid ? $DB->record_exists('role_capabilities',
        ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id, 'permission' => CAP_ALLOW]) : false;
    if ($has) {
        cli_writeln("  cap ok    {$cap}");
    } else {
        cli_writeln("  cap add   {$cap}");
        if ($commit && $roleid) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
    }
}
cli_writeln("");

// --- 2. Service accounts --------------------------------------------------
$accounts = [
    'svc_website' => ['first' => 'Website', 'last' => 'Service', 'email' => 'svc_website@azmsi.invalid'],
    'svc_apply'   => ['first' => 'Apply',   'last' => 'Service', 'email' => 'svc_apply@azmsi.invalid'],
];
$userids = [];
foreach ($accounts as $username => $a) {
    $u = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($u) {
        $userids[$username] = $u->id;
        cli_writeln("• user {$username}: exists (id={$u->id})");
    } else {
        cli_writeln("• user {$username}: MISSING -> create (auth=webservice, confirmed)");
        if ($commit) {
            $new = new stdClass();
            $new->username   = $username;
            $new->auth       = 'webservice';
            $new->confirmed  = 1;
            $new->mnethostid = $CFG->mnet_localhost_id;
            $new->firstname  = $a['first'];
            $new->lastname   = $a['last'];
            $new->email      = $a['email'];
            $new->policyagreed = 1;
            $new->password   = ''; // webservice auth has no interactive login.
            $userids[$username] = user_create_user($new, false, false);
            cli_writeln("  created user id={$userids[$username]}");
        }
    }
    // Assign ws_consumer at system context.
    if ($roleid && !empty($userids[$username])) {
        $assigned = $DB->record_exists('role_assignments',
            ['roleid' => $roleid, 'userid' => $userids[$username], 'contextid' => $syscontext->id]);
        if ($assigned) {
            cli_writeln("  role ok   ws_consumer @ system");
        } else {
            cli_writeln("  role add  ws_consumer @ system");
            if ($commit) {
                role_assign($roleid, $userids[$username], $syscontext->id);
            }
        }
    }
}
cli_writeln("");

// --- 3. Tokens (one each, permanent, scoped to azmsi_ws) ------------------
$tokens = [];
foreach (array_keys($accounts) as $username) {
    $uid = $userids[$username] ?? null;
    if (!$uid) {
        cli_writeln("• token {$username}: (user not created in dry-run) -> would mint");
        continue;
    }
    $existing = $DB->get_record('external_tokens',
        ['userid' => $uid, 'externalserviceid' => $service->id, 'tokentype' => EXTERNAL_TOKEN_PERMANENT]);
    if ($existing) {
        cli_writeln("• token {$username}: exists (not reprinted)");
    } else {
        cli_writeln("• token {$username}: MISSING -> mint permanent token scoped to azmsi_ws");
        if ($commit) {
            $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $uid, $syscontext);
            $tokens[$username] = $token;
            cli_writeln("  minted: {$token}");
        }
    }
}

// --- 4. Optionally append tokens to a gitignored .env ---------------------
if ($commit && $tokens && $options['write-env']) {
    $envfile = $options['write-env'];
    $map = ['svc_website' => 'MOODLE_WS_TOKEN', 'svc_apply' => 'MOODLE_WS_TOKEN_APPLY'];
    $lines = "";
    foreach ($tokens as $username => $tok) {
        if (isset($map[$username])) {
            $lines .= "{$map[$username]}={$tok}\n";
        }
    }
    file_put_contents($envfile, $lines, FILE_APPEND);
    cli_writeln("");
    cli_writeln("✓ appended " . count($tokens) . " token(s) to {$envfile}");
}

cli_writeln("");
if ($commit) {
    cli_writeln("Done. Verify with:");
    cli_writeln("  curl '{$CFG->wwwroot}/webservice/rest/server.php?wstoken=<TOKEN>&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json'");
    cli_writeln("Remember: azmsi_ws is still disabled — enable it once its functions are ready.");
} else {
    cli_writeln("Dry-run only. Re-run with --commit (inside maintenance + backup on prod) to apply.");
}
exit(0);

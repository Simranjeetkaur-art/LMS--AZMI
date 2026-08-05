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

/**
 * Language strings for local_emdverify.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'eMD content verifier';

// Capabilities.
$string['emdverify:view'] = 'View the content verification ledger';
$string['emdverify:run'] = 'Queue a content verification run';
$string['emdverify:adjudicate'] = 'Sign off on a verification finding';
$string['emdverify:managesources'] = 'Manage the evidence corpus and source allowlist';

// Navigation and pages.
$string['ledger'] = 'Content verification';
$string['ledgerfor'] = 'Content verification: {$a}';
$string['courseoverview'] = 'Verification overview';
$string['week'] = 'Week {$a}';
$string['queuerun'] = 'Verify this week';
$string['requeuerun'] = 'Re-verify';
$string['runqueued'] = 'Verification queued. It runs in the background; this page updates itself.';
$string['runrunning'] = 'Running — {$a}% complete';
$string['runfailed'] = 'Verification failed: {$a}';
$string['runnever'] = 'Not yet verified';
$string['lastverified'] = 'Last verified {$a}';
$string['noclaims'] = 'No claims were extracted. The week may contain no prose pages.';

// Verdicts.
$string['verdict:supported'] = 'Supported';
$string['verdict:partially_supported'] = 'Partially supported';
$string['verdict:contradicted'] = 'Contradicted';
$string['verdict:contested'] = 'Contested';
$string['verdict:unsupported'] = 'No evidence found';
$string['verdict:outdated'] = 'Possibly outdated';
$string['verdict:needs_source'] = 'Needs a source';
$string['verdict:contested_help'] = 'Sources disagree, or a source permits this as one of ' .
    'several accepted variants. This is a prompt to look, not a claim that the content is wrong.';

// Ledger UI.
$string['claim'] = 'Claim';
$string['sources'] = 'Sources';
$string['supportingquote'] = 'Supporting quote';
$string['quotenotverbatim'] = 'This quote could not be matched verbatim in the cited source ' .
    'and should be treated as unreliable.';
$string['suggestedcorrection'] = 'Suggested correction';
$string['machinereasoning'] = 'Machine reasoning';
$string['modelconfidence'] = 'Model confidence: {$a}';
$string['modelconfidence_help'] = 'Recorded for inspection only. This number is deliberately ' .
    'not used to rank or filter anything — it read 1.0 on a verified false positive.';
$string['quorum'] = 'Adjudication';
$string['reviewprompt'] = 'Is this finding correct?';
$string['review:agree'] = 'Agree';
$string['review:disagree'] = 'Disagree';
$string['review:unsure'] = 'Unsure';
$string['reviewnotes'] = 'Notes (optional)';
$string['reviewsaved'] = 'Saved';
$string['reviewedby'] = 'Reviewed by {$a->user} on {$a->date}';
$string['filterall'] = 'All';
$string['filterflagged'] = 'Needs attention';
$string['filterunreviewed'] = 'Not yet reviewed';
$string['summary'] = '{$a->total} claims · {$a->flagged} need attention · {$a->reviewed} reviewed';

// Accuracy panel.
$string['accuracy'] = 'Verifier accuracy';
$string['accuracydesc'] = 'How often a human agreed with the machine, by verdict. Until the ' .
    '"Contradicted" row clears 80%, treat every finding as a suggestion only.';
$string['accuracyrow'] = '{$a->verdict}: {$a->agree}/{$a->total} agreed ({$a->pct}%)';
$string['accuracynone'] = 'Not enough reviews yet to report accuracy.';

// Corpus.
$string['sourcesheading'] = 'Evidence sources';
$string['sourcetier'] = 'Tier';
$string['sourcetier1'] = 'Tier 1 — licensed textbook';
$string['sourcetier2'] = 'Tier 2 — guideline body';
$string['sourcetier3'] = 'Tier 3 — open reference';
$string['tierwarning'] = 'This corpus currently contains only tier 3 (open reference) material. ' .
    'It is adequate for checking whether the pipeline behaves, but it is not an authoritative ' .
    'medical corpus. Do not treat a finding as clinically decisive.';
$string['rebuildcorpus'] = 'Rebuild corpus';
$string['corpusstatus'] = '{$a->sources} sources · {$a->chunks} passages embedded';

// Settings.
$string['setting:serverheading'] = 'AI server';
$string['setting:serverheading_desc'] = 'The self-hosted GPU gateway used for verification.';
$string['setting:endpoint'] = 'Endpoint';
$string['setting:endpoint_desc'] = 'Base URL of the Ollama gateway.';
$string['setting:token'] = 'Bearer token';
$string['setting:token_desc'] = 'Sent as an Authorization header.';
$string['setting:useragent'] = 'User agent';
$string['setting:useragent_desc'] = 'Must look like a browser — the gateway is behind ' .
    'Cloudflare, which challenges bot user-agents on this hostname.';
$string['setting:modelheading'] = 'Models';
$string['setting:modelheading_desc'] = 'Model names as listed by /api/tags on the gateway.';
$string['setting:model_atomise'] = 'Atomiser model';
$string['setting:model_atomise_desc'] = 'Splits course prose into individual claims. A small ' .
    'fast model is appropriate.';
$string['setting:model_adjudicate'] = 'Adjudicator model';
$string['setting:model_adjudicate_desc'] = 'Judges a claim against retrieved sources. Use the ' .
    'largest model the box can hold comfortably.';
$string['setting:model_embed'] = 'Embedding model';
$string['setting:model_embed_desc'] = 'Used for retrieval and de-duplication.';
$string['setting:tuningheading'] = 'Request tuning';
$string['setting:tuningheading_desc'] = 'These defaults were established by measurement against ' .
    'the live gateway. Change them only with a reason.';
$string['setting:numctx'] = 'Context window (num_ctx)';
$string['setting:numctx_desc'] = 'The gateway otherwise loads models with a 262144-token ' .
    'context, which allocates enough VRAM to force evictions between models.';
$string['setting:numpredict'] = 'Max generated tokens (num_predict)';
$string['setting:numpredict_desc'] = 'Hard ceiling per call. A JSON schema constrains shape but ' .
    'not length; without a ceiling one runaway generation blocks every queued call behind it.';
$string['setting:timeout'] = 'Request timeout (seconds)';
$string['setting:timeout_desc'] = 'Keep this short. Requests can sit behind a busy model with ' .
    'no bytes returned; failing fast and retrying drains past the queue.';
$string['setting:keepalive'] = 'Model keep-alive';
$string['setting:keepalive_desc'] = 'Holds the adjudicator resident so a run does not pay a ' .
    'cold model load on every claim.';
$string['setting:retrievalheading'] = 'Retrieval';
$string['setting:retrievalheading_desc'] = 'Evidence is found by semantic similarity over the ' .
    'local corpus. Live keyword search is deliberately not used — it returned unrelated ' .
    'articles and hit third-party rate limits.';
$string['setting:topk'] = 'Passages per claim';
$string['setting:topk_desc'] = 'How many corpus passages are shown to the adjudicator.';
$string['setting:minscore'] = 'Minimum similarity';
$string['setting:minscore_desc'] = 'Below this a claim is reported as needing a source rather ' .
    'than being judged on weak evidence.';
$string['setting:dedupe'] = 'Duplicate threshold';
$string['setting:dedupe_desc'] = 'Claims more similar than this are collapsed; the same ' .
    'assertion usually recurs across the overview, lecture and reading.';

// Errors.
$string['error:notconfigured'] = 'The AI server endpoint or token is not configured.';
$string['error:aicall'] = 'The AI server call failed: {$a}';
$string['error:aihttp'] = 'The AI server returned HTTP {$a}.';
$string['error:aijson'] = 'The AI server did not return valid JSON: {$a}';
$string['error:aiembedding'] = 'The AI server did not return an embedding.';
$string['error:nocorpus'] = 'The evidence corpus is empty. Build it before running verification.';
$string['error:serverdown'] = 'The AI server is not responding. Verification was not queued.';

// Privacy.
$string['privacy:metadata:local_emdverify_review'] = 'Decisions a reviewer records against ' .
    'verification findings.';
$string['privacy:metadata:local_emdverify_review:userid'] = 'The user who recorded the decision.';
$string['privacy:metadata:local_emdverify_review:decision'] = 'Whether the reviewer agreed ' .
    'with the machine verdict.';
$string['privacy:metadata:local_emdverify_review:notes'] = 'Free-text notes from the reviewer.';
$string['privacy:metadata:local_emdverify_review:timecreated'] = 'When the decision was made.';
$string['privacy:metadata:aiserver'] = 'Course content is sent to the configured self-hosted AI ' .
    'server for verification. No personal data is transmitted.';
$string['privacy:metadata:aiserver:content'] = 'The text of course pages being verified.';

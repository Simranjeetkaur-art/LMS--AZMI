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
 * Language strings for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Content checker';
$string['settings'] = 'Settings';
$string['coursedashboard'] = 'Content checker';
$string['siteoverview'] = 'Content checker overview';
$string['managesources'] = 'Reference sources';
$string['manageassets'] = 'Enrichment assets';
$string['managepronunciation'] = 'Pronunciation dictionary';
$string['tier'] = 'Tier';
$string['quorum:single'] = 'Single pass';

// Capabilities.
$string['contentchecker:view'] = 'View content checker dashboards and findings';
$string['contentchecker:manage'] = 'Run checks and use the enrichment and publishing tools';
$string['contentchecker:approve'] = 'Decide on AI suggestions, including applying them to live content';
$string['contentchecker:runbackground'] = 'Queue background whole-course verification jobs';
$string['contentchecker:viewsitereports'] = 'View content checker status across all courses';
$string['contentchecker:manageconfig'] = 'Manage reference sources, assets and the pronunciation dictionary';

// Message provider.
$string['messageprovider:jobcomplete'] = 'Background verification job finished';

// Dashboard.
$string['dashboard:week'] = 'Week';
$string['dashboard:items'] = 'Items';
$string['dashboard:status'] = 'Status';
$string['dashboard:pending'] = 'Awaiting review';
$string['dashboard:lastchecked'] = 'Last checked';
$string['dashboard:actions'] = 'Actions';
$string['dashboard:verifyweek'] = 'Verify this week';
$string['dashboard:verifycourse'] = 'Verify the whole course in the background';
$string['dashboard:review'] = 'Review findings';
$string['dashboard:publish'] = 'Publish layout';
$string['dashboard:course'] = 'Course';
$string['dashboard:courses'] = 'Courses';
$string['dashboard:checks'] = 'Checks run';
$string['dashboard:queued'] = 'Queued';
$string['dashboard:jobqueued'] = 'Queued {$a} week check(s). You will be notified when the job finishes.';

// Status badges.
$string['status:never'] = 'Never checked';
$string['status:ok'] = 'Verified OK';
$string['status:needsreview'] = 'Needs review';
$string['status:failed'] = 'Check failed';
$string['status:running'] = 'Checking';
$string['status:empty'] = 'Nothing extracted';

// Verdicts.
$string['verdict:supported'] = 'Supported';
$string['verdict:partially_supported'] = 'Partially supported';
$string['verdict:contradicted'] = 'Contradicted';
$string['verdict:contested'] = 'Contested';
$string['verdict:unsupported'] = 'Unsupported';
$string['verdict:outdated'] = 'Outdated';
$string['verdict:needs_source'] = 'No source found';
$string['verdict:error'] = 'Could not judge';

// Decisions.
$string['decision:pending'] = 'Awaiting a decision';
$string['decision:approved'] = 'Approved';
$string['decision:rejected'] = 'Rejected';
$string['decision:edited'] = 'Edited and approved';

// Activity drill-down.
$string['activity:inthisweek'] = 'Activities in this week';
$string['activity:name'] = 'Activity';
$string['activity:kind'] = 'Type';
$string['activity:size'] = 'Content';
$string['activity:chars'] = '{$a} characters';
$string['activity:claims'] = 'Claims checked';
$string['activity:verify'] = 'Verify this activity';
$string['activity:open'] = 'Open in course';
$string['activity:backtoweek'] = 'Back to the week';
$string['activity:content'] = 'Content';
$string['activity:nocontent'] = 'This activity has no prose long enough to check.';
$string['activity:none'] = 'This week has no activities whose content can be checked.';

$string['kind:reading'] = 'Reading';
$string['kind:video'] = 'Video';
$string['kind:assignment'] = 'Assignment';
$string['kind:discussion'] = 'Discussion';
$string['kind:quiz'] = 'Quiz';

// Diff review.
$string['review:heading'] = 'Review findings';
$string['review:none'] = 'Nothing is awaiting review for this week.';
$string['review:claim'] = 'Claim:';
$string['review:current'] = 'Current content';
$string['review:suggested'] = 'Suggested correction';
$string['review:reasoning'] = 'Reasoning:';
$string['review:confidence'] = 'Confidence';
$string['review:sources'] = 'Sources checked';
$string['review:verbatim'] = 'Quote verified in source';
$string['review:notverbatim'] = 'Quote not found in any source';
$string['review:approve'] = 'Approve';
$string['review:reject'] = 'Reject';
$string['review:edit'] = 'Edit and approve';
$string['review:saveapprove'] = 'Save and approve';
$string['review:cancel'] = 'Cancel';
$string['review:notes'] = 'Reviewer note (optional)';
$string['review:showall'] = 'Show every claim, not just flagged ones';
$string['review:showflagged'] = 'Show only flagged claims';
$string['review:confirmapprove'] = 'This will change the live content students see. Continue?';
$string['review:notapplicable'] = 'The original sentence could not be located word for word in the activity, so this correction cannot be applied automatically. Review the activity and make the change by hand if you agree with it.';

// Applying a correction.
$string['apply:done'] = 'Decision recorded.';
$string['apply:notext'] = 'There is no replacement text to apply.';
$string['apply:nooriginal'] = 'This finding has no located original sentence, so nothing can be replaced.';
$string['apply:itemgone'] = 'The activity this finding came from has changed or been deleted.';
$string['apply:notlocated'] = 'The original sentence could not be located unambiguously in the activity, so nothing was changed.';
$string['apply:nochange'] = 'Applying this would not change the content.';

// Audit trail.
$string['audit:heading'] = 'Decision history';
$string['audit:none'] = 'Nothing has been decided in this course yet.';
$string['audit:when'] = 'When';
$string['audit:who'] = 'Who';
$string['audit:what'] = 'What';
$string['audit:before'] = 'Before';
$string['audit:after'] = 'After';

// Background jobs.
$string['jobs:recent'] = 'Recent background jobs';
$string['jobs:scope'] = 'Scope';
$string['jobs:status'] = 'Status';
$string['jobs:progress'] = 'Progress';
$string['jobs:flagged'] = 'Flagged';
$string['jobs:queued'] = 'Queued';
$string['jobs:scope:course'] = 'One course';
$string['jobs:scope:multicourse'] = '{$a} courses';

$string['notify:jobcomplete:subject'] = 'Content verification finished';
$string['notify:jobcomplete:body'] = 'Your background content verification has finished. It ran {$a->checks} week check(s) across {$a->courses} course(s) and flagged {$a->flagged} item(s) for review. Review them at: {$a->url}';
$string['notify:jobcomplete:bodyhtml'] = '<p>Your background content verification has finished.</p><p>It ran {$a->checks} week check(s) across {$a->courses} course(s) and flagged <strong>{$a->flagged}</strong> item(s) for review.</p><p><a href="{$a->url}">Review the findings</a></p>';
$string['notify:jobcomplete:small'] = 'Verification finished: {$a->flagged} item(s) flagged.';

// Enrichment.
$string['enrich:heading'] = 'Insert enrichment';
$string['enrich:intro'] = 'Insert a registered 3D model or diagram, or build a comparison table, and append it to an activity. The activity keeps its existing format and stays editable as normal.';
$string['enrich:activity'] = 'Insert into';
$string['enrich:target'] = 'Week {$a->section}: {$a->name}';
$string['enrich:notargets'] = 'This course has no activities with content this tool can edit.';
$string['enrich:kind'] = 'What to insert';
$string['enrich:kind:asset'] = 'A registered 3D model or diagram';
$string['enrich:kind:table'] = 'A comparison table';
$string['enrich:asset'] = 'Asset';
$string['enrich:position'] = 'Place it';
$string['enrich:position_help'] = 'Where the element goes in the activity\'s content.

"At the end" appends it after everything. "At the start" puts it first. Choosing a heading places it at the end of that section, immediately before the next heading -- which is usually where an illustration belongs.';
$string['enrich:pos:end'] = 'At the end of the content';
$string['enrich:pos:start'] = 'At the start of the content';
$string['enrich:pos:after'] = 'At the end of the section "{$a}"';
$string['asset:pendingurl'] = 'Awaiting URL';
$string['asset:needsurl'] = 'No URL yet, so this cannot be inserted. Add the model URL to make it available.';
$string['asset:preview'] = 'Show preview';
$string['enrich:noassets'] = 'No assets are registered yet. An administrator can add them under Enrichment assets.';
$string['enrich:tablecaption'] = 'Table caption';
$string['enrich:tablecsv'] = 'Table content';
$string['enrich:tablecsv_help'] = 'One row per line, cells separated by commas. The first line is the header row and the first column labels each row, which is what makes the table readable in a screen reader.

For example:

    Feature,Drug A,Drug B
    Onset,Rapid,Slow
    Route,Oral,Intravenous';
$string['enrich:tabletooshort'] = 'A comparison table needs a header row and at least one data row.';
$string['enrich:tableragged'] = 'Every row must have the same number of cells as the header row.';
$string['enrich:insert'] = 'Insert';
$string['enrich:inserted'] = 'Inserted into the activity.';
$string['enrich:insertfailed'] = 'Nothing could be inserted into that activity.';
$string['enrich:preview'] = 'Preview';

// Image search and insert.
$string['enrich:kind:image'] = 'An image';
$string['enrich:imagesearch'] = 'Find an image';
$string['enrich:imageusepicker'] = 'Search below and click an image to insert it. This button is only for assets and tables.';
$string['image:source'] = 'Image source';
$string['image:source:openverse'] = 'Openverse (openly licensed)';
$string['image:source:repository'] = 'Files already on this site';
$string['image:query'] = 'Search terms';
$string['image:queryplaceholder'] = 'e.g. cardiac anatomy';
$string['image:search'] = 'Search';
$string['image:searching'] = 'Searching...';
$string['image:noresults'] = 'No images matched that search.';
$string['image:resultcount'] = '{$a} image(s) found. Click one to insert it.';
$string['image:notarget'] = 'Choose an activity to insert into first.';
$string['image:insertthis'] = 'Insert';
$string['image:insert'] = 'Insert image';
$string['image:inserting'] = 'Inserting...';
$string['image:inserted'] = 'Image inserted, with its attribution stored alongside it.';
$string['image:confirminsert'] = 'This image will be copied into the activity and published with the attribution below. Continue?';
$string['image:viewsource'] = 'View original';
$string['image:licence'] = 'Licence';
$string['image:by'] = 'by {$a}';
$string['image:licensedunder'] = 'licensed under {$a}';
$string['image:defaultalt'] = 'Course illustration';
$string['image:attributionnote'] = 'The image is copied into this activity, not linked to, so it survives the source going away and travels with a course backup. Its creator and licence are stored on the file itself and shown beneath it.';

// Assets.
$string['asset:intro'] = 'Register any compatible open-source 3D model or diagram here and it becomes insertable by editors. Nothing about the set of assets is fixed in code.';
$string['asset:add'] = 'Register an asset';
$string['asset:none'] = 'No assets are registered yet.';
$string['asset:name'] = 'Name';
$string['asset:assettype'] = 'Kind';
$string['asset:type:model3d'] = '3D model';
$string['asset:type:diagram'] = 'Diagram';
$string['asset:type:embed'] = 'Embed';
$string['asset:viewer'] = 'Viewer';
$string['asset:viewer_help'] = 'How the asset is rendered in content:

* **3D model viewer** - a glTF or GLB file shown in an interactive `<model-viewer>` element.
* **Image** - a static image such as a rendered diagram.
* **Mermaid diagram** - diagram source rendered in the browser, falling back to readable text.
* **Embedded frame** - a sandboxed iframe for an external interactive viewer.';
$string['asset:viewer:modelviewer'] = '3D model viewer';
$string['asset:viewer:image'] = 'Image';
$string['asset:viewer:mermaid'] = 'Mermaid diagram';
$string['asset:viewer:iframe'] = 'Embedded frame';
$string['asset:url'] = 'Asset URL';
$string['asset:posterurl'] = 'Poster image URL';
$string['asset:posterurl_help'] = 'Shown before the 3D model loads, and as the fallback in a browser that does not support the model viewer. Worth setting for every 3D asset.';
$string['asset:body'] = 'Diagram source';
$string['asset:description'] = 'Description';
$string['asset:description_help'] = 'Used as the alternative text for the asset, so write what the asset shows rather than what it is called.';
$string['asset:licence'] = 'Licence';
$string['asset:attribution'] = 'Attribution';
$string['asset:sortorder'] = 'Sort order';
$string['asset:enabled'] = 'Available to editors';
$string['asset:disabled'] = 'Disabled';
$string['asset:saved'] = 'Asset saved.';
$string['asset:deleted'] = 'Asset deleted.';

// Publish templates.
$string['publish:heading'] = 'Publish layout';
$string['publish:intro'] = 'Choose how this week is arranged when published. The choice is stored and can be changed at any time.';
$string['publish:layout'] = 'Layout';
$string['publish:preview'] = 'Live preview';
$string['publish:save'] = 'Save layout';
$string['publish:saved'] = 'Layout saved.';
$string['publish:slot:media'] = 'Media (image, video or 3D model)';
$string['publish:slot:body'] = 'Main text';
$string['publish:slot:intro'] = 'Introduction';
$string['publish:slot:sidebar'] = 'Sidebar summary';
$string['publish:slot:callouts'] = 'Callouts';
$string['publish:slot:tabs'] = 'Tabs';
$string['publish:entrytitle'] = 'Title';
$string['publish:entrybody'] = 'Content';

$string['layout:medialeft'] = 'Media left, text right';
$string['layout:stackedcallout'] = 'Stacked text with callouts';
$string['layout:tabbedcomparison'] = 'Tabbed comparison';
$string['layout:fullwidthvisual'] = 'Full-width visual with sidebar summary';

// Read-aloud.
$string['readaloud'] = 'Read aloud';
$string['readaloud:play'] = 'Read aloud';
$string['readaloud:pause'] = 'Pause';
$string['readaloud:resume'] = 'Resume';
$string['readaloud:stop'] = 'Stop';
$string['readaloud:voice'] = 'Voice';
$string['readaloud:voicestandard'] = 'Standard voice';
$string['readaloud:voicehigh'] = 'High-quality voice';
$string['readaloud:hint'] = 'Click any sentence to start reading from there.';

// Follow-up questions.
$string['questions:manage'] = 'Follow-up questions';
$string['questions:intro'] = 'Auto-generated self-check questions. Nothing here is visible to learners until you approve it, and none of it touches the gradebook.';
$string['questions:activity'] = 'Activity';
$string['questions:allactivities'] = 'All activities';
$string['questions:filter'] = 'Show';
$string['questions:generate'] = 'Generate questions';
$string['questions:regenerate'] = 'Regenerate drafts';
$string['questions:generating'] = 'Generating...';
$string['questions:generated'] = 'Created {$a} draft question(s).';
$string['questions:none'] = 'No questions have been generated yet.';
$string['questions:text'] = 'Question text';
$string['questions:explanation'] = 'Feedback shown after answering';
$string['questions:correctanswer'] = 'Correct';
$string['questions:save'] = 'Save';
$string['questions:approve'] = 'Approve';
$string['questions:reject'] = 'Reject';
$string['questions:delete'] = 'Delete';
$string['questions:confirmdelete'] = 'Delete this question permanently?';
$string['questions:heading'] = 'Check your understanding';
$string['questions:ungraded'] = 'Not graded';
$string['questions:check'] = 'Check my answer';
$string['questions:correct'] = 'Correct.';
$string['questions:incorrect'] = 'Not quite.';
$string['questions:choose'] = 'Choose an answer first.';
$string['questions:expected'] = 'Expected answer:';

$string['qtype:multichoice'] = 'Multiple choice';
$string['qtype:checkbox'] = 'Select all that apply';
$string['qtype:truefalse'] = 'True or false';
$string['qtype:shorttext'] = 'Short answer';

$string['qstatus:draft'] = 'Draft';
$string['qstatus:approved'] = 'Approved';
$string['qstatus:rejected'] = 'Rejected';

// Reference sources.
$string['source:why'] = 'The verifier only ever cites passages ingested from this list, which is what stops it inventing a reference. Live keyword search against third-party APIs is deliberately not a retrieval channel.';
$string['source:add'] = 'Add a source';
$string['source:none'] = 'No reference sources have been added. Until at least one is ingested, every claim will be reported as having no source.';
$string['source:title'] = 'Title';
$string['source:type'] = 'Source type';
$string['source:type:wikipedia'] = 'Wikipedia article';
$string['source:type:url'] = 'Web page';
$string['source:type:youtube'] = 'Video page';
$string['source:type:upload'] = 'Uploaded document';
$string['source:ref'] = 'Reference';
$string['source:ref_help'] = 'For a Wikipedia article, the article title, for example `Anatomical_plane`. For anything else, the full URL.';
$string['source:files'] = 'Files';
$string['source:tier'] = 'Tier';
$string['source:tier_help'] = 'How much weight this source can carry:

* **Tier 1** - licensed textbook.
* **Tier 2** - guideline body or professional college.
* **Tier 3** - open reference such as an encyclopaedia article.

A corpus containing only tier 3 sources can show that the pipeline behaves, but it cannot settle a clinical question, and the interface warns while that is the case.';
$string['source:tier1'] = 'Tier 1 - licensed textbook';
$string['source:tier2'] = 'Tier 2 - guideline body';
$string['source:tier3'] = 'Tier 3 - open reference';
$string['source:enabled'] = 'Enabled';
$string['source:chunks'] = 'Passages';
$string['source:fetched'] = 'Last ingested';
$string['source:ingest'] = 'Ingest';
$string['source:ingested'] = 'Ingested {$a} passage(s).';
$string['source:saved'] = 'Source saved.';
$string['source:deleted'] = 'Source deleted.';

// Pronunciation dictionary.
$string['pronounce:intro'] = 'Tune how the high-quality voice says medical vocabulary. Entries are applied before synthesis, so a term is pronounced the same way wherever it appears.';
$string['pronounce:nottsconfigured'] = 'No high-quality voice endpoint is configured, so these entries are not being used yet. Learners currently hear the browser voice only.';
$string['pronounce:add'] = 'Add an entry';
$string['pronounce:none'] = 'The dictionary is empty.';
$string['pronounce:term'] = 'Term';
$string['pronounce:term_help'] = 'The word or phrase as it is written in the course content.';
$string['pronounce:replacement'] = 'Say it as';
$string['pronounce:replacement_help'] = 'A respelling the voice pronounces correctly, for example `angina` said as `an-JY-nuh`.';
$string['pronounce:matchtype'] = 'Match';
$string['pronounce:matchtype:word'] = 'Whole word';
$string['pronounce:matchtype:substring'] = 'Anywhere in a word';
$string['pronounce:notes'] = 'Notes';
$string['pronounce:enabled'] = 'Enabled';
$string['pronounce:duplicate'] = 'There is already an entry for that term.';
$string['pronounce:saved'] = 'Entry saved.';
$string['pronounce:deleted'] = 'Entry deleted.';

// Warnings.
$string['warning:notconfigured'] = 'No AI endpoint is configured, so no check can run. Set one under Site administration.';
$string['warning:emptycorpus'] = 'No reference material has been ingested yet, so every claim will be reported as having no source. Add and ingest at least one reference source.';
$string['warning:tier3only'] = 'The reference corpus currently contains only tier 3 open references. That is enough to show the checker behaves, but it cannot settle a clinical question. Treat every finding as a suggestion.';

// Tasks.
$string['task:verifycourse'] = 'Run a content verification check';
$string['task:refreshcorpus'] = 'Refresh the reference corpus';
$string['task:reapqueue'] = 'Close out abandoned content checks';

// Events.
$string['event:suggestiondecided'] = 'AI suggestion decided';
$string['event:checkcompleted'] = 'Content check completed';

// Errors.
$string['error:notconfigured'] = 'The content checker has no AI endpoint configured.';
$string['error:aicall'] = 'The AI backend could not be reached: {$a}';
$string['error:aitruncated'] = 'The AI backend hit its output limit of {$a} tokens and returned an incomplete response. Raise the extraction token ceiling in the plugin settings.';
$string['error:extractionpartial'] = 'Finished, but {$a->count} passage(s) could not be read and were NOT checked: {$a->reason}';
$string['error:aijson'] = 'The AI backend returned something that was not valid JSON: {$a}';
$string['error:aihttp'] = 'The AI backend returned HTTP status {$a}.';
$string['error:aiembedding'] = 'The AI backend returned no embedding.';
$string['error:thirdpartydisabled'] = 'The external AI fallback is switched off.';
$string['error:gpubusy'] = 'The AI server is busy with other checks right now. Try again in a moment.';
$string['error:cmnotincourse'] = 'That activity is not in this course.';
$string['error:unknowntemplate'] = 'That is not a layout this plugin provides.';
$string['error:unknownop'] = 'Unknown operation.';
$string['error:nothingtocheck'] = 'There is nothing to check.';
$string['error:abandoned'] = 'The check stopped without finishing and was closed out automatically.';
$string['error:sourcefetch'] = 'The source could not be fetched.';
$string['error:questionsdisabled'] = 'Follow-up questions are switched off for this site.';
$string['error:readalouddisabled'] = 'Read aloud is switched off for this site.';
$string['error:nopermissiontoread'] = 'You cannot read that activity.';
$string['error:ttsnotconfigured'] = 'No high-quality voice is configured.';
$string['error:ttsempty'] = 'There is nothing to read.';
$string['error:ttsfailed'] = 'The voice server could not render that passage: {$a}';
$string['error:imagesearch'] = 'The image search could not be completed: {$a}';
$string['error:imagefetch'] = 'That image could not be retrieved.';
$string['error:imagetoolarge'] = 'That image is larger than the configured maximum.';
$string['error:imagenotimage'] = 'That file is not a usable image.';
$string['error:imagenopermission'] = 'You do not have access to that file.';
$string['error:imagesourceunknown'] = 'Unknown or disabled image source: {$a}';
$string['error:imagetargetunsupported'] = 'Images cannot be inserted into that kind of content.';

// Settings.
$string['setting:serverheading'] = 'Self-hosted AI backend';
$string['setting:serverheading_desc'] = 'The AWS GPU server the plugin sends content to. Course content is never sent anywhere else unless the external fallback below is explicitly switched on.';
$string['setting:endpoint'] = 'Endpoint';
$string['setting:endpoint_desc'] = 'Base URL of the GPU server, with no trailing slash.';
$string['setting:token'] = 'Bearer token';
$string['setting:token_desc'] = 'Sent as an Authorization header. Stored server-side and never exposed to the browser.';
$string['setting:useragent'] = 'User agent';
$string['setting:useragent_desc'] = 'Sent with every request. A browser user agent is needed when the endpoint sits behind a proxy that challenges bot traffic.';

$string['setting:modelheading'] = 'Models';
$string['setting:modelheading_desc'] = 'Which model does which job. Different stages have very different cost and accuracy needs, so they are configured separately.';
$string['setting:model_atomise'] = 'Claim extraction model';
$string['setting:model_atomise_desc'] = 'Splits course prose into individually checkable claims. A smaller, faster model is appropriate here.';
$string['setting:model_adjudicate'] = 'Adjudication model';
$string['setting:model_adjudicate_desc'] = 'Judges each claim against the retrieved sources. This is the accuracy-critical stage and deserves the largest model available.';
$string['setting:model_questions'] = 'Question generation model';
$string['setting:model_questions_desc'] = 'Writes the draft follow-up questions.';
$string['setting:model_embed'] = 'Embedding model';
$string['setting:model_embed_desc'] = 'Produces the vectors used for retrieval and for collapsing duplicate claims.';

$string['setting:tuningheading'] = 'Request tuning';
$string['setting:tuningheading_desc'] = 'These defaults were established by measuring a live gateway. Each one guards a specific failure, so change them only alongside re-measuring.';
$string['setting:numctx'] = 'Context window';
$string['setting:numctx_desc'] = 'Tokens of context per request. Leaving this unset lets the server allocate its own very large default, which can force models to evict each other from memory.';
$string['setting:numpredict'] = 'Maximum generated tokens';
$string['setting:numpredict_desc'] = 'A hard ceiling on output length. A JSON schema constrains the shape of a response but not its length, and an unbounded free-text field can run past the ceiling and return truncated, unparseable JSON.';
$string['setting:numpredict_atomise'] = 'Maximum generated tokens (extraction)';
$string['setting:numpredict_atomise_desc'] = 'A separate, larger ceiling for claim extraction and question generation. Those emit one object per assertion, each repeating a source sentence, so their output grows with the passage; a verdict is always short. Sharing the smaller ceiling truncated extraction mid-string, which surfaced only as unreadable JSON.';
$string['setting:timeout'] = 'Request timeout (seconds)';
$string['setting:timeout_desc'] = 'A request can sit behind a busy model returning nothing. Failing fast and retrying gets past the queue; a long timeout just blocks the run.';
$string['setting:retries'] = 'Retries';
$string['setting:retries_desc'] = 'Attempts after the first, with a growing pause between them.';
$string['setting:keepalive'] = 'Keep model loaded for';
$string['setting:keepalive_desc'] = 'Holds the model in GPU memory between calls so one run does not pay a cold load per claim.';
$string['setting:maxconcurrent'] = 'Concurrent AI requests';
$string['setting:maxconcurrent_desc'] = 'How many checks may talk to the GPU at once. There is one server and it serialises internally, so a high number does not produce parallelism, it produces queueing inside requests that are already waiting.';

$string['setting:retrievalheading'] = 'Reference material';
$string['setting:retrievalheading_desc'] = 'Where the evidence a claim is judged against comes from.';
$string['setting:fetcher'] = 'Retrieval strategy';
$string['setting:fetcher_desc'] = 'Whether the plugin assembles reference material itself or the model retrieves its own.';
$string['fetcher:corpus'] = 'The plugin retrieves from its own ingested corpus';
$string['fetcher:modelrag'] = 'The model retrieves its own sources';
$string['setting:topk'] = 'Passages per claim';
$string['setting:topk_desc'] = 'How many retrieved passages the adjudicator sees.';
$string['setting:minscore'] = 'Similarity floor';
$string['setting:minscore_desc'] = 'Passages scoring below this are discarded. A claim left with nothing is reported as having no source rather than being judged on weak evidence.';
$string['setting:dedupe'] = 'Duplicate claim threshold';
$string['setting:dedupe_desc'] = 'Claims more similar than this are treated as the same claim. The same assertion normally recurs across a week\'s overview, lecture and reading.';

$string['setting:enrichheading'] = 'Enrichment viewers';
$string['setting:enrichheading_desc'] = 'Optional local JavaScript libraries used to upgrade embedded assets. Content still renders without them.';
$string['setting:modelviewer_script'] = '3D model viewer script';
$string['setting:modelviewer_script_desc'] = 'Local URL of the model-viewer web component. Leave empty to show poster images and download links instead. Use a locally hosted copy rather than a CDN, so no asset request leaves the environment.';
$string['setting:mermaid_script'] = 'Diagram renderer script';
$string['setting:mermaid_script_desc'] = 'Local URL of the Mermaid library. Leave empty to show diagram source as readable text.';

$string['setting:imageheading'] = 'Image sources';
$string['setting:imageheading_desc'] = 'Where editors may search for images to insert. Sources are pluggable: enabling or disabling one here changes what the picker offers, with no code change.';
$string['setting:image_openverse_enabled'] = 'Enable Openverse';
$string['setting:image_openverse_enabled_desc'] = 'Openverse indexes openly licensed and public-domain images and needs no account, so every result is safe to reuse in teaching material with attribution.';
$string['setting:image_openverse_endpoint'] = 'Openverse API URL';
$string['setting:image_openverse_endpoint_desc'] = 'Base URL of the Openverse API.';
$string['setting:image_openverse_key'] = 'Openverse client key';
$string['setting:image_openverse_key_desc'] = 'Optional. Openverse answers anonymously; a key only raises the rate limit. Stored server-side and never exposed to the browser.';
$string['setting:image_openverse_commercial'] = 'Restrict to commercial-use and modifiable licences';
$string['setting:image_openverse_commercial_desc'] = 'Filters out licences that forbid commercial use or derivative works. Worth enabling if course material may be sold or relicensed.';
$string['setting:image_repository_enabled'] = 'Enable search of this site\'s files';
$string['setting:image_repository_enabled_desc'] = 'Searches images already uploaded to this Moodle through the repository subsystem, which enforces the same access rules as the file picker.';
$string['setting:image_maxsize'] = 'Maximum image size (MB)';
$string['setting:image_maxsize_desc'] = 'Images larger than this are refused rather than copied into course content.';

$string['setting:ttsheading'] = 'Read aloud';
$string['setting:ttsheading_desc'] = 'The default engine is the browser\'s own speech synthesis, which needs no configuration. The settings below add an optional higher-quality voice.';
$string['setting:readaloud_enabled'] = 'Enable read aloud';
$string['setting:readaloud_enabled_desc'] = 'Show the read-aloud control on learner-facing content pages.';
$string['setting:tts_endpoint'] = 'Voice endpoint';
$string['setting:tts_endpoint_desc'] = 'URL of the self-hosted text-to-speech server. While this is empty the high-quality option is hidden from learners entirely rather than offered and broken.';
$string['setting:tts_token'] = 'Voice bearer token';
$string['setting:tts_token_desc'] = 'Sent as an Authorization header to the voice server.';
$string['setting:tts_voice'] = 'Voice name';
$string['setting:tts_voice_desc'] = 'Passed to the voice server to select a voice.';
$string['setting:tts_maxchars'] = 'Maximum characters per request';
$string['setting:tts_maxchars_desc'] = 'Caps how much text one synthesis request may carry, so the endpoint cannot be driven as a general-purpose speech service.';

$string['setting:questionsheading'] = 'Follow-up questions';
$string['setting:questionsheading_desc'] = 'Formative self-checks embedded after each content block. These never create grade items and never affect course completion.';
$string['setting:questions_enabled'] = 'Enable follow-up questions';
$string['setting:questions_enabled_desc'] = 'Show approved questions to learners and allow editors to generate them.';
$string['setting:questions_per_block'] = 'Questions per content block';
$string['setting:questions_per_block_desc'] = 'How many questions to ask the model for per block.';
$string['setting:questions_shorttext'] = 'Allow short free-text questions';
$string['setting:questions_shorttext_desc'] = 'Free-text answers are shown alongside the expected answer rather than marked, because a string comparison on a clinical answer is wrong more often than it is right. Off by default.';

$string['setting:fallbackheading'] = 'External AI fallback';
$string['setting:fallbackheading_desc'] = 'Off by default and best left that way. Switching this on sends unpublished medical course content to a third party.';
$string['setting:allow_thirdparty'] = 'Allow an external AI API';
$string['setting:allow_thirdparty_desc'] = 'When ticked, the plugin may fall back to the external API below if the self-hosted server is unreachable. The self-hosted server is always tried first.';
$string['setting:thirdparty_endpoint'] = 'External API base URL';
$string['setting:thirdparty_endpoint_desc'] = 'An OpenAI-compatible base URL, for example one ending in /v1.';
$string['setting:thirdparty_key'] = 'External API key';
$string['setting:thirdparty_key_desc'] = 'Stored server-side and never exposed to the browser.';
$string['setting:thirdparty_model'] = 'External model';
$string['setting:thirdparty_model_desc'] = 'Model name to use with the external API.';

// Privacy.
$string['privacy:metadata:local_cchecker_suggestions'] = 'Decisions recorded on AI content suggestions.';
$string['privacy:metadata:local_cchecker_suggestions:decidedby'] = 'The user who decided on the suggestion.';
$string['privacy:metadata:local_cchecker_suggestions:decidedat'] = 'When the decision was recorded.';
$string['privacy:metadata:local_cchecker_suggestions:notes'] = 'A note the reviewer left with their decision.';
$string['privacy:metadata:local_cchecker_checks'] = 'Verification checks that were run.';
$string['privacy:metadata:local_cchecker_checks:usermodified'] = 'The user who started the check.';
$string['privacy:metadata:local_cchecker_checks:timequeued'] = 'When the check was started.';
$string['privacy:metadata:local_cchecker_audit'] = 'The audit trail of decisions on AI output.';
$string['privacy:metadata:local_cchecker_audit:userid'] = 'The user who performed the action.';
$string['privacy:metadata:local_cchecker_audit:action'] = 'What the user did.';
$string['privacy:metadata:local_cchecker_audit:timecreated'] = 'When the action happened.';
$string['privacy:metadata:local_cchecker_jobs'] = 'Background verification jobs.';
$string['privacy:metadata:local_cchecker_jobs:usermodified'] = 'The user who queued the job and is notified when it finishes.';
$string['privacy:metadata:local_cchecker_jobs:timequeued'] = 'When the job was queued.';
$string['privacy:metadata:gpu'] = 'Course content is sent to the self-hosted AI server for verification and question generation. No personal data about learners is included.';
$string['privacy:metadata:gpu:content'] = 'The course content being checked or read aloud.';

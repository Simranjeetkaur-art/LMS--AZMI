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
 * English strings for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcard'] = 'Add a card';
$string['addcardoftype'] = 'Add card: {$a}';
$string['addmoreitems'] = 'Add 2 more steps';
$string['addmorepairs'] = 'Add 2 more pairs';
$string['addmoreparts'] = 'Add 2 more word parts';
$string['addmorerows'] = 'Add 2 more rows';
$string['alsoaccepted'] = 'also accepted: {$a}';
$string['answershown'] = 'Answer shown';
$string['blanknumber'] = 'Blank {$a}';
$string['browsecards'] = 'Browse all cards';
$string['cardback'] = 'Back (answer)';
$string['carddeleted'] = 'Card deleted.';
$string['cardfront'] = 'Front (prompt)';
$string['cardfront_help'] = 'What the learner sees first. Keep it to a single prompt — the learner should attempt the answer from memory before revealing the back.';
$string['cardprogress'] = 'Card position within the deck';
$string['cardsaved'] = 'Card saved.';
$string['cardsummary'] = 'Front';
$string['cardtags'] = 'Tags';
$string['cardtags_help'] = 'Optional comma-separated topic tags (for example "cardiology, prefixes"). Tags make cards filterable and reusable across decks.';
$string['cardtype'] = 'Card type';
$string['cardtypebasic'] = 'Basic (two-sided)';
$string['cardtypecloze'] = 'Cloze / type the answer';
$string['cardtypecomparecontrast'] = 'Compare / contrast';
$string['cardtypeimagelabel'] = 'Image / label';
$string['cardtypematching'] = 'Matching';
$string['cardtypeordering'] = 'Ordering / sequence';
$string['cardtypeqanda'] = 'Q&A (open recall)';
$string['cardtypetermdissection'] = 'Term dissection';
$string['casesensitive'] = 'Case-sensitive checking';
$string['checkanswers'] = 'Check';
$string['clozetext'] = 'Text with blanks';
$string['clozetext_help'] = 'Write the sentence or passage and mark each blank with double square brackets: `The powerhouse of the cell is the [[mitochondrion]].` Alternative accepted answers are separated with a pipe: `[[mitochondrion|mitochondria]]`. Checking ignores case and surrounding spaces unless case-sensitive checking is enabled.';
$string['comparea'] = 'First column entry {$a}';
$string['compareaspect'] = 'Aspect {$a}';
$string['compareb'] = 'Second column entry {$a}';
$string['comparecolumna'] = 'First column title';
$string['comparecolumnb'] = 'Second column title';
$string['comparefronthint'] = 'Recall each cell, then reveal the full comparison.';
$string['compareprompt'] = 'Prompt';
$string['correctorder'] = 'Correct order:';
$string['deckname'] = 'Deck name';
$string['definition'] = 'Definition';
$string['deletecardconfirm'] = 'Delete this card? Learners\' review history for it will also be deleted.';
$string['dissectprompt'] = 'Recall the word parts and their meanings, then reveal the answer.';
$string['editcard'] = 'Edit card';
$string['erralttextrequired'] = 'Describe the image for screen-reader users.';
$string['errbadpart'] = 'Each word part needs text and a valid role (prefix, root, suffix or link).';
$string['errclozeblank'] = 'Add at least one blank using [[answer]].';
$string['errcomparefields'] = 'A prompt and both column titles are required.';
$string['errcontentface'] = 'Card content is missing the "{$a}" face.';
$string['errdefinitionrequired'] = 'A definition is required.';
$string['errfacerequired'] = 'Both faces of the card need content.';
$string['errimagerequired'] = 'Choose an image.';
$string['errinvalidgrade'] = 'Invalid grade.';
$string['errlabelrequired'] = 'A label is required.';
$string['errmaxparts'] = 'A term can have at most {$a} word parts.';
$string['errmeaningrequired'] = 'Give this word part a meaning (only the combining-vowel link may leave it empty).';
$string['errminitems'] = 'Add at least two steps.';
$string['errminpairs'] = 'Add at least two complete pairs.';
$string['errminparts'] = 'Break the term into at least two word parts.';
$string['errminrows'] = 'Add at least one complete row.';
$string['errnewperdayrange'] = 'Enter a number between 1 and 500.';
$string['errregionrange'] = 'Region centre must be 0–100% and the radius 1–50%.';
$string['errsampledeck'] = 'The sample deck could not be loaded: {$a}';
$string['errtermrequired'] = 'A term is required.';
$string['errunknowncardtype'] = 'Unknown card type "{$a}".';
$string['eventcardreviewed'] = 'Card reviewed';
$string['flashdeck:addinstance'] = 'Add a new flashcard deck';
$string['flashdeck:managecards'] = 'Author, edit and delete cards';
$string['flashdeck:study'] = 'Study a deck (records personal review progress)';
$string['flashdeck:view'] = 'View a flashcard deck';
$string['flashdeck:viewreports'] = 'View study reports for all learners';
$string['flip'] = 'Flip card';
$string['gradeagain'] = 'Again';
$string['gradeeasy'] = 'Easy';
$string['gradegood'] = 'Good';
$string['gradehard'] = 'Hard';
$string['gradeprompt'] = 'How well did you recall it?';
$string['guidance'] = 'Marking guidance (optional)';
$string['guidance_help'] = 'Shown with the model answer to support an honest self-grade, e.g. "A strong answer mentions the spacing effect and retrieval practice."';
$string['hotspothit'] = 'Correct — that is the spot.';
$string['hotspotmiss'] = 'Not quite — reveal the answer to see the region.';
$string['hotspotprompt'] = 'Click the {$a} in the image.';
$string['identifyprompt'] = 'Name the highlighted structure.';
$string['imagealttext'] = 'Image description (alt text)';
$string['imagealttext_help'] = 'A concise description of the image for screen-reader users, e.g. "Shoulder muscles, lateral view". Required — the card is not accessible without it.';
$string['imagedescription'] = 'Answer note (optional)';
$string['imagefile'] = 'Image';
$string['imagequestion'] = 'Prompt (optional, a default is used when empty)';
$string['imagevariant'] = 'Interaction';
$string['keyboardhint'] = 'Keyboard: Space or Enter flips the card, Left and Right arrows move between cards.';
$string['keyboardhintlearn'] = 'Keyboard: Space or Enter flips the card; after revealing, 1–4 grade it (1 Again, 2 Hard, 3 Good, 4 Easy).';
$string['labeltext'] = 'Label (the structure name)';
$string['legendtitle'] = 'Word-part colour key';
$string['linkmeaningdefault'] = 'combining vowel';
$string['loadsampledeck'] = 'Load sample deck (EMD-101 Week 1)';
$string['managecards'] = 'Manage cards';
$string['matchleft'] = 'Left item {$a}';
$string['matchprompt'] = 'Prompt';
$string['matchright'] = 'Right match {$a}';
$string['modulename'] = 'Flashcard deck';
$string['modulename_help'] = 'The flashcard deck activity lets teachers author interactive flashcards and lets learners study them by active recall: see a prompt, attempt the answer from memory, reveal, and self-grade. Spaced repetition schedules each card per learner so difficult cards return sooner. Multiple card types (basic, term dissection, and more) let the same engine serve very different subjects.';
$string['modulenameplural'] = 'Flashcard decks';
$string['newperday'] = 'New cards per day';
$string['newperday_help'] = 'The maximum number of unseen cards introduced to each learner per day once spaced repetition is active. Keeps daily study sessions a predictable size.';
$string['nextcard'] = 'Next';
$string['nextreviewat'] = 'Next review:';
$string['nocards'] = 'There are no cards in this deck yet.';
$string['nodecksincourse'] = 'There are no flashcard decks in this course.';
$string['noimage'] = 'No image attached.';
$string['orderitem'] = 'Step {$a}';
$string['orderprompt'] = 'Prompt';
$string['partmeaning'] = 'Meaning {no}';
$string['partrole'] = 'Role {no}';
$string['parttext'] = 'Word part {no}';
$string['pluginadministration'] = 'Flashcard deck administration';
$string['pluginname'] = 'Flashcard deck';
$string['positionfor'] = 'Position for {$a}';
$string['prevcard'] = 'Previous';
$string['previewday'] = '1 day';
$string['previewdays'] = '{$a} days';
$string['previewminutes'] = '{$a} min';
$string['privacy:metadata:flashdeck_review'] = 'Per-learner spaced-repetition state for each card studied.';
$string['privacy:metadata:flashdeck_review:cardid'] = 'The card this review state belongs to.';
$string['privacy:metadata:flashdeck_review:duedate'] = 'When the card next becomes due for review.';
$string['privacy:metadata:flashdeck_review:easefactor'] = 'The scheduling ease factor reflecting how easy the learner finds the card.';
$string['privacy:metadata:flashdeck_review:intervaldays'] = 'The current review interval in days.';
$string['privacy:metadata:flashdeck_review:lapses'] = 'How many times the learner forgot the card after learning it.';
$string['privacy:metadata:flashdeck_review:lastgrade'] = 'The last self-assessed grade (again, hard, good or easy).';
$string['privacy:metadata:flashdeck_review:lastreviewed'] = 'When the learner last reviewed the card.';
$string['privacy:metadata:flashdeck_review:repetitions'] = 'How many successful reviews the learner has made in a row.';
$string['privacy:metadata:flashdeck_review:state'] = 'The learning state of the card (new, learning, review, relearning).';
$string['privacy:metadata:flashdeck_review:userid'] = 'The learner this review state belongs to.';
$string['privacy:reviewspath'] = 'Card review progress';
$string['promptshown'] = 'Prompt shown';
$string['regioncx'] = 'Centre X (%)';
$string['regioncy'] = 'Centre Y (%)';
$string['regiongroup'] = 'Target region';
$string['regiongroup_help'] = 'The target region as an ellipse over the image, in percentages of the image size: centre X, centre Y and radius. For example X 42, Y 31, radius 8 rings a structure slightly left of and above the middle.';
$string['regionradius'] = 'Radius (%)';
$string['rolelink'] = 'Link';
$string['roleprefix'] = 'Prefix';
$string['roleroot'] = 'Root';
$string['rolesuffix'] = 'Suffix';
$string['sampledeckloaded'] = '{$a} sample cards added to the deck.';
$string['scheduler'] = 'Spaced-repetition scheduler';
$string['scheduler_help'] = 'How the next review of each card is scheduled per learner. **SM-2 (recommended)** adapts each card\'s interval from a four-button self-grade, like Anki. **Leitner** is a simpler five-box system with fixed intervals, for lower cognitive load. Scheduling becomes active in the study loop; the choice is stored per deck.';
$string['schedulerleitner'] = 'Leitner (5 boxes, simpler)';
$string['schedulersm2'] = 'SM-2 (adaptive, recommended)';
$string['selfgradehint'] = 'Compare your answer with the model answer, then grade yourself honestly.';
$string['showanswer'] = 'Show answer';
$string['statduenow'] = 'due now';
$string['statlearning'] = 'in learning';
$string['statnewleft'] = 'new left today';
$string['studydone'] = 'All caught up!';
$string['studydonebody'] = 'Nothing is due right now. Spacing reviews out is what makes them stick — come back when the next one is ready.';
$string['studymodelearn'] = 'Study (spaced repetition)';
$string['studysettings'] = 'Study settings';
$string['term'] = 'Term';
$string['term_help'] = 'The complete medical term as the learner should recall it, for example "gastroenterology". Break it into its word parts below; the parts are colour-coded by role on the answer side.';
$string['varianthotspot'] = 'Find the structure (learner clicks the image)';
$string['variantidentify'] = 'Identify the structure (marker shown)';

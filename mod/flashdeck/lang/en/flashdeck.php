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
$string['addmoreparts'] = 'Add 2 more word parts';
$string['answershown'] = 'Answer shown';
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
$string['cardtypetermdissection'] = 'Term dissection';
$string['deckname'] = 'Deck name';
$string['definition'] = 'Definition';
$string['deletecardconfirm'] = 'Delete this card? Learners\' review history for it will also be deleted.';
$string['dissectprompt'] = 'Recall the word parts and their meanings, then reveal the answer.';
$string['editcard'] = 'Edit card';
$string['errbadpart'] = 'Each word part needs text and a valid role (prefix, root, suffix or link).';
$string['errcontentface'] = 'Card content is missing the "{$a}" face.';
$string['errdefinitionrequired'] = 'A definition is required.';
$string['errfacerequired'] = 'Both faces of the card need content.';
$string['errmaxparts'] = 'A term can have at most {$a} word parts.';
$string['errmeaningrequired'] = 'Give this word part a meaning (only the combining-vowel link may leave it empty).';
$string['errminparts'] = 'Break the term into at least two word parts.';
$string['errnewperdayrange'] = 'Enter a number between 1 and 500.';
$string['errsampledeck'] = 'The sample deck could not be loaded: {$a}';
$string['errtermrequired'] = 'A term is required.';
$string['errunknowncardtype'] = 'Unknown card type "{$a}".';
$string['flashdeck:addinstance'] = 'Add a new flashcard deck';
$string['flashdeck:managecards'] = 'Author, edit and delete cards';
$string['flashdeck:study'] = 'Study a deck (records personal review progress)';
$string['flashdeck:view'] = 'View a flashcard deck';
$string['flashdeck:viewreports'] = 'View study reports for all learners';
$string['flip'] = 'Flip card';
$string['keyboardhint'] = 'Keyboard: Space or Enter flips the card, Left and Right arrows move between cards.';
$string['legendtitle'] = 'Word-part colour key';
$string['linkmeaningdefault'] = 'combining vowel';
$string['loadsampledeck'] = 'Load sample deck (EMD-101 Week 1)';
$string['managecards'] = 'Manage cards';
$string['modulename'] = 'Flashcard deck';
$string['modulename_help'] = 'The flashcard deck activity lets teachers author interactive flashcards and lets learners study them by active recall: see a prompt, attempt the answer from memory, reveal, and self-grade. Spaced repetition schedules each card per learner so difficult cards return sooner. Multiple card types (basic, term dissection, and more) let the same engine serve very different subjects.';
$string['modulenameplural'] = 'Flashcard decks';
$string['newperday'] = 'New cards per day';
$string['newperday_help'] = 'The maximum number of unseen cards introduced to each learner per day once spaced repetition is active. Keeps daily study sessions a predictable size.';
$string['nextcard'] = 'Next';
$string['nocards'] = 'There are no cards in this deck yet.';
$string['nodecksincourse'] = 'There are no flashcard decks in this course.';
$string['partmeaning'] = 'Meaning';
$string['partrole'] = 'Role';
$string['parttext'] = 'Word part';
$string['pluginadministration'] = 'Flashcard deck administration';
$string['pluginname'] = 'Flashcard deck';
$string['prevcard'] = 'Previous';
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
$string['rolelink'] = 'Link';
$string['roleprefix'] = 'Prefix';
$string['roleroot'] = 'Root';
$string['rolesuffix'] = 'Suffix';
$string['sampledeckloaded'] = '{$a} sample cards added to the deck.';
$string['scheduler'] = 'Spaced-repetition scheduler';
$string['scheduler_help'] = 'How the next review of each card is scheduled per learner. **SM-2 (recommended)** adapts each card\'s interval from a four-button self-grade, like Anki. **Leitner** is a simpler five-box system with fixed intervals, for lower cognitive load. Scheduling becomes active in the study loop; the choice is stored per deck.';
$string['schedulerleitner'] = 'Leitner (5 boxes, simpler)';
$string['schedulersm2'] = 'SM-2 (adaptive, recommended)';
$string['showanswer'] = 'Show answer';
$string['studysettings'] = 'Study settings';
$string['term'] = 'Term';
$string['term_help'] = 'The complete medical term as the learner should recall it, for example "gastroenterology". Break it into its word parts below; the parts are colour-coded by role on the answer side.';

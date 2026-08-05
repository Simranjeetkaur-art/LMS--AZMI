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

namespace local_contentchecker\reference;

/**
 * Assembles the reference material a claim is judged against.
 *
 * Whether the plugin needs to do this at all depends on the GPU model: a model
 * with its own live web/RAG access can retrieve for itself, one without cannot.
 * That is an open question at the time of writing, so the decision is a plugin
 * setting and both answers are implementations of this interface. Swapping
 * between them touches no calling code.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface fetcher {

    /**
     * Reference passages for one claim, best first.
     *
     * @param string $claim The claim being checked.
     * @param array $claimvector Embedding of the claim, where one is available.
     * @param int $topk How many passages to return.
     * @param float $minscore Similarity floor; passages below it are dropped.
     * @return array List of [float score, stdClass passage] pairs. Each passage
     *      carries title, url, tier and content.
     */
    public function retrieve(string $claim, array $claimvector, int $topk,
        float $minscore): array;

    /**
     * Can this fetcher supply anything at all right now?
     *
     * A corpus with no ingested sources cannot, and the UI needs to say so
     * rather than reporting every claim as unverifiable.
     *
     * @return bool True when it has material to work with.
     */
    public function is_ready(): bool;

    /**
     * Does the judging model need passages passed into its prompt?
     *
     * False when the model retrieves for itself, in which case the pipeline
     * asks it to cite its own sources instead of supplying them.
     *
     * @return bool True when passages must be supplied.
     */
    public function supplies_passages(): bool;
}

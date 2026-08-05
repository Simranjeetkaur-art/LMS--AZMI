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

use local_contentchecker\api\ai_backend;

/**
 * Fetcher for a GPU model with no live web access of its own.
 *
 * Passages come from the locally ingested corpus and are passed into the
 * judging prompt. This is the default, and the one that matches the deployed
 * model today.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class corpus_fetcher implements fetcher {

    /** @var corpus The embedded corpus. */
    protected $corpus;

    /**
     * Constructor.
     *
     * @param ai_backend|null $client Backend used for embeddings.
     * @param corpus|null $corpus Optional corpus override, for tests.
     */
    public function __construct(?ai_backend $client = null, ?corpus $corpus = null) {
        $this->corpus = $corpus ?? new corpus($client);
    }

    /**
     * Reference passages for one claim, best first.
     *
     * @param string $claim The claim being checked.
     * @param array $claimvector Embedding of the claim.
     * @param int $topk How many passages to return.
     * @param float $minscore Similarity floor.
     * @return array List of [score, passage] pairs.
     */
    public function retrieve(string $claim, array $claimvector, int $topk,
            float $minscore): array {
        if (!$claimvector) {
            return [];
        }
        return $this->corpus->retrieve($claimvector, $topk, $minscore);
    }

    /**
     * Has anything been ingested yet?
     *
     * @return bool True when the corpus holds at least one passage.
     */
    public function is_ready(): bool {
        return corpus::chunk_count() > 0;
    }

    /**
     * The model cannot retrieve for itself, so passages must be supplied.
     *
     * @return bool Always true.
     */
    public function supplies_passages(): bool {
        return true;
    }
}

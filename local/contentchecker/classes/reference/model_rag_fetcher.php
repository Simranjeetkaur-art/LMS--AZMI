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
 * Fetcher for a GPU model that does its own retrieval.
 *
 * Supplies nothing: the model is expected to search and to return its own
 * citations, which the pipeline then records as the source reference. Selected
 * by setting the "Reference material" option to "Model retrieves its own".
 *
 * The anti-hallucination guarantee is weaker in this mode and the pipeline says
 * so -- a quote cannot be checked verbatim against a passage the plugin never
 * saw, so quoteverbatim is never set and the UI does not claim the citation was
 * verified.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_rag_fetcher implements fetcher {

    /**
     * Nothing to retrieve locally.
     *
     * @param string $claim The claim being checked.
     * @param array $claimvector Embedding of the claim.
     * @param int $topk How many passages to return.
     * @param float $minscore Similarity floor.
     * @return array Always empty.
     */
    public function retrieve(string $claim, array $claimvector, int $topk,
            float $minscore): array {
        return [];
    }

    /**
     * Always ready: readiness is the model's problem in this mode.
     *
     * @return bool Always true.
     */
    public function is_ready(): bool {
        return true;
    }

    /**
     * The model retrieves for itself.
     *
     * @return bool Always false.
     */
    public function supplies_passages(): bool {
        return false;
    }
}

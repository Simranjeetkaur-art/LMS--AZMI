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
 * Chooses the reference-fetching strategy.
 *
 * Driven entirely by the "Reference material" admin setting, so answering the
 * open question about whether the GPU model has its own RAG access is a config
 * change rather than a code change.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetcher_factory {

    /**
     * The configured fetcher.
     *
     * @param ai_backend|null $client Backend used for embeddings.
     * @return fetcher The strategy to use.
     */
    public static function make(?ai_backend $client = null): fetcher {
        $mode = get_config('local_contentchecker', 'fetcher') ?: 'corpus';
        if ($mode === 'modelrag') {
            return new model_rag_fetcher();
        }
        return new corpus_fetcher($client);
    }
}

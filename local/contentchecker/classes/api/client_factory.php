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

namespace local_contentchecker\api;

/**
 * Chooses which AI backend the plugin talks to.
 *
 * The self-hosted GPU server is always tried first. The external fallback is
 * reached only when an admin has explicitly opted in AND the GPU is genuinely
 * unusable -- never as a convenience, and never silently as the first choice.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_factory {

    /**
     * The backend to use.
     *
     * @param bool $checkhealth Probe the GPU before falling back. Skip this on
     *      a hot path where a failed call will surface the problem anyway.
     * @return ai_backend The chosen backend.
     */
    public static function make(bool $checkhealth = false): ai_backend {
        if (gpu_client::is_configured()) {
            $client = new gpu_client();
            if (!$checkhealth || $client->healthy()) {
                return $client;
            }
        }

        if (thirdparty_client::is_available()) {
            return new thirdparty_client();
        }

        // No fallback opted into: fail rather than route content off-site.
        throw new \moodle_exception('error:notconfigured', 'local_contentchecker');
    }
}

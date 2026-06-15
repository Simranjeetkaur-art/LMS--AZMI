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

namespace local_azmsi\task;

/**
 * Adhoc task: call the Next.js on-demand revalidation webhook so the public
 * site's cached numbers refresh on a real change (01_ARCHITECTURE §5).
 *
 * No-ops safely until the webhook URL + shared secret are configured (AGENT_10),
 * so it never emits a request to an unset endpoint.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class revalidate_website extends \core\task\adhoc_task {
    /**
     * POST to the revalidation webhook if configured.
     */
    public function execute(): void {
        $url = get_config('local_azmsi', 'revalidate_url');
        $secret = get_config('local_azmsi', 'revalidate_secret');
        if (empty($url) || empty($secret)) {
            mtrace('local_azmsi: revalidate_website skipped — webhook not configured.');
            return;
        }

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('X-AZMSI-Secret: ' . $secret);
        $curl->post($url, json_encode(['source' => 'moodle', 'event' => 'course_completed']));
        $info = $curl->get_info();
        mtrace('local_azmsi: revalidate_website POST -> HTTP ' . ($info['http_code'] ?? '0'));
    }
}

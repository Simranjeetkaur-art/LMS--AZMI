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

namespace local_azmsi\hook;

use core\hook\output\before_http_headers;

/**
 * Output-related hook callbacks for local_azmsi.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {

    /**
     * Render activity-preview iframe requests chrome-free.
     *
     * The eMD course-format preview opens each activity in a modal iframe with
     * `azembed=1` appended to the URL. For those requests we switch the page to
     * Moodle's "embedded" layout, which outputs only the activity content (no
     * primary/secondary navigation, breadcrumb, drawers or footer).
     *
     * This fires inside core_renderer::header() before the layout file is
     * resolved, so changing the page layout here takes effect.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;

        if (optional_param('azembed', 0, PARAM_INT) !== 1) {
            return;
        }
        if ($PAGE->pagelayout !== 'embedded') {
            $PAGE->set_pagelayout('embedded');
        }
    }
}

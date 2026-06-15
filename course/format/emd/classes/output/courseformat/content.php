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

namespace format_emd\output\courseformat;

use core_courseformat\output\local\content as content_base;

/**
 * Course content output for the eMD format.
 *
 * Uses the core reactive content template unchanged (so section/activity
 * rendering, completion and availability are native); the eMD master-template
 * header is added separately via format_emd::course_content_header().
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    /** @var bool eMD does not offer "add section" affordances to keep the master template fixed. */
    protected $hasaddsection = true;
}

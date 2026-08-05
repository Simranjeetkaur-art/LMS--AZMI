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

namespace local_contentchecker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads plugin settings, honouring declared defaults.
 *
 * This exists because of a failure mode that has now bitten this plugin three
 * times. An `admin_setting` default is only written to the database when
 * somebody SAVES the settings page. Until then `get_config()` returns `false`,
 * which is indistinguishable from a setting an admin deliberately switched off.
 *
 * A feature gated on `if (!get_config(...))` therefore ships switched OFF on
 * every fresh install, silently, with the settings page cheerfully displaying
 * the default that is not actually in force. `readaloud_enabled` and both image
 * sources were all disabled this way.
 *
 * Every boolean feature flag goes through here so the declared default is the
 * effective default.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {

    /**
     * The declared default for each boolean flag, mirroring settings.php.
     *
     * @return array name => default.
     */
    protected static function defaults(): array {
        return [
            'readaloud_enabled' => true,
            'questions_enabled' => true,
            'questions_shorttext' => false,
            'image_openverse_enabled' => true,
            'image_repository_enabled' => true,
            'image_openverse_commercial' => false,
            // Off by default and must stay that way: enabling it sends
            // unpublished medical content to a third party.
            'allow_thirdparty' => false,
        ];
    }

    /**
     * Is a boolean feature flag on?
     *
     * @param string $name Setting name.
     * @param bool|null $default Override the declared default.
     * @return bool The effective setting.
     */
    public static function enabled(string $name, ?bool $default = null): bool {
        $value = get_config('local_contentchecker', $name);

        if ($value === false || $value === null || $value === '') {
            return $default ?? (self::defaults()[$name] ?? false);
        }

        return (bool) $value;
    }
}

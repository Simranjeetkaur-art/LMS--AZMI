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

namespace local_contentchecker\image;

/**
 * The pluggable set of image sources.
 *
 * Adding a source is adding a class to the list below; enabling or disabling
 * one is a config setting. Nothing in the picker, the web services or the
 * inserter names a specific source.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_registry {

    /**
     * Read a boolean setting, honouring its documented default when unset.
     *
     * get_config() returns false for a setting that has never been written,
     * which is indistinguishable from one deliberately switched off. Treating
     * those the same would hide a source that settings.php documents as on by
     * default, on any site where admin defaults have not been applied yet.
     *
     * @param string $name Setting name.
     * @param bool $default Value to use when the setting has never been saved.
     * @return bool The effective setting.
     */
    public static function setting_enabled(string $name, bool $default = true): bool {
        // Delegates so there is exactly one implementation of the
        // unset-means-default rule.
        return \local_contentchecker\local\settings::enabled($name, $default);
    }

    /**
     * Every known source class, in picker order.
     *
     * @return string[] Fully-qualified class names.
     */
    protected static function classes(): array {
        return [
            openverse_source::class,
            repository_source::class,
        ];
    }

    /**
     * All sources, regardless of availability.
     *
     * @return image_source[] Keyed by source id.
     */
    public static function all(): array {
        $sources = [];
        foreach (self::classes() as $class) {
            $source = new $class();
            $sources[$source->get_id()] = $source;
        }
        return $sources;
    }

    /**
     * Sources that are enabled and configured.
     *
     * @return image_source[] Keyed by source id.
     */
    public static function available(): array {
        return array_filter(self::all(), fn($source) => $source->is_available());
    }

    /**
     * One source by id, only if it is currently usable.
     *
     * Resolving through this rather than instantiating by name is what stops a
     * request naming a disabled source and getting served anyway.
     *
     * @param string $id The source id.
     * @return image_source The source.
     */
    public static function get(string $id): image_source {
        $available = self::available();
        if (!isset($available[$id])) {
            throw new \moodle_exception('error:imagesourceunknown', 'local_contentchecker', '', $id);
        }
        return $available[$id];
    }

    /**
     * Available sources as picker metadata.
     *
     * @return array List of {id, name}.
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::available() as $source) {
            $menu[] = ['id' => $source->get_id(), 'name' => $source->get_name()];
        }
        return $menu;
    }
}

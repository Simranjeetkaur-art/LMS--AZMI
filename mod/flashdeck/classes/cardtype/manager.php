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

namespace mod_flashdeck\cardtype;

/**
 * Card-type registry.
 *
 * New card types are added here (and only here): one class in this
 * namespace plus one line in the TYPES map. The study engine resolves
 * types through this manager and never hardcodes type behaviour.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string[] identifier => implementing class */
    const TYPES = [
        'basic' => basic::class,
        'termdissection' => termdissection::class,
    ];

    /** @var card_type[] instance cache */
    protected static $instances = [];

    /**
     * All registered card types.
     *
     * @return card_type[] identifier => instance
     */
    public static function get_types(): array {
        foreach (array_keys(self::TYPES) as $identifier) {
            self::get($identifier);
        }
        return self::$instances;
    }

    /**
     * Whether a type identifier is registered.
     *
     * @param string $identifier type identifier
     * @return bool
     */
    public static function exists(string $identifier): bool {
        return array_key_exists($identifier, self::TYPES);
    }

    /**
     * Resolve a card type instance.
     *
     * @param string $identifier type identifier
     * @return card_type
     * @throws \moodle_exception if the type is unknown
     */
    public static function get(string $identifier): card_type {
        if (!self::exists($identifier)) {
            throw new \moodle_exception('errunknowncardtype', 'mod_flashdeck', '', $identifier);
        }
        if (!isset(self::$instances[$identifier])) {
            $classname = self::TYPES[$identifier];
            self::$instances[$identifier] = new $classname();
        }
        return self::$instances[$identifier];
    }
}

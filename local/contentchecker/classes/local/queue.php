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

use core\lock\lock;
use core\lock\lock_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Concurrency cap on live GPU requests.
 *
 * There is one GPU box and it serialises per model. Without a cap, five editors
 * pressing "Verify" at once do not get five parallel checks; they get five
 * requests queued inside Ollama, all of them slow, each holding a PHP process
 * and a database connection until it times out.
 *
 * A small number of named slots is enough for v1. An editor who finds them all
 * busy is told to try again rather than being silently queued behind a wait
 * they cannot see.
 *
 * Slots are Moodle locks, so a PHP process that dies mid-request releases its
 * slot when its database connection closes rather than wedging the queue.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue {

    /** @var string Lock type namespace. */
    const LOCK_TYPE = 'local_contentchecker_gpu';

    /**
     * How many live requests may be in flight at once.
     *
     * @return int The configured cap, never below one.
     */
    public static function capacity(): int {
        return max(1, (int) (get_config('local_contentchecker', 'maxconcurrent') ?: 2));
    }

    /**
     * Take a slot, or return null when the GPU is already at capacity.
     *
     * @param int $timeout Seconds to wait for a slot. Zero returns immediately.
     * @return lock|null The held slot; release it when the work is done.
     */
    public static function acquire(int $timeout = 0): ?lock {
        $factory = lock_config::get_lock_factory(self::LOCK_TYPE);
        $capacity = self::capacity();

        for ($slot = 0; $slot < $capacity; $slot++) {
            $lock = $factory->get_lock('slot' . $slot, $timeout);
            if ($lock) {
                return $lock;
            }
        }
        return null;
    }

    /**
     * Run something with a slot held, releasing it whatever happens.
     *
     * @param callable $callback The work to do.
     * @param int $timeout Seconds to wait for a slot.
     * @return mixed The callback's return value.
     */
    public static function with_slot(callable $callback, int $timeout = 0) {
        $lock = self::acquire($timeout);
        if (!$lock) {
            throw new \moodle_exception('error:gpubusy', 'local_contentchecker');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}

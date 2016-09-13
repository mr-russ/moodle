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

/**
 * The library file for the static cache store.
 *
 * This file is part of the static cache store, it contains the API for interacting with an instance of the store.
 * This is used as a default cache store within the Cache API. It should never be deleted.
 *
 * @package    phpunit_cachestore_static
 * @category   cache
 * @copyright  2016 Russell Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cache/stores/static/lib.php');

/**
 * The static store class for PHPUnit testing.  It can operate as an application cache for unit testing.
 *
 * @copyright  2016 Russell Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_phpunitstatic extends cachestore_static implements cache_is_key_aware, cache_is_searchable {
    /**
     * Returns the supported modes as a combined int.
     *
     * @param array $configuration
     * @return int
     */
    public static function get_supported_modes(array $configuration = array()) {
        return self::MODE_REQUEST + self::MODE_APPLICATION;
    }

    /**
     * Returns true if the given mode is supported by this store.
     *
     * @param int $mode One of cache_store::MODE_*
     * @return bool
     */
    public static function is_supported_mode($mode) {
        return ($mode === self::MODE_APPLICATION || $mode === self::MODE_REQUEST);
    }

    /**
     * Returns true if this cache store instance is both suitable for testing, and ready for testing.
     *
     * Cache stores that support being used as the default store for unit and acceptance testing should
     * override this function and return true if there requirements have been met.
     *
     * @return bool
     */
    public static function ready_to_be_used_for_testing() {
        return true;
    }

    /**
     * Returns true if we are running in PHPUnit.
     *
     * @return bool
     */
    public static function are_requirements_met() {
        return defined('PHPUNIT') && PHPUNIT;
    }

    /**
     * Returns true if the user can add an instance of the store plugin.
     *
     * @return bool
     */
    public static function can_add_instance() {
        return defined('PHPUNIT') && PHPUNIT;
    }
}
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
 * PHPunit tests for the session cache API
 *
 * This file is part of Moodle's session cache API.
 * It contains the components that are required in order to use session caching properly.
 *
 * @package    core
 * @category   cache
 * @copyright  2016 Russell Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include the necessary evils.
global $CFG;
require_once($CFG->dirroot.'/cache/locallib.php');
require_once($CFG->dirroot.'/cache/tests/fixtures/lib.php');

/**
 * PHPunit tests for the cache API
 *
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_cache_session_testcase extends advanced_testcase {

    /**
     * Set things back to the default before each test.
     */
    public function setUp() {
        parent::setUp();
        cache_factory::reset();
        // We must save the test example to ensure we have a real filestore to work with.
        cache_config_testing::create_configuration(null, 'file', null, true);
    }

    /**
     * Final task is to reset the cache system
     */
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        cache_factory::reset();
    }

    /**
     * Create a valid user session and login as them.
     *
     * @param $user A user object to make a valid sessions for
     * @return string The session id created.
     */
    protected function make_session($user) {
        global $CFG, $DB;

        // The file handler is used by default, so let's fake the data somehow.
        $sid = md5('hokus'.microtime(true).random_string());
        session_id($sid);
        $this->assertEquals($sid, session_id());
        if (!check_dir_exists("$CFG->dataroot/sessions/")) {
            mkdir("$CFG->dataroot/sessions/", $CFG->directorypermissions, true);
        }
        touch("$CFG->dataroot/sessions/sess_$sid");

        $this->assertFalse(\core\session\manager::session_exists($sid));

        @\core\session\manager::login_user($user); // Ignore header error messages.

        // Logging in a user resets the session id.
        $sid = session_id();
        $this->assertTrue(\core\session\manager::session_exists($sid));
        return $sid;

    }

    public function test_verify_using_file_session_store() {
        global $DB;

        $this->resetAfterTest();
        $factory = cache_factory::instance();
        $config = $factory->create_config_instance();
        $definitions = $config->get_definitions();
        $validsessions = $DB->get_records('sessions', null, '', 'sid');;
        foreach ($definitions as $definitionarray) {
            // We are only interested in session caches.
            if (!($definitionarray['mode'] & cache_store::MODE_SESSION)) {
                continue;
            }
            $definition = $factory->create_definition($definitionarray['component'], $definitionarray['area']);
            $stores = $config->get_stores_for_definition($definition);

            $this->assertArrayHasKey('test_session', $stores);
            $this->assertArrayHasKey('plugin', $stores['test_session']);
            $this->assertEquals('file', $stores['test_session']['plugin']);
        }
    }

    public function test_store_and_retrieve_session_data() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Make a cache.
        $cache = cache::make('core', 'userselections');

        $this->make_session($user1);
        $cache->set('a', 'user1data');
        $this->assertEquals('user1data', $cache->get('a'));

        $this->make_session($user2);
        $this->assertFalse($cache->get('a'));
        $cache->set('a', 'user2data');
        $this->assertEquals('user2data', $cache->get('a'));

        // On session change, cache is changed to a different keyspace.
        $this->make_session($user1);
        $this->assertFalse($cache->get('a'));
    }

    public function test_no_keys_purged_when_logged_in() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $sid = $this->make_session($user);

        // Make a cache.
        $cache = cache::make('core', 'userselections');

        $cache->set('a', 'user1data');

        $this->assertTrue(\core\session\manager::session_exists($sid));
        cache_helper::clean_old_session_data(true);
        $this->expectOutputString("Cleaning up stale session data from cache stores.\n");
    }

    public function test_keys_purged_when_session_removed() {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $sid = $this->make_session($user);

        // Make a cache.
        $cache = cache::make('core', 'userselections');
        $cache->set('a', 'user1data');

        $sid2 = $this->make_session($user2);
        $cache->set('a', 'user2data');

        $this->assertTrue(\core\session\manager::session_exists($sid));
        $this->assertTrue(\core\session\manager::session_exists($sid2));
        $DB->delete_records('sessions', array('sid' => $sid2));
        $this->assertFalse(\core\session\manager::session_exists($sid2));
        $this->assertTrue(\core\session\manager::session_exists($sid));
        cache_helper::clean_old_session_data(true);
        $this->expectOutputString("Cleaning up stale session data from cache stores.\n".
                "- Removed 1 old core/userselections sessions from the 'File test' cache store.\n");
    }

    public function test_session_purged_when_remove_user_session_called() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $sid = $this->make_session($user);

        // Make a cache.
        $cache = cache::make('core', 'userselections');
        $cache->set('a', 'user1data');
        $cache->set('b', 'user1datab');
        $cache->set('c', 'user1datac');

        $sid2 = $this->make_session($user2);
        $cache->set('a', 'user2data');
        $cache->set('b', 'user2data');

        \cache_helper::remove_cache_for_session($sid);

        session_id($sid);  // Switch back to session 1.
        $this->assertFalse($cache->get('a'));
        $this->assertFalse($cache->get('b'));
        $this->assertFalse($cache->get('c'));
        session_id($sid2);  // Switch back to session 2.
        $this->assertEquals('user2data', $cache->get('a'));
        $this->assertTrue(\core\session\manager::session_exists($sid));
        $this->assertTrue(\core\session\manager::session_exists($sid2));
    }
}

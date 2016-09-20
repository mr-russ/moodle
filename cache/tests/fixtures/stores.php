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
 * Cache store test fixtures.
 *
 * @package    core
 * @category   cache
 * @copyright  2013 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * An abstract class to make writing unit tests for cache stores very easy.
 *
 * @package    core
 * @category   cache
 * @copyright  2013 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class cachestore_tests extends advanced_testcase {

    /**
     * Returns the class name for the store.
     *
     * @return string
     */
    abstract protected function get_class_name();

    /**
     * Array of instances for different cache stores and type to test against.
     * @var array
     */
    public static $datalist = null;

    public function instance_list() {
        $this->setup_store();
        $class = $this->get_class_name();
        return self::$datalist[$class];
    }

    private function setup_store() {
        $class = $this->get_class_name();

        self::$datalist[$class] = array();

        if (!class_exists($class) || !method_exists($class, 'initialise_test_instance') || !$class::are_requirements_met()) {
            return;
        }

        $modes = $class::get_supported_modes();
        if ($modes & cache_store::MODE_APPLICATION && empty(self::$datalist[$class]['Application'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_APPLICATION, $class, 'phpunit_test');
            self::$datalist[$class]['Application'] = [$class::initialise_unit_test_instance($definition)];
        }
        if ($modes & cache_store::MODE_SESSION && empty(self::$datalist['Session'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_SESSION, $class, 'phpunit_test');
            self::$datalist[$class]['Session'] = [$class::initialise_unit_test_instance($definition)];
        }
        if ($modes & cache_store::MODE_REQUEST && empty(self::$datalist['Request'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_REQUEST, $class, 'phpunit_test');
            self::$datalist[$class]['Request'] = [$class::initialise_unit_test_instance($definition)];
        }
    }
    public function data_with_types() {
        $data = array();

        $this->setup_store();
        $class = $this->get_class_name();

        // Create a nasty self referencing object to test if references are broken.
        $object = new stdClass();
        $object->test = 'element';
        $object->object = $object;

        foreach(self::$datalist[$class] as $type => $instance) {
            $instance = reset($instance);
            $thisdata =  [$type.'-int' => [$instance, 1],
                $type.'-string' => [$instance, 'teststring'],
                $type.'-string-empty' => [$instance, ''],
                $type.'-string-int' => [$instance, '3'],
                $type.'-string-bool' => [$instance, 'true'],
                $type.'-string-float' => [$instance, '2.48'],
                $type.'-string-null' => [$instance, 'null'],
                $type.'-bool' => [$instance, true],
                $type.'-float' => [$instance, 2.48],
                $type.'-null' => [$instance, null],
                $type.'-object' => [$instance, $object],
                $type.'-array' => [$instance, [1, 'teststring', 'true', '2.48', '', 'null', 'false', true, 2.48, null, false]]
            ];
            $data += $thisdata;
        }
        return $data;
    }

    public function setUp() {
        $class = $this->get_class_name();

        if (!class_exists($class) || !method_exists($class, 'initialise_test_instance') || !$class::are_requirements_met()) {
            $this->markTestSkipped('Could not test '.$class.'. Requirements are not met.');
        }

        // Ensure that each cache is purged prior to the beginning of each test.
        foreach(self::$datalist[$class] as $instance) {
            $instance = reset($instance);

            $this->assertTrue($instance->purge(), 'Must be able to purge during setup to allow other tests to run.');
            $this->assertFalse($instance->get('test1'), 'Purge result must have no data.');
        }
        parent::setUp();
    }

    /**
     * Test that each type is returned using the same type as it was submitted.
     * @param $instance The instac
     *
     * @dataProvider data_with_types
     */
    public function test_get_set_types_stored_correctly($instance, $data) {
        // Test set with a string.
        $this->assertTrue($instance->set('test1', $data));

        $class = $this->get_class_name();

        if (is_object($data) && ($class::get_supported_features() & cache_store::DEREFERENCES_OBJECTS)) {
            // We have an object and know that they must not be the same.
            $this->assertNotSame($data, $instance->get('test1'),
                'Object must be dereferenced is the cachestore reports that it supports it.');
            $this->assertEquals($data, $instance->get('test1'));
        } else if (is_object($data)) {
            // We have an object and cannot be sure whether they are the same or not.
            $this->assertEquals($data, $instance->get('test1'));
        } else {
            $this->assertSame($data, $instance->get('test1'),
                'The return value must be of the same type as the submitted values.');
        }
    }

    /**
     * Test that each type is returned using the same type as it was submitted.
     * @param $instance The instac
     *
     * @dataProvider data_with_types
     */
    public function test_getmany_setmany_types_stored_correctly($instance, $data) {
        // Test set with a string.
        $manydatareturn = ['test1' => $data, 'test2' => $data];
        $manydata = [['key' => 'test1', 'value' => $data], ['key' => 'test2', 'value' => $data]];

        $this->assertSame(2, $instance->set_many($manydata));

        $class = $this->get_class_name();

        if (is_object($data) && ($class::get_supported_features() & cache_store::DEREFERENCES_OBJECTS)) {
            // We have an object and know that they must not be the same.
            $this->assertNotSame($manydatareturn, $instance->get_many(['test1', 'test2']),
                'Object must be dereferenced is the cachestore reports that it supports it.');
            $this->assertEquals($manydatareturn, $instance->get_many(['test1', 'test2']));
        } else if (is_object($data)) {
            // We have an object and cannot be sure whether they are the same or not.
            $this->assertEquals($manydatareturn, $instance->get_many(['test1', 'test2']));
        } else {
            $this->assertSame($data, $instance->get('test1'),
                'Get and set passed, set_many is not saving the correct data.');
            $this->assertSame($manydatareturn, $instance->get_many(['test1', 'test2']),
                'The return value must be of the same type as the submitted values.');
        }
    }

    /**
     * Test the store for basic functionality.
     *
     * @dataProvider instance_list
     */
    public function test_delete($instance) {
        $this->assertTrue($instance->set('test1', true));
        $this->assertTrue($instance->set('test2', 2));

        $this->assertTrue($instance->delete('test1'));
        $this->assertSame(1, $instance->delete_many(['test2', 'test3']));
        $this->assertFalse($instance->get('test1'));
        $this->assertFalse($instance->get('test2'));
        $this->assertTrue($instance->set('test1', 'test1'));
    }

    /**
     * Test the store for basic functionality.
     *
     * @dataProvider instance_list
     */
    public function test_purge($instance) {
        // Test purge.
        $this->assertTrue($instance->set('test1', 1));
        $this->assertTrue($instance->set('test2', 2));
        $this->assertTrue($instance->purge());
        $this->assertFalse($instance->get('test1'));
        $this->assertFalse($instance->get('test2'));
    }

    /**
     * Test the store for basic functionality.
     *
     * @dataProvider instance_list
     */
    public function test_has($instance) {
        $this->assertTrue($instance->set('test1', true));
        $this->assertTrue($instance->set('test2', 2));

        $this->assertTrue($instance->has('test1'));
        $this->assertFalse($instance->has('test3'));

        $this->assertTrue($instance->has_all(['test1', 'test2']));
        $this->assertFalse($instance->has_all(['test1', 'test3']));
        $this->assertFalse($instance->has_all(['test4', 'test3']));

        $this->assertTrue($instance->has_any(['test1', 'test2']));
        $this->assertTrue($instance->has_any(['test1', 'test3']));
        $this->assertFalse($instance->has_any(['test4', 'test3']));
    }

    /**
     * @param $instance
     *
     * @dataProvider instance_list
     */
    public function test_searching($instance) {
        // Only run assertion when we implement this interface.
        if (!($instance instanceof cache_is_searchable)) {
            $this->markTestSkipped('Store does not support searching.');
        }

        $instance->set('test1', 'blah');
        $instance->set('test2', 'blah2');
        $instance->set('fred', 'blah2');

        $this->assertEquals(['test1','test2'], $instance->find_by_prefix('test'), '', 0, 10, true);
        $this->assertEquals(['test1','test2','fred'], $instance->find_all(), '', 0, 10, true);
    }
    /*
     * can't easily test TTL because of the structure of cache::now().  Unlesss we can reset it to the future.
     */
    public function test_instances_behave_separately() {
        $this->setup_store();
        $class = $this->get_class_name();
        $instance1 = null;
        $instance2 = null;

        // Pick the first cache type that's supported to confirm separation of caches.
        if (isset(self::$datalist[$class]['Application'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_APPLICATION, $class, 'phpunit_test_2');
            $instance2 = $class::initialise_unit_test_instance($definition);
            $instance1 = reset(self::$datalist[$class]['Application']);
        } else if (isset(self::$datalist[$class]['Session'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_SESSION, $class, 'phpunit_test_2');
            $instance2 = $class::initialise_unit_test_instance($definition);
            $instance1 = reset(self::$datalist[$class]['Session']);
        } else if (isset(self::$datalist[$class]['Request'])) {
            $definition = cache_definition::load_adhoc(cache_store::MODE_REQUEST, $class, 'phpunit_test_2');
            $instance2 = $class::initialise_unit_test_instance($definition);
            $instance1 = reset(self::$datalist[$class]['Request']);
        }

        if ($instance1 === null || $instance2 === null) {
            $this->fail('Must be able to initialise two caches using the same store at the same time.');
        }

        $instance1->set('test1', 1);
        $this->assertSame(1, $instance1->get('test1'));
        $this->assertFalse($instance2->get('test1'));
        $instance2->set('test2', 2);
        $this->assertSame(2, $instance2->get('test2'));

        $instance2->purge();
        $this->assertSame(1, $instance1->get('test1'), 'A purge of one cache must not clear another.');
    }

    public function test_modes_behave_separately() {
        $this->setup_store();
        $class = $this->get_class_name();
        $instance1 = null;
        $instance2 = null;
        $instance3 = null;

        // Pick the first cache type that's supported to confirm separation of caches.
        if (isset(self::$datalist[$class]['Application'])) {
            $instance1 = reset(self::$datalist[$class]['Application']);
        }
        if (isset(self::$datalist[$class]['Session'])) {
            if ($instance1 === null) {
                $instance1 = reset(self::$datalist[$class]['Session']);
            } else {
                $instance2 = reset(self::$datalist[$class]['Session']);
            }
        }
        if (isset(self::$datalist[$class]['Request'])) {
            if ($instance1 === null) {
                $instance1 = reset(self::$datalist[$class]['Request']);
            } else if ($instance2 === null) {
                $instance2 = reset(self::$datalist[$class]['Request']);
            } else {
                $instance3 = reset(self::$datalist[$class]['Request']);
            }
        }

        // Ensure there are two instances to check against, otherwise we don't need this test.
        if ($instance2 === null) {
            // Automatic pass as this issue can never happen.
            return;
        }

        $instance1->set('test1', 1);
        $this->assertSame(1, $instance1->get('test1'));
        $this->assertFalse($instance2->get('test1'));
        $instance2->set('test2', 2);
        $this->assertSame(2, $instance2->get('test2'));

        // If there is instance 3, confirm it doesn't overlap with the first two.
        if (isset($instance3)) {
            $instance3->set('test3', 3);
            $this->assertFalse($instance2->get('test1'));
            $this->assertFalse($instance1->get('test3'));
        }

        $instance2->purge();
        $this->assertSame(1, $instance1->get('test1'), 'A purge of one cache must not clear another.');

        if (isset($instance3)) {
            $this->assertSame(3, $instance3->get('test1'), 'A purge of one cache must not clear another.');
        }
    }
}
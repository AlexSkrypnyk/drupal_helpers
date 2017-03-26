<?php

namespace Drupal\drupal_helpers\Tests;

use Drupal\drupal_helpers\Variable;

/**
 * Class VariableTestCase.
 *
 * @package Drupal\drupal_helpers\Tests
 */
class VariableTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * Test functionality of extractNames() method.
   *
   * @dataProvider providerExtractNames
   */
  public function testExtractNames($names, $variables, $expected_names) {
    $obj = new Variable();
    $method = self::getMethod($obj, 'extractNames');
    $actual_names = $method->invokeArgs($obj, [$names, $variables]);

    $this->assertEquals($expected_names, $actual_names);
  }

  /**
   * Data provider for testArrayItems().
   */
  public function providerExtractNames() {
    $data = [
      'name' => 'val',
      'prefixname' => 'val',
      'namesuffix' => 'val',
      'prefixnamesuffix' => 'val',
    ];

    return [
      ['', [], []],

      [[], [], []],

      [
        ['name'],
        $data,
        [
          'name',
        ],
      ],

      [
        'name',
        $data,
        [
          'name',
        ],
      ],

      [
        ['name*'],
        $data,
        [
          'name',
          'namesuffix',
        ],
      ],

      [
        'name*',
        $data,
        [
          'name',
          'namesuffix',
        ],
      ],

      [
        ['*name'],
        $data,
        [
          'name',
          'prefixname',
        ],
      ],

      [
        ['name*'],
        $data,
        [
          'name',
          'namesuffix',
        ],
      ],

      [
        ['*name*'],
        $data,
        [
          'name',
          'prefixname',
          'namesuffix',
          'prefixnamesuffix',
        ],
      ],

      [
        ['name', '*suffix'],
        $data,
        [
          'name',
          'namesuffix',
          'prefixnamesuffix',
        ],
      ],
    ];
  }

  /**
   * Helper to get protected method.
   */
  protected static function getMethod($class, $name) {
    $class = new \ReflectionClass($class);
    $method = $class->getMethod($name);
    $method->setAccessible(TRUE);

    return $method;
  }

}

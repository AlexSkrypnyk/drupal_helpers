<?php

namespace Drupal\drupal_helpers\Tests;

/**
 * Class ArrayItemsRandomTestCase.
 *
 * @package Drupal\drupal_helpers\Tests
 */
class RandomTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * Test functionality of arrayItems() method.
   *
   * @dataProvider providerArrayItems
   */
  public function testArrayItems($haystack, $count, $expected_count = NULL) {
    $expected_count = is_null($expected_count) ? $count : $expected_count;
    $actual = call_user_func('Drupal\drupal_helpers\Random::arrayItems', $haystack, $count);

    $this->assertEquals($expected_count, count($actual), 'Returned array has expected count');
    $this->assertEquals(0, count(array_diff($actual, $haystack)), 'Values of returned array are from original array');
    $actual_keys = array_keys($actual);
    $haystack_keys = array_keys($haystack);

    $this->assertEquals(0, count(array_diff($actual_keys, $haystack_keys)), 'Keys of returned array are from original array');
  }

  /**
   * Data provider for testArrayItems().
   */
  public function providerArrayItems() {
    return [
      [[], 0],
      [[1], 1],
      [[1, 2, 3], 1],
      [[1, 2, 3], 2],
      [[1], 2, 1],
      [[], 2, 0],
      [
        [4 => 1, 5 => 2, 6 => 3],
        1,
      ],
      [
        ['a' => 1, 'b' => 2, 'c' => 3],
        1,
      ],
      [
        ['a' => 1, 'b' => 2, 'c' => 3],
        2,
      ],
      [
        ['a' => 1, 'b' => 2, 'c' => 3],
        4,
        3,
      ],
      [
        ['a' => 1, 1 => 'b', 'c' => 'd'],
        2,
      ],
    ];
  }

  /**
   * Test functionality of lipsum() method.
   *
   * @dataProvider providerLipsum
   * @group wip
   */
  public function testLipsum($count, $type, $html, $headers, $start_lorem, $expected_regexes) {
    $actual = call_user_func(['Drupal\drupal_helpers\Random', 'lipsum'], $count, $type, $html, $headers, $start_lorem);
    $expected_regexes = is_array($expected_regexes) ? $expected_regexes : [$expected_regexes];

    $result = TRUE;
    foreach ($expected_regexes as $expected_regex) {
      $expected_regex = self::stringToRegex($expected_regex);
      $result = $result && preg_match($expected_regex, $actual) > 0;
      if (!$result) {
        $this->fail(sprintf('Result did not match condition "%s": "%s"', $expected_regex, print_r($actual, TRUE)));
        break;
      }
    };

    // Assert boolean true to make sure that this case is counted as assertion.
    $this->assertTrue(TRUE);
  }

  /**
   * Data provider for testLipsum().
   */
  public function providerLipsum() {
    return [
      // Zero items.
      [0, 'words', FALSE, FALSE, TRUE, ''],
      [0, 'paragraphs', FALSE, FALSE, TRUE, ''],
      [0, 'words', TRUE, TRUE, TRUE, ''],
      [0, 'paragraphs', TRUE, TRUE, TRUE, ''],

      // 1 and more items.
      [1, 'words', FALSE, FALSE, TRUE, 'lorem'],
      [2, 'words', FALSE, FALSE, TRUE, 'lorem ipsum'],
      [
        1, 'paragraphs', FALSE, FALSE, TRUE,
        [
          '/^Lorem .*/',
          '/[^\n]/',
        ],
      ],
      [
        2, 'paragraphs', FALSE, FALSE, TRUE,
        [
          '/^Lorem .*/',
          '/[^\n]{1}/',
        ],
      ],
      [
        3, 'paragraphs', FALSE, FALSE, TRUE,
        [
          '/^Lorem .*/',
          '/[^\n]{2}/',
        ],
      ],

      // HTML tags.
      // Words - start with Lorem.
      [
        1, 'words', TRUE, FALSE, TRUE,
        [
          // Should start with plain text Lorem or wrapped with a tag.
          '/\<(?:i|b|span)\>lorem\<\/(?:i|b|span)\>/',
        ],
      ],
      [
        2, 'words', TRUE, FALSE, TRUE,
        [
          // At least one word should have a tag.
          '/\<(?:i|b|span)\>[^\<]+\<\/(?:i|b|span)\>/',
          // Should start with plain text Lorem or wrapped with a tag.
          '/^(?:\<(?:i|b|span)\>lorem\<\/(?:i|b|span))\>|lorem/',
        ],
      ],

      // Words - start with random word.
      [
        1, 'words', TRUE, FALSE, TRUE,
        [
          // Should start with plain text Lorem or wrapped with a tag.
          '/\<(?:i|b|span)\>[^\<]+\<\/(?:i|b|span)\>/',
        ],
      ],
      [
        2, 'words', TRUE, FALSE, TRUE,
        [
          // At least one word should have a tag.
          '/\<(?:i|b|span)\>[^\<]+\<\/(?:i|b|span)\>/',
        ],
      ],

      // Paragraphs - start with Lorem word.
      [
        1, 'paragraphs', TRUE, FALSE, TRUE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should start with Lorem wrapped with a tag.
          '/^\<(?:p|div)\>Lorem .*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],
      [
        2, 'paragraphs', TRUE, FALSE, TRUE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should start with Lorem wrapped with a tag.
          '/^\<(?:p|div)\>Lorem .*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],
      // Paragraphs - start with random word.
      [
        1, 'paragraphs', TRUE, FALSE, FALSE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should start with any word wrapped with a tag.
          '/^\<(?:p|div)\>[^\<]+.*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],
      [
        2, 'paragraphs', TRUE, FALSE, FALSE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should start with any word wrapped with a tag.
          '/^\<(?:p|div)\>[^\<]+.*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],

      // Headers - only works with paragraphs in html mode.
      [
        1, 'paragraphs', TRUE, TRUE, FALSE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should have heading in tag.
          '/\<(?:h1|h2|h3)\>[^\<]+\<\/(?:h1|h2|h3)\>/',
          // Should start with a heading wrapped with a tag.
          '/^\<(?:h1|h2|h3)\>[^\<]+.*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],
      [
        2, 'paragraphs', TRUE, TRUE, FALSE,
        [
          // At least one line should have a tag.
          '/\<(?:p|div)\>[^\<]+\<\/(?:p|div)\>/',
          // Should have heading in tag.
          '/\<(?:h1|h2|h3)\>[^\<]+\<\/(?:h1|h2|h3)\>/',
          // Should start with a heading wrapped with a tag.
          '/^\<(?:h1|h2|h3)\>[^\<]+.*/',
          // Should contain at least 2 words.
          '/[\s]{1,}/',
        ],
      ],
    ];
  }

  /**
   * Convert a string to regex if it is not already one.
   *
   * @param string $string
   *   A string that potentially can be a regex.
   *
   * @return string
   *   String converted to regex or original string if it is already a regex.
   */
  protected static function stringToRegex($string) {
    if ($string == '') {
      return '/^$/';
    }

    return preg_match('/^\/.+\/[a-z]*$/i', $string) ? $string : '/' . preg_quote(self::pregUnquote($string), '/') . '/';
  }

  /**
   * Unqoute previously quoted string.
   *
   * @param string $str
   *   String that has "quited" characters.
   *
   * @return string
   *   String with removed quoted characters.
   */
  protected static function pregUnquote($str) {
    $str = str_replace('\\\\', 'DOUBLESLASH', $str);
    $str = str_replace('\\', '', $str);
    $str = str_replace('DOUBLESLASH', '\\', $str);

    return $str;
  }

}

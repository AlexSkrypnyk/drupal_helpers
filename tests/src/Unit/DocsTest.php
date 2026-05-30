<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_helpers\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for docs generation.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 * phpcs:disable Drupal.Commenting.InlineComment.SpacingAfter
 * phpcs:disable DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 */
#[CoversFunction('parse_class_description')]
#[CoversFunction('parse_methods')]
#[CoversFunction('parse_method_docblock')]
#[CoversFunction('build_signature')]
#[CoversFunction('dedent_code')]
#[CoversFunction('replace_content')]
#[CoversFunction('render_info')]
#[CoversFunction('extract_class_info')]
#[CoversFunction('extract_info')]
class DocsTest extends TestCase {

  /**
   * Temporary directory for fixtures.
   */
  protected static string $tmp;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../docs.php';

    static::$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drupal_helpers_docs_test_' . getmypid();

    if (is_dir(static::$tmp)) {
      $this->removeDir(static::$tmp);
    }

    mkdir(static::$tmp, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();

    if (is_dir(static::$tmp)) {
      $this->removeDir(static::$tmp);
    }
  }

  protected function removeDir(string $dir): void {
    $items = scandir($dir);
    if ($items === FALSE) {
      return;
    }
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      is_dir($path) ? $this->removeDir($path) : unlink($path);
    }
    rmdir($dir);
  }

  // =========================================================================
  // parse_class_description()
  // =========================================================================

  /**
   * @dataProvider dataProviderParseClassDescription
   */
  #[DataProvider('dataProviderParseClassDescription')]
  public function testParseClassDescription(string $source, string $expected): void {
    $actual = parse_class_description($source);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderParseClassDescription(): array {
    return [
      'simple class docblock' => [
        <<<'EOD'
<?php

/**
 * User helpers for deploy hooks.
 */
class User extends HelperBase {
}
EOD,
        'User helpers for deploy hooks.',
      ],

      'multiline docblock takes first line' => [
        <<<'EOD'
<?php

/**
 * Config helpers for deploy hooks.
 *
 * Provides methods for configuration management.
 */
class Config extends HelperBase {
}
EOD,
        'Config helpers for deploy hooks.',
      ],

      'docblock with tags skips tag lines' => [
        <<<'EOD'
<?php

/**
 * Entity helpers.
 *
 * @package Drupal\drupal_helpers\Helpers
 */
class Entity extends HelperBase {
}
EOD,
        'Entity helpers.',
      ],

      'no class docblock' => [
        <<<'EOD'
<?php

class NoDocblock extends HelperBase {
}
EOD,
        '',
      ],

      'empty docblock' => [
        <<<'EOD'
<?php

/**
 */
class EmptyDocblock extends HelperBase {
}
EOD,
        '',
      ],

      'docblock only has tags' => [
        <<<'EOD'
<?php

/**
 * @package Foo
 * @deprecated Use something else.
 */
class OnlyTags extends HelperBase {
}
EOD,
        '',
      ],

      'multiple docblocks picks first before class' => [
        <<<'EOD'
<?php

/**
 * File-level docblock.
 */

namespace Foo;

/**
 * This is the class description.
 */
class MultiDoc {
}
EOD,
        'File-level docblock.',
      ],

      'empty source' => [
        '',
        '',
      ],

      'class keyword in string but no actual class' => [
        <<<'EOD'
<?php

$x = "class Foo";
EOD,
        '',
      ],

      'abstract class' => [
        <<<'EOD'
<?php

/**
 * Base helper class.
 */
abstract class HelperBase {
}
EOD,
        '',
      ],
    ];
  }

  // =========================================================================
  // parse_method_docblock()
  // =========================================================================

  /**
   * @dataProvider dataProviderParseMethodDocblock
   */
  #[DataProvider('dataProviderParseMethodDocblock')]
  public function testParseMethodDocblock(string $docblock, ?array $expected): void {
    $actual = parse_method_docblock($docblock);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderParseMethodDocblock(): array {
    return [
      'simple description no example' => [
        <<<'EOD'
/**
 * Create a user account.
 *
 * @param string $email
 *   Email address.
 *
 * @return \Drupal\user\UserInterface
 *   Created user entity.
 */
EOD,
        [
          'description' => 'Create a user account.',
          'example' => '',
        ],
      ],

      'description with code example' => [
        <<<'EOD'
/**
 * Create a user account.
 *
 * @code
 * Helper::user()->create('admin@example.com', ['administrator']);
 * @endcode
 *
 * @param string $email
 *   Email address.
 */
EOD,
        [
          'description' => 'Create a user account.',
          'example' => "Helper::user()->create('admin@example.com', ['administrator']);",
        ],
      ],

      'indented code example gets dedented' => [
        <<<'EOD'
/**
 * Create a user account.
 *
 * @code
 *   Helper::user()->create('admin@example.com');
 *   Helper::user()->create('editor@example.com');
 * @endcode
 */
EOD,
        [
          'description' => 'Create a user account.',
          'example' => "Helper::user()->create('admin@example.com');\nHelper::user()->create('editor@example.com');",
        ],
      ],

      'inheritdoc returns null' => [
        <<<'EOD'
/**
 * {@inheritdoc}
 */
EOD,
        NULL,
      ],

      'inheritdoc with code still returns data' => [
        <<<'EOD'
/**
 * {@inheritdoc}
 *
 * @code
 * Example code here.
 * @endcode
 */
EOD,
        [
          'description' => '{@inheritdoc}',
          'example' => 'Example code here.',
        ],
      ],

      'empty docblock' => [
        <<<'EOD'
/**
 */
EOD,
        [
          'description' => '',
          'example' => '',
        ],
      ],

      'multiline description takes first line only' => [
        <<<'EOD'
/**
 * First line of description.
 * Second line of description.
 *
 * @param string $foo
 *   Foo param.
 */
EOD,
        [
          'description' => 'First line of description.',
          'example' => '',
        ],
      ],

      'code example with empty lines' => [
        <<<'EOD'
/**
 * Do something.
 *
 * @code
 *   $a = 1;
 *
 *   $b = 2;
 * @endcode
 */
EOD,
        [
          'description' => 'Do something.',
          'example' => "\$a = 1;\n\n\$b = 2;",
        ],
      ],

      'description only with no tags' => [
        <<<'EOD'
/**
 * Simple description.
 */
EOD,
        [
          'description' => 'Simple description.',
          'example' => '',
        ],
      ],

      'tags after code block are skipped' => [
        <<<'EOD'
/**
 * Perform action.
 *
 * @code
 * doThing();
 * @endcode
 *
 * @param string $name
 *   The name.
 * @return void
 */
EOD,
        [
          'description' => 'Perform action.',
          'example' => 'doThing();',
        ],
      ],

      'code with deeper indentation' => [
        <<<'EOD'
/**
 * Deep indent.
 *
 * @code
 *     $x = 1;
 *     $y = 2;
 * @endcode
 */
EOD,
        [
          'description' => 'Deep indent.',
          'example' => "\$x = 1;\n\$y = 2;",
        ],
      ],
    ];
  }

  // =========================================================================
  // build_signature()
  // =========================================================================

  /**
   * @dataProvider dataProviderBuildSignature
   */
  #[DataProvider('dataProviderBuildSignature')]
  public function testBuildSignature(string $method_name, string $raw_params, string $return_type, string $expected): void {
    $actual = build_signature($method_name, $raw_params, $return_type);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderBuildSignature(): array {
    return [
      'simple params' => [
        'create',
        'string $email, array $roles = []',
        'UserInterface',
        'create(string $email, array $roles = []): UserInterface',
      ],

      'no params' => [
        'getAll',
        '',
        'array',
        'getAll(): array',
      ],

      'multiline params collapsed' => [
        'create',
        "string \$email,\n    array \$roles = [],\n    array \$fields = []",
        'UserInterface',
        'create(string $email, array $roles = [], array $fields = []): UserInterface',
      ],

      'trailing comma removed' => [
        'foo',
        'string $bar,',
        'void',
        'foo(string $bar): void',
      ],

      'trailing comma and space removed' => [
        'foo',
        'string $bar, ',
        'void',
        'foo(string $bar): void',
      ],

      'whitespace only params' => [
        'noArgs',
        '   ',
        'void',
        'noArgs(): void',
      ],

      'complex types' => [
        'process',
        'array $items, ?string $label = NULL',
        'array',
        'process(array $items, ?string $label = NULL): array',
      ],

      'tabs in params' => [
        'method',
        "string \$a,\tint \$b",
        'bool',
        'method(string $a, int $b): bool',
      ],

      'single param' => [
        'delete',
        'int $id',
        'void',
        'delete(int $id): void',
      ],

      'nullable return type' => [
        'find',
        'int $id',
        '?UserInterface',
        'find(int $id): ?UserInterface',
      ],
    ];
  }

  // =========================================================================
  // dedent_code()
  // =========================================================================

  /**
   * @dataProvider dataProviderDedentCode
   */
  #[DataProvider('dataProviderDedentCode')]
  public function testDedentCode(string $code, string $expected): void {
    $actual = dedent_code($code);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderDedentCode(): array {
    return [
      'no indentation' => [
        "\$a = 1;\n\$b = 2;\n",
        "\$a = 1;\n\$b = 2;",
      ],

      'uniform indentation' => [
        "  \$a = 1;\n  \$b = 2;\n",
        "\$a = 1;\n\$b = 2;",
      ],

      'mixed indentation keeps relative' => [
        "    \$a = 1;\n      \$b = 2;\n    \$c = 3;\n",
        "\$a = 1;\n  \$b = 2;\n\$c = 3;",
      ],

      'empty lines preserved' => [
        "  \$a = 1;\n\n  \$b = 2;\n",
        "\$a = 1;\n\n\$b = 2;",
      ],

      'single line' => [
        "  \$a = 1;\n",
        "\$a = 1;",
      ],

      'empty string' => [
        '',
        '',
      ],

      'only whitespace' => [
        "   \n   \n",
        '',
      ],

      'already no indent' => [
        "line1\nline2\n",
        "line1\nline2",
      ],

      'trailing whitespace on lines' => [
        "  foo  \n  bar  \n",
        "foo  \nbar",
      ],

      'single empty line' => [
        "\n",
        '',
      ],

      'deep indentation' => [
        "        \$a = 1;\n        \$b = 2;\n",
        "\$a = 1;\n\$b = 2;",
      ],
    ];
  }

  // =========================================================================
  // replace_content()
  // =========================================================================

  /**
   * @dataProvider dataProviderReplaceContent
   */
  #[DataProvider('dataProviderReplaceContent')]
  public function testReplaceContent(string $haystack, string $start, string $end, string $replacement, string|null $expected, ?string $exception_message = NULL): void {
    if ($exception_message !== NULL) {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage($exception_message);
    }

    $actual = replace_content($haystack, $start, $end, $replacement);

    if ($expected !== NULL) {
      $this->assertEquals($expected, $actual);
    }
  }

  public static function dataProviderReplaceContent(): array {
    return [
      'simple replacement' => [
        "# Title\n## Start\nold content\n[//]: # (END)\nfooter",
        '## Start',
        '[//]: # (END)',
        "\nnew content\n",
        "# Title\n## Start\n\nnew content\n\n[//]: # (END)\nfooter",
      ],

      'empty replacement' => [
        "before\n## Start\nold\n[//]: # (END)\nafter",
        '## Start',
        '[//]: # (END)',
        '',
        "before\n## Start\n\n[//]: # (END)\nafter",
      ],

      'multiline content between markers' => [
        "header\n## Start\nline1\nline2\nline3\n[//]: # (END)\nfooter",
        '## Start',
        '[//]: # (END)',
        "\nreplaced\n",
        "header\n## Start\n\nreplaced\n\n[//]: # (END)\nfooter",
      ],

      'missing start marker throws exception' => [
        "no marker here\n[//]: # (END)\n",
        '## Start',
        '[//]: # (END)',
        'content',
        NULL,
        'Start marker "## Start" not found.',
      ],

      'missing end marker throws exception' => [
        "## Start\nno end marker\n",
        '## Start',
        '[//]: # (END)',
        'content',
        NULL,
        'End marker "[//]: # (END)" not found.',
      ],

      'both markers missing throws exception for start' => [
        'no markers at all',
        '## Start',
        '[//]: # (END)',
        'content',
        NULL,
        'Start marker "## Start" not found.',
      ],

      'markers adjacent' => [
        '## Start[//]: # (END)',
        '## Start',
        '[//]: # (END)',
        "\ninserted\n",
        "## Start\n\ninserted\n\n[//]: # (END)",
      ],

      'content before first marker preserved' => [
        "line1\nline2\n## Start\ncontent\n[//]: # (END)\nline3",
        '## Start',
        '[//]: # (END)',
        "\nnew\n",
        "line1\nline2\n## Start\n\nnew\n\n[//]: # (END)\nline3",
      ],
    ];
  }

  // =========================================================================
  // parse_methods()
  // =========================================================================

  /**
   * @dataProvider dataProviderParseMethods
   */
  #[DataProvider('dataProviderParseMethods')]
  public function testParseMethods(string $source, array $skip_methods, array $expected): void {
    $actual = parse_methods($source, $skip_methods);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderParseMethods(): array {
    return [
      'single public method' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Do something.
   *
   * @param string $name
   *   The name.
   */
  public function doSomething(string $name): void {
  }
}
EOD,
        [],
        [
          [
            'name' => 'doSomething',
            'signature' => 'doSomething(string $name): void',
            'description' => 'Do something.',
            'example' => '',
          ],
        ],
      ],

      'skips __construct' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Constructor.
   */
  public function __construct(string $dep): void {
  }

  /**
   * Real method.
   */
  public function realMethod(): void {
  }
}
EOD,
        [],
        [
          [
            'name' => 'realMethod',
            'signature' => 'realMethod(): void',
            'description' => 'Real method.',
            'example' => '',
          ],
        ],
      ],

      'skips methods in skip list' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Skipped method.
   */
  public function batch(): void {
  }

  /**
   * Kept method.
   */
  public function doWork(): void {
  }
}
EOD,
        ['batch'],
        [
          [
            'name' => 'doWork',
            'signature' => 'doWork(): void',
            'description' => 'Kept method.',
            'example' => '',
          ],
        ],
      ],

      'skips inheritdoc methods' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * {@inheritdoc}
   */
  public function inherited(): void {
  }

  /**
   * Documented method.
   */
  public function documented(): void {
  }
}
EOD,
        [],
        [
          [
            'name' => 'documented',
            'signature' => 'documented(): void',
            'description' => 'Documented method.',
            'example' => '',
          ],
        ],
      ],

      'static public method' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Static method.
   */
  public static function staticMethod(): string {
  }
}
EOD,
        [],
        [
          [
            'name' => 'staticMethod',
            'signature' => 'staticMethod(): string',
            'description' => 'Static method.',
            'example' => '',
          ],
        ],
      ],

      'no public methods' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Private method.
   */
  private function privateMethod(): void {
  }

  /**
   * Protected method.
   */
  protected function protectedMethod(): void {
  }
}
EOD,
        [],
        [],
      ],

      'method with code example' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Create something.
   *
   * @code
   * $foo->create('test');
   * @endcode
   *
   * @param string $name
   *   The name.
   */
  public function create(string $name): bool {
  }
}
EOD,
        [],
        [
          [
            'name' => 'create',
            'signature' => 'create(string $name): bool',
            'description' => 'Create something.',
            'example' => "\$foo->create('test');",
          ],
        ],
      ],

      'multiple methods' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * First method.
   */
  public function first(): void {
  }

  /**
   * Second method.
   */
  public function second(int $id): string {
  }
}
EOD,
        [],
        [
          [
            'name' => 'first',
            'signature' => 'first(): void',
            'description' => 'First method.',
            'example' => '',
          ],
          [
            'name' => 'second',
            'signature' => 'second(int $id): string',
            'description' => 'Second method.',
            'example' => '',
          ],
        ],
      ],

      'empty source' => [
        '',
        [],
        [],
      ],

      'method with multiline params' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Complex method.
   */
  public function complex(
    string $a,
    int $b,
    array $c,
  ): void {
  }
}
EOD,
        [],
        [
          [
            'name' => 'complex',
            'signature' => 'complex(string $a, int $b, array $c): void',
            'description' => 'Complex method.',
            'example' => '',
          ],
        ],
      ],

      'method with default values' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * With defaults.
   */
  public function withDefaults(string $name, int $count = 0, bool $flag = TRUE): array {
  }
}
EOD,
        [],
        [
          [
            'name' => 'withDefaults',
            'signature' => 'withDefaults(string $name, int $count = 0, bool $flag = TRUE): array',
            'description' => 'With defaults.',
            'example' => '',
          ],
        ],
      ],

      'all methods skipped' => [
        <<<'EOD'
<?php
class Foo {
  /**
   * Batch method.
   */
  public function batch(): void {
  }
}
EOD,
        ['batch'],
        [],
      ],
    ];
  }

  // =========================================================================
  // render_info()
  // =========================================================================

  /**
   * @dataProvider dataProviderRenderInfo
   */
  #[DataProvider('dataProviderRenderInfo')]
  public function testRenderInfo(array $info, string $expected): void {
    $actual = render_info($info, static::$tmp);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderRenderInfo(): array {
    return [
      'single class with single method' => [
        [
          'User' => [
            'name' => 'User',
            'description' => 'User helpers for deploy hooks.',
            'source' => 'src/Helpers/User.php',
            'methods' => [
              [
                'name' => 'create',
                'signature' => 'create(string $email): UserInterface',
                'description' => 'Create a user account.',
                'example' => "Helper::user()->create('admin@example.com');",
              ],
            ],
          ],
        ],
        "| Helper | Description |\n"
          . "| --- | --- |\n"
          . "| [User](#user) | User helpers for deploy hooks. |\n"
          . "\n---\n"
          . "\n### User\n\n"
          . "[Source](src/Helpers/User.php)\n\n"
          . ">  User helpers for deploy hooks.\n\n"
          . "<details>\n"
          . "  <summary>Create a user account.<br/><code>create(string \$email): UserInterface</code></summary>\n\n"
          . "```php\n"
          . "Helper::user()->create('admin@example.com');\n"
          . "```\n\n"
          . "</details>\n\n",
      ],

      'method without example' => [
        [
          'Config' => [
            'name' => 'Config',
            'description' => 'Config helpers.',
            'source' => 'src/Helpers/Config.php',
            'methods' => [
              [
                'name' => 'get',
                'signature' => 'get(string $key): mixed',
                'description' => 'Get a config value.',
                'example' => '',
              ],
            ],
          ],
        ],
        "| Helper | Description |\n"
          . "| --- | --- |\n"
          . "| [Config](#config) | Config helpers. |\n"
          . "\n---\n"
          . "\n### Config\n\n"
          . "[Source](src/Helpers/Config.php)\n\n"
          . ">  Config helpers.\n\n"
          . "<details>\n"
          . "  <summary>Get a config value.<br/><code>get(string \$key): mixed</code></summary>\n\n\n"
          . "</details>\n\n",
      ],

      'multiple classes' => [
        [
          'Alpha' => [
            'name' => 'Alpha',
            'description' => 'Alpha desc.',
            'source' => 'src/Helpers/Alpha.php',
            'methods' => [
              [
                'name' => 'run',
                'signature' => 'run(): void',
                'description' => 'Run alpha.',
                'example' => '',
              ],
            ],
          ],
          'Beta' => [
            'name' => 'Beta',
            'description' => 'Beta desc.',
            'source' => 'src/Helpers/Beta.php',
            'methods' => [
              [
                'name' => 'start',
                'signature' => 'start(): void',
                'description' => 'Start beta.',
                'example' => '',
              ],
            ],
          ],
        ],
        "| Helper | Description |\n"
          . "| --- | --- |\n"
          . "| [Alpha](#alpha) | Alpha desc. |\n"
          . "| [Beta](#beta) | Beta desc. |\n"
          . "\n---\n"
          . "\n### Alpha\n\n"
          . "[Source](src/Helpers/Alpha.php)\n\n"
          . ">  Alpha desc.\n\n"
          . "<details>\n"
          . "  <summary>Run alpha.<br/><code>run(): void</code></summary>\n\n\n"
          . "</details>\n\n"
          . "\n### Beta\n\n"
          . "[Source](src/Helpers/Beta.php)\n\n"
          . ">  Beta desc.\n\n"
          . "<details>\n"
          . "  <summary>Start beta.<br/><code>start(): void</code></summary>\n\n\n"
          . "</details>\n\n",
      ],

      'empty info' => [
        [],
        "| Helper | Description |\n"
          . "| --- | --- |\n"
          . "\n"
          . "\n---\n",
      ],

      'multiple methods in one class' => [
        [
          'Entity' => [
            'name' => 'Entity',
            'description' => 'Entity helpers.',
            'source' => 'src/Helpers/Entity.php',
            'methods' => [
              [
                'name' => 'load',
                'signature' => 'load(int $id): EntityInterface',
                'description' => 'Load entity.',
                'example' => '',
              ],
              [
                'name' => 'delete',
                'signature' => 'delete(int $id): void',
                'description' => 'Delete entity.',
                'example' => "\$helper->delete(123);",
              ],
            ],
          ],
        ],
        "| Helper | Description |\n"
          . "| --- | --- |\n"
          . "| [Entity](#entity) | Entity helpers. |\n"
          . "\n---\n"
          . "\n### Entity\n\n"
          . "[Source](src/Helpers/Entity.php)\n\n"
          . ">  Entity helpers.\n\n"
          . "<details>\n"
          . "  <summary>Load entity.<br/><code>load(int \$id): EntityInterface</code></summary>\n\n\n"
          . "</details>\n\n"
          . "<details>\n"
          . "  <summary>Delete entity.<br/><code>delete(int \$id): void</code></summary>\n\n"
          . "```php\n"
          . "\$helper->delete(123);\n"
          . "```\n\n"
          . "</details>\n\n",
      ],
    ];
  }

  // =========================================================================
  // extract_class_info()
  // =========================================================================

  /**
   * @dataProvider dataProviderExtractClassInfo
   */
  #[DataProvider('dataProviderExtractClassInfo')]
  public function testExtractClassInfo(string $file_content, string $class_name, array $skip_methods, ?array $expected): void {
    $helpers_dir = static::$tmp . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Helpers';
    mkdir($helpers_dir, 0777, TRUE);

    $file_path = $helpers_dir . DIRECTORY_SEPARATOR . $class_name . '.php';
    file_put_contents($file_path, $file_content);

    $fqcn = 'Drupal\\drupal_helpers\\Helpers\\' . $class_name;
    $src_file = 'src/Helpers/' . $class_name . '.php';

    $actual = extract_class_info($fqcn, $src_file, static::$tmp, $skip_methods);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderExtractClassInfo(): array {
    return [
      'class with one method' => [
        <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Test helpers.
 */
class TestHelper extends HelperBase {

  /**
   * Do something useful.
   *
   * @param string $name
   *   The name.
   */
  public function doSomething(string $name): void {
  }
}
EOD,
        'TestHelper',
        [],
        [
          'name' => 'TestHelper',
          'description' => 'Test helpers.',
          'source' => 'src/Helpers/TestHelper.php',
          'methods' => [
            [
              'name' => 'doSomething',
              'signature' => 'doSomething(string $name): void',
              'description' => 'Do something useful.',
              'example' => '',
            ],
          ],
        ],
      ],

      'class with no documentable methods returns null' => [
        <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Empty class.
 */
class EmptyHelper extends HelperBase {

  /**
   * {@inheritdoc}
   */
  public function inherited(): void {
  }
}
EOD,
        'EmptyHelper',
        [],
        NULL,
      ],

      'class with skipped methods only returns null' => [
        <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Skippable class.
 */
class SkipHelper extends HelperBase {

  /**
   * Batch method.
   */
  public function batch(): void {
  }
}
EOD,
        'SkipHelper',
        ['batch'],
        NULL,
      ],

      'class with constructor and method' => [
        <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * With constructor.
 */
class ConstructHelper extends HelperBase {

  /**
   * Constructor.
   */
  public function __construct(string $dep): void {
  }

  /**
   * Real work.
   *
   * @code
   * $h->work();
   * @endcode
   */
  public function work(): bool {
  }
}
EOD,
        'ConstructHelper',
        [],
        [
          'name' => 'ConstructHelper',
          'description' => 'With constructor.',
          'source' => 'src/Helpers/ConstructHelper.php',
          'methods' => [
            [
              'name' => 'work',
              'signature' => 'work(): bool',
              'description' => 'Real work.',
              'example' => '$h->work();',
            ],
          ],
        ],
      ],

      'nonexistent file returns null' => [
        '',
        'Nonexistent',
        [],
        NULL,
      ],

      'class with multiple methods some skipped' => [
        <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Mixed methods.
 */
class MixedHelper extends HelperBase {

  /**
   * Batch method.
   */
  public function batch(): void {
  }

  /**
   * Useful method.
   */
  public function useful(): string {
  }

  /**
   * Set sandbox.
   */
  public function setSandbox(): void {
  }
}
EOD,
        'MixedHelper',
        ['batch', 'setSandbox'],
        [
          'name' => 'MixedHelper',
          'description' => 'Mixed methods.',
          'source' => 'src/Helpers/MixedHelper.php',
          'methods' => [
            [
              'name' => 'useful',
              'signature' => 'useful(): string',
              'description' => 'Useful method.',
              'example' => '',
            ],
          ],
        ],
      ],
    ];
  }

  // =========================================================================
  // extract_class_info() - nonexistent file.
  // =========================================================================

  // phpcs:ignore DrevOps.TestingPractices.DataProviderMatchesTestName.InvalidProviderName
  public function testExtractClassInfoNonexistentFile(): void {
    $actual = @extract_class_info(
      'Drupal\\drupal_helpers\\Helpers\\Missing',
      'src/Helpers/Missing.php',
      static::$tmp,
      []
    );

    $this->assertNull($actual);
  }

  // =========================================================================
  // extract_info()
  // =========================================================================

  /**
   * @dataProvider dataProviderExtractInfo
   */
  #[DataProvider('dataProviderExtractInfo')]
  public function testExtractInfo(array $fixture_files, array $expected): void {
    $helpers_dir = static::$tmp . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Helpers';
    mkdir($helpers_dir, 0777, TRUE);

    // Always create HelperBase (it gets skipped).
    file_put_contents($helpers_dir . DIRECTORY_SEPARATOR . 'HelperBase.php', "<?php\nclass HelperBase {}\n");

    foreach ($fixture_files as $filename => $content) {
      file_put_contents($helpers_dir . DIRECTORY_SEPARATOR . $filename, $content);
    }

    $actual = extract_info(static::$tmp);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderExtractInfo(): array {
    return [
      'single helper class' => [
        [
          'Foo.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Foo helpers.
 */
class Foo extends HelperBase {

  /**
   * Do foo.
   */
  public function doFoo(): void {
  }
}
EOD,
        ],
        [
          'Foo' => [
            'name' => 'Foo',
            'description' => 'Foo helpers.',
            'source' => 'src/Helpers/Foo.php',
            'methods' => [
              [
                'name' => 'doFoo',
                'signature' => 'doFoo(): void',
                'description' => 'Do foo.',
                'example' => '',
              ],
            ],
          ],
        ],
      ],

      'multiple helper classes sorted' => [
        [
          'Zebra.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Zebra helpers.
 */
class Zebra extends HelperBase {

  /**
   * Stripe.
   */
  public function stripe(): void {
  }
}
EOD,
          'Alpha.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Alpha helpers.
 */
class Alpha extends HelperBase {

  /**
   * Run alpha.
   */
  public function run(): string {
  }
}
EOD,
        ],
        [
          'Alpha' => [
            'name' => 'Alpha',
            'description' => 'Alpha helpers.',
            'source' => 'src/Helpers/Alpha.php',
            'methods' => [
              [
                'name' => 'run',
                'signature' => 'run(): string',
                'description' => 'Run alpha.',
                'example' => '',
              ],
            ],
          ],
          'Zebra' => [
            'name' => 'Zebra',
            'description' => 'Zebra helpers.',
            'source' => 'src/Helpers/Zebra.php',
            'methods' => [
              [
                'name' => 'stripe',
                'signature' => 'stripe(): void',
                'description' => 'Stripe.',
                'example' => '',
              ],
            ],
          ],
        ],
      ],

      'skips HelperBase' => [
        [],
        [],
      ],

      'skips class with only inheritdoc methods' => [
        [
          'Bare.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Bare class.
 */
class Bare extends HelperBase {

  /**
   * {@inheritdoc}
   */
  public function inherited(): void {
  }
}
EOD,
        ],
        [],
      ],

      'skips non-php files' => [
        [
          'readme.txt' => 'not a php file',
          'Valid.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Valid helpers.
 */
class Valid extends HelperBase {

  /**
   * Do valid.
   */
  public function doValid(): bool {
  }
}
EOD,
        ],
        [
          'Valid' => [
            'name' => 'Valid',
            'description' => 'Valid helpers.',
            'source' => 'src/Helpers/Valid.php',
            'methods' => [
              [
                'name' => 'doValid',
                'signature' => 'doValid(): bool',
                'description' => 'Do valid.',
                'example' => '',
              ],
            ],
          ],
        ],
      ],

      'skips built-in skip methods' => [
        [
          'WithSkip.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * With skip methods.
 */
class WithSkip extends HelperBase {

  /**
   * Required modules.
   */
  public function requiredModules(): array {
  }

  /**
   * Set sandbox.
   */
  public function setSandbox(): void {
  }

  /**
   * Set batch size.
   */
  public function setBatchSize(): void {
  }

  /**
   * Batch.
   */
  public function batch(): void {
  }

  /**
   * Batch entity.
   */
  public function batchEntity(): void {
  }

  /**
   * Useful method.
   */
  public function useful(): string {
  }
}
EOD,
        ],
        [
          'WithSkip' => [
            'name' => 'WithSkip',
            'description' => 'With skip methods.',
            'source' => 'src/Helpers/WithSkip.php',
            'methods' => [
              [
                'name' => 'useful',
                'signature' => 'useful(): string',
                'description' => 'Useful method.',
                'example' => '',
              ],
            ],
          ],
        ],
      ],

      'class with code examples' => [
        [
          'ExampleClass.php' => <<<'EOD'
<?php

namespace Drupal\drupal_helpers\Helpers;

/**
 * Example class helpers.
 */
class ExampleClass extends HelperBase {

  /**
   * Create item.
   *
   * @code
   * $helper->createItem('test');
   * @endcode
   *
   * @param string $name
   *   The name.
   */
  public function createItem(string $name): bool {
  }
}
EOD,
        ],
        [
          'ExampleClass' => [
            'name' => 'ExampleClass',
            'description' => 'Example class helpers.',
            'source' => 'src/Helpers/ExampleClass.php',
            'methods' => [
              [
                'name' => 'createItem',
                'signature' => 'createItem(string $name): bool',
                'description' => 'Create item.',
                'example' => "\$helper->createItem('test');",
              ],
            ],
          ],
        ],
      ],
    ];
  }

}

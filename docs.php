<?php

/**
 * @file
 * Documentation generator for drupal_helpers.
 *
 * Parses docblock comments from helper classes in src/Helpers/ and generates
 * API reference documentation in METHODS.md and README.md.
 *
 * Run: php docs.php
 * Run with --fail-on-change to fail if documentation is outdated (for CI).
 * Run with --path=path/to/dir to specify a custom base path.
 *
 * @phpcs:disable DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter
 * @phpcs:disable Squiz.WhiteSpace.FunctionSpacing.After
 */

declare(strict_types=1);

// Execute only when run directly.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'docs.php') {
  $options = getopt('', ['fail-on-change', 'path::']);
  main($options);
}
// @codeCoverageIgnoreEnd

/**
 * Main function to handle the documentation generation process.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function main(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : __DIR__;

  $info = extract_info($base_path);

  $markdown = PHP_EOL . render_info($info, $base_path) . PHP_EOL;

  $readme_file = 'README.md';
  $readme_path = $base_path . DIRECTORY_SEPARATOR . $readme_file;
  $readme_contents = file_get_contents($readme_path);
  if ($readme_contents === FALSE) {
    printf('Failed to read %s.' . PHP_EOL, $readme_file);
    exit(1);
  }
  $readme_replaced = replace_content($readme_contents, '## Available methods', '[//]: # (END)', $markdown);

  if ($readme_replaced === $readme_contents) {
    echo 'Documentation is up to date. No changes were made.' . PHP_EOL;
    exit(0);
  }

  $fail_on_change = isset($options['fail-on-change']);
  if ($fail_on_change) {
    echo 'Documentation is outdated. No changes were made.' . PHP_EOL;
    exit(1);
  }

  file_put_contents($readme_path, $readme_replaced);
  echo 'Documentation updated.' . PHP_EOL;
}
// @codeCoverageIgnoreEnd

/**
 * Extract documentation info from helper classes.
 *
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<string, array<string, mixed>>
 *   Array keyed by class short name with 'name', 'description', 'source',
 *   and 'methods' keys.
 */
function extract_info(string $base_path): array {
  $info = [];
  $helpers_path = $base_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Helpers';

  $files = scandir($helpers_path) ?: [];
  sort($files);

  // Classes to skip — base classes and abstract classes.
  $skip = ['HelperBase'];
  // Methods inherited from base classes that should not be documented.
  $skip_methods = [
    'requiredModules',
    'setSandbox',
    'setBatchSize',
    'batch',
    'batchEntity',
  ];

  foreach ($files as $file) {
    if (!str_ends_with($file, '.php')) {
      continue;
    }

    $class_name = basename($file, '.php');

    if (in_array($class_name, $skip, TRUE)) {
      continue;
    }

    $fqcn = 'Drupal\\drupal_helpers\\Helpers\\' . $class_name;
    $src_file = 'src/Helpers/' . $file;

    $class_info = extract_class_info($fqcn, $src_file, $base_path, $skip_methods);
    if ($class_info !== NULL) {
      $info[$class_name] = $class_info;
    }
  }

  return $info;
}

/**
 * Extract info from a single class using file parsing.
 *
 * @param string $fqcn
 *   Fully qualified class name.
 * @param string $src_file
 *   Relative path to source file.
 * @param string $base_path
 *   Base path for the repository.
 * @param array<int, string> $skip_methods
 *   Method names to skip.
 *
 * @return array<string, mixed>|null
 *   Class info or NULL if no documentable methods.
 */
function extract_class_info(string $fqcn, string $src_file, string $base_path, array $skip_methods): ?array {
  $file_path = $base_path . DIRECTORY_SEPARATOR . $src_file;
  $source = file_get_contents($file_path);

  if ($source === FALSE) {
    return NULL;
  }

  $parts = explode('\\', $fqcn);
  $class_name = end($parts);

  $class_description = parse_class_description($source);

  $methods = parse_methods($source, $skip_methods);

  if (empty($methods)) {
    return NULL;
  }

  return [
    'name' => $class_name,
    'description' => $class_description,
    'source' => $src_file,
    'methods' => $methods,
  ];
}

/**
 * Parse the class-level docblock description from source.
 *
 * @param string $source
 *   File source code.
 *
 * @return string
 *   First line of the class docblock.
 */
function parse_class_description(string $source): string {
  // Find the class docblock — the last docblock before "class ".
  if (preg_match('#/\*\*\s*\n(.*?)\*/\s*\n\s*class\s#s', $source, $match)) {
    $lines = explode("\n", $match[1]);
    foreach ($lines as $line) {
      $line = preg_replace('/^\s*\*\s?/', '', $line);
      $line = trim((string) $line);
      if (!empty($line) && !str_starts_with($line, '@')) {
        return $line;
      }
    }
  }

  return '';
}

/**
 * Parse public methods and their docblocks from source code.
 *
 * @param string $source
 *   File source code.
 * @param array<int, string> $skip_methods
 *   Method names to skip.
 *
 * @return array<int, array<string, string>>
 *   Array of method info with 'name', 'signature', 'description', and
 *   'example' keys.
 */
function parse_methods(string $source, array $skip_methods): array {
  $methods = [];

  // Match docblock immediately followed by public function declaration.
  // The docblock must start with /** on its own line and cannot contain
  // another */ before the closing */.
  $pattern = '#(/\*\*\n(?:[ \t]*\*[^\n]*\n)*?[ \t]*\*/)\s*\n\s*public\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*:\s*([^\s{]+)#';

  if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
    return [];
  }

  foreach ($matches as $match) {
    $docblock = $match[1];
    $method_name = $match[2];
    $raw_params = $match[3];
    $return_type = $match[4];

    if ($method_name === '__construct') {
      continue;
    }

    if (in_array($method_name, $skip_methods, TRUE)) {
      continue;
    }

    $parsed = parse_method_docblock($docblock);

    if ($parsed === NULL) {
      continue;
    }

    $signature = build_signature($method_name, $raw_params, $return_type);

    $methods[] = [
      'name' => $method_name,
      'signature' => $signature,
      'description' => $parsed['description'],
      'example' => $parsed['example'],
    ];
  }

  return $methods;
}

/**
 * Build a clean method signature string.
 *
 * @param string $method_name
 *   Method name.
 * @param string $raw_params
 *   Raw parameter string from the function declaration.
 * @param string $return_type
 *   Return type.
 *
 * @return string
 *   Formatted signature like
 *   "create(string $email, array $roles = []): UserInterface".
 */
function build_signature(string $method_name, string $raw_params, string $return_type): string {
  // Clean up parameter formatting.
  $params = trim($raw_params);
  // Collapse multi-line parameters into single line.
  $params = preg_replace('/\s+/', ' ', $params);
  // Remove trailing comma.
  $params = rtrim((string) $params, ', ');

  return sprintf('%s(%s): %s', $method_name, $params, $return_type);
}

/**
 * Parse a method docblock for description and example.
 *
 * @param string $docblock
 *   The raw docblock string.
 *
 * @return array<string, string>|null
 *   Array with 'description' and 'example' keys, or NULL if the docblock
 *   is just {@inheritdoc}.
 */
function parse_method_docblock(string $docblock): ?array {
  // Skip {@inheritdoc} only docblocks.
  if (preg_match('/\{@inheritdoc\}/', $docblock) && !preg_match('/@code/', $docblock)) {
    return NULL;
  }

  $result = [
    'description' => '',
    'example' => '',
  ];

  $lines = explode("\n", $docblock);

  $in_code = FALSE;
  $found_description = FALSE;

  foreach ($lines as $line) {
    // Strip docblock markup.
    $line = preg_replace('#^\s*/?\*+/?\s?#', '', $line);
    $line = rtrim((string) $line);

    if (str_starts_with(trim($line), '@code')) {
      $in_code = TRUE;
      continue;
    }

    if (str_starts_with(trim($line), '@endcode')) {
      $in_code = FALSE;
      continue;
    }

    if ($in_code) {
      $result['example'] .= $line . PHP_EOL;
      continue;
    }

    // Stop collecting description at @param or other tags.
    if (str_starts_with(trim($line), '@')) {
      continue;
    }

    if (!$found_description && !empty(trim($line))) {
      $result['description'] = trim($line);
      $found_description = TRUE;
    }
  }

  // Clean up example indentation.
  if (!empty($result['example'])) {
    $result['example'] = dedent_code($result['example']);
  }

  return $result;
}

/**
 * Remove common leading indentation from a code block.
 *
 * @param string $code
 *   Code block string.
 *
 * @return string
 *   Dedented code.
 */
function dedent_code(string $code): string {
  $lines = explode(PHP_EOL, $code);

  // Find minimum indentation of non-empty lines.
  $min_indent = PHP_INT_MAX;
  foreach ($lines as $line) {
    if (trim($line) !== '') {
      $indent = strspn($line, ' ');
      $min_indent = min($min_indent, $indent);
    }
  }

  if ($min_indent === PHP_INT_MAX || $min_indent === 0) {
    return rtrim($code);
  }

  $dedented = array_map(static function (string $line) use ($min_indent): string {
    return strlen($line) >= $min_indent ? substr($line, $min_indent) : $line;
  }, $lines);

  return rtrim(implode(PHP_EOL, $dedented));
}

/**
 * Render documentation info as markdown.
 *
 * @param array<string, array<string, mixed>> $info
 *   Extracted class info.
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return string
 *   Markdown content.
 */
function render_info(array $info, string $base_path = __DIR__): string {
  // Build index table.
  $index_rows = [];
  foreach ($info as $class_info) {
    $anchor = '#' . strtolower((string) $class_info['name']);
    $index_rows[] = sprintf(
      '| [%s](%s) | %s |',
      $class_info['name'],
      $anchor,
      $class_info['description']
    );
  }

  $output = '';
  $output .= '| Helper | Description |' . PHP_EOL;
  $output .= '| --- | --- |' . PHP_EOL;
  $output .= implode(PHP_EOL, $index_rows) . PHP_EOL;

  $output .= PHP_EOL . '---' . PHP_EOL;

  // Build detailed sections.
  foreach ($info as $class_info) {
    $output .= PHP_EOL . sprintf('### %s', $class_info['name']) . PHP_EOL . PHP_EOL;
    $output .= sprintf('[Source](%s)', $class_info['source']) . PHP_EOL . PHP_EOL;
    $output .= sprintf('>  %s', $class_info['description']) . PHP_EOL . PHP_EOL;

    foreach ($class_info['methods'] as $method) {
      $output .= '<details>' . PHP_EOL;
      $output .= sprintf('  <summary>%s<br/><code>%s</code></summary>', $method['description'], $method['signature']) . PHP_EOL;
      $output .= PHP_EOL;

      if (!empty($method['example'])) {
        $output .= '```php' . PHP_EOL;
        $output .= $method['example'] . PHP_EOL;
        $output .= '```' . PHP_EOL;
      }

      $output .= PHP_EOL;
      $output .= '</details>' . PHP_EOL;
      $output .= PHP_EOL;
    }
  }

  return $output;
}

/**
 * Replace content between markers in a string.
 *
 * @param string $haystack
 *   The content to search and replace in.
 * @param string $start
 *   The start marker.
 * @param string $end
 *   The end marker.
 * @param string $replacement
 *   The replacement content.
 *
 * @return string
 *   Updated content.
 */
function replace_content(string $haystack, string $start, string $end, string $replacement): string {
  if (!str_contains($haystack, $start)) {
    throw new \Exception(sprintf('Start marker "%s" not found.', $start));
  }

  if (!str_contains($haystack, $end)) {
    throw new \Exception(sprintf('End marker "%s" not found.', $end));
  }

  $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';

  return (string) preg_replace($pattern, $start . PHP_EOL . $replacement . PHP_EOL . $end, $haystack);
}

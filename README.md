<div align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="logo.png" alt="Drupal Helpers logo"></a>
</div>

<h1 align="center">Helper utilities for Drupal</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/AlexSkrypnyk/drupal_helpers.svg)](https://github.com/AlexSkrypnyk/drupal_helpers/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/AlexSkrypnyk/drupal_helpers.svg)](https://github.com/AlexSkrypnyk/drupal_helpers/pulls)
[![Build, test and deploy](https://github.com/AlexSkrypnyk/drupal_helpers/actions/workflows/test.yml/badge.svg)](https://github.com/AlexSkrypnyk/drupal_helpers/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/AlexSkrypnyk/drupal_helpers/graph/badge.svg?token=T6IXYAT4VU)](https://codecov.io/gh/AlexSkrypnyk/drupal_helpers)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/AlexSkrypnyk/drupal_helpers)
![LICENSE](https://img.shields.io/github/license/AlexSkrypnyk/drupal_helpers)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777BB4.svg)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4.svg)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4.svg)
![Drupal 10](https://img.shields.io/badge/Drupal-10-009CDE.svg)
![Drupal 11](https://img.shields.io/badge/Drupal-11-006AA9.svg)

</div>

---

## Features

<details>
  <summary>🎯 <strong>Static facade for clean deploy hooks</strong></summary>

Access all helpers through `Helper::term()`, `Helper::config()`, etc. - no
need to inject services or know container names. One `use` statement is all
you need.

```php
use Drupal\drupal_helpers\Helper;

Helper::term()->createTree('tags', ['News', 'Events', 'Blog']);
Helper::config()->set('system.site', 'name', 'My Site');
```

</details>

<details>
  <summary>⚡ <strong>Batch processing for large datasets</strong></summary>

Pass the `$sandbox` array from your deploy hook and the helper automatically
batches operations across multiple requests - no manual tracking of
`$sandbox['#finished']`.

```php
// Batch-update every article node:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::entity($sandbox)->batchEntity('node', 'article', function ($node) {
    $node->set('field_migrated', TRUE);
    $node->save();
  });
}

// Batch-delete all articles:
function my_module_deploy_002(array &$sandbox): ?string {
  return Helper::entity($sandbox)->deleteAll('node', 'article');
}

// Batch-process arbitrary items with a callback:
function my_module_deploy_003(array &$sandbox): ?string {
  $emails = ['user1@example.com', 'user2@example.com', /* ... hundreds more */];
  return Helper::user($sandbox)->batch($emails, function ($email) {
    Helper::user()->create($email, ['editor']);
  }, 'users');
}
```

</details>

<details>
  <summary>📊 <strong>Operation logging and result reporting</strong></summary>

Every helper feeds a shared reporter that tallies what a run created, updated,
skipped, deleted, or failed, logs each operation to a dedicated `drupal_helpers`
logger channel, and surfaces it through the messenger. Return the tally from a
deploy hook with `Helper::report()` - it renders as a single line for both drush
and `update.php`, then resets so the next hook starts clean.

```php
use Drupal\drupal_helpers\Helper;

function my_module_deploy_001(array &$sandbox): ?string {
  Helper::term()->createTree('topics', $large_tree);
  // e.g. "Created 12, skipped 3."
  return Helper::report();
}
```

Pass `continue_on_error: TRUE` to the batch helpers to tolerate per-item
failures: each is reported as a warning and counted, and the run keeps going
instead of aborting.

</details>

<details>
  <summary>🧰 <strong>Taxonomy, menu, block, display, field, entity, config, module, user, role, redirect, URL alias, and translation helpers</strong></summary>

Common deploy hook operations covered out of the box:
- Create taxonomy term trees (flat or nested) with safe, update, and sync reconciliation modes, plus export back to PHP or YAML.
- Build menu link hierarchies from arrays with the same reconciliation modes and export.
- Place theme blocks and create or delete block content.
- Set or hide components on entity form and view displays.
- Create fields with sensible display defaults, attach them to more bundles, and delete fields or instances with automatic data purging.
- Import config YAML from modules.
- Install and uninstall modules, including force-removal of orphaned modules whose code is gone.
- Create users with roles and auto-generated passwords.
- Create and delete roles and grant or revoke permissions (unknown permissions rejected).
- Create, export, import (CSV round trip), delete, and clean up redirects.
- Create, find, update, import (CSV), and clean up URL aliases (core, no contrib).
- Add or update interface translations, with context support.

</details>

<details>
  <summary>🔌 <strong>Extendable via Drupal services</strong></summary>

Every helper is a standard Drupal service registered in
`drupal_helpers.services.yml`. You can override, decorate, or inject them
into your own services using Drupal's dependency injection container.

```yaml
# Use a helper as a dependency in your own service:
services:
  my_module.migrator:
    class: Drupal\my_module\Migrator
    arguments: ['@drupal_helpers.term', '@drupal_helpers.entity']
```

</details>

<details>
  <summary>🛡️ <strong>Module requirement checking</strong></summary>

Helpers that depend on contrib modules (e.g., Redirect requires the `redirect`
module) declare their requirements via `requiredModules()`. The facade checks
these at access time and throws a clear error if a module is missing - no
cryptic "service not found" exceptions.

</details>

## Installation

```bash
composer require drupal/drupal_helpers
drush pm:install drupal_helpers
```

## Usage

All helpers are accessed via the `Helper` facade:

```php
use Drupal\drupal_helpers\Helper;

// Simple - no sandbox:
Helper::term()->createTree('topics', $tree);
Helper::field()->delete('field_old');

// Batched - with sandbox:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::entity($sandbox)->deleteAll('node', 'article');
}
```

## Available methods

| Helper | Description |
| --- | --- |
| [Alias](#alias) | URL alias helpers for deploy hooks. |
| [Block](#block) | Block placement and block content helpers for deploy hooks. |
| [Config](#config) | Configuration helpers for deploy hooks. |
| [Display](#display) | Entity form and view display helpers for deploy hooks. |
| [Entity](#entity) | Entity helpers for deploy hooks. |
| [Field](#field) | Field helpers for deploy hooks. |
| [Menu](#menu) | Menu link helpers for deploy hooks. |
| [Module](#module) | Module install and uninstall helpers for deploy hooks. |
| [Redirect](#redirect) | Redirect helpers for deploy hooks. |
| [Role](#role) | Role and permission helpers for deploy hooks. |
| [Term](#term) | Taxonomy term helpers for deploy hooks. |
| [Translation](#translation) | Interface translation helpers for deploy hooks. |
| [User](#user) | User helpers for deploy hooks. |

---

### Alias

[Source](src/Helpers/Alias.php)

>  URL alias helpers for deploy hooks.

<details>
  <summary>Create a URL alias.<br/><code>create(string $path, string $alias, ?string $langcode = NULL, bool $skip_existing = TRUE): ?PathAliasInterface</code></summary>

```php
Helper::alias()->create('/node/1', '/about-us');
Helper::alias()->create('/node/2', '/a-propos', 'fr');
```

</details>

<details>
  <summary>Create multiple URL aliases.<br/><code>createMultiple(array $aliases): int</code></summary>

```php
Helper::alias()->createMultiple([
  ['path' => '/node/1', 'alias' => '/about-us'],
  ['path' => '/node/2', 'alias' => '/a-propos', 'langcode' => 'fr'],
]);
```

</details>

<details>
  <summary>Find an alias by system path.<br/><code>findByPath(string $path, ?string $langcode = NULL): ?PathAliasInterface</code></summary>

```php
$alias = Helper::alias()->findByPath('/node/1');
```

</details>

<details>
  <summary>Find an alias by its alias string.<br/><code>findByAlias(string $alias, ?string $langcode = NULL): ?PathAliasInterface</code></summary>

```php
$alias = Helper::alias()->findByAlias('/about-us');
```

</details>

<details>
  <summary>Rename the alias for a system path.<br/><code>updateByPath(string $path, string $alias, ?string $langcode = NULL): ?PathAliasInterface</code></summary>

```php
Helper::alias()->updateByPath('/node/1', '/about-us');
```

</details>

<details>
  <summary>Retarget an alias to a new system path.<br/><code>updateByAlias(string $alias, string $path, ?string $langcode = NULL): ?PathAliasInterface</code></summary>

```php
Helper::alias()->updateByAlias('/about-us', '/node/5');
```

</details>

<details>
  <summary>Delete aliases by system path.<br/><code>deleteByPath(string $path, ?string $langcode = NULL): int</code></summary>

```php
Helper::alias()->deleteByPath('/node/1');
```

</details>

<details>
  <summary>Delete aliases by their alias string.<br/><code>deleteByAlias(string $alias, ?string $langcode = NULL): int</code></summary>

```php
Helper::alias()->deleteByAlias('/about-us');
```

</details>

<details>
  <summary>Delete all URL aliases.<br/><code>deleteAll(): ?string</code></summary>

```php
Helper::alias()->deleteAll();
```

</details>

<details>
  <summary>Import URL aliases from a CSV file.<br/><code>importFromCsv(string $file_path): ?string</code></summary>

```php
Helper::alias()->importFromCsv('/path/to/aliases.csv');

// With sandbox for large files:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::alias($sandbox)->importFromCsv('/path/to/aliases.csv');
}
```

</details>


### Block

[Source](src/Helpers/Block.php)

>  Block placement and block content helpers for deploy hooks.

<details>
  <summary>Place a plugin block into a theme region.<br/><code>place(string $plugin_id, string $theme, string $region, array $options = [], bool $skip_existing = TRUE): ?EntityInterface</code></summary>

```php
Helper::block()->place('system_powered_by_block', 'olivero', 'footer', [
  'weight' => 10,
]);

// With visibility conditions:
Helper::block()->place('system_branding_block', 'olivero', 'header', [
  'visibility' => [
    'request_path' => [
      'id' => 'request_path',
      'pages' => '/admin/*',
      'negate' => TRUE,
    ],
  ],
]);
```

</details>

<details>
  <summary>Place multiple plugin blocks.<br/><code>placeMultiple(array $blocks): int</code></summary>

```php
Helper::block()->placeMultiple([
  [
    'plugin' => 'system_powered_by_block',
    'theme' => 'olivero',
    'region' => 'footer',
  ],
  [
    'plugin' => 'system_branding_block',
    'theme' => 'olivero',
    'region' => 'header',
    'options' => ['weight' => -10],
  ],
]);
```

</details>

<details>
  <summary>Remove a placed block.<br/><code>remove(string $id): bool</code></summary>

```php
Helper::block()->remove('olivero_system_powered_by_block');
```

</details>

<details>
  <summary>Create a block content entity.<br/><code>createContent(string $bundle, array $values = [], bool $skip_existing = TRUE): ?EntityInterface</code></summary>

```php
Helper::block()->createContent('basic', [
  'info' => 'Footer contact',
  'body' => 'Call us on 1234',
]);
```

</details>

<details>
  <summary>Create multiple block content entities.<br/><code>createContentMultiple(array $blocks): int</code></summary>

```php
Helper::block()->createContentMultiple([
  ['type' => 'basic', 'info' => 'Footer contact', 'body' => 'Call us'],
  ['type' => 'basic', 'info' => 'Opening hours', 'body' => '9am - 5pm'],
]);
```

</details>

<details>
  <summary>Delete block content by info label.<br/><code>deleteContent(string $info, ?string $bundle = NULL): int</code></summary>

```php
Helper::block()->deleteContent('Footer contact');
Helper::block()->deleteContent('Footer contact', 'basic');
```

</details>


### Config

[Source](src/Helpers/Config.php)

>  Configuration helpers for deploy hooks.

<details>
  <summary>Set a value in a configuration object, optionally guarded.<br/><code>set(string $config_name, string $key, mixed $value, mixed $expected = self::NO_EXPECTED): string</code></summary>

```php
// Unconditional write:
Helper::config()->set('system.site', 'name', 'My Site');
// Guarded - applies only while the live value is still 'Old Name':
return Helper::config()->set('system.site', 'name', 'New Name', 'Old Name');
```

</details>

<details>
  <summary>Get a value from a configuration object.<br/><code>get(string $config_name, string $key): mixed</code></summary>

```php
$site_name = Helper::config()->get('system.site', 'name');
```

</details>

<details>
  <summary>Delete a configuration object.<br/><code>delete(string $config_name): void</code></summary>

```php
Helper::config()->delete('my_module.settings');
```

</details>

<details>
  <summary>Import a config from a module's config/install directory.<br/><code>import(string $module, string $config_name, string $subdirectory = 'install'): void</code></summary>

```php
Helper::config()->import('my_module', 'views.view.my_view');
Helper::config()->import('my_module', 'node.type.page', 'optional');
```

</details>

<details>
  <summary>Import multiple configs from a module.<br/><code>importMultiple(string $module, array $config_names, string $subdirectory = 'install'): void</code></summary>

```php
Helper::config()->importMultiple('my_module', [
  'views.view.my_view',
  'field.storage.node.field_custom',
]);
```

</details>

<details>
  <summary>Set the site front page.<br/><code>setFrontPage(string $path): void</code></summary>

```php
Helper::config()->setFrontPage('/node/1');
```

</details>


### Display

[Source](src/Helpers/Display.php)

>  Entity form and view display helpers for deploy hooks.

<details>
  <summary>Set a widget component on an entity form display.<br/><code>setFormComponent(string $entity_type, string $bundle, string $mode, string $field_name, array $options = []): EntityDisplayInterface</code></summary>

```php
Helper::display()->setFormComponent('node', 'article', 'default', 'field_subtitle', [
  'type' => 'string_textfield',
  'weight' => 5,
]);
```

</details>

<details>
  <summary>Set a formatter component on an entity view display.<br/><code>setViewComponent(string $entity_type, string $bundle, string $mode, string $field_name, array $options = []): EntityDisplayInterface</code></summary>

```php
Helper::display()->setViewComponent('node', 'article', 'teaser', 'field_subtitle', [
  'type' => 'string',
  'label' => 'hidden',
  'weight' => 5,
]);
```

</details>

<details>
  <summary>Hide a component on an entity form display.<br/><code>hideFormComponent(string $entity_type, string $bundle, string $mode, string $field_name): EntityDisplayInterface</code></summary>

```php
Helper::display()->hideFormComponent('node', 'article', 'default', 'field_subtitle');
```

</details>

<details>
  <summary>Hide a component on an entity view display.<br/><code>hideViewComponent(string $entity_type, string $bundle, string $mode, string $field_name): EntityDisplayInterface</code></summary>

```php
Helper::display()->hideViewComponent('node', 'article', 'teaser', 'field_subtitle');
```

</details>


### Entity

[Source](src/Helpers/Entity.php)

>  Entity helpers for deploy hooks.

<details>
  <summary>Create an entity of a given type and bundle.<br/><code>create(string $entity_type, string $bundle, array $values, ?string $identity = NULL): EntityInterface</code></summary>

```php
Helper::entity()->create('node', 'article', [
  'title' => 'Welcome',
  'body' => 'Hello world',
]);

// Skip re-creating an entity that already has the same identity value:
Helper::entity()->create('node', 'article', [
  'title' => 'Welcome',
], identity: 'title');
```

</details>

<details>
  <summary>Create multiple entities with optional sandbox batching.<br/><code>createMultiple(string $entity_type, string $bundle, array $rows, ?string $identity = NULL): ?string</code></summary>

```php
$rows = [
  ['title' => 'Page one'],
  ['title' => 'Page two'],
];
Helper::entity()->createMultiple('node', 'article', $rows, identity: 'title');

// With sandbox for large datasets:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::entity($sandbox)->createMultiple('node', 'article', $rows, identity: 'title');
}
```

</details>

<details>
  <summary>Update entities matched by a set of properties.<br/><code>update(string $entity_type, array $properties, array $values): ?string</code></summary>

```php
Helper::entity()->update('node', ['type' => 'article'], ['status' => 0]);

// With sandbox for large datasets:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::entity($sandbox)->update('node', ['type' => 'article'], ['status' => 0]);
}
```

</details>

<details>
  <summary>Delete all entities of a given type and optional bundle.<br/><code>deleteAll(string $entity_type, ?string $bundle = NULL): ?string</code></summary>

```php
Helper::entity()->deleteAll('node', 'article');

// With sandbox for large datasets:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::entity($sandbox)->deleteAll('node', 'article');
}
```

</details>

<details>
  <summary>Process entities matching an entity query with optional sandbox batching.<br/><code>batchQuery(QueryInterface $query, callable $callback, bool $continue_on_error = FALSE, ?string $status = Reporter::PROCESSED): ?string</code></summary>

```php
// Migrate a value on every legacy article, tolerating per-item failures:
function my_module_deploy_001(array &$sandbox): ?string {
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->condition('field_legacy', 1);
  return Helper::entity($sandbox)->batchQuery($query, function ($node): void {
    $node->set('field_migrated', TRUE);
    $node->save();
  }, continue_on_error: TRUE);
}
```

</details>

<details>
  <summary>Set a field value on every entity matching an entity query.<br/><code>batchSetField(QueryInterface $query, string $field_name, mixed $value, bool $continue_on_error = FALSE): ?string</code></summary>

```php
// Archive every article:
function my_module_deploy_001(array &$sandbox): ?string {
  $query = \Drupal::entityQuery('node')->condition('type', 'article');
  return Helper::entity($sandbox)->batchSetField($query, 'field_status', 'archived');
}
```

</details>


### Field

[Source](src/Helpers/Field.php)

>  Field helpers for deploy hooks.

<details>
  <summary>Create a field storage and instance on a bundle from a settings array.<br/><code>create(string $entity_type, string $bundle, string $field_name, array $settings): FieldConfigInterface</code></summary>

```php
Helper::field()->create('node', 'article', 'field_subtitle', [
  'type' => 'string',
  'label' => 'Subtitle',
]);
```

</details>

<details>
  <summary>Attach an existing field storage to one or more additional bundles.<br/><code>attachToBundles(string $field_name, string $entity_type, array $bundles): array</code></summary>

```php
Helper::field()->attachToBundles('field_subtitle', 'node', ['page', 'landing']);
```

</details>

<details>
  <summary>Delete a field from all entity bundles and purge its data.<br/><code>delete(string $field_name): void</code></summary>

```php
Helper::field()->delete('field_subtitle');
```

</details>

<details>
  <summary>Delete a field instance from a specific entity bundle.<br/><code>deleteInstance(string $field_name, string $entity_type, string $bundle): void</code></summary>

```php
Helper::field()->deleteInstance('field_subtitle', 'node', 'article');
```

</details>


### Menu

[Source](src/Helpers/Menu.php)

>  Menu link helpers for deploy hooks.

<details>
  <summary>Create menu links from a nested tree structure.<br/><code>createTree(string $menu_name, array $tree, string $mode = self::MODE_SAFE): array</code></summary>

```php
$tree = [
  'Home' => '/',
  'About' => [
    'path' => '/about',
    'children' => [
      'Team' => '/about/team',
      'Contact' => '/about/contact',
    ],
  ],
  'External' => 'https://example.com',
];
Helper::menu()->createTree('main', $tree);

// Reconcile: re-apply the tree to existing links and delete any not listed.
Helper::menu()->createTree('main', $tree, mode: Menu::MODE_SYNC);
```

</details>

<details>
  <summary>Export a menu to the nested tree accepted by createTree().<br/><code>exportTree(string $menu_name, string $format = self::FORMAT_ARRAY): array|string</code></summary>

```php
// Snapshot structure as data:
$tree = Helper::menu()->exportTree('main');

// Render as ready-to-paste PHP or YAML:
$php = Helper::menu()->exportTree('main', Menu::FORMAT_PHP);
$yaml = Helper::menu()->exportTree('main', Menu::FORMAT_YAML);
```

</details>

<details>
  <summary>Delete all menu links from a menu.<br/><code>deleteTree(string $menu_name): ?string</code></summary>

```php
Helper::menu()->deleteTree('main');
```

</details>

<details>
  <summary>Find a menu link by properties.<br/><code>findItem(string $menu_name, array $properties): ?MenuLinkContentInterface</code></summary>

```php
$link = Helper::menu()->findItem('main', ['title' => 'About']);
```

</details>

<details>
  <summary>Update properties on an existing menu link found by properties.<br/><code>updateItem(string $menu_name, array $find_properties, array $updates): ?MenuLinkContentInterface</code></summary>

```php
Helper::menu()->updateItem('main', ['title' => 'About'], [
  'path' => '/about-us',
  'weight' => 5,
]);
```

</details>


### Module

[Source](src/Helpers/Module.php)

>  Module install and uninstall helpers for deploy hooks.

<details>
  <summary>Install a module and its dependencies.<br/><code>install(string $module): string</code></summary>

```php
Helper::module()->install('pathauto');
```

</details>

<details>
  <summary>Uninstall a module.<br/><code>uninstall(string $module, ?callable $callback = NULL): string</code></summary>

```php
Helper::module()->uninstall('legacy_feature');

// Orphaned module (code removed, still in the database):
Helper::module()->uninstall('ghost_module', function (string $module): void {
  \Drupal::database()->schema()->dropTable('ghost_module_data');
});
```

</details>


### Redirect

[Source](src/Helpers/Redirect.php)

>  Redirect helpers for deploy hooks.

<details>
  <summary>Create a redirect.<br/><code>create(string $source_path, string $target_path, int $status_code = 301, bool $skip_existing = TRUE, ?string $langcode = NULL): mixed</code></summary>

```php
Helper::redirect()->create('old-page', '/new-page');
Helper::redirect()->create('legacy', 'https://example.com', 302);
Helper::redirect()->create('vieux', '/nouveau', 301, TRUE, 'fr');
```

</details>

<details>
  <summary>Create multiple redirects.<br/><code>createMultiple(array $redirects): int</code></summary>

```php
Helper::redirect()->createMultiple([
  ['source' => 'old-page', 'target' => '/new-page'],
  ['source' => 'legacy', 'target' => 'https://example.com', 'status_code' => 302],
]);
```

</details>

<details>
  <summary>Delete redirects by source path.<br/><code>deleteBySource(string $source_path): int</code></summary>

```php
Helper::redirect()->deleteBySource('old-page');
```

</details>

<details>
  <summary>Delete all redirect entities.<br/><code>deleteAll(): ?string</code></summary>

```php
Helper::redirect()->deleteAll();
```

</details>

<details>
  <summary>Export all redirects to a CSV file.<br/><code>exportToCsv(string $file_path): string</code></summary>

```php
Helper::redirect()->exportToCsv('/path/to/redirects.csv');
```

</details>

<details>
  <summary>Import redirects from a CSV file.<br/><code>importFromCsv(string $file_path): ?string</code></summary>

```php
Helper::redirect()->importFromCsv('/path/to/redirects.csv');

// With sandbox for large files:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::redirect($sandbox)->importFromCsv('/path/to/redirects.csv');
}
```

</details>

<details>
  <summary>Delete redirects listed in a CSV file.<br/><code>deleteFromCsv(string $file_path): ?string</code></summary>

```php
Helper::redirect()->deleteFromCsv('/path/to/remove.csv');

// With sandbox for large files:
function my_module_deploy_001(array &$sandbox): ?string {
  return Helper::redirect($sandbox)->deleteFromCsv('/path/to/remove.csv');
}
```

</details>


### Role

[Source](src/Helpers/Role.php)

>  Role and permission helpers for deploy hooks.

<details>
  <summary>Create a user role.<br/><code>create(string $id, string $label): RoleInterface</code></summary>

```php
Helper::role()->create('editor', 'Editor');
```

</details>

<details>
  <summary>Delete a user role.<br/><code>delete(string $id): void</code></summary>

```php
Helper::role()->delete('editor');
```

</details>

<details>
  <summary>Grant permissions to a role.<br/><code>grantPermissions(string $id, array $permissions): RoleInterface</code></summary>

```php
Helper::role()->grantPermissions('editor', [
  'access content overview',
  'edit any article content',
]);
```

</details>

<details>
  <summary>Revoke permissions from a role.<br/><code>revokePermissions(string $id, array $permissions): RoleInterface</code></summary>

```php
Helper::role()->revokePermissions('editor', ['edit any article content']);
```

</details>


### Term

[Source](src/Helpers/Term.php)

>  Taxonomy term helpers for deploy hooks.

<details>
  <summary>Create terms from a nested tree structure.<br/><code>createTree(string $vocabulary, array $tree, string $mode = self::MODE_SAFE): array</code></summary>

```php
// Flat list:
Helper::term()->createTree('tags', ['News', 'Events', 'Blog']);

// Nested hierarchy:
Helper::term()->createTree('topics', [
  'Finance' => [
    'Budgets',
    'Grants',
  ],
  'Governance' => [
    'Policy' => [
      'Internal',
      'External',
    ],
    'Compliance',
  ],
  'Operations',
]);

// Reconcile: re-apply the tree to existing terms and delete any not listed.
$tree = ['Finance' => ['Budgets', 'Grants'], 'Operations'];
Helper::term()->createTree('topics', $tree, mode: Term::MODE_SYNC);
```

</details>

<details>
  <summary>Export a vocabulary to the nested tree accepted by createTree().<br/><code>exportTree(string $vocabulary, string $format = self::FORMAT_ARRAY): array|string</code></summary>

```php
// Snapshot structure as data:
$tree = Helper::term()->exportTree('topics');

// Render as ready-to-paste PHP or YAML:
$php = Helper::term()->exportTree('topics', Term::FORMAT_PHP);
$yaml = Helper::term()->exportTree('topics', Term::FORMAT_YAML);
```

</details>

<details>
  <summary>Delete all terms from a vocabulary.<br/><code>deleteAll(string $vocabulary): ?string</code></summary>

```php
Helper::term()->deleteAll('tags');
```

</details>

<details>
  <summary>Find a term by name in a vocabulary.<br/><code>find(string $name, ?string $vocabulary = NULL): ?TermInterface</code></summary>

```php
$term = Helper::term()->find('News', 'tags');
```

</details>


### Translation

[Source](src/Helpers/Translation.php)

>  Interface translation helpers for deploy hooks.

<details>
  <summary>Add or update the translation of a source string for a language.<br/><code>set(string $langcode, string $source, string $translation, string $context = ''): void</code></summary>

```php
Helper::translation()->set('fr', 'Submit', 'Envoyer');
// Disambiguate a source string that carries a context:
Helper::translation()->set('fr', 'May', 'Mai', 'Long month name');
```

</details>


### User

[Source](src/Helpers/User.php)

>  User helpers for deploy hooks.

<details>
  <summary>Create a user account.<br/><code>create(string $email, array $roles = [], array $fields = []): UserInterface</code></summary>

```php
Helper::user()->create('admin@example.com', ['administrator']);
Helper::user()->create('editor@example.com', ['editor'], [
  'name' => 'editor1',
  'status' => 1,
]);
```

</details>

<details>
  <summary>Create multiple user accounts.<br/><code>createMultiple(array $emails, array $roles = [], array $fields = []): array</code></summary>

```php
Helper::user()->createMultiple([
  'user1@example.com',
  'user2@example.com',
], ['editor']);
```

</details>

<details>
  <summary>Assign roles to an existing user.<br/><code>assignRoles(string $user_identifier, array $roles): void</code></summary>

```php
Helper::user()->assignRoles('admin@example.com', ['administrator']);
```

</details>

<details>
  <summary>Remove roles from an existing user.<br/><code>removeRoles(string $user_identifier, array $roles): void</code></summary>

```php
Helper::user()->removeRoles('admin@example.com', ['administrator']);
```

</details>



[//]: # (END)

## Local development

1. Install PHP with SQLite support, Composer and [Ahoy](https://github.com/ahoy-cli/ahoy)
2. Clone this repository
3. Run `ahoy build`

## Building website

`ahoy build` assembles the codebase, starts the PHP server and provisions the
Drupal website with this extension enabled. These operations are executed using
scripts within [`.devtools`](.devtools) directory. CI uses the same scripts to
build and test this extension.

The resulting codebase is then placed in the `build` directory. The extension
files are symlinked into the Drupal site structure.

The `build` command is a wrapper for more granular commands:
```bash
ahoy assemble     # Assemble the codebase
ahoy start        # Start the PHP server
ahoy provision    # Provision the Drupal website
```

The `provision` command is useful for re-installing the Drupal website without
re-assembling the codebase.

### Drupal versions

The Drupal version used for the codebase assembly is determined by the
`DRUPAL_VERSION` variable and defaults to the latest stable version.

You can specify a different version by setting the `DRUPAL_VERSION` environment
variable before running the `ahoy build` command:

```bash
DRUPAL_VERSION=11 ahoy build        # Drupal 11
DRUPAL_VERSION=11@alpha ahoy build  # Drupal 11 alpha
DRUPAL_VERSION=10@beta ahoy build   # Drupal 10 beta
DRUPAL_VERSION=11.1 ahoy build      # Drupal 11.1
```

The `minimum-stability` setting in the `composer.json` file is
automatically adjusted to match the specified Drupal version's stability.

### Patching dependencies

To apply patches to the dependencies, add a patch to the `extra.patches` section of `composer.dev.json`. Local patches are sourced from the `patches` directory.

### Providing `GITHUB_TOKEN`

To overcome GitHub API rate limits, you may provide a `GITHUB_TOKEN` environment
variable with a personal access token.

### Provisioning the website

The `provision` command installs the Drupal website from the `standard`
profile with the extension (and any `suggest`'ed extensions) enabled. The
profile can be changed by setting the `DRUPAL_PROFILE` environment variable.

The website will be available at http://localhost:8000 by default. The
hostname can be changed by setting the `WEBSERVER_HOST` environment variable.

The `WEBSERVER_PORT` is resolved with the following precedence:

1. **`WEBSERVER_PORT` exported in the shell** - used as-is. Useful for one-off
   runs: `WEBSERVER_PORT=9000 ahoy build`.
2. **`WEBSERVER_PORT` line in the project-root `.env` file** - used as-is.
   The `start` script does not modify `.env` when this entry is already
   present, so the same port is reused across `start`, `stop`, `provision`,
   `drush` and `login` commands.
3. **Neither is set** - the `start` script discovers the first free port in
   the range `8000-8099` and writes it to `.env` as `WEBSERVER_PORT=NNNN`.
   Subsequent commands read this value from `.env`.

To force re-discovery, delete `.env` (or just the `WEBSERVER_PORT` line in
it) and re-run `ahoy start`.

An SQLite database is created in `/tmp/site_drupal_helpers.sqlite` file.
You can browse the contents of the created SQLite database using
[DB Browser for SQLite](https://sqlitebrowser.org/).

A one-time login link will be printed to the console.

### Step-debugging with XDebug

PHP step-debugging is supported via [XDebug](https://xdebug.org/docs/install). Install the XDebug PHP extension on your host (`php -v` should mention `with Xdebug`), then toggle it on the development server:

```bash
ahoy debug      # restart with XDebug enabled (aliases: debug-on, xdebug, xdebug-on)
ahoy start      # restart without XDebug (aliases: debug-off, xdebug-off)
```

The `debug` command probes the running PHP server's command line for `xdebug.mode=debug` and skips the restart if XDebug is already enabled. Code coverage stays on [pcov](https://github.com/krakjoe/pcov) because `xdebug.mode=debug` does not include `coverage`.

To start and stop debug sessions from the browser, install the Xdebug Helper extension: [Chrome](https://chromewebstore.google.com/detail/xdebug-helper-by-jetbrain/aoelhdemabeimdhedkidlnbkfhnhgnhm) / [Firefox](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-by-jetbrains/).

## Coding standards

The `ahoy lint` command checks the codebase using multiple tools:
- PHP code standards checking against `Drupal` and `DrupalPractice` standards.
- PHP code static analysis with PHPStan.
- PHP deprecated code analysis and auto-fixing with Drupal Rector.
- Twig code analysis with Twig CS Fixer.
- README API reference freshness check with `php docs.php --fail-on-change`.

The configuration files for these tools are located in the root of the codebase.

### Fixing coding standards issues

To fix coding standards issues automatically, run `ahoy lint-fix`. This runs
the same tools as `lint` command but with the `--fix` option (for the tools
that support it).

## Testing

The `ahoy test` command runs the PHPUnit tests for this extension.

The tests are located in the `tests/src` directory. The `phpunit.xml` file
configures PHPUnit to run the tests. It uses Drupal core's bootstrap file
`core/tests/bootstrap.php` to bootstrap the Drupal environment before running
the tests.

The `test` command is a wrapper for multiple test commands:
```bash
ahoy test-unit                    # Run Unit tests
ahoy test-kernel                  # Run Kernel tests
ahoy test-functional              # Run Functional tests
ahoy test-functional-javascript   # Run FunctionalJavascript tests
```

### Running FunctionalJavascript tests

FunctionalJavascript tests require a browser controlled via WebDriver.

```bash
ahoy selenium-start
WEBSERVER_HOST=0.0.0.0 ahoy start
ahoy provision
ahoy test-functional-javascript
ahoy selenium-stop
```

### Running specific tests

You can run specific tests by passing a path to the test file or PHPUnit CLI
option (`--filter`, `--group`, etc.) to the `ahoy test` command:

```bash
ahoy test-unit tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group=wip
```

You may also run tests using the `phpunit` command directly:

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
```

## Updating the scaffold

To pull the latest development-environment infrastructure from the template into this project, ask Claude Code to "update scaffold" - see [`AGENTS.md`](AGENTS.md) for details.

---
_This repository was created using the [Drupal Extension Scaffold](https://github.com/AlexSkrypnyk/drupal_extension_scaffold) project template_

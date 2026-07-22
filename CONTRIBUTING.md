# Contributing

Thank you for considering a contribution to Drupal Helpers.

## Reporting issues

Use the [GitHub issue queue](https://github.com/AlexSkrypnyk/drupal_helpers/issues) to report bugs and request features. Include reproduction steps and the Drupal and PHP versions you are running.

## Development setup

1. Install PHP with SQLite support, Composer and [Ahoy](https://github.com/ahoy-cli/ahoy).
2. Fork and clone this repository.
3. Run `ahoy build` to assemble the codebase into `build/`, start the PHP server and provision a Drupal site with the module enabled.

See the [Local development](README.md#local-development) section of the README for the full command reference.

## Making changes

1. Create a feature branch off `2.x`.
2. Make changes in `src/` and cover them with tests in `tests/src/`.
3. Regenerate the README API reference if helper docblocks changed: `php docs.php`.
4. Check coding standards: `ahoy lint`.
5. Run the tests: `ahoy test`.
6. Open a pull request against the `2.x` branch and fill in the pull request template.

Pull requests are tested against PHP 8.2-8.5 and Drupal 10-11 in CI.

# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

This is a Drupal Helpers template for creating contributed modules or themes. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.

## Development Commands

**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper `chdir`s into `build/` and runs the tool with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.

Run each tool through its `ahoy` wrapper, never the binary directly:

- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.

### Build and Environment Management

**Using Ahoy:**
- `ahoy build` - Complete build (stop → assemble → start → provision)
- `ahoy assemble` - Assemble codebase with dependencies
- `ahoy start` - Start PHP development server
- `ahoy stop` - Stop development server
- `ahoy provision` - Install/provision Drupal site
- `ahoy reset` - Clean build directory and logs

### Code Quality

**Linting:**
- `ahoy lint` - Run all linting tools (phpcs, phpstan, rector dry-run, twig-cs-fixer, docs check)
- `ahoy lint-fix` - Auto-fix coding standards violations

**Testing:**
- `ahoy test` - Run all PHPUnit tests
- `ahoy test-unit` - Run unit tests only
- `ahoy test-kernel` - Run kernel tests only
- `ahoy test-functional` - Run functional tests only
- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (uses the local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `ahoy browser-start` - Start the browser for FunctionalJavascript tests (local Chrome by default; set `WEBDRIVER_BACKEND=selenium` for Docker)
- `ahoy browser-stop` - Stop the browser

### Drupal Commands

- `ahoy drush <command>` - Run Drush commands
- `ahoy login` - Get one-time login link

### Diagnostics

- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node versions, webserver host/port (with source), XDebug state, build directory, database path, and active profile. (alias: `ahoy describe`)

## Project Structure

**Key Directories:**
- `src/` - Extension source code (services, helpers, traits)
- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
- `build/` - Assembled Drupal codebase (symlinked extension)
- `.devtools/` - Build and deployment scripts used by CI

## Architecture

- **Service-based architecture**: Main functionality in services registered via `*.services.yml`
- **Static facade**: `Helper::term()`, `Helper::config()`, etc. for clean deploy hook usage
- **Batch processing**: Helpers accept a `$sandbox` array for batched operations
- **Test coverage**: Unit, Kernel and Functional test examples provided

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`)
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
- `WEBDRIVER_BACKEND` - FunctionalJavascript WebDriver backend: `chromedriver` (default, drives the locally installed Chrome with no Docker) or `selenium` (Docker container)
- `WEBDRIVER_PORT` - Port for the WebDriver endpoint (both backends). Auto-discovered from 4444 and written to `.env` if not already set, so several projects can run FunctionalJavascript tests simultaneously
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits
- `DEBUG` - Set to `1` to stream the full output of the underlying commands (Composer, npm, Drush). By default this output is suppressed and shown only when a command fails

## Development Workflow

1. Build environment: `ahoy build`
2. Develop helpers code in `src/`
3. Regenerate API docs in README: `php docs.php`
4. Check standards: `ahoy lint`
5. Run tests: `ahoy test`
6. Access site at http://localhost:8000

## Code Quality Tools

- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes
- **Twig CS Fixer**: Twig template formatting

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` and deployment
- **Matrix testing**: PHP 8.3-8.5, Drupal 10-11
- **Automated deployment**: Mirror to Drupal.org on release

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/`
- SQLite database created in `/tmp/site_drupal_helpers.sqlite`
- All quality tools run from within `build/` directory
- The `docs.php` script generates API reference documentation in the README from helper class docblocks. The lint step verifies the README is up to date with `php docs.php --fail-on-change`

## Updating the scaffold

When the user asks to update this project's scaffold (e.g. "update scaffold"), fetch the update skill from GitHub into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-drupal-extension-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/drupal_extension_scaffold/1.x/.scaffold/skills/update-consumer-drupal-extension-scaffold/SKILL.md -o .claude/skills/update-consumer-drupal-extension-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-drupal-extension-scaffold` skill and follow its steps.

The skill directory is git-ignored - it is fetched on demand and not committed to the project.

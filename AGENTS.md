# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

This is a Drupal Helpers template for creating contributed modules or themes. The project provides a complete development environment with CI configuration, testing setup, and deployment automation for publishing to Drupal.org.

## Development Commands

### Build and Environment Management

**Using Ahoy:**
- `ahoy build` - Complete build process
- `ahoy assemble` - Assemble codebase
- `ahoy start` - Start development server
- `ahoy provision` - Provision Drupal site

### Code Quality

**Linting:**
- `ahoy lint` - Run all linting tools (phpcs, phpstan, rector dry-run)
- `ahoy lint-fix` - Auto-fix coding standards violations

**Testing:**
- `ahoy test` - Run all PHPUnit tests
- `ahoy test-unit` - Run unit tests only
- `ahoy test-kernel` - Run kernel tests only
- `ahoy test-functional` - Run functional tests only
- `ahoy test-functional-javascript` - Run FunctionalJavascript tests (requires Selenium)

### Drupal Commands

- `ahoy drush <command>` - Run Drush commands
- `ahoy login` - Get one-time login link

## Project Structure

**Key Directories:**
- `src/` - Extension source code (services, forms, etc.)
- `tests/src/` - PHPUnit tests (Unit/, Kernel/, Functional/)
- `config/schema/` - Configuration schema definitions
- `build/` - Assembled Drupal codebase (symlinked extension)
- `.devtools/` - Build and deployment scripts used by CI

**Template Files (before init):**
- `drupal_helpers.*` - Template extension files
- `DrupalHelpersService.php` - Main service class template

## Architecture

- **Service-based architecture**: Main functionality in services registered via `*.services.yml`
- **Configuration-driven**: Uses Drupal configuration system with schema validation
- **Test coverage**: Unit, kernel, and functional test examples provided
- **Form integration**: Admin forms in `src/Form/` for configuration

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`)
- `DRUPAL_PROJECT_REPO` - Custom drupal-project fork URL
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port (default: 8000)
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits

## Development Workflow

1. Run `php init.php` to customize template for Drupal Helpers
2. Build environment: `ahoy build`
3. Develop Drupal Helpers code in `src/`
4. Check standards: `ahoy lint`
5. Run tests: `ahoy test`
6. Access site at http://localhost:8000

## Code Quality Tools

- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` and deployment
- **CircleCI**: `.circleci/config.yml` configuration
- **Matrix testing**: PHP 8.2-8.4, Drupal 10-11
- **Automated deployment**: Mirror to Drupal.org on release

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/`
- SQLite database created in `/tmp/site_drupal_helpers.sqlite`
- All quality tools run from within `build/` directory

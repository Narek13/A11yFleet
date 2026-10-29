# CLAUDE.md

A11yFleet — a standalone WordPress plugin that scans content for accessibility issues (WCAG 2.1 AA / EU Accessibility Act / EN 301 549), scores pages, and guides remediation. Published on WordPress.org (https://wordpress.org/plugins/a11yfleet); the GitHub repo is public.

## Rules
- **Never auto-commit.** Make edits and stop; Narek creates commits himself.
- **Public, neutral dependencies only.** This is a private/personal plugin: no private-registry packages, proprietary packages, or employer-internal tooling. Use publicly available equivalents.
- **wp.org-bound:** follow WordPress Coding Standards (PHPCS config in `phpcs.xml.dist`) and wp.org plugin guidelines — prefix globals, escape output, sanitize input, no remote code loading.

## Architecture
Modern, namespaced PSR-4 (`A11yFleet\` → `src/`). No framework. Tiny service locator in `src/Plugin.php`. Custom DB tables (scans/issues), not postmeta. REST API under `/wp-json/a11yfleet/v1`. Scan engine is pure PHP (DOMDocument) — see `src/Scanner/`.

## Local dev
- **DDEV:** `ddev start` (first run downloads WP + activates the plugin via `.ddev/commands/setup.sh`). PHP 8.1. The plugin is mounted into `.ddev/wordpress/wp-content/plugins/a11yfleet`.
- Also a Local (Flywheel) site at `~/Local Sites/a11yfleet` for manual testing.
- **QA:** `composer install` then `composer test` (PHPCS + PHPStan + PHPUnit), or `npm run check`.
- Build admin assets: `npm install && npm run build` (outputs to `assets/`).

## Releasing
Pushing a `v*` tag builds, runs Plugin Check and deploys to WordPress.org SVN. See `RELEASING.md`.

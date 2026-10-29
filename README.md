# A11yFleet – Accessibility Checker for WCAG, ADA & EAA Compliance

A WordPress plugin that scans your posts and pages for accessibility issues
against **WCAG 2.1 level AA**, scores every page, shows exactly what to fix and
why, and generates a compliance report with a draft accessibility statement
for the **European Accessibility Act (EN 301 549)**.

Install it from WordPress.org: https://wordpress.org/plugins/a11yfleet/

## What it checks

Nine rules, each mapped to a WCAG 2.1 success criterion:

| Rule | WCAG |
| --- | --- |
| Images without alt text | 1.1.1 |
| Skipped heading levels | 1.3.1 |
| Empty headings | 1.3.1 |
| Form controls without a label | 1.3.1 |
| Non-descriptive link text | 2.4.4 |
| Missing document language | 3.1.1 |
| Duplicate IDs | 4.1.1 |
| Iframes without a title | 4.1.2 |
| Buttons without an accessible name | 4.1.2 |

Automated tools catch only part of WCAG. A11yFleet says so in the UI and never
claims a site is "100% compliant".

## Features

- **Dashboard** listing pages with issues, worst score first, with per-issue
  ignore / restore that persists across re-scans.
- **Block-editor panel** to scan the content you're editing (before saving) or
  the full rendered page, including theme header, navigation and footer.
- **Background full-site scan** in batches, optionally on a schedule (hourly,
  daily or weekly).
- **Compliance report** grouped by WCAG criterion, with an auto-generated
  accessibility-statement draft, printable to PDF.
- **REST API** under `/wp-json/a11yfleet/v1`.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Architecture

Namespaced PSR-4 (`A11yFleet\` → `src/`), no framework. The scan engine is pure
PHP (`DOMDocument`); results live in custom database tables rather than
postmeta. See [`docs/architecture.md`](docs/architecture.md) and the decision
records in [`adr/`](adr/).

## Development

```bash
composer install && npm ci
npm run build     # compiles src/Admin/resources → assets/
composer test     # PHPCS (WPCS) + PHPStan + PHPUnit
npm run check     # the above plus JS/CSS lint
```

A DDEV environment is included (`ddev start`). See [`SETUP.md`](SETUP.md).
Releases are automated, see [`RELEASING.md`](RELEASING.md).

## License

GPL-2.0-or-later

# 0003 — WordPress Coding Standards for wp.org

- **Status:** Accepted
- **Date:** 2026-06-20

## Context
The plugin is intended for distribution on WordPress.org, whose review process and ecosystem expect WordPress Coding Standards (WPCS). The codebase, however, is modern PSR-4 / namespaced OO, which conflicts with some WPCS conventions (file naming, snake_case identifiers).

## Decision
Base PHPCS on `WordPress-Extra` + `WordPress-Docs`, but exclude only the sniffs that fight PSR-4/namespaced code:
- `WordPress.Files.FileName` (allow `Foo.php`)
- `WordPress.NamingConventions.ValidVariableName` / `ValidFunctionName` (allow camelCase)

All security, escaping, sanitisation, i18n and prefixing rules — what the review team actually enforces — stay on. Indentation follows WPCS (tabs); `composer phpcs:fix` normalises it.

## Consequences
- Passes the substantive wp.org review checks while keeping modern OO style.
- Min PHP 8.1 (enums, readonly) — narrower install base, simpler code.
- Some `phpcs:fix` passes needed as the code grows; CI enforces it.

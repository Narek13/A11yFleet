# 0001 — Custom database tables instead of postmeta

- **Status:** Accepted
- **Date:** 2026-06-20

## Context
The scanner produces many rows (one scan per page, many issues per scan) that we query by score, severity, status and date — for dashboards and, later, an account-level "fleet" rollup across many sites. WordPress's default extensibility point, `postmeta`, is an unindexed key/value (EAV) store that performs poorly for this access pattern.

## Decision
Store scans and issues in dedicated, indexed custom tables (`{prefix}a11yfleet_scans`, `{prefix}a11yfleet_issues`), created via `dbDelta` on activation. Table names come from `$wpdb->prefix`; all user values go through `$wpdb->prepare()`.

## Consequences
- Fast, indexed queries by score/severity/date; clean rollups later.
- We own schema migrations (versioned via `a11yfleet_db_version`).
- PHPCS `WordPress.DB.*` sniffs flag direct queries — accepted and scoped out for `src/Storage/*` only, with justification.
- Not portable via standard WP export/import; acceptable for derived/regenerable scan data.

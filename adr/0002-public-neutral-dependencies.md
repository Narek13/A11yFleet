# 0002 — Public, neutral dependencies only

- **Status:** Accepted
- **Date:** 2026-06-20

## Context
This is a private, personally-owned plugin (not employer work). Depending on private package registries, proprietary packages, or any employer-internal tooling would create licensing/ownership ambiguity and tie the project to infrastructure outside this repository.

## Decision
Use only publicly available, neutral packages and self-contained CI:
- DI/bootstrap: a small in-repo service locator (no external modularity framework).
- Coding standards: `wp-coding-standards/wpcs`.
- PHPStan WP symbols: `szepeviktor/phpstan-wordpress`.
- CI: self-contained GitHub Actions (no third-party reusable workflows, no private registries).

## Consequences
- Zero dependency on private infrastructure; clean personal ownership.
- Slightly more configuration maintained by hand vs. inherited tooling.
- Keeps the path to WordPress.org distribution and a clear IP story open.

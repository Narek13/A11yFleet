# 0004 — DDEV for local development

- **Status:** Accepted
- **Date:** 2026-06-20

## Context
The plugin repo needs a reproducible local WordPress for development and testing, independent of any single machine's setup. The author already runs a Local (Flywheel) site for manual testing, but wants containerised parity (matching their other plugin projects) and a PHP 8.1 toolchain for PHPUnit/PHPCS regardless of the host.

## Decision
Provide a self-contained DDEV config (`.ddev/`). WordPress core is downloaded into `.ddev/wordpress` (gitignored) and the plugin repo root is volume-mounted into `wp-content/plugins/${DDEV_PROJECT}` via `.ddev/docker-compose.wp-plugin.yaml` — avoiding symlink recursion. A `post-start` hook (`.ddev/commands/setup.sh`) installs WP and activates the plugin.

## Consequences
- `ddev start` yields a ready dev site; reproducible across machines.
- Two valid dev paths (DDEV and the existing Local site) — documented in SETUP.md.
- `.ddev/` is excluded from the distributed plugin (`.distignore`).

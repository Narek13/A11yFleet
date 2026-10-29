# Architecture

A high-level map of the plugin. For *why* decisions were made, see `../adr/`. For setup/commands, see `../SETUP.md`.

## Layers

```
a11yfleet.php            Bootstrap: header, constants, autoloader, activation/boot hooks
src/  (PSR-4 A11yFleet\)
├── Plugin.php           Tiny service locator + boot()
├── Activation/          Activator (dbDelta install) · Deactivator
├── Scanner/             The engine (pure PHP, no WP needed)
│   ├── Engine.php       Parses HTML once, runs each Rule
│   ├── Rule.php         Rule contract
│   ├── Rules/           One class per WCAG check (image-alt, link-text, heading-order, …)
│   ├── Issue.php        A single finding (rule, WCAG ref, severity, message, fix)
│   ├── ScanResult.php   Issues + derived 0–100 score
│   ├── Severity.php     enum error/warning/notice (weighted)
│   └── ScanService.php  Renders content, runs the engine, persists; WP hooks
├── Storage/             Custom tables (Schema) + Scan/Issue repositories
├── Admin/               Menu + dashboard + asset enqueue
└── Rest/                REST API under /wp-json/a11yfleet/v1
templates/               Server-rendered admin views
src/Admin/resources/     JS/CSS source → built into assets/ by wp-scripts
```

## Flow

1. A post is published/updated → `save_post` → `ScanService::scanPost()`.
2. Content is rendered through `the_content` filters (what visitors actually see).
3. `Engine` parses the HTML once and asks every `Rule` for findings.
4. Findings become `Issue`s; a `ScanResult` computes the score.
5. Repositories persist the scan + issues (replacing the page's previous scan).
6. The dashboard and REST API read it back.

## Extending

- **New WCAG check:** add a class in `src/Scanner/Rules/` implementing `Rule`, register it in `Plugin::engine()`. See `.claude/commands/new-rule.md`.
- **New post types:** filter `a11yfleet_scannable_post_types`.

## Key principles
- The scan engine has no WordPress dependency (testable in isolation; see `tests/`).
- Honest reporting: automated checks cover only part of WCAG; the UI says so and never claims "100% compliant".

---
paths:
  - "*.php"
---

# PHP file conventions

- `declare(strict_types=1);` is mandatory at the top of every PHP file.
- PSR-4 namespace: `A11yFleet\` → `src/`. Class name must match file name.
- PHP 8.1 is the minimum — enums, readonly properties, match expressions, named arguments, and first-class callable syntax ARE available and preferred where they improve clarity.
- WordPress Coding Standards apply (`WordPress-Extra` + `WordPress-Docs`). PSR-4 file, variable, and method naming exceptions are already configured in `phpcs.xml.dist`; camelCase methods and class-per-file naming are fine.
- PHPStan runs at level 5 with `treatPhpDocTypesAsCertain: false` — type all parameters and return values. Docblocks are not a substitute for type declarations.
- Default to no docblock. Add one only to capture a non-obvious contract a signature cannot express (e.g., a hook callback shape, a filter's expected return type), or to point at an ADR.
- No `else` when an early return or `match` arm handles the prior case. Prefer guard clauses.
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- Use `$wpdb->prepare()` for every DB query that includes user-supplied values. Direct `$wpdb` queries are allowed only in `src/Storage/` and `uninstall.php`.
- Comments stay evergreen: describe the code as it stands. No PR numbers, ticket IDs, or "fix later" notes — those belong in commits and PRs.
- 4-space indentation. LF line endings.

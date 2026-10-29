# A11yFleet environment setup

Run everything from the plugin folder. Requires PHP 8.1+, Composer and Node 22.

## 1. Install dependencies

```bash
composer install      # PHP_CodeSniffer (WPCS), PHPStan (+WP stubs), PHPUnit, Brain Monkey
npm ci                # @wordpress/scripts (asset build)
```

## 2. Quality gates (what CI runs)

```bash
composer phpcs               # WordPress Coding Standards (wp.org-aligned)
composer phpcs:fix           # auto-fix what's fixable
composer phpstan             # static analysis (level 5)
composer phpunit             # unit tests (Brain Monkey, no WP install)
composer test                # phpcs + phpstan + phpunit
npm run check                # the above plus JS/CSS lint
```

The PHPCS config excludes only the sniffs that conflict with PSR-4/namespaced
code (file naming, camelCase) and keeps every security, escaping and i18n rule
the wp.org review team checks.

## 3. Build admin assets

```bash
npm run build        # compiles src/Admin/resources → assets/ (gitignored)
npm start            # watch mode while developing
```

## 4. Run WordPress with DDEV

```bash
ddev start           # downloads WP into .ddev/wordpress, mounts the plugin, activates it
ddev launch wp-admin # admin / admin
```

WP core lives in `.ddev/wordpress` (gitignored). The plugin repo is
volume-mounted in via `.ddev/docker-compose.wp-plugin.yaml`, so there's no
symlink recursion. The post-start hook (`.ddev/commands/setup.sh`) installs WP
and activates the plugin on first run.

`*.ddev.site` resolves to 127.0.0.1, so a phone can't reach it directly. To test
on a real device, use `ddev share` and point the site URL at the tunnel.

## Notes
- **Public, neutral dependencies only**: all tooling is publicly available.
- Min PHP 8.1 (uses enums/readonly), set in the plugin header and `composer.json`.

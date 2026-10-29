# Releasing

A11yFleet is published at https://wordpress.org/plugins/a11yfleet from the SVN
repository https://plugins.svn.wordpress.org/a11yfleet. Releases are automated:
pushing a `v*` tag publishes to SVN. Nobody commits to SVN by hand.

## One-time setup
Repository secrets on GitHub (Settings → Secrets and variables → Actions):
- `SVN_USERNAME`: `nareknovembit` (the case-sensitive wp.org username).
- `SVN_PASSWORD`: the SVN password from the "Account & Security" section of the
  WordPress.org profile (separate from the login password).

## Cutting a release
1. Bump the version in three places: the `Version:` header and `A11YFLEET_VERSION`
   in `a11yfleet.php`, and `Stable tag:` in `readme.txt`. Add a changelog entry.
2. Regenerate the translation template:
   `ddev exec wp i18n make-pot . languages/a11yfleet.pot --slug=a11yfleet --domain=a11yfleet --exclude=node_modules,vendor,.ddev,tests,assets,docs,adr,temp,dist`
3. `composer test` and `npm run check` green.
4. Commit, then `git tag vX.Y.Z && git push origin main vX.Y.Z`.

`.github/workflows/build-and-distribute.yml` then:
- fails if the three version numbers or the tag disagree;
- builds the admin assets (`npm ci && npm run build`);
- builds `dist/a11yfleet.zip` (`bin/build-zip.sh`; what is left out is defined
  once, in `.distignore`);
- runs Plugin Check on the built package;
- attaches the zip to the GitHub release;
- deploys the built package to SVN `trunk/` and `tags/X.Y.Z/`, and syncs
  `.wordpress-org/` to SVN `assets/`.

## Plugin page assets
`.wordpress-org/` holds exactly what goes into SVN `assets/`: icons, both
banners and `screenshot-1..4.png` (captions are `== Screenshots ==` in
readme.txt). The banner source is `docs/brand/banner.html`.

Changes to `readme.txt` or `.wordpress-org/` alone go out without a release:
pushing them to `main` runs `.github/workflows/wporg-readme-assets.yml`, which
updates the readme in SVN `trunk/` and the stable tag, and syncs `assets/`.
Do not bump the version for those.

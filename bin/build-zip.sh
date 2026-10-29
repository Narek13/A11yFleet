#!/usr/bin/env bash
#
# Builds dist/a11yfleet.zip for WordPress.org.
# Run `npm ci && npm run build` first: the compiled admin assets/ are not
# committed. What is left out is defined once, in .distignore.

set -euo pipefail

PLUGIN=a11yfleet
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"

if [ ! -f "$ROOT/assets/js/index.js" ] || [ ! -f "$ROOT/assets/js/editor.js" ]; then
	echo "Built assets missing. Run: npm ci && npm run build" >&2
	exit 1
fi

rm -rf "$DIST"
mkdir -p "$DIST/$PLUGIN"

rsync -a --exclude-from="$ROOT/.distignore" --exclude='/dist' --exclude='/vendor' --exclude='.DS_Store' --exclude='/.idea' \
	"$ROOT/" "$DIST/$PLUGIN/"

( cd "$DIST" && zip -qr "$PLUGIN.zip" "$PLUGIN" )

echo "Built $DIST/$PLUGIN.zip"
unzip -l "$DIST/$PLUGIN.zip"

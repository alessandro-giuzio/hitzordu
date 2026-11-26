#!/usr/bin/env bash
#
# Builds an installable WordPress plugin zip: dist/hitzordu-<version>.zip
#
# It builds the widget, copies only the files the plugin needs at runtime, installs
# production Composer dependencies (with a real copy of packages/core, not a symlink),
# checks the result, and zips it. Nothing in the working tree is modified.
#
# Usage: scripts/build-release.sh
# Needs: php, composer, node, npm, zip, rsync, python3

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/plugins/hitzordu"
CORE="$ROOT/packages/core"
WIDGET="$ROOT/packages/widget"
DIST="$ROOT/dist"
STAGE="$DIST/stage/hitzordu"

# The version comes from the plugin header, and must match the PHP constant.
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$PLUGIN/plugin.php" | head -n 1)"
CONSTANT="$(sed -n "s/^define( 'HITZORDU_VERSION', '\(.*\)' );/\1/p" "$PLUGIN/plugin.php" | head -n 1)"

if [ -z "$VERSION" ] || [ "$VERSION" != "$CONSTANT" ]; then
	echo "Version mismatch: header '$VERSION', HITZORDU_VERSION '$CONSTANT'." >&2
	exit 1
fi

ZIP="$DIST/hitzordu-$VERSION.zip"

echo "==> Building hitzordu $VERSION"

echo "==> Building the widget"
cd "$WIDGET"
if [ ! -d node_modules ]; then
	npm ci --no-audit --no-fund
fi
npm test --silent
npm run build --silent

echo "==> Staging plugin files"
rm -rf "$DIST/stage" "$ZIP"
mkdir -p "$STAGE"
cd "$PLUGIN"
rsync -a \
	--exclude '.DS_Store' \
	plugin.php uninstall.php README.txt LICENSE.txt composer.json \
	blocks languages public src \
	"$STAGE/"

if [ ! -f "$STAGE/public/js/hitzordu-widget.js" ]; then
	echo "The built widget is missing from public/js." >&2
	exit 1
fi

echo "==> Installing production dependencies"
# composer.json points at ../../packages/core, which does not exist from the stage,
# so point it at the real folder. The lock file is not copied: its only package is
# the local core library.
python3 - "$STAGE/composer.json" "$CORE" <<'PY'
import json, sys
path, core = sys.argv[1], sys.argv[2]
with open(path) as f:
    data = json.load(f)
for repo in data.get("repositories", []):
    if repo.get("type") == "path":
        repo["url"] = core
        repo.setdefault("options", {})["symlink"] = False
with open(path, "w") as f:
    json.dump(data, f, indent=2)
    f.write("\n")
PY

cd "$STAGE"
COMPOSER_MIRROR_PATH_REPOS=1 composer update \
	--no-dev --no-interaction --no-progress --no-scripts \
	--optimize-autoloader --classmap-authoritative

# Keep only what the plugin runs. The core library's tests and dev files stay out.
CORE_COPY="$STAGE/vendor/agencyga/hitzordu-core"
rm -rf "$CORE_COPY/tests" "$CORE_COPY/vendor" "$CORE_COPY/phpunit.xml" "$CORE_COPY/composer.lock"
rm -f "$STAGE/composer.lock"

# The zip must not carry this machine's paths. The plugin's composer.json is only
# needed to build, and installed.json records where the core library was copied from.
rm -f "$STAGE/composer.json"
python3 - "$STAGE/vendor/composer/installed.json" "$CORE" <<'PY'
import sys
path, core = sys.argv[1], sys.argv[2]
with open(path) as f:
    text = f.read()
with open(path, "w") as f:
    f.write(text.replace(core.replace("/", "\\/"), "packages/core").replace(core, "packages/core"))
PY

if grep -rqF "$ROOT" "$STAGE"; then
	echo "The staged plugin still contains this machine's paths:" >&2
	grep -rlF "$ROOT" "$STAGE" >&2
	exit 1
fi

echo "==> Checking the staged plugin"
while IFS= read -r file; do
	php -l "$file" > /dev/null
done < <(find "$STAGE" -name '*.php' -not -path '*/vendor/*')

php -r '
require $argv[1] . "/vendor/autoload.php";
foreach (["Hitzordu\\Core\\SlotGenerator", "Hitzordu\\Plugin\\Plugin", "Hitzordu\\Plugin\\Availability"] as $class) {
	if (!class_exists($class)) {
		fwrite(STDERR, "Class not found: $class\n");
		exit(1);
	}
}
' "$STAGE"

if [ -L "$CORE_COPY" ]; then
	echo "The core library is a symlink, not a copy." >&2
	exit 1
fi

echo "==> Zipping"
cd "$DIST/stage"
zip -qr "$ZIP" hitzordu -x '*.DS_Store'

echo "==> Done: $ZIP ($(du -h "$ZIP" | cut -f1))"
unzip -l "$ZIP" | tail -n 1

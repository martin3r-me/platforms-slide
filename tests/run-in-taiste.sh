#!/usr/bin/env bash
# Führt Slides-Tests in der lokalen taiste-Kopie aus, ohne taiste zu verändern:
# Testdateien in einen eigenen Unterordner kopieren, Provider-Cache in ein Temp-Verzeichnis, danach aufräumen.
# Aufruf: tests/run-in-taiste.sh <Test-Datei> [weitere …]   (Harness wird automatisch mitkopiert)
set -euo pipefail
MODULE="$(cd "$(dirname "$0")/.." && pwd)"
APP="${TAISTE_PATH:-/Users/martin3r/Platforms/instances/taiste}"
PHP="${PHP_BIN:-/opt/homebrew/opt/php@8.4/bin/php}"
RUN="sl_$$_$RANDOM"
TMP="$(mktemp -d)"
DEST="$APP/tests/Feature/$RUN"
mkdir -p "$DEST"
trap 'rm -rf "$DEST" "$TMP"' EXIT
cp "$MODULE/tests/Feature/SlidesTestHarness.php" "$DEST/"
for f in "$@"; do cp "$MODULE/$f" "$DEST/"; done
cd "$APP"
APP_PACKAGES_CACHE="$TMP/packages.php" APP_SERVICES_CACHE="$TMP/services.php" \
SLIDES_MODULE_PATH="$MODULE" DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  "$PHP" -d memory_limit=2G vendor/bin/phpunit "tests/Feature/$RUN" 2>&1 | grep -v "^/Users/.*vendor/" | tail -60

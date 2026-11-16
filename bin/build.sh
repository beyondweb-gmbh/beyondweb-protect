#!/usr/bin/env bash
# Baut die Release-ZIP: dist/beyondweb-protect-<version>.zip plus SHA-256-Datei.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION="${1:?Version angeben, z. B. 1.0.3}"

grep -Eq "^ \* Version:\s+${VERSION}$" beyondweb-protect/beyondweb-protect.php \
  || { echo "Version im Plugin-Kopf passt nicht zu ${VERSION}"; exit 1; }
grep -q "define( 'BW_PROTECT_VERSION', '${VERSION}' );" beyondweb-protect/beyondweb-protect.php \
  || { echo "BW_PROTECT_VERSION passt nicht zu ${VERSION}"; exit 1; }

find beyondweb-protect -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

rm -rf dist && mkdir dist
zip -rqX "dist/beyondweb-protect-${VERSION}.zip" beyondweb-protect
( cd dist && sha256sum "beyondweb-protect-${VERSION}.zip" > "beyondweb-protect-${VERSION}.zip.sha256" )
cat "dist/beyondweb-protect-${VERSION}.zip.sha256"

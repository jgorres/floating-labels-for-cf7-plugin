#!/bin/bash
clear

# ==================================================
# Script name: build.sh
# Author: Jörn Gorres
# Date: 21.10.2026
# Description: Builds the release ZIP of the Floating Labels for CF7 plugin
#              for the WordPress.org directory: checks the version numbers,
#              exports plugin/ without the files listed in .distignore and
#              zips the result into dist/.
# ==================================================

set -euo pipefail

echo "===================================================="
echo "   FLOATING LABELS FOR CF7: RELEASE-ZIP ERZEUGEN"
echo "===================================================="
echo ""

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${REPO_DIR}/plugin"
SLUG="floating-labels-for-cf7"
TARGET_DIR="${REPO_DIR}/dist"
STAGE_DIR="$(mktemp -d)"

trap 'rm -rf "${STAGE_DIR}"' EXIT

# --------------------------------------------------
# 1. Version numbers must agree everywhere.
# --------------------------------------------------
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' "${PLUGIN_DIR}/${SLUG}.php")"

if [[ -z "${VERSION}" ]]; then
	echo "Fehler: Version im Plugin-Header nicht gefunden." >&2
	exit 1
fi

echo "Version laut Plugin-Header: ${VERSION}"

check_version() {
	local label="$1"
	local found="$2"

	if [[ "${found}" != "${VERSION}" ]]; then
		echo "Fehler: ${label} steht auf '${found}', erwartet '${VERSION}'." >&2
		exit 1
	fi

	echo "  ok  ${label}"
}

check_version "Konstante JGOR_FLCF7_VERSION" \
	"$(sed -n "s/^define( 'JGOR_FLCF7_VERSION', '\([0-9.]*\)' );/\1/p" "${PLUGIN_DIR}/${SLUG}.php")"
check_version "readme.txt Stable tag" \
	"$(sed -n 's/^Stable tag:[[:space:]]*\([0-9.]*\).*/\1/p' "${PLUGIN_DIR}/readme.txt")"

if ! grep -q "^= ${VERSION} =" "${PLUGIN_DIR}/readme.txt"; then
	echo "Fehler: readme.txt hat keinen Changelog-Eintrag für ${VERSION}." >&2
	exit 1
fi

echo "  ok  readme.txt Changelog"

# --------------------------------------------------
# 2. Export without the files from .distignore.
# --------------------------------------------------
echo ""
echo "Dateien zusammenstellen ..."

rsync -a --exclude-from="${REPO_DIR}/.distignore" "${PLUGIN_DIR}/" "${STAGE_DIR}/${SLUG}/"

if find "${STAGE_DIR}/${SLUG}" \( -name '*.po' -o -name '*.mo' -o -name '*.l10n.php' \) -print -quit | grep -q .; then
	echo "Fehler: Übersetzungsdateien im Paket gefunden." >&2
	exit 1
fi

if find "${STAGE_DIR}/${SLUG}" -name '.*' -print -quit | grep -q .; then
	echo "Fehler: versteckte Dateien im Paket gefunden." >&2
	exit 1
fi

if [[ ! -f "${STAGE_DIR}/${SLUG}/languages/${SLUG}.pot" ]]; then
	echo "Fehler: languages/${SLUG}.pot fehlt im Paket." >&2
	exit 1
fi

echo "  ok  languages/${SLUG}.pot"

FILE_COUNT="$(find "${STAGE_DIR}/${SLUG}" -type f | wc -l)"
echo "  ok  ${FILE_COUNT} Dateien"

# --------------------------------------------------
# 3. ZIP.
# --------------------------------------------------
echo ""
echo "ZIP schreiben ..."

mkdir -p "${TARGET_DIR}"
ZIP_FILE="${TARGET_DIR}/${SLUG}-${VERSION}.zip"
rm -f "${ZIP_FILE}"

( cd "${STAGE_DIR}" && zip -q -r -X "${ZIP_FILE}" "${SLUG}" )

echo "  ok  ${ZIP_FILE} ($(du -h "${ZIP_FILE}" | cut -f1))"
echo ""
echo "Inhalt:"
unzip -Z1 "${ZIP_FILE}" | grep -v '/$' | sed 's/^/  /'

echo ""
echo "===================================================="
echo "   Fertig!"
echo "===================================================="

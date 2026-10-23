#!/bin/bash
clear

# ==================================================
# Script name: screenshots-erzeugen.sh
# Author: Jörn Gorres
# Date: 22.10.2026
# Description: Erzeugt die WordPress.org-Screenshots 1-4 von Floating Labels
#              for CF7 auf der lokalen Testsite: legt ein Screenshot-Formular
#              und drei Seiten (Outlined, Filled, Minimal) an, fotografiert sie
#              headless mit Chromium, baut den Fehlerfall aus dem gerenderten
#              HTML nach, schneidet auf den Formularbereich zu und räumt die
#              Seiten und das Formular wieder weg. Die Screenshots 5 und 6
#              erzeugt docs/screenshots-admin-erzeugen.js.
# ==================================================

set -euo pipefail

echo "===================================================="
echo "   FLOATING LABELS FOR CF7: SCREENSHOTS ERZEUGEN"
echo "===================================================="
echo ""

SITE_PATH="${1:-/var/www/local-sites/floating-labels-for-cf7.gorres.local}"
SITE_URL="${2:-https://floating-labels-for-cf7.gorres.local}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${REPO_DIR}/assets"
WORK_DIR="$(mktemp -d)"
WIDTH=1280
HEIGHT=1000

ERROR_HTML="${SITE_PATH}/flcf7-error-shot.html"

trap 'rm -rf "${WORK_DIR}" "${ERROR_HTML}"' EXIT

for tool in wp chromium python3 curl; do
	command -v "${tool}" >/dev/null || { echo "Fehler: ${tool} fehlt." >&2; exit 1; }
done

# --------------------------------------------------
# 1. Formular und Seiten anlegen (idempotent über Titel und Slug).
# --------------------------------------------------
cat > "${WORK_DIR}/setup.php" <<'PHP'
<?php
( defined( 'WP_CLI' ) && WP_CLI ) || exit;

$jgor_form = "<label> Your name\n    [text* your-name \"John Doe\"] </label>\n\n"
	. "<label> Your email\n    [email* your-email \"john@example.com\"] </label>\n\n"
	. "<label> Your message\n    [textarea* your-message 40x4] </label>\n\n"
	. "[submit class:wp-element-button \"Send message\"]";

$jgor_existing = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'title' => 'FL Screenshot', 'numberposts' => 1, 'post_status' => 'any' ) );
$jgor_cf = $jgor_existing ? WPCF7_ContactForm::get_instance( $jgor_existing[0]->ID ) : WPCF7_ContactForm::get_template( array( 'title' => 'FL Screenshot', 'locale' => 'en_US' ) );
$jgor_cf->set_properties( array( 'form' => $jgor_form, 'additional_settings' => "skip_mail: on\ndemo_mode: on" ) );
$jgor_cf->save();
$jgor_id = (int) $jgor_cf->id();

$jgor_urls = array();
foreach ( array( 'outlined', 'filled', 'minimal' ) as $jgor_style ) {
	$jgor_slug    = 'flcf7-shot-' . $jgor_style;
	$jgor_content = '<!-- wp:shortcode -->[contact-form-7 id="' . $jgor_id . '" html_class="jgor-flcf7-style-' . $jgor_style . '"]<!-- /wp:shortcode -->';
	$jgor_page    = get_page_by_path( $jgor_slug, OBJECT, 'page' );
	if ( $jgor_page instanceof WP_Post ) {
		wp_update_post( array( 'ID' => $jgor_page->ID, 'post_content' => $jgor_content ) );
		$jgor_page_id = (int) $jgor_page->ID;
	} else {
		$jgor_page_id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contact us', 'post_name' => $jgor_slug, 'post_content' => $jgor_content ) );
	}
	$jgor_urls[ $jgor_style ] = get_permalink( $jgor_page_id );
}
WP_CLI::log( wp_json_encode( $jgor_urls ) );
PHP

cat > "${WORK_DIR}/cleanup.php" <<'PHP'
<?php
( defined( 'WP_CLI' ) && WP_CLI ) || exit;
foreach ( array( 'outlined', 'filled', 'minimal' ) as $jgor_style ) {
	$jgor_page = get_page_by_path( 'flcf7-shot-' . $jgor_style, OBJECT, 'page' );
	if ( $jgor_page instanceof WP_Post ) {
		wp_delete_post( $jgor_page->ID, true );
	}
}
foreach ( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'title' => 'FL Screenshot', 'numberposts' => -1, 'post_status' => 'any' ) ) as $jgor_post ) {
	wp_delete_post( $jgor_post->ID, true );
}
WP_CLI::log( 'cleanup done' );
PHP

echo "Formular und Seiten anlegen ..."
URLS_JSON="$(wp --path="${SITE_PATH}" eval-file "${WORK_DIR}/setup.php")"
echo "  ok  ${URLS_JSON}"

url_for() {
	python3 -c 'import json,sys; print(json.loads(sys.argv[1])[sys.argv[2]])' "${URLS_JSON}" "$1"
}

# --------------------------------------------------
# 2. Screenshots 1-3: die drei Varianten.
# --------------------------------------------------
shoot() {
	local url="$1"
	local out="$2"
	chromium --headless=new --no-sandbox --disable-gpu --hide-scrollbars --ignore-certificate-errors \
		--window-size="${WIDTH},${HEIGHT}" --virtual-time-budget=5000 --screenshot="${out}" "${url}" >/dev/null 2>&1
	[[ -s "${out}" ]] || { echo "Fehler: Screenshot ${out} fehlt." >&2; exit 1; }
}

echo ""
echo "Screenshots aufnehmen ..."
N=1
for style in outlined filled minimal; do
	shoot "$(url_for "${style}")" "${WORK_DIR}/shot-${N}.png"
	echo "  ok  ${style}"
	N=$((N + 1))
done

# --------------------------------------------------
# 3. Screenshot 4: Fehlerfall aus dem gerenderten HTML der Outlined-Seite.
#    Das leere Pflichtfeld „Your message“ bekommt die Klassen, die Contact Form 7
#    bei einem Fehler setzt, plus den Hinweis; Skripte werden entfernt, damit nichts nachträglich
#    umbaut. Die Datei liegt kurz im Webroot der Site, damit CSS und Schriften
#    von derselben Herkunft laden (Schriften unterliegen CORS).
# --------------------------------------------------
curl -sk "$(url_for outlined)" > "${WORK_DIR}/outlined.html"
python3 - "${WORK_DIR}/outlined.html" "${ERROR_HTML}" <<'PY'
import re, sys
src, dst = sys.argv[1], sys.argv[2]
html = open(src, encoding="utf-8").read()
html = re.sub(r"<script\b.*?</script>", "", html, flags=re.S)
html = re.sub(r'(<form[^>]*class=")([^"]*wpcf7-form)', r"\1\2 invalid", html, count=1)
tag = re.search(r'<textarea[^>]*name="your-message"[^>]*>', html).group(0)
fixed = tag.replace('aria-invalid="false"', 'aria-invalid="true"').replace('class="', 'class="wpcf7-not-valid ', 1)
html = html.replace(tag, fixed, 1)
html = re.sub(r'(data-name="your-message">.*?</label>)', r'\1<span class="wpcf7-not-valid-tip" aria-hidden="true">Please fill out this field.</span>', html, count=1, flags=re.S)
html = re.sub(r'<div class="wpcf7-response-output"[^>]*>.*?</div>', '<div class="wpcf7-response-output" aria-hidden="true">One or more fields have an error. Please check and try again.</div>', html, count=1, flags=re.S)
open(dst, "w", encoding="utf-8").write(html)
PY
shoot "${SITE_URL}/flcf7-error-shot.html" "${WORK_DIR}/shot-4.png"
rm -f "${ERROR_HTML}"
echo "  ok  Fehlerfall"

# --------------------------------------------------
# 4. Auf den Formularbereich zuschneiden und ablegen.
#    Links, oben und rechts sind für Twenty Twenty-Five bei 1280 px Breite
#    bemessen; unten endet der Ausschnitt mit Abstand unter dem Formular, am
#    ersten großen Weißraum vor dem Footer.
# --------------------------------------------------
echo ""
echo "Zuschneiden ..."
python3 - "${WORK_DIR}" "${OUT_DIR}" <<'PY'
import pathlib, sys
from PIL import Image, ImageChops
work, out = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
LEFT, TOP, RIGHT, PAD, GAP = 250, 190, 1030, 36, 80
for n in range(1, 5):
    im = Image.open(work / f"shot-{n}.png").convert("RGB")
    column = im.crop((LEFT, TOP, RIGHT, im.height))
    diff = ImageChops.difference(column, Image.new("RGB", column.size, (255, 255, 255))).convert("L")
    rows = [diff.crop((0, y, diff.width, y + 1)).getbbox() is not None for y in range(diff.height)]
    last, blank = 0, 0
    for y, filled in enumerate(rows):
        if filled:
            last, blank = y, 0
        else:
            blank += 1
            if last and blank >= GAP:
                break
    bottom = min(TOP + last + PAD, im.height)
    im.crop((LEFT, TOP, RIGHT, bottom)).save(out / f"screenshot-{n}.png", optimize=True)
    print(f"  ok  assets/screenshot-{n}.png ({RIGHT - LEFT}x{bottom - TOP})")
PY

# --------------------------------------------------
# 5. Aufräumen.
# --------------------------------------------------
echo ""
echo "Seiten und Formular entfernen ..."
wp --path="${SITE_PATH}" eval-file "${WORK_DIR}/cleanup.php" >/dev/null
echo "  ok"

echo ""
echo "===================================================="
echo "   Fertig!"
echo "===================================================="

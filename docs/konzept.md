# Floating Labels for CF7 – Konzept und Aufbau

Version: 1.1 · Stand: 22.10.2026 · Plugin-Version: noch kein Code

## 1. Zweck

Contact Form 7 (CF7) rendert Formularfelder mit festem Label über dem Feld
oder mit einem Placeholder im Feld. Beides hat Nachteile: Das feste Label
kostet Platz, der Placeholder verschwindet beim Tippen und ist für
Screenreader nur ein Notbehelf. Ein Floating Label liegt im leeren Feld wie
ein Placeholder und rutscht bei Fokus oder Eingabe klein an den oberen Rand.
Das Label bleibt dauerhaft sichtbar und ist als echtes `<label for>` mit dem
Feld verknüpft.

Das Plugin baut das von CF7 gerenderte Formular serverseitig um und liefert
das CSS dazu. Es braucht kein JavaScript: Alle Zustände (leer, fokussiert,
gefüllt, fehlerhaft) entstehen aus CSS-Pseudoklassen. Damit ist es
CSP-tauglich und funktioniert auch, wenn CF7-Formulare ohne Ajax abgeschickt
werden.

Vorläufer: die Floating-Labels des Welpenthemes (`cf7-sv-kontaktformular/`,
Kundenprojekt Schäferhunde). Dort setzt die Formularvorlage ein festes
BEM-Markup voraus und ein kleines JS setzt die Klasse `--filled`. Beides
entfällt hier: Das Plugin erkennt die CF7-Standardvorlage und die
`placeholder`-Option selbst, und die Klasse `--filled` wird durch
`:placeholder-shown` ersetzt, weil das Plugin den Placeholder-Wert selbst
steuert.

Das Plugin ist für das WordPress-Verzeichnis gedacht. Oberfläche und Texte im
Plugin sind englisch, diese Projektdoku ist deutsch. Übersetzung nur Deutsch
in beiden Anreden über translate.wordpress.org.

## 2. Eckdaten

| Feld | Wert |
| --- | --- |
| Name | Floating Labels for CF7 |
| Slug / Text Domain | `floating-labels-for-cf7` |
| Prefix | `jgor_flcf7_`, `JGOR_FLCF7_`, Handles `jgor-flcf7-`, CSS-Klassen `jgor-flcf7-` |
| Description | Turns placeholders and labels of Contact Form 7 fields into floating labels. Pure CSS, no JavaScript. |
| Requires at least | 6.5 (wegen `Requires Plugins`) |
| Tested up to | 7.1 (nur in `readme.txt`) |
| Requires PHP | 8.1 |
| Requires Plugins | `contact-form-7` (Header und `readme.txt`) |
| Plugin URI | `https://github.com/jgorres/floating-labels-for-cf7-plugin` (Repo seit 22.10.2026 privat, Remote `origin`; vor der Einreichung öffentlich stellen) |
| Lokale Site | `https://floating-labels-for-cf7.gorres.local` (local-sites auf Bodhran, PHP 8.4, WordPress 7.1.3 „nackt“, Twenty Twenty-Five, Admin `jgorres`, Sprache en_US, de_DE installiert), CF7, Plugin Check und Query Monitor per wp-cli, Plugin per Symlink `wp-content/plugins/floating-labels-for-cf7` → `plugin/`; Browser läuft auf Silentium, Hosts-Eintrag dort über `hosts-von-bodhran.sh` |
| Test-Site Hostsharing | `https://<hs-testsite>`: Paket `<hs-paket>`, Domain-Admin `<hs-admin>`, DB und DB-User `<hs-db>`, PHP 8.4 (`fcgiphpbin`, in HSAdmin gesetzt), Let’s Encrypt. Kopie der lokalen Site über Stufe 6 `local-sites-hostsharing.sh` (Zugangsdaten `~/.config/local-sites/hostsharing/floating-labels-for-cf7.gorres.local.env`); Änderungen nachschieben mit `dateien floating-labels-for-cf7.gorres.local --ueberschreiben`; wp-cli dort mit `php8.4 /home/pacs/<hs-paket>/bin/wp` |
| Rechte | Einstellungsseite `manage_options` |
| Geprüft gegen | CF7 6.2.3 (lokal und Hostsharing; verlangt WordPress 7.1 und PHP 8.3, liegt über `Requires at least` 6.5 und `Requires PHP` 8.1, Entscheidung beim Gerüst), Doku `https://contactform7.com/docs/` |

Namensprüfung: „Contact Form 7“ ist eine eingetragene Marke von Rock Lobster
Inc. Die Trademark-Policy (`contactform7.com/trademark-policy/`) nimmt die
Abkürzung „CF7“ ausdrücklich aus und erlaubt für WP.org-Plugins die Nennung
in Name und Slug, soweit die WP.org-Richtlinien es zulassen. „Floating Labels
for CF7“ beginnt nicht mit dem Markenbegriff (Richtlinie 17). Die Slugs
`floating-labels-for-cf7` und `floating-labels-for-contact-form-7` waren am
21.10.2026 frei; die WP.org-Suche kennt kein dediziertes Floating-Label-Plugin
für CF7. In Beschreibung und readme steht „Contact Form 7“ nur als
Erwähnung, ohne Anschein einer Verbindung.

## 3. Verzeichnisse

Aufbau nach `~/dev/jgorres-im-WP-Repository/README.md` (1.5), ohne
`package.json` (keine Blöcke, kein Build-Schritt).

```
floating-labels-for-cf7/
├── plugin/
│   ├── floating-labels-for-cf7.php   Header, Konstanten, Laden der Module
│   ├── readme.txt
│   ├── uninstall.php                 löscht die Option
│   ├── includes/
│   │   ├── class-jgor-flcf7-plugin.php    Bootstrap: CF7 vorhanden?, Hooks, Hinweis
│   │   ├── class-jgor-flcf7-settings.php  Defaults, Lesen und Sanitize der Option, Filter
│   │   ├── class-jgor-flcf7-markup.php    Modul 2: Umbau des Formular-HTML
│   │   ├── class-jgor-flcf7-assets.php    Modul 3: CSS einbinden
│   │   └── class-jgor-flcf7-admin.php     Modul 4: Einstellungsseite
│   ├── assets/css/floating-labels.css     Frontend (eine Datei, alle Varianten)
│   └── languages/floating-labels-for-cf7.pot
├── docs/  assets/  glotpress/
├── composer.json, phpcs.xml.dist, phpstan.neon.dist, phpstan-bootstrap.php, stubs/
└── build.sh, .distignore, .gitignore, LICENSE
```

`stubs/cf7.php` hält die CF7-Klassen und -Funktionen, die das Plugin
aufruft (`WPCF7_ContactForm`, `WPCF7_FormTag`, `wpcf7_enqueue_styles`), damit
PHPStan ohne CF7-Installation läuft.

## 4. Grundsätze

- **Nichts kaputt machen:** Das Plugin ändert nur Felder, die es sicher
  erkennt. Alles andere (Checkboxen, Radios, Dateien, Select, Quiz, Captcha,
  Submit, eigenes HTML in der Vorlage) bleibt Byte für Byte so, wie CF7 es
  rendert. Schlägt der Umbau fehl, geht das Original-HTML unverändert raus.
- **Kein JavaScript:** Zustände über `:focus`, `:placeholder-shown`,
  `.wpcf7-not-valid` und `[aria-required]`. CF7 setzt nach dem Versand
  `form.reset()`, dadurch sinken die Labels von selbst zurück. Das CSS enthält
  keine Inline-Anteile, das Plugin ist CSP-tauglich.
- **Zugänglichkeit:** Jedes umgebaute Feld bekommt ein echtes `<label for>`
  mit passender `id`. Der Placeholder wird zu einem Leerzeichen, damit
  Screenreader nicht zwei Namen vorlesen. CF7-Attribute (`aria-required`,
  `aria-invalid`, `aria-describedby`) bleiben erhalten.
- **CF7-Konventionen:** Die Steuerung je Formular läuft über die Additional
  Settings (`floating_labels: off`), je Feld über eine Form-Tag-Option
  (`nofloat`). Das CSS folgt der Ladepolitik von CF7 über die Action
  `wpcf7_enqueue_styles`.
- **Theme-freundlich:** Farben, Maße und Dauer als Custom Properties
  `--jgor-flcf7-*`, gesetzt auf `.wpcf7`. Die Variante „Minimal“ liefert nur
  Positionierung und Zustände, die Optik kommt dann aus dem Theme.
- **Entwickler-Filter:** `jgor_flcf7_settings` (Konfiguration aus Code),
  `jgor_flcf7_enabled` (je Formular), `jgor_flcf7_label_text` (je Feld),
  `jgor_flcf7_field_types`.
- **Schlank:** Eine Option (`jgor_flcf7_settings`, autoload), eine
  CSS-Datei, keine Tabellen, keine Transients, kein Cron. Der Umbau läuft
  einmal je gerendertem Formular auf einem kleinen HTML-Fragment.

## 5. Was CF7 liefert (Grundlagen)

Aus der CF7-Doku und dem Quelltext 6.2.2:

- **Form-Tags:** `[text* your-name placeholder "Your name"]`. Die Option
  `placeholder` (Alias `watermark`) gibt es für `text`, `email`, `url`,
  `tel`, `textarea`, `number`, `range`, `date` und `captchar`. CF7 schreibt
  den Wert in das Attribut `placeholder`, der Default-Wert bleibt leer.
  Optionen `id:` und `class:` setzen `id` und `class` des Steuerelements.
  Unbekannte Optionen wie `nofloat` nimmt der Parser an und ignoriert sie;
  `WPCF7_FormTag::has_option()` liest sie.
- **Markup je Feld:**
  `<span class="wpcf7-form-control-wrap" data-name="your-name"><input … class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required" aria-required="true" aria-invalid="false" placeholder="Your name"></span>`.
  Bei Validierungsfehlern ohne Ajax steht hinter dem Feld noch
  `<span class="wpcf7-not-valid-tip">…</span>` im Wrap. CF7-CSS setzt
  `.wpcf7-form-control-wrap { position: relative }`.
- **Standardvorlage:** `<label> Your name\n    [text* your-name autocomplete:name] </label>`.
  CF7 wendet `wpautop` an (`WPCF7_AUTOP`), gerendert also
  `<p><label> Your name<br>\n<span class="wpcf7-form-control-wrap" …>…</span> </label></p>`.
- **Hooks:** `wpcf7_form_elements` (Filter, erhält das fertige Formular-HTML
  nach dem Ersetzen der Form-Tags), `WPCF7_ContactForm::get_current()` liefert
  das Formular, `unit_tag()` die Instanz (`wpcf7-f{id}-p{post}-o{n}`),
  `pref( 'name' )` den ersten Wert einer Zeile aus den Additional Settings,
  `scan_form_tags()` die Form-Tag-Objekte.
- **Assets:** CF7 registriert seine Styles auf `wp_enqueue_scripts` und
  bindet sie ein, wenn `wpcf7_load_css()` wahr ist (Standard, abschaltbar über
  `WPCF7_LOAD_CSS` oder Filter `wpcf7_load_css`). `wpcf7_enqueue_styles()`
  feuert `do_action( 'wpcf7_enqueue_styles' )`; die Doku empfiehlt bei
  abgeschaltetem Autoload den Aufruf im Template vor `wp_head()`. Ein
  Lazy-Enqueue beim Rendern gibt es nicht.
- **Client-Validierung:** Das CF7-JS setzt bei Fehlern `.wpcf7-not-valid` und
  `aria-invalid="true"` auf das Feld und hängt `<span
  class="wpcf7-not-valid-tip" aria-hidden="true">` per `appendChild` ans Ende
  des Wraps. Nach erfolgreichem Versand `form.reset()`. DOM-Events
  (`wpcf7invalid`, `wpcf7spam`, `wpcf7mailsent`, `wpcf7mailfailed`,
  `wpcf7submit`) braucht das Plugin nicht.
- **Block-Editor:** Der CF7-Block rendert denselben Shortcode, der Filter
  greift dort ebenso.

## 6. Modul 1: Grundgerüst (`class-jgor-flcf7-plugin.php`, `class-jgor-flcf7-settings.php`)

- Hauptdatei mit Header, Konstanten `JGOR_FLCF7_VERSION`, `JGOR_FLCF7_MIN_PHP`,
  `JGOR_FLCF7_FILE`, `JGOR_FLCF7_PATH`, `JGOR_FLCF7_URL`, Laden der Klassen
  aus `includes/`, Start auf `plugins_loaded`.
- `JGOR_FLCF7_Plugin::instance()->init()`: prüft `class_exists(
  'WPCF7_ContactForm' )` und `class_exists( 'DOMDocument' )`. Fehlt CF7, zeigt
  das Plugin einen Admin-Hinweis und registriert sonst nichts (der Header
  `Requires Plugins` verhindert die Aktivierung ohne CF7 ohnehin ab WP 6.5;
  der Hinweis deckt das spätere Deaktivieren von CF7 ab). Fehlt `ext-dom`,
  Hinweis und kein Umbau; das CSS wird trotzdem nicht geladen, weil es ohne
  Umbau nichts zu stylen gibt.
- `JGOR_FLCF7_Settings`: Defaults, `get()`, `sanitize()`, Option
  `jgor_flcf7_settings` (Array, autoload). Werte:

| Schlüssel | Typ | Default | Bedeutung |
| --- | --- | --- | --- |
| `mode_placeholder` | bool | true | Placeholder-Modus (7.1) |
| `mode_label` | bool | true | Label-Modus (7.2) |
| `types` | array | text, email, url, tel, number, textarea | erlaubte Feldtypen (CF7-Basistyp) |
| `style` | string | `outlined` | `outlined`, `filled`, `minimal` |
| `load_css` | bool | true | Plugin-CSS einbinden |
| `required_marker` | bool | false | Stern hinter dem Label bei `aria-required` |

- Filter `jgor_flcf7_settings` wie in Gorres Control REST-API 14.1: Werte aus
  Code übersteuern die Option, die Oberfläche zeigt sie schreibgeschützt.
- Deaktivierung hinterlässt nichts; `uninstall.php` löscht die Option (bei
  Multisite je Site, siehe 10).

## 7. Modul 2: Umbau des Markups (`class-jgor-flcf7-markup.php`)

Hook: `add_filter( 'wpcf7_form_elements', …, 20 )`. Ablauf je Formular:

1. Formular über `WPCF7_ContactForm::get_current()`. Kein Formular oder
   `pref( 'floating_labels' ) === 'off'` oder Filter `jgor_flcf7_enabled`
   falsch → HTML unverändert zurück. Steht in den Additional Settings
   `floating_labels: placeholder` oder `floating_labels: label`, gilt für
   dieses Formular nur der genannte Modus.
2. Schneller Vorab-Test per `strpos()` auf `wpcf7-form-control-wrap`; sonst
   unverändert zurück.
3. Ausnahmen sammeln: `scan_form_tags()` → Namen aller Tags mit
   `has_option( 'nofloat' )`, dazu die Basistypen je Name (für die
   Typprüfung ohne Attribut-Raten).
4. HTML in DOMDocument laden (7.5), alle `span.wpcf7-form-control-wrap`
   durchgehen. Je Wrap: genau ein Steuerelement `input`/`textarea` als Kind,
   Basistyp laut Form-Tag in `types`, Name nicht in den Ausnahmen, Wrap noch
   nicht umgebaut (Klasse `jgor-flcf7-wrap` fehlt).
5. Label-Text bestimmen: Label-Modus (7.2) hat Vorrang, dann Placeholder-Modus
   (7.1). Kein Text → Feld überspringen. Text durch Filter
   `jgor_flcf7_label_text` (`$text`, `$name`, `$contact_form`).
6. Umbau (7.6), zurück zu HTML.

### 7.1 Placeholder-Modus

Das Steuerelement hat ein nicht leeres Attribut `placeholder`. Der Text wird
Label-Text; `placeholder` wird auf ein einzelnes Leerzeichen gesetzt, damit
`:placeholder-shown` weiter den Leerzustand meldet und kein zweiter Text im
Feld liegt. Keine Änderung an der Formularvorlage nötig; bestehende Formulare
mit Placeholdern bekommen die Floating Labels sofort.

### 7.2 Label-Modus

Zwei Formen der CF7-Standardvorlage:

- **Umschließendes Label:** Der nächste Vorfahr des Wraps ist ein `<label>`,
  das genau diesen einen Wrap enthält. Alle anderen Kindknoten des Labels
  (Text, Inline-Elemente wie `<strong>`, nicht aber `<br>`) werden der
  Label-Text, Whitespace getrimmt. Das umschließende `<label>` wird durch
  `<span class="jgor-flcf7-field">` ersetzt, weil ein `<label>` kein weiteres
  `<label>` enthalten darf; `<br>` aus `wpautop` entfällt. Das `<p>` außen
  bleibt.
- **Verknüpftes Label:** Ein `<label for="…">` im Formular zeigt auf die `id`
  des Steuerelements (Option `id:`). Das Label wird mitsamt Inhalt in den Wrap
  hinter das Steuerelement verschoben.

In beiden Fällen wird ein vorhandener Placeholder ebenfalls auf ein
Leerzeichen gesetzt, sonst lägen Placeholder und Label übereinander.

### 7.3 Feld-ID

Hat das Steuerelement eine `id`, bleibt sie. Sonst
`{unit_tag}-fl-{name}`, bereinigt wie CF7 es für seine Fehler-IDs tut
(`[^0-9a-z_-]` entfernt). Der Unit-Tag ist je Instanz eindeutig, auch wenn
dasselbe Formular zweimal auf einer Seite steht.

### 7.4 Ausnahmen

- Form-Tag-Option `nofloat`: `[text* your-name placeholder "Name" nofloat]`.
- Additional Settings: `floating_labels: off` (ganzes Formular),
  `floating_labels: placeholder`, `floating_labels: label`.
- Filter `jgor_flcf7_enabled` (`$enabled`, `$contact_form`).
- Feldtypen außerhalb von `types`.

### 7.5 Technik: DOMDocument

`WP_HTML_Tag_Processor` kann Attribute ändern, aber keine Elemente einfügen
oder verschieben; `WP_HTML_Processor` (6.4+) ebenfalls nicht. Darum
DOMDocument (`ext-dom`):

- `libxml_use_internal_errors( true )`, Laden mit vorangestelltem
  `<?xml encoding="UTF-8">` und einem Wrapper `<div id="jgor-flcf7-root">`,
  Flags `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD`; Ausgabe über
  `saveHTML( $root )`, Wrapper-Tags abgeschnitten, `libxml_clear_errors()`.
- Scheitert das Laden oder fehlt der Root-Knoten → Original-HTML.
- Leere Elemente kommen als `<input …>` ohne Schrägstrich zurück, in HTML5
  gleichwertig. Textknoten werden beim Speichern neu escaped; der Label-Text
  stammt aus einem Attribut oder aus Textknoten, die CF7 bereits escaped hat.
- Das Fragment ist klein (ein Formular), der Umbau läuft einmal je Rendern;
  kein Cache nötig.

Sollte `WP_HTML_Processor` in einer späteren WP-Version Einfügen unterstützen,
kann der Umbau darauf wechseln (Abschnitt 14).

### 7.6 Ergebnis-Markup

```html
<span class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined" data-name="your-name">
  <input type="text" name="your-name" id="wpcf7-f12-p3-o1-fl-your-name"
         class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required jgor-flcf7-control"
         aria-required="true" aria-invalid="false" placeholder=" ">
  <label class="jgor-flcf7-label" for="wpcf7-f12-p3-o1-fl-your-name">Your name</label>
</span>
```

Das Label steht direkt hinter dem Steuerelement, damit reine
Geschwister-Selektoren reichen (`.jgor-flcf7-control:focus + .jgor-flcf7-label`,
`.jgor-flcf7-control:not(:placeholder-shown) + .jgor-flcf7-label`). Ein
`.wpcf7-not-valid-tip` (serverseitig schon vorhanden oder per JS angehängt)
landet hinter dem Label. Die Varianten-Klasse (`jgor-flcf7-outlined`,
`-filled`, `-minimal`) sitzt am Wrap, damit eine CSS-Datei alle Varianten
abdeckt.

## 8. Modul 3: CSS (`class-jgor-flcf7-assets.php`, `assets/css/floating-labels.css`)

- Einbinden auf `add_action( 'wpcf7_enqueue_styles', … )`:
  `wp_enqueue_style( 'jgor-flcf7', …, array( 'contact-form-7' ), JGOR_FLCF7_VERSION )`.
  Die Abhängigkeit sorgt für die Reihenfolge hinter dem CF7-CSS. Lädt CF7 sein
  CSS nicht (`WPCF7_LOAD_CSS` false), lädt das Plugin seins auch nicht; wer
  `wpcf7_enqueue_styles()` im Template aufruft, bekommt beides. `load_css`
  false → kein Enqueue.
- Custom Properties auf `.wpcf7`: `--jgor-flcf7-padding-x`,
  `--jgor-flcf7-label-color`, `--jgor-flcf7-label-color-active`,
  `--jgor-flcf7-label-color-error`, `--jgor-flcf7-label-size`,
  `--jgor-flcf7-label-size-floated`, `--jgor-flcf7-border-color`,
  `--jgor-flcf7-border-color-active`, `--jgor-flcf7-border-color-error`,
  `--jgor-flcf7-radius`, `--jgor-flcf7-background`, `--jgor-flcf7-duration`.
- Gemeinsame Regeln: `.jgor-flcf7-wrap { display: block; position: relative }`,
  `.jgor-flcf7-control { width: 100%; box-sizing: border-box }`, Label
  `position: absolute; inset-inline-start: var(--jgor-flcf7-padding-x);
  pointer-events: none`; Ruheposition vertikal mittig (`top: 50%` plus
  `translateY(-50%)`), bei `textarea` fest oben; gefloatet klein und oben.
  Zustände: `:focus + label`, `:not(:placeholder-shown) + label`,
  `:autofill + label`, `.wpcf7-not-valid + label` (Fehlerfarbe),
  `[aria-required="true"] + label::after { content: " *" }` nur bei
  `required_marker`.
- **Outlined:** Rahmen rundum, Label floatet auf die obere Rahmenlinie mit
  Hintergrund als „Notch“ (wie im Welpentheme). **Filled:** Hintergrundfläche,
  nur untere Linie, Label floatet innerhalb des Feldes nach oben, Eingabetext
  rutscht durch Padding nach unten. **Minimal:** nur Positionierung und
  Zustände, keine Farben, Rahmen oder Hintergründe.
- `@media (prefers-reduced-motion: reduce)` schaltet die Transition ab. RTL
  über logische Eigenschaften (`inset-inline-start`, `padding-inline`).
- Browser: `:placeholder-shown`, Custom Properties und logische Eigenschaften
  sind seit 2020 in allen Evergreen-Browsern; `:has()` wird in 1.0 nicht
  gebraucht.

## 9. Modul 4: Einstellungsseite (`class-jgor-flcf7-admin.php`)

- Untermenü im CF7-Menü (`add_submenu_page( 'wpcf7', … )`, Slug
  `wpcf7-floating-labels`), Capability `manage_options`, Settings API mit
  `register_setting()` und `sanitize()` aus Modul 1, Nonce über
  `settings_fields()`.
- Felder: zwei Checkboxen für die Modi, Checkboxen je Feldtyp, Radio für die
  Variante, Checkbox CSS laden, Checkbox Pflichtfeld-Stern. Kurzer Hinweistext
  mit den drei Steuerwegen (`nofloat`, `floating_labels: off`, Custom
  Properties) und den Filtern.
- Aus Code gesperrte Werte (Filter `jgor_flcf7_settings`) erscheinen
  deaktiviert mit Hinweis.
- Kein eigenes Admin-CSS/JS, solange die Standardtabelle der Settings API
  reicht.

### 9.1 Konfiguration aus Code (`jgor_flcf7_settings`)

```php
add_filter( 'jgor_flcf7_settings', function ( $settings ) {
    $settings['style']    = 'minimal';
    $settings['load_css'] = false;
    return $settings;
} );
```

Gesperrt ist jeder Schlüssel, den der Filter setzt; die Option behält ihren
gespeicherten Wert, die Oberfläche zeigt den Filterwert schreibgeschützt.

## 10. Multisite

Option je Site, keine netzwerkweiten Einstellungen. `uninstall.php` läuft bei
Multisite über alle Sites (`get_sites()`, `switch_to_blog()` /
`restore_current_blog()` paarweise).

## 11. Deinstallation (`uninstall.php`)

`defined( 'WP_UNINSTALL_PLUGIN' ) || exit;`, dann `delete_option(
'jgor_flcf7_settings' )`, bei Multisite je Site. Keine Transients, keine
Tabellen, keine Dateien.

## 12. Prüfung

- `composer check` (PHPCS/WPCS, PHPStan Level 6 mit `stubs/cf7.php`) vor
  jedem Commit.
- `wp plugin check` auf dem gebauten ZIP (keine externen URLs im Code,
  `Tested up to` nur in `readme.txt`, Kurzbeschreibung höchstens 150 Zeichen).
- Testsite `floating-labels-for-cf7.gorres.local` (CF7, Plugin Check, Query
  Monitor, Symlink auf `plugin/`, Abschnitt 2), Testformulare: (a) CF7-
  Standardvorlage (Label-Modus), (b) Placeholder-Vorlage, (c) gemischt mit
  `nofloat`, `id:`-Option und
  verknüpftem `<label for>`, Select, Checkbox, Datei, (d) Formular mit
  `floating_labels: off`, (e) dasselbe Formular zweimal auf einer Seite.
- Per `wp eval`: `WPCF7_ContactForm::get_instance( $id )->form_html()` →
  Label je Feld mit `for` = `id` des Feldes, `placeholder=" "`, unberührte
  Felder byteidentisch zum Original (Vergleich mit deaktiviertem Filter).
- Browser (Chromium, Firefox, Edge per Playwright): Ruhezustand, Fokus,
  Eingabe, Autofill, Fehlerfall (Pflichtfeld leer abschicken → Label und
  Rahmen rot, Tip unter dem Feld), Versand mit `skip_mail: on` → Labels
  sinken zurück; Screenshots für `assets/`.
- Ohne `ext-dom` (per `wp eval` mit Klassen-Stub nicht simulierbar; Codepfad
  per PHPStan und Lesen prüfen): Hinweis im Admin, Formular unverändert.
- Gegenprobe auf der Hostsharing-Testsite `<hs-testsite>`
  (PHP 8.4, Produktiv-Stack) vor jedem Release; Stand mit
  `local-sites-hostsharing.sh dateien … --ueberschreiben` nachziehen.
- Testeinstellungen und Testformulare nach der Prüfung entfernen.

## 13. Reihenfolge der Umsetzung

1. Gerüst (Header, Konstanten, Bootstrap, Settings-Klasse, Hinweis ohne
   CF7), `stubs/cf7.php`, `composer install`, Commit. Erledigt am
   22.10.2026: GitHub-Remote (privat, Commit a8cdc4d), lokale Testsite und
   Hostsharing-Testsite (Abschnitt 2).
2. Modul 2 Markup: Placeholder-Modus, dann Label-Modus, Ausnahmen, Filter.
   Prüfung per `wp eval`.
3. Modul 3 CSS: Variante Outlined, dann Filled und Minimal, Fehlerzustand,
   Reduced Motion, RTL. Browser-Prüfung.
4. Modul 4 Einstellungsseite mit Sperren aus Code.
5. `readme.txt`, Kontexthilfe, `.pot`, Assets (Icon, Banner, Screenshots),
   Plugin Check, Repo öffentlich stellen, Release 1.0.0, Einreichung;
   danach GlotPress (de/default, de/formal).

## 14. Offen

- Feldtypen `date` (Chrome zeigt eine Formatmaske, Label müsste dauerhaft
  oben stehen) und `select` mit `include_blank` (Leerzustand nur über
  `:has( option[value=""]:checked )` erkennbar) als Erweiterung 1.1.
- `range`, `file`, `checkbox`, `radio`, `acceptance`, `quiz`, `captchar`:
  bewusst nie.
- CF7-Formularklasse `use-floating-validation-tip` (absolut positionierte
  Fehlertips) mit dem Wrap abstimmen, sobald ein Fall auftaucht.
- Umstieg von DOMDocument auf `WP_HTML_Processor`, sobald der Core das
  Einfügen von Elementen unterstützt.

## Änderungen

| Version | Datum | Änderung |
| --- | --- | --- |
| 1.0 | 21.10.2026 | Erste Fassung: Zweck, Eckdaten mit Namensprüfung, CF7-Grundlagen, vier Module, Prüfung, Reihenfolge |
| 1.1 | 22.10.2026 | Testumgebung: GitHub-Repo (privat), lokale Site `floating-labels-for-cf7.gorres.local`, Hostsharing-Site `<hs-testsite>` (<hs-admin>, DB `<hs-db>`, PHP 8.4); CF7 6.2.3 verlangt WordPress 7.1 und PHP 8.3 |

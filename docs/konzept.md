# Floating Labels for CF7 – Konzept und Aufbau

Version: 2.24 · Stand: 29.10.2026 · Plugin-Version: 1.0.0 (Module 1–5 umgesetzt, Nachbesserungen aus der Sichtprüfung eingearbeitet, Release-Vorbereitung läuft)

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
| Test-Site Hostsharing | `https://<hs-testsite>`: Paket `<hs-paket>`, Domain-Admin `<hs-admin>`, DB und DB-User `<hs-db>`, PHP 8.4 (`fcgiphpbin`, in HSAdmin gesetzt), Let’s Encrypt. Kopie der lokalen Site über Stufe 6 `local-sites-hostsharing.sh` (Zugangsdaten `~/.config/local-sites/hostsharing/floating-labels-for-cf7.gorres.local.env`); Änderungen nachschieben mit `dateien floating-labels-for-cf7.gorres.local --ueberschreiben`; wp-cli dort mit `php8.4 /home/pacs/<hs-paket>/bin/wp` Seit 29.10.2026 mit Basic-Auth: Benutzer `<basic-auth-user>`, Passwort in der lokalen Ablage; `.htpasswd` (bcrypt) außerhalb des Webroots, Block in `<webroot>/.htaccess`; in der Site-env `HS_RSYNC_AUSSCHLUESSE_SITE=".htaccess .htpasswd"`, damit `dateien --ueberschreiben` beides stehen lässt. Abrufe per curl/Playwright mit `-u <basic-auth-user>:…`; WP-Cron per HTTP bekommt 401. |
| Rechte | Einstellungsseite `manage_options` |
| Geprüft gegen | CF7 6.2.4 (lokal und Hostsharing seit 29.10.2026, vorher 6.2.3; das Security-Release 6.2.4 und 6.2.4.1 mit FormDataTree 2.0.10 ändern nur Uploads, SWV und FormDataTree, alle Prüfscripte danach ohne Befund), Doku `https://contactform7.com/docs/`. CF7 6.2.3 verlangt für sich WordPress 7.1 und PHP 8.3; `Requires at least` 6.5 und `Requires PHP` 8.1 bleiben trotzdem (Entscheidung 22.10.2026): Das Plugin nutzt nur CF7-API, die es seit 5.x gibt, ältere CF7-Versionen laufen auf WP 6.5 / PHP 8.1 weiter |

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
│   ├── readme.txt                    WP.org-Readme: Stable tag, Tested up to, FAQ, Changelog
│   ├── uninstall.php                 löscht die Option und die Beispielseite (9.3)
│   ├── includes/
│   │   ├── class-jgor-flcf7-plugin.php    Bootstrap: CF7 vorhanden?, Hooks, Hinweis
│   │   ├── class-jgor-flcf7-settings.php  Defaults, Lesen und Sanitize der Option, Filter
│   │   ├── class-jgor-flcf7-markup.php    Modul 2: Umbau des Formular-HTML
│   │   ├── class-jgor-flcf7-assets.php    Modul 3: CSS einbinden
│   │   ├── class-jgor-flcf7-admin.php     Modul 4: Einstellungsseite
│   │   ├── class-jgor-flcf7-editor.php    Modul 5: Block-Seitenleiste (Editor-Script und Daten laden)
│   │   ├── class-jgor-flcf7-placement-design.php  Modul 5, Server: Design je Platzierung (Block-Attribut, Shortcode-Attribut, style am Wrapper)
│   │   └── class-jgor-flcf7-samples.php   Modul 6: Beispielseite mit Formularen, Box und Nachfrage (9.3)
│   ├── assets/css/floating-labels.css     Frontend (eine Datei, alle Varianten, Fokus-Effekte)
│   ├── assets/css/admin.css               Einstellungsseite: Spalten der Feld-Gruppen (9)
│   ├── assets/css/editor.css              Block-Editor: Farbzeilen der Design-Panels (10.1)
│   ├── assets/js/block-editor.js          Panels „Floating labels“ (Platzierung und Design) im CF7- und Shortcode-Block, ohne Build-Schritt
│   ├── assets/js/admin.js                 Farbwähler auf der Einstellungsseite
│   └── languages/floating-labels-for-cf7.pot  Übersetzungsvorlage (wp i18n make-pot plugin … --slug=floating-labels-for-cf7)
├── tests/
│   ├── create-test-forms.php         Testformulare und Seite „Contact“ anlegen (wp eval-file)
│   ├── verify-markup.php             88 PASS/FAIL-Prüfungen für Modul 2 und das Design je Platzierung (wp eval-file)
│   ├── verify-admin.php              54 PASS/FAIL-Prüfungen für Modul 4 (wp eval-file)
│   ├── verify-design.php             29 PASS/FAIL-Prüfungen für die Design-Einstellungen (wp eval-file)
│   ├── verify-samples.php            37 PASS/FAIL-Prüfungen für die Beispielseite (wp eval-file)
│   ├── verify-editor.php             27 PASS/FAIL-Prüfungen für Modul 5, Enqueue und Block-Attribut (wp eval-file)
│   └── verify-editor-js.mjs          99 PASS/FAIL-Prüfungen für das Editor-Script (node)
├── docs/
│   ├── konzept.md                    dieses Dokument
│   ├── assets-erzeugen.py            Icon (SVG und APNG) und Banner (APNG), Pillow
│   ├── screenshots-erzeugen.sh       Screenshots 1–4 auf der lokalen Testsite, Chromium headless
│   └── screenshots-admin-erzeugen.js Screenshots 5–6 (Einstellungsseite, Block-Seitenleiste), playwright-core + Chromium
├── assets/                           WP.org-Assets: icon.svg, icon-128x128.png, icon-256x256.png,
│                                     banner-772x250.png, banner-1544x500.png, screenshot-1..6.png
├── glotpress/
├── composer.json, phpcs.xml.dist, phpstan.neon.dist, phpstan-bootstrap.php, stubs/
└── build.sh, .distignore, .gitignore, LICENSE
```

`stubs/cf7.php` hält die CF7-Klassen und -Funktionen, die das Plugin
aufruft (`WPCF7_ContactForm`, `WPCF7_FormTag`, `wpcf7_enqueue_styles`), damit
PHPStan ohne CF7-Installation läuft. `tests/` liegt außerhalb von `plugin/`
und kommt nicht ins Release-ZIP; die PHP-Scripte laufen nur unter WP-CLI
(`defined( 'WP_CLI' )`) und nur auf lokalen Testsites, das Node-Script ohne
WordPress (Abschnitt 13).

## 4. Grundsätze

- **Nichts kaputt machen:** Das Plugin ändert nur Felder, die es sicher
  erkennt. Alles andere (Checkboxen, Radios, Dateien, Select, Quiz, Captcha,
  Submit, eigenes HTML in der Vorlage) bleibt Byte für Byte so, wie CF7 es
  rendert. Schlägt der Umbau fehl oder ändert er kein Feld, geht das
  Original-HTML unverändert raus.
- **Kein JavaScript im Frontend:** Zustände über `:focus`, `:placeholder-shown`,
  `:autofill`, `.wpcf7-not-valid` / `[aria-invalid="true"]`,
  `[aria-required]` und die Formularklasse `invalid`. CF7 setzt nach dem
  Versand `form.reset()`, dadurch sinken die Labels von selbst zurück.
  Inline-CSS gibt es nur für geänderte Design-Werte (9.2) und als
  `style`-Attribut am Wrapper einer Platzierung mit eigenen Design-Werten
  (10.1), sonst ist das Plugin ohne Inline-Anteile CSP-tauglich. Auch die
  Fokus-Effekte (Glow, pulsierender Glow, umlaufendes Licht; 8) sind reines
  CSS. Scripts laufen nur im Admin:
  das Editor-Script von Modul 5 (10) und der Farbwähler der
  Einstellungsseite (9.2).
- **Fehlerfall ohne Springen:** Bei Validierungsfehlern floatet das Label
  des Feldes in der Fehlerfarbe nach oben, der CF7-Hinweis sitzt im Feld auf
  der Textzeile, und der Senden-Button wechselt Farbe und Text (8). Der
  Seitenfluss bleibt unverändert.
- **Zugänglichkeit:** Jedes umgebaute Feld bekommt ein echtes `<label for>`
  mit passender `id`. Der Placeholder wird zu einem Leerzeichen, damit
  Screenreader nicht zwei Namen vorlesen. CF7-Attribute (`aria-required`,
  `aria-invalid`, `aria-describedby`) bleiben erhalten.
- **CF7-Konventionen:** Die Steuerung je Formular läuft über die Additional
  Settings (`floating_labels: off`, `floating_labels_style: filled`, …), je
  Platzierung über das Shortcode-/Block-Attribut `html_class` (im
  Block-Editor per Panel „Floating labels“, Modul 5), je Feld über
  eine Form-Tag-Option (`nofloat`); Rangfolge in 7.5. Das CSS folgt der
  Ladepolitik von CF7 über die Action `wpcf7_enqueue_styles`.
- **Theme-freundlich:** Farben, Maße, Dauer und die Werte des
  Fokus-Effekts als Custom Properties
  `--jgor-flcf7-*`, gesetzt auf `.wpcf7`; dieselben Werte sind Felder der
  Einstellungsseite (9.2). Das Steuerelement erbt die Schrift des Themes
  (`font: inherit`, Größe über `--jgor-flcf7-font-size`). Die Variante „Minimal“ liefert nur
  Positionierung, Zustände und Label-Farben, die Optik des Feldes kommt dann
  aus dem Theme.
- **Entwickler-Filter:** `jgor_flcf7_settings` (Konfiguration aus Code),
  `jgor_flcf7_form_settings` (je Rendering), `jgor_flcf7_enabled` (je
  Formular), `jgor_flcf7_label_text` (je Feld), `jgor_flcf7_field_types`.
- **Schlank:** Eine Option (`jgor_flcf7_settings`, autoload; dazu
  `jgor_flcf7_samples` nur, solange die Beispielseite aus 9.3 angelegt ist), eine
  CSS-Datei, keine Tabellen, keine Transients, kein Cron. Der Umbau läuft
  einmal je gerendertem Formular auf einem kleinen HTML-Fragment.

## 5. Was CF7 liefert (Grundlagen)

Aus der CF7-Doku und dem Quelltext 6.2.3:

- **Form-Tags:** `[text* your-name placeholder "Your name"]`. Die Option
  `placeholder` (Alias `watermark`) gibt es für `text`, `email`, `url`,
  `tel`, `textarea`, `number`, `range`, `date` und `captchar`. CF7 schreibt
  den Wert in das Attribut `placeholder`, der Default-Wert bleibt leer.
  Optionen `id:` und `class:` setzen `id` und `class` des Steuerelements.
  Die `id:`-Option gibt `WPCF7_FormTag::get_id_option()` je Request nur
  einmal aus (statische Liste; Werte mit Präfix `wpcf7` nie): steht
  dasselbe Formular zweimal auf einer Seite, hat das Feld beim zweiten
  Rendering keine `id`, und das `<label for>` der Vorlage zeigt ins Leere
  (7.2). Unbekannte Optionen wie `nofloat` nimmt der Parser an und
  ignoriert sie;
  `WPCF7_FormTag::has_option()` liest sie.
- **Form-Tag-Syntax:** Optionen ohne Anführungszeichen (`nofloat`, `id:x`,
  `min:0`) müssen vor den Werten in Anführungszeichen stehen
  (`WPCF7_FormTagsManager::parse_atts()`). Richtig: `[text* your-name
  nofloat placeholder "Your name"]`. Steht eine Option hinter einem
  `"…"`-Wert, scheitert das Attribut-Pattern und CF7 gibt den Tag als
  Klartext aus, ohne Fehler und ohne Feld.
- **Markup je Feld:**
  `<span class="wpcf7-form-control-wrap" data-name="your-name"><input … class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required" aria-required="true" aria-invalid="false" placeholder="Your name"></span>`.
  Bei Validierungsfehlern ohne Ajax steht hinter dem Feld noch
  `<span class="wpcf7-not-valid-tip">…</span>` im Wrap. CF7-CSS setzt
  `.wpcf7-form-control-wrap { position: relative }`.
- **Standardvorlage:** `<label> Your name\n    [text* your-name autocomplete:name] </label>`.
  CF7 wendet `wpautop` an (`WPCF7_AUTOP`), gerendert also
  `<p><label> Your name<br>\n<span class="wpcf7-form-control-wrap" …>…</span> </label></p>`.
  `wpcf7_autop()` arbeitet seit CF7 5.x mit `WPCF7_HTMLFormatter`
  (Form-Tags vorher durch Platzhalter ersetzt): eine Zeile, die mit `<br />`
  endet, beginnt einen neuen Absatz. `Text<br />\n[tag]` (alte Vorlage)
  wird zu `<p>Text</p><p>[wrap]</p>`, `Text\n[tag]` zu
  `<p>Text<br>[wrap]</p>`, `Text [tag]` zu `<p>Text [wrap]</p>`; innerhalb
  eines `<label>` bleibt der Zeilenumbruch ein `<br>` (geprüft mit 6.2.3 und 6.2.4 am
  29.10.2026, Grundlage von 7.3).
- **Hooks:** `wpcf7_form_elements` (Filter, erhält das fertige Formular-HTML
  nach `wpautop` und dem Ersetzen der Form-Tags; die Priorität spielt für die
  Reihenfolge zu `wpautop` keine Rolle), `WPCF7_ContactForm::get_current()`
  liefert das Formular, `unit_tag()` die Instanz (`wpcf7-f{id}-p{post}-o{n}`,
  `n` zählt je Request hoch), `pref( 'name' )` den ersten Wert einer Zeile
  aus den Additional Settings (`null` ohne Eintrag), `scan_form_tags()` die
  Form-Tag-Objekte. `get_instance( $id )` liefert `null` für unbekannte
  Posts. `shortcode_attr( 'html_class' )` (seit CF7 5.0) liefert das
  Attribut des Shortcodes (`[contact-form-7 id="…" html_class="…"]`) bzw. des Blocks
  (Feld „Class“ im Seitenleisten-Panel „Form attributes“); CF7 schreibt die
  Klassen per `sanitize_html_class()` auf das `<form>`.
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
- **Block-Editor:** Der CF7-Block (`contact-form-7/contact-form-selector`)
  rendert denselben Shortcode, der Filter greift dort ebenso. Seine
  Attribute `htmlId`, `htmlName`, `htmlTitle`, `htmlClass` entsprechen den
  Shortcode-Attributen `html_*`; das Panel „Form attributes“ des Blocks
  zeigt `htmlClass` als Feld „Class“, die Panels erscheinen erst, wenn ein
  Formular gewählt ist (`attributes.id`), und `html_class` wird nur bei
  nicht leerem Wert in den Shortcode geschrieben. Der Shortcode-Block
  (`core/shortcode`) hat keine eigenen Einstellungen; sein Attribut `text`
  enthält den Shortcode als Klartext, WordPress parst `html_class` dort mit
  doppelten, einfachen oder ohne Anführungszeichen. Der CF7-Block hat keinen
  Server-Renderer (`block.json` ohne `render`): der Filter
  `render_block_contact-form-7/contact-form-selector` liefert den
  gespeicherten Inhalt mit noch unverarbeitetem Shortcode-Text.
  Block-Attribute aus `block.json` erreichen den Editor über
  `get_block_editor_server_block_settings()`, und der Block registriert sich
  clientseitig ohne eigene Attributliste; ein per `register_block_type_args`
  ergänztes Attribut gilt daher auch im Editor (10.1). WordPress’ Filter
  `do_shortcode_tag` reicht alle Shortcode-Attribute roh durch
  (`shortcode_parse_atts()`, Namen kleingeschrieben), auch unbekannte, die
  CF7s `shortcode_atts()` verwirft.

## 6. Modul 1: Grundgerüst (`class-jgor-flcf7-plugin.php`, `class-jgor-flcf7-settings.php`)

- Hauptdatei mit Header, Konstanten `JGOR_FLCF7_VERSION`, `JGOR_FLCF7_MIN_PHP`,
  `JGOR_FLCF7_FILE`, `JGOR_FLCF7_PATH`, `JGOR_FLCF7_URL`, Laden der Klassen
  aus `includes/` (mit `file_exists()`, fehlende Module werden übersprungen),
  Start auf `plugins_loaded` mit Priorität 20 (nach CF7), Aktivierungs-Hook
  mit PHP-Prüfung, `ensure_option()` und dem Transient für die Nachfrage
  nach der Beispielseite (9.3).
- `JGOR_FLCF7_Plugin::instance()->init()`: prüft `class_exists(
  'WPCF7_ContactForm' )` und `class_exists( 'DOMDocument' )`. Fehlt CF7, zeigt
  das Plugin einen Admin-Hinweis (`wp_admin_notice()`, nur für
  `activate_plugins`) und registriert sonst nichts (der Header `Requires
  Plugins` verhindert die Aktivierung ohne CF7 ohnehin ab WP 6.5; der Hinweis
  deckt das spätere Deaktivieren von CF7 ab). Fehlt `ext-dom`, Hinweis und
  kein Umbau; das CSS wird dann ebenfalls nicht geladen, weil es ohne Umbau
  nichts zu stylen gibt. Die Option wird in jedem Fall auf `init`
  registriert (`register_setting()`, Gruppe `jgor_flcf7`), damit der
  Sanitize-Callback auch für `update_option()` in Code und WP-CLI greift. Die
  Module 2 und 3 sowie der Server-Teil von Modul 5
  (`JGOR_FLCF7_Placement_Design`, 10.1) werden im Bootstrap auf jeder
  Anfrage gestartet, Modul 4, Modul 6 (9.3) und das Editor-Script von
  Modul 5 nur bei `is_admin()`.
- `JGOR_FLCF7_Settings` (Singleton `instance()`): Konstanten `OPTION`,
  `GROUP`, `BASE_TYPES` (text, email, url, tel, number, textarea), `STYLES`,
  `EFFECTS` (none, glow, pulse, sweep);
  Methoden `field_types()`, `defaults()`, `stored()` (Option plus Defaults,
  ohne Filter), `all()` (wirksame Werte mit Filter, gecacht), `get()`,
  `locked_keys()`, `is_locked()`, `reset_cache()`, `ensure_option()`,
  `sanitize()`. Option `jgor_flcf7_settings` (Array, autoload). Werte:

| Schlüssel | Typ | Default | Bedeutung |
| --- | --- | --- | --- |
| `mode_placeholder` | bool | true | Placeholder-Modus (7.1) |
| `mode_label` | bool | true | Label-Modus (7.2) |
| `mode_text` | bool | false | Text-Modus (7.3): Text vor dem Feld in Vorlagen ohne Label und Placeholder |
| `types` | array | text, email, url, tel, number, textarea | erlaubte Feldtypen (CF7-Basistyp) |
| `style` | string | `outlined` | `outlined`, `filled`, `minimal` |
| `effect` | string | `none` | Fokus-Effekt (8): `none`, `glow`, `pulse`, `sweep`; außer `none` als Klasse `jgor-flcf7-<effect>` am Wrap |
| `load_css` | bool | true | Plugin-CSS einbinden |
| `required_marker` | bool | true (seit 2.6, vorher false) | Stern hinter dem Label bei `aria-required` (Klasse `jgor-flcf7-marker` am Wrap) |
| `submit_error_text` | string | `Please check the highlighted fields` | Text auf dem Senden-Button bei Validierungsfehlern; leer = nur Farbwechsel. Konstante `DEFAULT_SUBMIT_ERROR_TEXT`, unübersetzt gespeichert und beim Rendern übersetzt, solange der Wert dem Default entspricht; `sanitize_text_field()`, höchstens 200 Zeichen |
| `design` | array | leer | Design-Werte Property → Wert ohne Prefix (9.2); nur gültige, nicht leere Werte, fehlender Eintrag = Default |

- Filter `jgor_flcf7_field_types`: Liste der wählbaren Basistypen (Ausgang
  `BASE_TYPES`, Ergebnis per `sanitize_key()` bereinigt, ungültiges Ergebnis
  fällt auf `BASE_TYPES` zurück). Ein per Filter ergänzter Typ wird erst
  umgebaut, wenn er in `types` angehakt oder per `jgor_flcf7_settings`
  gesetzt ist; `sanitize()` behält nur Typen aus dieser Liste, eine leere
  Liste ist gültig.
- Filter `jgor_flcf7_settings` wie in Gorres Control REST-API 14.1: Werte aus
  Code übersteuern die Option, die Oberfläche zeigt sie schreibgeschützt.
  Filterwerte durchlaufen dieselbe Sanitize-Prüfung wie Formulareingaben; ein
  gesperrter Schlüssel behält beim Speichern seinen gespeicherten Wert (sein
  Feld wird nicht übertragen). Was „gesperrt“ heißt, steht in 9.1.
- Deaktivierung hinterlässt nichts; `uninstall.php` löscht die Option (bei
  Multisite je Site, siehe 11).

## 7. Modul 2: Umbau des Markups (`class-jgor-flcf7-markup.php`)

Hook: `add_filter( 'wpcf7_form_elements', …, 20 )`. Der Filter ist nicht auf
das Frontend begrenzt. Ablauf je Formular:

1. Schneller Vorab-Test: kein String oder kein `wpcf7-form-control-wrap`
   per `strpos()` → unverändert zurück.
2. Formular über `WPCF7_ContactForm::get_current()`; kein Formular →
   unverändert. Wirksame Einstellungen für dieses Rendering bestimmen (7.5):
   Modi, Feldtypen, Variante und Pflichtstern aus der Option, übersteuert
   durch Additional Settings, `html_class` und Filter. Abgeschaltet, kein
   Modus aktiv oder kein Feldtyp erlaubt → unverändert.
3. Felder sammeln: `scan_form_tags()` → Name → Basistyp und `id:`-Option
   (für 7.2), nur die wirksamen Feldtypen. Ausgeschlossen: Tags mit
   `has_option( 'nofloat' )`, Tags ohne Namen, Namen mit zwei verschiedenen
   Basistypen (mehrdeutig). Leere Liste → unverändert.
4. HTML in DOMDocument laden (7.6), alle `span.wpcf7-form-control-wrap`
   (XPath) durchgehen. Je Wrap: `data-name` in der Feldliste, Klasse
   `jgor-flcf7-wrap` fehlt noch, Inhalt des Wraps ist genau ein
   `input`/`textarea` plus höchstens Whitespace und ein
   `span.wpcf7-not-valid-tip`. `input`-Typen `hidden`, `checkbox`, `radio`,
   `file`, `submit`, `button`, `reset`, `image`, `range`, `color` nie.
5. Label-Text bestimmen: Label-Modus (7.2) hat Vorrang (umschließendes
   Label, sonst verknüpftes Label), dann Placeholder-Modus (7.1), zuletzt
   Text-Modus (7.3). Kein Text → Feld überspringen. Text durch Filter
   `jgor_flcf7_label_text` (`$text`, `$name`, `$contact_form`); kein String
   oder leer → Feld bleibt unverändert. Whitespace (auch geschützte
   Leerzeichen) wird überall zu einem Leerzeichen zusammengezogen und
   getrimmt.
6. Umbau (7.7) mit der wirksamen Variante und dem wirksamen Pflichtstern.
   Wurde kein Feld geändert, geht das Original-HTML zurück (keine
   Neu-Serialisierung). Sonst wird jeder Senden-Button
   (`input[type="submit"].wpcf7-submit`) in `<span
   class="jgor-flcf7-submit">` gehüllt, dahinter steht `<span
   class="jgor-flcf7-submit-error" aria-hidden="true">` mit dem wirksamen
   Fehlertext (entfällt bei leerem Text); das `input` selbst bleibt
   unverändert, CF7s Spinner und Deaktivieren funktionieren weiter. Dann
   zurück zu HTML.

### 7.1 Placeholder-Modus

Das Steuerelement hat ein nicht leeres Attribut `placeholder`. Der Text wird
Label-Text; `placeholder` wird auf ein einzelnes Leerzeichen gesetzt, damit
`:placeholder-shown` weiter den Leerzustand meldet und kein zweiter Text im
Feld liegt. Keine Änderung an der Formularvorlage nötig; bestehende Formulare
mit Placeholdern bekommen die Floating Labels sofort.

### 7.2 Label-Modus

Zwei Formen der CF7-Standardvorlage:

- **Umschließendes Label:** Der Elternknoten des Wraps ist ein `<label>`.
  Erlaubte Kindknoten des Labels: Text, Kommentare, `<br>` (wird zu einem
  Leerzeichen), Inline-Elemente `strong`, `em`, `b`, `i`, `span`, `small`,
  `abbr`, `code`, `sup`, `sub`, `mark`, `u`, `s` ohne Steuerelement darin,
  und genau ein Wrap (der gerade bearbeitete). Text und Inline-Inhalte
  ergeben den Label-Text. Enthält das Label etwas anderes (zweiten Wrap,
  Block-Element, Select), wird es nicht angefasst; es gilt dann das
  verknüpfte Label oder der Placeholder-Modus. Das umschließende `<label>`
  wird durch `<span class="jgor-flcf7-field">` ersetzt (vorhandene Klassen
  des Labels werden übernommen), weil ein `<label>` kein weiteres `<label>`
  enthalten darf; nur der Wrap wandert in den Span, Text und `<br>` aus
  `wpautop` entfallen dort. Das `<p>` außen bleibt.
- **Verknüpftes Label:** Genau ein `<label for="…">` im Formular zeigt auf
  die `id` des Steuerelements (Option `id:`) und enthält selbst kein
  Steuerelement und kein Label (IDs mit Apostroph werden nicht gesucht). Das
  Label wird mitsamt Inhalt in den Wrap hinter das Steuerelement verschoben,
  Inline-Markup bleibt erhalten. Ein direkt folgendes `<br>` (aus `wpautop`)
  entfällt, ein dadurch leer gewordenes `<p>` wird entfernt. Hat der Filter
  `jgor_flcf7_label_text` den Text verändert, wird der Inhalt des Labels
  durch den neuen Klartext ersetzt; Inline-Markup geht dann verloren.
  Hat das Steuerelement keine `id`, obwohl das Form-Tag eine `id:`-Option
  trägt (CF7 gibt jede `id:` nur einmal je Seitenaufruf aus, 5), sucht das
  Plugin das Label über den Wert der Option; das Steuerelement bekommt dann
  eine ID nach 7.4, und das `for` des Labels zeigt darauf. So erhält auch
  das zweite Exemplar desselben Formulars sein Floating Label, und das
  sonst ins Leere zeigende Label der Vorlage ist korrekt verknüpft.

In beiden Fällen wird ein vorhandener Placeholder ebenfalls auf ein
Leerzeichen gesetzt, sonst lägen Placeholder und Label übereinander.

### 7.3 Text-Modus

Für bestehende Formulare ohne `<label>` und ohne Placeholder: Die
CF7-Standardvorlage vor Version 5.4 lautete `<p>Your Name (required)<br />
[text* your-name]</p>`, und so stehen Formulare auf vielen älteren Sites
noch. Option `mode_text` (6), Default aus, weil die Quelle eine Heuristik
ist; wer sie einschaltet (global oder je Formular mit `floating_labels:
text`, 7.5), prüft das Ergebnis. Greift als letzte Stufe, wenn 7.2 und 7.1
nichts liefern. Die gespeicherte Vorlage bleibt unverändert, der Umbau
passiert wie bei den anderen Modi nur beim Rendern.

Der CF7-Formatter (5) trennt `Text<br />\n[tag]` in zwei Absätze
`<p>Text</p><p>[wrap]</p>`; `Text\n[tag]` und `Text [tag]` bleiben mit dem
Wrap in einem Absatz. Deshalb zwei Suchstufen (`preceding_text()`), beide
nur, wenn der Elternknoten des Wraps ein `p` oder `div` ist (der
Wurzelknoten des Fragments ist ein `div`; in einem `<label>` gilt 7.2):

1. **Text vor dem Wrap im selben Elternknoten** (`text_before_wrap()`):
   vom Wrap aus rückwärts über die Geschwisterknoten: zuerst
   Whitespace-Textknoten, dann höchstens ein `<br>`, dann Textknoten und
   Textquellen-Elemente bis zum Anfang des Elternknotens, zu einem weiteren
   `<br>`, zu einem anderen Wrap oder zu einem sonstigen Element; Kommentare
   werden übersprungen und bleiben stehen. Die eingesammelten Knoten (samt
   dem `<br>` direkt vor dem Wrap) entfallen; ein `<br>`, das die Grenze zum
   vorigen Feld bildete, bleibt, damit die Felder untereinander stehen.
   Deckt `<p>Text<br>[wrap]</p>` (`Text\n[tag]` in der Vorlage),
   `<p>Text [wrap]</p>` und Vorlagen ohne `wpautop` ab.
2. **Absatz davor** (`text_in_previous_paragraph()`): nur wenn Stufe 1
   nichts fand, der Elternknoten ein `<p>` ist und außer dem Wrap nur
   Whitespace und Kommentare enthält. Das vorige Element-Geschwister
   (Whitespace und Kommentare dazwischen übersprungen) muss ein `<p>` sein,
   das nur aus Text, `<br>` (wird zu einem Leerzeichen), Kommentaren und
   Textquellen-Elementen besteht (`loose_text()`); sein Text wird
   Label-Text, der ganze Absatz entfällt. Deckt die alte Vorlage ab. Ein
   Erklärabsatz direkt vor einem Feld ohne eigene Beschriftung wird dabei
   ebenfalls genommen – Teil der Heuristik, deshalb Default aus; Abhilfe
   `nofloat` am Feld oder `floating_labels: off` am Formular.

Textquellen-Elemente (`is_text_source()`): die Inline-Elemente aus 7.2 und
ein `<label>` ohne `for`-Attribut (weder umschließend noch verknüpft),
jeweils ohne Steuerelement darin; das nackte `<label>` entfällt zugunsten
des echten `<label for>` im Wrap. Der gesammelte Text wird wie in 7,
Schritt 5, zusammengezogen; leer → Feld überspringen. Nur Klartext wie in
7.1, Inline-Markup geht verloren. Der Umbau folgt 7.7, das Label sitzt wie
sonst im Wrap.

Grenzen: Text hinter dem Feld zählt nie; ein vorangehendes Block-Element,
`<label for>`, Select oder ein anderer Wrap bildet die Grenze und bleibt
stehen, nur der Text dahinter zählt. Ein zweiter Wrap ohne `<br>` davor
(`<p>Name [a] [b]</p>`: „Name“ geht an [a], [b] bleibt ohne Label) und ein
Absatz vor einem Absatz mit zwei Wraps bleiben ohne Label, ebenso Wraps in
Tabellen, Listen oder `<fieldset>` (Elternknoten weder `p` noch `div`).

Zusätze wie „(required)“ aus alten Vorlagen bleiben im Label-Text und
stehen neben dem Pflichtstern; der Filter `jgor_flcf7_label_text` kann sie
entfernen, oder der Pflichtstern wird für das Formular abgeschaltet. Kein
neues CSS, keine Umwandlung gespeicherter Vorlagen (15).

### 7.4 Feld-ID

Hat das Steuerelement eine `id`, bleibt sie. Sonst
`{unit_tag}-fl-{name}`, bereinigt wie CF7 es für seine Fehler-IDs tut
(`[^0-9a-z_-]` entfernt; leere Basis → `jgor-flcf7-field`). Vor dem Umbau
werden alle IDs des Fragments gesammelt; kollidiert eine neue ID, hängt das
Plugin `-2`, `-3`, … an. Der Unit-Tag ist je Instanz eindeutig, auch wenn
dasselbe Formular zweimal auf einer Seite steht.

### 7.5 Steuerung: global, je Formular, je Platzierung, je Feld

Die wirksamen Werte eines Renderings entstehen in Schichten; jede spätere
Schicht übersteuert die frühere, ungültige Werte werden ignoriert:

1. **Option** (Modul 1): `mode_placeholder`, `mode_label`, `mode_text`,
   `types`, `style`, `effect`, `required_marker`.
2. **Additional Settings des Formulars** (Tab „Additional Settings“ im
   CF7-Editor, gelesen per `pref()`):

   ```
   floating_labels: off
   floating_labels: placeholder
   floating_labels: text
   floating_labels_style: filled
   floating_labels_effect: glow
   floating_labels_marker: on
   floating_labels_types: text email textarea
   ```

   `floating_labels: off` (auch `false`, `no`, `0`) schaltet das Formular
   ab, `placeholder`, `label` oder `text` lässt nur den genannten Modus
   zu, auch wenn die Option ihn abgeschaltet hat. `floating_labels_style`
   nimmt `outlined`, `filled`, `minimal`; `floating_labels_effect` nimmt `none`,
   `glow`, `pulse`, `sweep`; `floating_labels_marker` nimmt `on`/`off`
   (auch `true`/`false`, `yes`/`no`, `1`/`0`); `floating_labels_types` ist
   eine Liste von Basistypen, durch Leerzeichen oder Kommas getrennt, nur
   Typen aus `field_types()` zählen, eine leere Liste schaltet alle Typen
   ab. Groß-/Kleinschreibung ist egal.
3. **`html_class` der Platzierung** (Shortcode `[contact-form-7 id="…"
   html_class="…"]`, im CF7-Block das Feld „Class“ im Panel „Form
   attributes“; gelesen per `shortcode_attr( 'html_class' )`). Im
   Block-Editor setzt das Panel „Floating labels“ (Modul 5, 10) diese
   Klassen per Auswahlfeld. Erkannte Klassen: `jgor-flcf7-off`, `jgor-flcf7-on`, `jgor-flcf7-mode-placeholder`,
   `jgor-flcf7-mode-label`, `jgor-flcf7-mode-text`, `jgor-flcf7-style-outlined`,
   `jgor-flcf7-style-filled`, `jgor-flcf7-style-minimal`,
   `jgor-flcf7-effect-none`, `jgor-flcf7-effect-glow`,
   `jgor-flcf7-effect-pulse`, `jgor-flcf7-effect-sweep`,
   `jgor-flcf7-marker-on`, `jgor-flcf7-marker-off`. Dasselbe Formular kann
   so je Seite anders aussehen. Die Namen unterscheiden sich absichtlich von
   den Wrap-Klassen aus 7.7: CF7 schreibt `html_class` auf das `<form>`, eine
   Wrap-Klasse dort würde die Varianten-Regeln des CSS auf alle Felder
   anwenden. Design-Werte je Platzierung laufen nicht über Klassen, sondern
   über das Attribut `jgor_flcf7_design` bzw. das Block-Attribut
   `jgorFlcf7Design` (10.1).
4. **Filter `jgor_flcf7_form_settings`** (`$settings`, `$contact_form`):
   erhält das Array `enabled`, `mode_placeholder`, `mode_label`,
   `mode_text`, `types`,
   `style`, `effect`, `required_marker`, `submit_error_text` nach den Schichten 1–3
   und gibt es zurück; nur bekannte Schlüssel mit gültigen Werten werden
   übernommen.
5. **Filter `jgor_flcf7_enabled`** (`$enabled`, `$contact_form`): läuft
   danach, schaltet in beide Richtungen. **Filter
   `jgor_flcf7_submit_error_text`** (`$text`, `$contact_form`): zuletzt,
   für den Text des Senden-Buttons; leer = nur Farbwechsel.
6. **Je Feld:** Form-Tag-Option `nofloat`, vor den Werten in
   Anführungszeichen (5): `[text* your-name nofloat placeholder "Your
   name"]`. Außerdem fallen heraus: Feldtypen außerhalb der wirksamen
   `types`, Tags ohne Namen, mehrdeutige Namen (7, Schritt 3), Wraps mit
   unbekanntem Inhalt (7, Schritt 4).

Farben und Maße je Formular oder Platzierung setzt das Theme über die
Custom Properties (8) auf dem Formular-Wrapper, zum Beispiel
`[id^="wpcf7-f123-"] { --jgor-flcf7-border-color-active: #0a7d3e; }`, oder
über eine eigene Klasse in `html_class`.

### 7.6 Technik: DOMDocument

`WP_HTML_Tag_Processor` kann Attribute ändern, aber keine Elemente einfügen
oder verschieben; `WP_HTML_Processor` (6.4+) ebenfalls nicht. Darum
DOMDocument (`ext-dom`):

- `libxml_use_internal_errors( true )`, Laden mit vorangestelltem
  `<?xml encoding="UTF-8">` und einem Wrapper `<div id="jgor-flcf7-root">`,
  Flags `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET`,
  `formatOutput` aus; Ausgabe über `saveHTML( $root )`, Wrapper-Tags am
  Anfang und Ende abgeschnitten, `libxml_clear_errors()`, vorheriger
  Fehlermodus wiederhergestellt.
- Scheitert das Laden, fehlt der Root-Knoten oder sitzt der Wrapper nicht am
  Anfang und Ende der Ausgabe → Original-HTML.
- Leere Elemente kommen als `<input …>` ohne Schrägstrich zurück, in HTML5
  gleichwertig. Textknoten werden beim Speichern neu escaped; der Label-Text
  stammt aus einem Attribut oder aus Textknoten, die CF7 bereits escaped hat
  (UTF-8 und Entities geprüft, `Straße & Ort` → `Straße &amp; Ort`).
- Das Fragment ist klein (ein Formular), der Umbau läuft einmal je Rendern;
  kein Cache nötig.

Sollte `WP_HTML_Processor` in einer späteren WP-Version Einfügen unterstützen,
kann der Umbau darauf wechseln (Abschnitt 15).

### 7.7 Ergebnis-Markup

```html
<span class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined" data-name="your-name">
  <input type="text" name="your-name" id="wpcf7-f12-p3-o1-fl-your-name"
         class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required jgor-flcf7-control"
         aria-required="true" aria-invalid="false" placeholder=" ">
  <label for="wpcf7-f12-p3-o1-fl-your-name" class="jgor-flcf7-label">Your name</label>
</span>
```

Der Senden-Button nach dem Umbau:

```html
<span class="jgor-flcf7-submit">
  <input class="wpcf7-form-control wpcf7-submit has-spinner" type="submit" value="Send">
  <span class="jgor-flcf7-submit-error" aria-hidden="true">Please check the highlighted fields</span>
</span>
```

Das Label steht direkt hinter dem Steuerelement, damit reine
Geschwister-Selektoren reichen (`.jgor-flcf7-control:focus + .jgor-flcf7-label`,
`.jgor-flcf7-control:not(:placeholder-shown) + .jgor-flcf7-label`). Ein
`.wpcf7-not-valid-tip` (serverseitig schon vorhanden oder per JS angehängt)
landet hinter dem Label. Klassen am Wrap in dieser Reihenfolge:
`jgor-flcf7-wrap`, Variante (`jgor-flcf7-outlined`, `-filled`, `-minimal`),
bei einer `textarea` zusätzlich `jgor-flcf7-textarea`, bei wirksamem
Pflichtstern (7.5) zusätzlich `jgor-flcf7-marker`, bei wirksamem
Fokus-Effekt außer `none` zuletzt `jgor-flcf7-glow`, `-pulse` oder
`-sweep`. Die Varianten-Klasse sitzt
am Wrap, damit eine CSS-Datei alle Varianten abdeckt; die Marker- und die
Effekt-Klasse schalten Pflichtstern und Effekt im CSS, ohne dass das Plugin
eine zweite CSS-Datei oder Inline-CSS bräuchte.

## 8. Modul 3: CSS (`class-jgor-flcf7-assets.php`, `assets/css/floating-labels.css`)

- Einbinden auf `add_action( 'wpcf7_enqueue_styles', … )`:
  `wp_enqueue_style( 'jgor-flcf7', …, array( 'contact-form-7' ),
  JGOR_FLCF7_Plugin::asset_version( … ) )` – seit 2.23 Plugin-Version plus
  `filemtime()` der Datei (`1.0.0.<Zeitstempel>`), damit Browser und
  Hosting-Caches eine geänderte Datei auch ohne neue Plugin-Version laden
  (Befund des Nutzers: Glow blieb im Browser blau, obwohl der Server rot
  lieferte). Gilt für alle fünf Assets (Frontend-CSS, `admin.js`,
  `admin.css`, `block-editor.js`, `editor.css`); bei unlesbarer Datei nur
  die Plugin-Version.
  Die Abhängigkeit sorgt für die Reihenfolge hinter dem CF7-CSS. Lädt CF7 sein
  CSS nicht (`WPCF7_LOAD_CSS` false), lädt das Plugin seins auch nicht; wer
  `wpcf7_enqueue_styles()` im Template aufruft, bekommt beides. `load_css`
  false → kein Enqueue. Keine separate RTL-Datei: das CSS nutzt durchgehend
  logische Eigenschaften.
- Custom Properties auf `.wpcf7` (Themes setzen sie dort oder auf einem
  Vorfahren):

| Property | Default | Bedeutung |
| --- | --- | --- |
| `--jgor-flcf7-padding-x` | `0.75em` | horizontales Padding des Steuerelements, Ausgangspunkt des Labels |
| `--jgor-flcf7-padding-y` | `0.75em` | vertikales Padding des Steuerelements |
| `--jgor-flcf7-font-size` | `1em` | Schriftgröße des Wraps, damit Feld, Label, Tip und Paddings gemeinsam skalieren; `1em` = umgebender Text |
| `--jgor-flcf7-label-color` | `#5f6368` | Label in Ruhe |
| `--jgor-flcf7-label-color-active` | `#1a73e8` | Label bei Fokus |
| `--jgor-flcf7-label-color-error` | `#dc3232` | Label bei Fehler |
| `--jgor-flcf7-label-size` | `1em` | Schriftgröße des Labels in Ruhe |
| `--jgor-flcf7-label-scale` | `0.75` | Verkleinerung des gefloateten Labels (`transform: scale()`) |
| `--jgor-flcf7-border-width` | `1px` | Rahmenstärke |
| `--jgor-flcf7-border-color` | `#80868b` | Rahmen in Ruhe |
| `--jgor-flcf7-border-color-active` | `#1a73e8` | Rahmen bei Fokus |
| `--jgor-flcf7-border-color-error` | `#dc3232` | Rahmen bei Fehler |
| `--jgor-flcf7-radius` | `4px` | Eckenradius |
| `--jgor-flcf7-background` | `#fff` | Feldhintergrund bei Outlined und Hintergrund der Label-„Notch“ |
| `--jgor-flcf7-background-filled` | `rgba(0, 0, 0, 0.06)` | Feldhintergrund bei Filled |
| `--jgor-flcf7-duration` | `0.15s` | Dauer der Übergänge |
| `--jgor-flcf7-submit-error-background` | `#dc3232` | Senden-Button bei Validierungsfehlern |
| `--jgor-flcf7-submit-error-color` | `#fff` | Fehlertext auf dem Senden-Button |
| `--jgor-flcf7-effect-color` | `#dc3232` (bis 2.17 `#1a73e8`) | Farbe von Glow und umlaufendem Licht; rot, damit sich der Effekt vom blauen aktiven Rahmen abhebt |
| `--jgor-flcf7-effect-size` | `8px` | Unschärfe des Glows (`box-shadow`, Spread ein Viertel davon); der Puls schwillt zwischen der Hälfte und dem Doppelten |
| `--jgor-flcf7-effect-width` | `3px` (bis 2.18 `2px`) | Breite des Rings beim umlaufenden Licht |
| `--jgor-flcf7-effect-duration` | `1.5s` | Dauer eines Pulses bzw. einer Umrundung |

  Gegenüber 1.2 neu: `padding-y`, `border-width`, `background-filled`,
  `label-scale`, seit 2.2 `font-size`, seit 2.6 die vier `effect-*`;
  `--jgor-flcf7-label-size-floated` entfällt, weil das Label
  beim Floaten nicht die Schriftgröße wechselt, sondern per `transform:
  scale()` schrumpft (die em-basierten Abstände bleiben so stabil, und der
  Übergang läuft ohne Layout-Neuberechnung).
- **Design-Werte aus der Option (9.2):** Modul 3 hängt per
  `wp_add_inline_style( 'jgor-flcf7', … )` eine Regel `.wpcf7 { … }` mit
  nur den vom Default abweichenden Properties hinter das Stylesheet
  (`inline_css()`); nichts bei Defaults, nichts bei `load_css` aus.
- **Gemeinsame Regeln:** `.jgor-flcf7-field { display: block }` (Ersatz für
  das umschließende Label); `.jgor-flcf7-wrap { display: block; position:
  relative; font-size: var(--jgor-flcf7-font-size) }`; Steuerelement `display: block; box-sizing: border-box; width:
  100%; max-width: 100%; margin: 0; padding: var(--jgor-flcf7-padding-y)
  var(--jgor-flcf7-padding-x); font: inherit` (Label und Eingabe teilen so
  Schrift und Zeilenhöhe). Label `position: absolute; pointer-events: none`,
  Ruheposition auf der ersten Textzeile des Steuerelements (`top: padding-y +
  border-width`, `line-height: inherit`) statt vertikal mittig: CF7 hängt den
  Fehler-Tip in den Wrap, der dadurch höher wird, und eine 50-%-Position
  würde dann wandern; eine `textarea` braucht so keine Sonderregel.
  Horizontal `inset-inline-start: padding-x − 0.25em` mit `padding: 0
  0.25em` (Luft für die Notch), `max-width` auf die Innenbreite des Feldes,
  `white-space: nowrap; overflow: hidden; text-overflow: ellipsis`,
  `transform-origin: 0 50%` (RTL über `.wpcf7[dir="rtl"]`: `100% 50%`),
  Transition auf `top`, `transform`, `color`, `background-color`.
- **Zustände:** `:focus + label` (aktive Farbe), `:not(:placeholder-shown) +
  label` (gefloatet), `:autofill + label` als eigene Regel je Variante (ein
  Browser ohne `:autofill` verwirft sonst die ganze Selektorliste), Fehler
  über `.wpcf7-not-valid` oder `[aria-invalid="true"]` (Label- und
  Rahmenfarbe, Label floatet wie bei Fokus), Pflichtstern
  `.jgor-flcf7-marker .jgor-flcf7-control[aria-required="true"] +
  .jgor-flcf7-label::after { content: " *" }`.
- **Fehler-Tip im Feld:** `.wpcf7-not-valid-tip` liegt absolut im Wrap auf
  der Textzeile des Steuerelements (gleiche `top`-Formel wie das ruhende
  Label je Variante), `pointer-events: none`, Fehlerfarbe, einzeilig mit
  Ellipse; solange das Feld leer ist, am Zeilenanfang (wo das Label ruhte),
  bei vorhandenem Wert (`:not(:placeholder-shown) ~ .wpcf7-not-valid-tip`)
  rechtsbündig mit `max-width: 50%`, damit der Eingabetext frei bleibt. Die
  Seite springt dadurch nicht. CF7s eigene Tip-Regeln, auch die der
  Formularklasse `use-floating-validation-tip`, werden durch die höhere
  Spezifität überstimmt.
- **Senden-Button bei Fehlern:** `.jgor-flcf7-submit` ist `inline-grid`,
  Button und Fehlertext teilen sich eine Zelle, deren Breite der breitere
  von beiden bestimmt; bei `form.invalid` (CF7 setzt die Klasse per JS und
  beim Versand ohne JS) wird der Button `width: 100%`, Hintergrund und
  Rahmen `--jgor-flcf7-submit-error-background`, eigener Text transparent,
  und der Fehlertext (`display: flex`, zentriert, `font: inherit`, nicht
  klickbar) liegt darüber. Der CF7-Spinner wird absolut neben die Hülle
  gesetzt, damit er die Zellenbreite nicht verfälscht. Beim nächsten
  Absenden (`submitting`) kehrt der Normalzustand zurück.
- **Outlined:** Rahmen rundum (`border-width`, `border-color`), Radius,
  Hintergrund `--jgor-flcf7-background`; gefloatet `top: 0; transform:
  translateY(-50%) scale(var(--jgor-flcf7-label-scale))` mit demselben
  Hintergrund als „Notch“ auf der Rahmenlinie (wie im Welpentheme).
- **Filled:** kein Rahmen, untere Linie `border-width + 1px`, Radius nur
  oben, Hintergrund `--jgor-flcf7-background-filled`; Padding des
  Steuerelements oben `padding-y + 0.6em`, unten `padding-y − 0.3em`, damit
  der Eingabetext unter dem gefloateten Label Platz hat; Label ruht auf
  `padding-y + 0.6em`, gefloatet `top: 0.1em; transform: scale(…)` innerhalb
  der Fläche.
- **Minimal:** keine Farben, Rahmen oder Hintergründe am Steuerelement, nur
  Geometrie und Zustände; die Label-Farben aus den Properties gelten weiter.
  Der Wrap bekommt oben Platz für das gefloatete Label (`padding-block-start:
  label-size × label-scale × 1.6`), das Label ruht entsprechend tiefer auf
  der ersten Textzeile und floatet über das Feld (`top: 0;
  inset-inline-start: 0; transform-origin: 0 0`, RTL `100% 0`).
- **Fokus-Effekte (seit 2.6, Option `effect`):** Die Effekt-Klasse
  (`jgor-flcf7-glow`, `-pulse`, `-sweep`) sitzt am Wrap neben der
  Varianten-Klasse; ohne sie gilt nichts aus diesem Abschnitt. Glow:
  `.jgor-flcf7-control:focus { box-shadow: 0 0 effect-size
  calc(effect-size / 4) effect-color }` (seit 2.18 mit Spread, damit der
  Glow über das Fokus-Outline eines Themes hinausreicht). Puls: Keyframes
  `jgor-flcf7-pulse` zwischen halbem Glow (Blur `effect-size / 2`) und
  doppeltem Glow (Blur `effect-size * 2`, Spread `effect-size / 2`),
  `ease-in-out infinite`, Dauer `effect-duration` (seit 2.18; bis dahin 0
  bis `effect-size`, im Outline-Band kaum sichtbar). Umlaufendes
  Licht: Ring am `::before` des Wraps (ein `input` trägt keine
  Pseudo-Elemente), `z-index: 1` über dem Steuerelement (seit 2.18: Twenty
  Twenty-Five zeichnet auf `:focus` ein 2 px dickes Outline genau über das
  Ringband, der Ring war dahinter unsichtbar; das Label liegt mit `z-index:
  2` darüber): `inset: -effect-width`, `padding: effect-width`,
  `conic-gradient(from var(--jgor-flcf7-angle), color-mix(in srgb,
  effect-color 30%, transparent) 0 60%, effect-color 88% 100%)` (seit 2.19:
  geschlossener Ring in 30 % der Effektfarbe, darüber der Komet mit Schweif
  über 28 % des Kreises und vollem Kopf von 12 %; 2.18 nur der Komet,
  davor Einblendung über 30 % ohne vollen Kopf), per `mask: linear-gradient(#000 0 0) content-box,
  linear-gradient(#000 0 0); mask-composite: exclude` (plus
  `-webkit-mask-composite: xor`) auf den äußeren Rand beschnitten, bei
  `:focus-within` sichtbar und per Keyframes `jgor-flcf7-sweep` gedreht;
  `@property --jgor-flcf7-angle { syntax: "<angle>" }` macht den Winkel
  animierbar. Radius des Rings `radius + effect-width`, bei Filled unten
  eckig, bei Minimal beginnt der Ring unter dem Platz für das gefloatete
  Label. Das gefloatete Label der Outlined-Variante malt über den Ring wie
  über den Rahmen. Ohne `@property` steht der Ring, ohne `mask-composite`
  fehlt er (beides seit 2024 bzw. 2023 in allen Evergreen-Browsern). Das
  Fokus-Outline des Themes bleibt erhalten (Zugänglichkeit); beim Lauflicht
  wird es auf dem fokussierten Steuerelement per `outline-color:
  color-mix(in srgb, currentColor 30%, transparent)` gedimmt (seit 2.19,
  Wunsch des Nutzers: das Licht fiel neben dem schwarzen Outline kaum auf),
  bei Glow und Puls bleibt es unverändert.
- `@media (prefers-reduced-motion: reduce)` schaltet die Transitionen von
  Label und Steuerelement ab; der Puls wird zum stehenden Glow, das
  umlaufende Licht zum stehenden Ring. RTL über logische Eigenschaften
  (`inset-inline-start`, `padding-block-*`, `margin-block-start`) und
  `transform-origin` je Schreibrichtung.
- Die Klasse `jgor-flcf7-textarea` hat in 1.0 keine eigene Regel (das Label
  liegt ohnehin auf der ersten Zeile); sie steht Themes für eigene Regeln
  bereit.
- Browser: `:placeholder-shown`, Custom Properties und logische Eigenschaften
  sind seit 2020 in allen Evergreen-Browsern; `:has()` wird in 1.0 nicht
  gebraucht.

## 9. Modul 4: Einstellungsseite (`class-jgor-flcf7-admin.php`)

- Untermenü „Floating Labels“ im CF7-Menü: Elternslug über
  `$admin_page_hooks` ermittelt, `wpcf7-dashboard` (CF7 6.2.3), sonst `wpcf7`
  (ältere CF7-Versionen), sonst `options-general.php`. Registrierung auf
  `admin_menu` mit Priorität 20 (CF7 registriert mit 9), Slug
  `wpcf7-floating-labels`, Capability `manage_options`. Formular an
  `options.php` mit `settings_fields( 'jgor_flcf7' )`,
  `do_settings_sections()`, `submit_button()`; `register_setting()` und
  `sanitize()` kommen aus Modul 1.
- Unter der Überschrift (nach `settings_errors()`, seit 2.14 auf Wunsch des
  Nutzers) ein Absatz: die Werte dieser Seite sind die Standardwerte für
  alle Formulare, übersteuerbar je Formular (Additional Settings) und je
  Platzierung im Block-Editor (Panels „Floating labels“), mit Verweis auf
  die Sektion „Per form, per placement, per field“. Der Hinweistext
  `text_per_placement()` nennt seit 2.14 auch die Reset-Buttons (10).
- Fünf Sektionen: „Label sources“ (Checkboxen `mode_label`,
  `mode_placeholder` und `mode_text`, letztere „Text before the field“ mit
  Hinweis auf Vorlagen ohne Label und Placeholder, Default aus), „Field
  types“ (Checkboxen aus `field_types()`, Beschriftung Text, Email, URL,
  Telephone, Number, Text area jeweils mit dem
  Form-Tag-Typ in Klammern, per Filter ergänzte Typen nur mit ihrem
  Schlüssel; alle abgewählt = Floating Labels aus), „Appearance“ (Radio für
  die Variante mit Kurzbeschreibung, Radio „Focus effect“ für `effect`
  mit Kurzbeschreibung und Hinweis auf Design und Reduced Motion, Checkbox
  Stylesheet laden, Checkbox
  Pflichtstern, Textfeld „Submit button on errors“ für
  `submit_error_text`), „Design“ (9.2) und „Per form, per placement, per
  field“ (Hinweistext zu den Steuerwegen aus 7.5 mit Codebeispielen für
  Additional Settings, `html_class`-Klassen und `nofloat` vor den Werten in
  Anführungszeichen, dazu die Filter).
- Aus Code gesperrte Werte (`is_locked()` / `locked_keys()` der
  Settings-Klasse) erscheinen `disabled` ohne `name`-Attribut, mit Hinweis
  unter dem Feld und einer Info-Notice oben; sie werden nicht übertragen,
  `sanitize()` setzt für sie den gespeicherten Wert (`stored()`) ein. Die
  Seite zeigt den wirksamen Wert (`get()`), nicht den gespeicherten.
- „Settings saved.“: WordPress zeigt die Meldung nach `options.php` nur auf
  Seiten unter „Einstellungen“ selbst an. Das Modul ergänzt sie bei
  `settings-updated` über `add_settings_error()` / `settings_errors()`,
  außer wenn das Fallback-Elternmenü `options-general.php` aktiv ist.
- „Settings“-Link in der Pluginliste (`plugin_action_links_…`, nur mit
  `manage_options`), URL über `menu_page_url()`.
- Kontexthilfe: der Reiter „Hilfe“ der Seite (`add_help_tab()` auf
  `load-{hook}`) mit „Overview“, „Per form and placement“ und „Design“
  sowie einer Seitenleiste mit Links zur Plugin-Seite und zur CF7-Doku.
  Die Codebeispiele kommen für Seite und Hilfe aus denselben Methoden.
- Die Seite nutzt die WordPress-Klassen `form-table`, `description`,
  `large-text code`, `notice`. Dazu seit 2.6 `assets/css/admin.css`
  (Handle `jgor-flcf7-admin`, nur auf dem Hook der Seite, nach dem
  Farbwähler-Stylesheet): die Fieldsets mit der Klasse
  `jgor-flcf7-columns` (Feldtypen sowie die vier Design-Zeilen Farben,
  Maße, Floated Label/Animation, Glow/Running light) sind ein Grid
  `repeat(auto-fill, 16em)` mit `max-width: 51em`: jede Spalte ist 16em
  breit, bei zwei wie bei drei Spalten, höchstens drei nebeneinander,
  schmale Fenster bekommen zwei oder eine. Die Zeile „Glow and running
  light“ trägt zusätzlich `jgor-flcf7-columns--4` (`max-width: 68.5em`),
  damit ihre vier Felder bei genug Breite in einer Reihe stehen (ab etwa
  1400 px Fensterbreite; darunter brechen sie wie die anderen auf drei
  Spalten um). Die Typen-Checkboxen stehen
  ohne `<br>` als Grid-Elemente. Seit 2.20 trägt die Überschrift der Box
  „Sample page“ (9.3) die Klasse `jgor-flcf7-samples__title` mit `margin:
  0 0 0.75em` statt der WordPress-Klasse `title`: die floatet eine
  Überschrift nach links (für „Titel + Button“), der Text floss daneben
  (Sichtprüfung des Nutzers). Das einzige
  Admin-Script ist der Farbwähler der Design-Felder (9.2).

### 9.1 Konfiguration aus Code (`jgor_flcf7_settings`)

```php
add_filter( 'jgor_flcf7_settings', function ( $settings ) {
    $settings['style']    = 'minimal';
    $settings['load_css'] = false;
    return $settings;
} );
```

Der Filter erhält das vollständige Array (gespeicherte Werte plus Defaults)
und gibt es zurück; welche Schlüssel er „gesetzt“ hat, ist daran nicht
ablesbar. Gesperrt ist deshalb jeder Schlüssel, dessen gefilterter Wert nach
Sanitize vom gespeicherten Wert abweicht. Folgen:

- Setzt der Filter einen Wert, der ohnehin gespeichert ist, gilt der
  Schlüssel als frei und ist auf der Einstellungsseite änderbar. Speichert
  jemand dort einen anderen Wert, gewinnt beim nächsten Laden der Filter,
  und die Seite zeigt den Schlüssel ab dann gesperrt mit dem Filterwert.
- Ein ungültiger Filterwert (Tippfehler in `style`, unbekannter Typ) wird
  wie eine Formulareingabe bereinigt und fällt auf den gespeicherten Wert
  zurück; der Schlüssel bleibt frei.

### 9.2 Design-Einstellungen

Vorgezogen aus 1.1 am 22.10.2026 (Commit b21db52): Farben, Maße und
Animation sollen ohne CSS einstellbar sein.

- Option-Schlüssel `design` (6): Array Property → Wert ohne Prefix, nur
  gültige, nicht leere Werte; fehlender Eintrag = Default. Konstanten in der
  Settings-Klasse: `DESIGN_DEFAULTS` (22 Properties in der Reihenfolge des
  `.wpcf7`-Blocks, seit 2.6 mit den vier `effect-*`; `css_defaults()`
  liefert sie mit Prefix,
  `verify-admin.php` prüft den Gleichstand mit der CSS) und `DESIGN_TYPES`.
  Sanitize je Typ in `sanitize_design_value()`: `color` = `#` mit drei oder
  sechs Hexziffern, kleingeschrieben (elf Properties); `length` = Zahl mit
  `px`, `em`, `rem`, `%` oder `0` (acht); `number` = 0,5 bis 1 mit
  höchstens zwei Nachkommastellen (`label-scale`); `time` = Zahl mit `s`
  oder `ms` (`duration`, `effect-duration`). Ungültiges wird zu „Default“. `design()` liefert
  die wirksamen Werte (Defaults plus Option plus Filter),
  `design_overrides()` nur die abweichenden. Der Filter
  `jgor_flcf7_settings` sperrt den Schlüssel als Ganzes (9.1).
- Einstellungsseite: Sektion „Design“ mit vier Feldzeilen „Colours“ (zehn
  Felder mit dem WordPress-Farbwähler: Script `jgor-flcf7-admin`,
  `assets/js/admin.js`, Abhängigkeit `wp-color-picker`, nur auf dem Hook der
  Seite geladen; `data-default-color` bei Hex-Defaults, das Filled-Default
  `rgba(0, 0, 0, 0.06)` nur als Hinweis), „Sizes“ (sechs Textfelder mit dem
  Default als Placeholder, darunter die Feld-Schriftgröße), „Floated
  label and animation“ (Zahlenfeld 0,5–1 in 0,01-Schritten, Textfeld für
  die Dauer) und seit 2.6 „Glow and running light“ (Farbwähler, zwei
  Größenfelder, Dauer; `DESIGN_GROUPS['design_effect']`), dazu die Zeile
  „CSS for themes“ mit der schreibgeschützten
  Textarea der wirksamen Werte (`css_snippet()`). Die Felder jeder Zeile
  stehen in Spalten (9, `admin.css`). Leere Felder = Default;
  gesperrt (`is_locked( 'design' )`) sind alle 22 Felder `disabled` ohne
  `name`.
- Ausgabe (Modul 3, 8): `inline_css()` baut `.wpcf7 { --jgor-flcf7-…: …; }`
  nur aus `design_overrides()` und hängt die Regel per
  `wp_add_inline_style( 'jgor-flcf7', … )` hinter das Stylesheet; nichts
  bei Defaults, nichts bei `load_css` aus. Das ist das einzige Inline-CSS
  des Plugins; eine strikte CSP braucht dafür `'unsafe-inline'` oder eine
  Nonce in `style-src`, was WordPress für seine eigenen Inline-Styles
  ohnehin voraussetzt.
- Neue Property `--jgor-flcf7-font-size` (Default `1em`) auf dem Wrap:
  Steuerelement, Label, Tip und Paddings sind em-basiert und skalieren
  gemeinsam; `1em` behält die Schriftgröße des umgebenden Textes.
- Je Platzierung lassen sich dieselben Properties im Block-Editor setzen
  (10.1); sie werden als `style`-Attribut am Wrapper des Formulars ausgegeben
  und gewinnen gegen Option und Inline-Regel.

### 9.3 Beispielseite (Modul 6, `class-jgor-flcf7-samples.php`)

Auf Wunsch des Nutzers vom 29.10.2026 (Konzept 2.15): eine optionale
Beispielseite mit Formularen, die die Optionen des Plugins zeigen – zum
Ausprobieren nach der Installation, jederzeit wieder entfernbar.

- **Inhalt:** Seite „Testpage Floating Labels for CF7“, Slug
  `testpage-floating-labels-for-cf7`, Status `private` (nur angemeldete
  Benutzer mit `read_private_pages` sehen sie; auf Live-Sites keine
  öffentliche Spam-Fläche, Veröffentlichen bleibt dem Nutzer überlassen).
  Sieben Formulare, Titel „FL Test <Name> - may be deleted“, alle mit
  `skip_mail: on` in den Additional Settings (kein Mailversand):

| Name | Vorlage | Additional Settings | Zeigt |
| --- | --- | --- | --- |
| Default template | CF7-Standardvorlage (`WPCF7_ContactForm::get_template()`) | – | Label-Modus, Variante und Effekt der Einstellungsseite |
| Placeholders | Felder mit `placeholder`-Option | – | Placeholder-Modus |
| Old template | `<p>Text<br />[tag]</p>` wie vor CF7 5.4 | `floating_labels: text` | Text-Modus (7.3) unabhängig von der Option |
| Filled and glow | wie Default | `floating_labels_style: filled`, `floating_labels_effect: glow` | Variante Filled, Effekt Glow |
| Minimal and running light | wie Placeholders | `floating_labels_style: minimal`, `floating_labels_effect: sweep`, `floating_labels_marker: off` | Variante Minimal, Effekt Sweep, ohne Pflichtstern |
| Mixed | verknüpftes `<label for>`, `nofloat`, `<strong>` im Label, `tel`, `number`, Select, Checkbox, Datei | `floating_labels_effect: pulse` | Grenzen des Umbaus, Effekt Puls |
| Off | wie Placeholders | `floating_labels: off` | Formular ohne Floating Labels |

  Die Seite: je Formular eine Überschrift (H2), ein Absatz mit einem Satz
  Erklärung und der CF7-Block (`contact-form-7/contact-form-selector` mit
  `id` und `title`); beim alten Formular stehen dazwischen (seit 2.22,
  Wunsch des Nutzers) zwei Code-Blöcke (`core/code`, Inhalt per
  `esc_html()`) mit der alten Vorlage `<p>Your Name (required)<br />[text*
  your-name] </p>` und dem heutigen `<label> Your name [text* your-name
  autocomplete:name] </label>`, jeweils mit einem übersetzbaren Satz davor;
  am Ende zwei Platzierungsbeispiele mit dem Formular
  „Default template“: ein CF7-Block mit `htmlClass`
  `jgor-flcf7-style-filled jgor-flcf7-effect-glow` und `jgorFlcf7Design`
  (`border-color`, `font-size`) sowie ein Shortcode-Block mit
  `html_class="jgor-flcf7-style-minimal"` und
  `jgor_flcf7_design="border-color:#0a7d3e;font-size:1.1em"` (10, 10.1).
  Überschriften und Erklärungen übersetzbar (Text-Domain), die
  Formularvorlagen englisch.
- **Option** `jgor_flcf7_samples` (nicht autoload): `page` (ID) und `forms`
  (IDs in der Reihenfolge der Tabelle). Sie existiert nur, solange die
  Beispielseite angelegt ist, und ist die einzige Quelle beim Entfernen:
  gelöscht werden genau diese IDs (`wp_delete_post( $id, true )`,
  endgültig; Formulare sind Posts vom Typ `wpcf7_contact_form`). Fehlt die
  Seite (von Hand gelöscht), gilt die Beispielseite als nicht angelegt;
  verwaiste Formular-IDs werden beim nächsten Anlegen entfernt und die
  Option neu geschrieben. Anlegen bei vorhandener Seite tut nichts (keine
  Duplikate).
- **Box „Sample page“** auf der Einstellungsseite (9), unter dem
  Hinweisabsatz und über dem Formular der Settings API, als eigenes
  `<form method="post" action="admin-post.php">`: Status („not created“
  bzw. „created“ mit Links „View“ und „Edit“), Button „Create sample page
  and forms“ bzw. „Remove sample page and forms“ (`button-secondary`),
  `wp_nonce_field( 'jgor_flcf7_samples' )`, verstecktes
  `action=jgor_flcf7_samples` und `do=create|remove`. Handler auf
  `admin_post_jgor_flcf7_samples`: `check_admin_referer()`, dann
  `current_user_can( 'manage_options' )` und `wpcf7_edit_contact_forms`
  (CF7-Capability, Standard `publish_pages`) bzw. beim Entfernen
  `wpcf7_delete_contact_forms` und `delete_pages`; Eingaben nur per
  `sanitize_key()`; Rückkehr per `wp_safe_redirect()` zur
  Einstellungsseite mit `jgor_flcf7_samples=created|removed|exists|
  absent|missing|failed`, dort als Notice über `add_settings_error()` (wie
  „Settings saved.“); nach `wp_safe_redirect()` beendet `exit` die Anfrage
  (ein `wp_die()` würde den 302 durch 500 ersetzen – die eine bewusste
  Ausnahme). Die Box hängt an der Action `jgor_flcf7_settings_page_top`,
  die `render_page()` zwischen Hinweisabsatz und Formular auslöst.
  Ohne CF7 (`WPCF7_ContactForm` fehlt) zeigt die Box nur einen Hinweis.
- **Nachfrage nach der Aktivierung:** der Aktivierungs-Hook setzt den
  Transient `jgor_flcf7_activated` (eine Stunde); solange er besteht und
  keine Beispielseite angelegt ist, zeigt Modul 6 auf `admin_notices` (nur
  `manage_options`, nur mit aktivem CF7) den Hinweis „Floating Labels for
  CF7: create a private sample page with forms that show the options?“ mit
  zwei Links auf `admin-post.php` (`do=create` und `do=dismiss`, beide mit
  Nonce). Beide löschen den Transient, „Not now“ sonst nichts. Keine
  Abfrage während der Aktivierung selbst (WordPress bietet dort keinen
  Dialog).
- **Deinstallation** (12): `uninstall.php` liest `jgor_flcf7_samples`,
  löscht die gespeicherten Posts endgültig und dann die Option; ohne
  Option nichts. Deaktivieren lässt alles stehen.
- **Abgrenzung:** `tests/create-test-forms.php` (13) bleibt das
  Entwicklungswerkzeug für `verify-markup.php` (Formulare A–F, Seite
  „Contact“); die Beispielseite ist Nutzer-Funktion mit eigenen Titeln.
- **Sicherheit:** Nonce und Capability vor jeder Aktion, Posts nur über
  die WordPress-API (`wp_insert_post()`, `WPCF7_ContactForm::save()`),
  Titel und Inhalte feste Strings aus dem Plugin, Ausgabe mit `esc_*`.
- **PHPStan/PHPCS:** `stubs/cf7.php` um `WPCF7_ContactForm::get_template()`,
  `set_properties()`, `save()`, `delete()` und `hash()`; die
  CF7-Capabilities als `custom_capabilities` in `phpcs.xml.dist`. Befund
  bei der Umsetzung: nach `save()` kennt das CF7-Objekt seinen Hash nicht,
  die Klasse lädt das Formular per `get_instance()` neu.

## 10. Modul 5: Block-Seitenleiste (`class-jgor-flcf7-editor.php`, `assets/js/block-editor.js`)

Vorgezogen aus 1.1 am 22.10.2026 (Commit a820fd7, Shortcode-Block am selben
Abend): Wer den CF7-Block oder den Shortcode-Block mit dem Formular markiert,
erwartet die Einstellungen unter „Block“ in der Seitenleiste, nicht in
einem Klassenfeld oder im Shortcode-Text.

- `JGOR_FLCF7_Editor` lädt auf `enqueue_block_editor_assets` das Script
  `assets/js/block-editor.js` (Handle `jgor-flcf7-editor`, Abhängigkeiten
  `wp-hooks`, `wp-compose`, `wp-element`, `wp-components`,
  `wp-block-editor`, `wp-i18n`, Version `JGOR_FLCF7_Plugin::asset_version()`
  (8), Footer), aber
  nur, wenn der Block `contact-form-7/contact-form-selector` registriert ist
  (`WP_Block_Type_Registry`); Übersetzungen per
  `wp_set_script_translations()` aus `languages/` bzw. den Sprachpaketen;
  per `wp_add_inline_script( …, 'before' )` bekommt das Script die
  Property-Liste `window.jgorFlcf7EditorData` für die Design-Panels (10.1).
  Start im Bootstrap zusammen mit Modul 4 unter `is_admin()`.
- Das Script braucht keinen Build-Schritt: es nutzt die globalen
  `wp.*`-Pakete und `wp.element.createElement` statt JSX. Es hängt sich per
  `wp.hooks.addFilter( 'editor.BlockEdit', 'jgor-flcf7/block-editor', … )`
  mit einer Higher-Order-Component an alle Blöcke, gibt für fremde Blöcke
  die Original-Komponente zurück und ergänzt ein `InspectorControls`-Panel
  „Floating labels“ (`PanelBody`, offen; die Felder stehen direkt im Panel
  und bekommen den Abstand des Block-Inspectors von 16 px wie die Panels
  der Core-Blöcke; eine `Flex`-Hülle mit eigenem Gap hatte den Abstand bis
  2.5 verdoppelt)
  beim CF7-Block, sobald ein Formular gewählt ist (`attributes.id`, wie die
  CF7-Panels selbst), und beim Shortcode-Block (`core/shortcode`), sobald
  sein Text einen `[contact-form-7 …]`-Shortcode enthält.
- Fünf `SelectControl`-Felder, je eine Gruppe der Platzierungsklassen aus
  7.5, erster Eintrag immer „Default“ (keine Klasse der Gruppe, es gelten
  Website- und Formular-Einstellungen):

| Feld | Werte | Klassen |
| --- | --- | --- |
| Floating labels | Default, On, Off | `jgor-flcf7-on`, `jgor-flcf7-off` |
| Label source | Default, Form template labels only, Placeholders only, Text before the field only | `jgor-flcf7-mode-label`, `jgor-flcf7-mode-placeholder`, `jgor-flcf7-mode-text` |
| Variant | Default, Outlined, Filled, Minimal | `jgor-flcf7-style-outlined`, `-filled`, `-minimal` |
| Focus effect | Default, None, Glow, Pulsing glow, Running light | `jgor-flcf7-effect-none`, `-glow`, `-pulse`, `-sweep` |
| Required fields | Default, Asterisk on, Asterisk off | `jgor-flcf7-marker-on`, `jgor-flcf7-marker-off` |

- Speicherort ist nur, was CF7 ohnehin liest (`storeFor()`): beim CF7-Block
  das Attribut `htmlClass`, das CF7 als `html_class` des Shortcodes und als
  Klasse des `<form>` ausgibt und im Panel „Form attributes“ als Feld
  „Class“ zeigt, beide Ansichten bleiben synchron; beim Shortcode-Block das
  Attribut `html_class` des ersten CF7-Shortcodes im Text
  (`shortcodeHtmlClass()` liest doppelte, einfache und fehlende
  Anführungszeichen, `withShortcodeHtmlClass()` schreibt mit doppelten,
  ersetzt an Ort und Stelle, hängt vor `]` bzw. `/]` an und entfernt das
  Attribut, wenn keine Klasse mehr übrig ist; Text ohne Shortcode bleibt
  unverändert). `withGroupValue()` ersetzt die erste Klasse der Gruppe an Ort
  und Stelle, verwirft weitere Klassen derselben Gruppe, hängt eine fehlende
  an und entfernt die Gruppe bei „Default“; fremde Klassen bleiben
  unverändert. `currentValue()` liest wie der Server die letzte Klasse der
  Gruppe. Ein Hinweistext im Panel nennt je Block das Feld „Class“ bzw. das
  Attribut `html_class`. Serverseitig ändert sich nichts (7.5, Schicht 3);
  ohne das Plugin bleiben die Klassen wirkungslos.
- Reset (seit 2.13, Schritt 11): unter den fünf Auswahlfeldern ein
  `Button` „Reset this placement“ (`variant: 'secondary'`, mit Rahmen –
  seit 2.24, weil der rahmenlose `tertiary`-Button im `disabled`-Zustand wie
  eine Beschriftung wirkte und der Nutzer ihn übersah; nicht
  `isDestructive`), der alle Klassen der fünf Gruppen aus `htmlClass`
  bzw. `html_class` entfernt (`withoutGroups()`; fremde Klassen bleiben,
  das Attribut entfällt, wenn nichts übrig ist) und die Design-Werte der
  Platzierung löscht (`writeDesign( {} )`: Block-Attribut `undefined`,
  Shortcode-Attribut entfernt). Inaktiv (`disabled`), solange weder eine
  Klasse der Gruppen noch ein Design-Wert gesetzt ist. Danach gelten
  wieder Website- und Formular-Einstellungen; der Speicherort bleibt.
- Die Helfer liegen auf `window.jgorFlcf7BlockEditor`, damit das
  Node-Prüfscript sie ohne Browser aufrufen kann (13).
- Eigenes CSS nur für die Farbzeilen der Design-Panels:
  `assets/css/editor.css` (gleiches Handle `jgor-flcf7-editor`,
  Abhängigkeit `wp-components`, zusammen mit dem Script geladen; 10.1).
  Ein Shortcode in einem Absatz-Block (ohne
  Shortcode-Block) bekommt kein Panel, dort gelten `html_class="…"` und
  `jgor_flcf7_design="…"` von Hand.

### 10.1 Design je Platzierung

Ergänzt am 22.10.2026 (Commit cbc7ca7): Farben, Maße und Animation (9.2)
sollen auch je Platzierung im Block-Panel einstellbar sein, nicht nur
global.

- Vier weitere Panels unter „Floating labels“, alle `PanelBody` und
  geschlossen (einklappbar): „Floating labels: colours“ (zehn Farbzeilen),
  „Floating labels: sizes“ (sechs `TextControl`-Felder), „Floating labels:
  animation“ (`label-scale` als Zahlenfeld 0,5–1, `duration` als
  Textfeld) und seit 2.6 „Floating labels: focus effect“ (eine Farbzeile,
  zwei Größenfelder, Dauer). Eine Farbzeile ist die Komponente
  `ColorControl` des Scripts: ein `Button` über die ganze Breite mit
  `ColorIndicator` (schraffiert, solange nichts gesetzt ist), 8 px Abstand
  (`editor.css`) und dem Namen
  der Property, der per `Dropdown` (Popover links neben der Seitenleiste)
  die `ColorPalette` der Komponenten öffnet, mit den Gruppen Theme,
  Default und Custom aus `wp.blockEditor.useSettings( 'color.palette.*',
  'color.defaultPalette' )` (`paletteGroups()`, WordPress 6.5+), freier
  Farbe, ohne Alpha, löschbar; nur Hex-Farben werden übernommen
  (`colorValue()`). Bis 2.5 war das Panel ein `PanelColorSettings` aus
  `wp.blockEditor`, das in WordPress 7.1 ein `ToolsPanel` rendert: immer
  ausgeklappt, nicht einklappbar. Leer = Wert der Einstellungsseite, der als Platzhalter steht.
  Die Property-Liste (Schlüssel, Gruppe, Typ, übersetztes Label, wirksamer
  Wert) liefert `JGOR_FLCF7_Editor::inline_data()` aus
  `JGOR_FLCF7_Admin::DESIGN_GROUPS`, `design_label()` und
  `JGOR_FLCF7_Settings::design()` als `window.jgorFlcf7EditorData`; ohne
  die Daten zeigt das Script nur das erste Panel.
- Speicherort (`storeFor()`): beim CF7-Block das Block-Attribut
  `jgorFlcf7Design` (Objekt Property → Wert), das
  `JGOR_FLCF7_Placement_Design` serverseitig per `register_block_type_args`
  am Block deklariert (5); der gespeicherte Shortcode bleibt unverändert,
  der Inhalt bleibt ohne Plugin gültig, ohne Werte verlässt das Attribut
  den Block (`undefined`). Beim Shortcode-Block das Attribut
  `jgor_flcf7_design="label-color:#123456;font-size:1.1em"` im Text,
  gelesen und geschrieben wie `html_class` (`shortcodeDesign()`,
  `withShortcodeDesign()`, entfällt ohne Werte); von Hand auch im
  klassischen Editor nutzbar. `cleanDesign()` behält nur bekannte
  Properties mit nicht leeren Werten und entfernt Zeichen, die den
  Shortcode brechen würden; die Werte selbst prüft der Server.
- Reset je Panel (seit 2.13, Schritt 11): am Ende jedes Design-Panels ein
  `Button` „Reset colours“ / „Reset sizes“ / „Reset animation“ / „Reset
  focus effect“ (`variant: 'secondary'`, 2.24), der nur die Properties dieser
  Gruppe aus den Design-Werten entfernt (`designWithoutGroup()`);
  inaktiv, solange die Gruppe keinen Wert hat. „Reset this placement“ im
  Panel „Floating labels“ (10) löscht alle vier Gruppen zusammen mit den
  Klassen.
- Server (`class-jgor-flcf7-placement-design.php`, auf jeder Anfrage
  gestartet): `render_block_contact-form-7/contact-form-selector` schreibt
  das Block-Attribut zur Laufzeit als `jgor_flcf7_design` in den ersten
  CF7-Shortcode des Blockinhalts (nichts wird gespeichert; ein schon
  vorhandenes Attribut bleibt). `do_shortcode_tag` liest bei
  `contact-form-7` das Attribut, `parse()`/`sanitize()` prüfen jeden Wert
  mit `JGOR_FLCF7_Settings::sanitize_design_value()` und ordnen nach dem
  `.wpcf7`-Block der CSS, `style()` baut `--jgor-flcf7-…: …;`, und die Regel
  landet als `style`-Attribut am Wrapper `<div class="wpcf7 no-js" …>`, wo
  das Stylesheet seine Defaults setzt; die Platzierung gewinnt so gegen
  Option und Inline-Regel aus 9.2. Nichts bei ungültigen Werten, nichts bei
  `load_css` aus. CSP-Vorbehalt wie in 9.2 (`style`-Attribute brauchen
  `'unsafe-inline'` oder `'unsafe-hashes'` in `style-src`).
- Unverändert: die Steuerung aus 7.5 (Klassen) und die globale Option
  (9.2); `html_class` und `jgor_flcf7_design` stehen unabhängig
  nebeneinander.

## 11. Multisite

Option je Site, keine netzwerkweiten Einstellungen. `uninstall.php` läuft bei
Multisite über alle Sites (`get_sites()`, `switch_to_blog()` /
`restore_current_blog()` paarweise).

## 12. Deinstallation (`uninstall.php`)

`defined( 'WP_UNINSTALL_PLUGIN' ) || exit;`, dann je Site (bei Multisite
über `switch_to_blog()`): Beispielseite und -formulare aus
`jgor_flcf7_samples` endgültig löschen (9.3, nur die gespeicherten IDs),
`delete_option()` für `jgor_flcf7_samples` und `jgor_flcf7_settings`,
`delete_transient( 'jgor_flcf7_activated' )`. Keine Tabellen, keine
Dateien.

## 13. Prüfung

- `composer check` (PHPCS/WPCS, PHPStan Level 6 mit `stubs/cf7.php`) vor
  jedem Commit.
- `wp plugin check` auf dem gebauten ZIP: `TERM=xterm ./build.sh` prüft die
  Version in Header, Konstante, `Stable tag` und Changelog und packt
  `plugin/` ohne die `.distignore`-Einträge nach
  `dist/floating-labels-for-cf7-<version>.zip`; das ZIP in einen frischen
  Ordner entpacken und `wp plugin check <Ordner> --path=<Site>` laufen
  lassen (`Tested up to` nur in `readme.txt`, Kurzbeschreibung höchstens
  150 Zeichen). Stand 29.10.2026: 17 Dateien, 72 KB (mit Modul 5,
  Design-Einstellungen, Design je Platzierung, `admin.css`, `editor.css`,
  Fokus-Effekten, Text-Modus, Reset-Buttons und Beispielseite; `.pot`
  192 Texte, per `--slug` mit korrektem Support-Link), ohne Befund; am 22.10.2026 ebenso
  ohne Befund auf dem Symlink-Ordner der Testsite und auf der
  Hostsharing-Site.
- Testsite `floating-labels-for-cf7.gorres.local` (CF7, Plugin Check, Query
  Monitor, Symlink auf `plugin/`, Abschnitt 2). Testformulare legt
  `tests/create-test-forms.php` an (idempotent über den Titel) und setzt sie
  auf die Seite „Contact“ (Slug `contact`,
  `https://floating-labels-for-cf7.gorres.local/index.php/contact/`):
  (a) „Contact form 1“, die CF7-Standardvorlage (Label-Modus), (b) „FL Test
  B Placeholder“ (nur Placeholder), (c) „FL Test C Mixed“ (verknüpftes
  `<label for>` mit `id:`, `nofloat`, umschließendes Label mit `<strong>`,
  `tel`, `number` mit `min:`/`max:`, Select, Checkbox, Datei), (d) „FL Test D
  Off“ (b mit `floating_labels: off`), (e) Formular (b) ein zweites Mal auf
  derselben Seite, (f) „FL Test F Old template“ (alte CF7-Vorlage mit
  „(required)“, zwei Felder ohne `<p>`, `Website [url]` in einer Zeile,
  Erklärabsatz, `<label>Nickname</label>` ohne `for`; Text-Modus 7.3).
  Seit 2.21 ruft `verify-markup.php` das Script selbst auf (eingebunden mit
  `$GLOBALS['jgor_flcf7_test_forms_quiet']`, Funktion
  `jgor_flcf7_create_test_forms()`) und entfernt B, C, D, F und die Seite
  am Ende wieder (`jgor_flcf7_remove_test_forms()`); `FLCF7_KEEP=1` lässt
  sie zum Nachsehen stehen. Nur „Contact form 1“ bleibt. Auf der Site
  liegen damit dauerhaft keine Testformulare mehr; was Besucher sehen
  sollen, zeigt die Beispielseite (9.3).

```bash
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/create-test-forms.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-markup.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-admin.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-editor.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-design.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-samples.php
node --check plugin/assets/js/block-editor.js && node tests/verify-editor-js.mjs
```

- `tests/verify-markup.php` rendert die Formulare per `form_html()` und
  prüft 111 Punkte (Exit-Code ungleich 0 bei Fehlern): Label- und
  Placeholder-Modus, Ersatz des umschließenden Labels, Label-Text aus der
  Vorlage, Varianten- und Textarea-Klasse, IDs aus dem Unit-Tag, `for` =
  `id`, `placeholder=" "`, unberührte Felder (Submit, `nofloat`, Select,
  Checkbox, Datei), erhaltene ARIA-Attribute, `floating_labels:
  off|placeholder|label`, Filter `jgor_flcf7_enabled` und
  `jgor_flcf7_label_text` (auch Rückgabe leer), Typen-Einstellung ohne
  `textarea`, Formular (d) byteidentisch zum Original (Unit-Tag
  normalisiert), zwei Instanzen mit verschiedenen IDs, zweites Rendering
  von (c) ohne CF7-`id` (Label über die `id:`-Option gefunden, ID aus dem
  Unit-Tag, `<br>` entfernt, vier Labels), UTF-8 und Entities,
  Text-Modus an (f) (Default aus → byteidentisch, `floating_labels: text`
  mit sieben Labels, fünf entfernte Textabsätze von 14, Erklärabsatz
  unberührt, Label ohne `for` ersetzt, `jgor-flcf7-mode-text`, `mode-label`
  übersteuert, (a) und (b) unberührt, Option an mit Vorrang des
  Label-Modus, Filter `jgor_flcf7_form_settings` und
  `jgor_flcf7_label_text`),
  Steuerung je Formular (`floating_labels_style`, `_marker`, `_types`, leere
  Liste, ungültiger Wert), je Platzierung (`html_class`-Klassen samt
  Rangfolge über das Formular und `jgor-flcf7-on` gegen `floating_labels:
  off`), Filter `jgor_flcf7_form_settings` (Vorrang vor `html_class`,
  `enabled` false, ungültige Werte), Fokus-Effekt (ohne Klasse per
  Default, `floating_labels_effect`, `jgor-flcf7-effect-*` mit Rangfolge
  über das Formular, `effect-none`, Filter, unbekannte Werte), Senden-Button (Hülle mit Fehlertext,
  Filter `jgor_flcf7_submit_error_text`, leerer Text ohne Fehler-Span,
  Option wird bereinigt), Design je Platzierung (10.1: `parse()`,
  `serialize()`, `style()`; Shortcode-Attribut → `style` am Wrapper mit nur
  gültigen Werten in CSS-Reihenfolge, ohne Attribut nichts; Block-Attribut
  → Shortcode-Attribut per `render_block()`, vorhandenes Attribut bleibt,
  nur ungültige Werte → unverändert; nichts bei `load_css` aus).
  `FLCF7_DUMP=1` gibt das HTML der Formulare (a) und (c) aus.
- `tests/verify-admin.php` prüft Modul 4 ohne Browser (59 Punkte): Box
  „Sample page“ zwischen Hinweisabsatz und Formular samt zweitem Nonce,
  Ergebnis-Notice von Modul 6, Menü
  unter `wpcf7-dashboard`, `wpcf7` und dem Fallback samt Seiten-URL,
  Kontexthilfe (Hook, drei Reiter, escapete Beispiele samt Effekt- und
  Text-Modus-Klassen, Hinweis auf die Option im Reiter „Overview“,
  Snippet, Seitenleiste), Farbwähler-Assets und `admin.css` (Grid-Regel
  mit 16em-Spalten) nur auf dem Seiten-Hook, fünf Sektionen
  und 14 Feldzeilen (Design-Gruppen decken jede Property genau einmal ab),
  das gerenderte Formular (Hinweisabsatz unter der Überschrift, Ziel
  `options.php`, Nonce, Defaults, Radios für
  Variante und Effekt, Checkbox `mode_text` unangehakt mit Hinweis auf
  alte Vorlagen, `for`/`id`,
  escapete Codebeispiele, Snippet in der Textarea, fünf Spaltengruppen
  ohne `<br>` zwischen den Typen, Design-Felder: elf Farbfelder
  mit Picker-Klasse und Hex-Defaults, zehn Größen- und Zeitfelder mit
  Placeholder, Zahlenfeld, 22 Labels), gesperrte Werte (disabled, ohne `name`, Hinweise,
  Notice, Design-Felder, Snippet mit wirksamem Wert), Capability (`wp_die()`
  ohne `manage_options`), „Settings“-Link und den Gleichstand von
  `css_defaults()` mit dem `.wpcf7`-Block der CSS. Das
  Script lädt `wp-admin/includes/plugin.php` und `template.php` und
  simuliert das CF7-Menü über `$admin_page_hooks` / `$_parent_pages`.
  Die PHP-Prüfscripte zählen Fehler in `$GLOBALS`, weil `wp eval-file` die
  Datei in einem Funktionsscope ausführt; der Exit-Code ist bei Fehlern
  ungleich 0. `verify-markup.php` und `verify-admin.php` sichern die
  gespeicherte Option, prüfen mit den Defaults und stellen die Option danach
  wieder her (seit 22.10.2026; vorher setzten sie die Defaults zurück, was
  eine im Browser gemachte Einstellung wie den Pflichtstern verwarf).
- `tests/verify-design.php` prüft die Design-Einstellungen ohne Browser
  (29 Punkte): Property-Listen (22, Typen 11/8/1/2), Sanitize je Typ
  (gültig und ungültig, auch die `effect-*`), Setting `effect` (Auswahl,
  Default `none`),
  Sanitize-Callback der Option, `design()` und `design_overrides()`,
  `inline_css()` und der Inline-Style hinter dem Stylesheet (leer bei
  Defaults, nur Abweichungen, ungültige Optionswerte verworfen, nichts bei
  `load_css` aus), Sperre per Filter, die Effekt-Regeln (Glow, Puls,
  `@property`, Ring mit `mask-composite`, Reduced Motion) und die
  `font-size`-Regel in der CSS.
- `tests/verify-editor.php` prüft Modul 5 ohne Browser (28 Punkte): Klasse
  geladen, CF7-Block registriert, Hook, Enqueue (Quelle, Abhängigkeiten,
  Version, Footer, Übersetzungen), Stylesheet `editor.css` (Quelle,
  Abhängigkeit `wp-components`, Version, Datei mit den Farbzeilen-Regeln),
  Inline-Daten (`window.jgorFlcf7EditorData`
  als JSON vor dem Script, alle 22 Properties, Gruppen 10/6/2/4 in der
  Reihenfolge der Einstellungsseite, Typ, Label, wirksamer Wert als
  Platzhalter), Block-Attribut `jgorFlcf7Design` am CF7-Block und in
  `get_block_editor_server_block_settings()`, Script-Datei (CF7-Block,
  Shortcode-Block, Filter, alle 14 Platzierungsklassen, Design-Attribute,
  Daten-Global, `ColorPalette` und `useSettings` statt
  `PanelColorSettings`, keine `Flex`-Hüllen, Reset-Buttons samt Helfern,
  Text-Domain an jedem `__()`
  und `_x()`) und kein
  Enqueue ohne registrierten Block (Registry wird wiederhergestellt).
- `tests/verify-editor-js.mjs` prüft das Editor-Script unter Node (99
  Punkte): die `wp.*`-Pakete sind Stubs, `createElement` liefert einfache
  Objekte (Funktionskomponenten wie die Farbzeile werden beim Suchen im
  Baum aufgerufen), `useSettings` liefert eine kleine Palette, die
  Server-Daten sind mit den 22 Properties gestubbt; geprüft
  werden Filter-Registrierung, Gruppen und Klassen (14, Effekt-Gruppe,
  Eintrag „Text before the field only“ samt Auswahl),
  `paletteGroups()`,
  `splitClasses()`, `currentValue()`, `withGroupValue()` (ersetzen,
  anhängen, entfernen, Duplikate, fremde Klassen), `shortcodeHtmlClass()`
  und `withShortcodeHtmlClass()` (Anführungszeichen, anhängen, ersetzen,
  entfernen, umgebender Text, `/]`, Text ohne Shortcode), die
  Higher-Order-Component (fremder Block, CF7-Block ohne und mit Formular,
  Shortcode-Block ohne und mit CF7-Shortcode), das Panel (Titel, fünf
  Felder direkt im Panel ohne `Flex`, Werte aus `htmlClass` bzw.
  `html_class`, Hinweistexte),
  `setAttributes()` nach einer Auswahl (auch Effekt), die Design-Helfer (`cleanDesign()`,
  `parseDesign()`, `serializeDesign()`, `colorValue()`,
  `shortcodeDesign()`, `withShortcodeDesign()`), die vier Design-Panels
  als geschlossene `PanelBody` (Farbzeilen als `Dropdown` mit Button,
  Indikator, Name, Palette-Gruppen und Hex-Werten, kein
  `PanelColorSettings`; Größen- und Animationsfelder mit Platzhaltern,
  Zahlenfeld, Effekt-Panel mit Farbzeile und drei Feldern, Hinweise,
  Speichern als Block-Attribut bzw.
  Shortcode-Attribut, Entfernen ohne Werte), die Reset-Buttons (10, 10.1: inaktiv ohne Werte; „Reset this placement“
  entfernt Klassen und Design-Werte und lässt fremde Klassen stehen, je
  Design-Panel nur die eigene Gruppe; CF7- und Shortcode-Block) und das
  Script ohne Server-Daten (nur das erste Panel). `node --check` läuft
  davor.
- `tests/verify-samples.php` prüft Modul 6 ohne Browser (37 Punkte):
  Anlegen (Seite privat mit Slug, sieben Formulare mit Suffix und
  `skip_mail: on`, Blöcke und Platzierungsbeispiele im Inhalt, Option),
  zweiter Aufruf ohne Duplikate, von Hand gelöschte Seite → Option
  bereinigt, Box und Notice (Zustände, Nonce, Links), Capability (Handler
  bricht ohne `manage_options` ab), Entfernen (Posts endgültig weg, Option
  weg). Räumt nach sich auf; eine vorher vorhandene Beispielseite legt das
  Script am Ende wieder an (neue IDs).
- Sichtprüfung auf Bodhran headless (`chromium --headless=new
  --ignore-certificate-errors --screenshot`), interaktiv im Browser auf
  Silentium (Frontend und Einstellungsseite
  `wp-admin/admin.php?page=wpcf7-floating-labels`). Browser-Matrix vor dem
  Release von Hand in Firefox und Edge auf Silentium (der Playwright-MCP
  steht auf Bodhran ohne Chrome nicht zur Verfügung, playwright-core mit dem
  System-Chromium schon, siehe `docs/screenshots-admin-erzeugen.js`):
  Ruhezustand, Fokus per Klick
  und per Tab, Eingabe, Autofill, Fehlerfall (Pflichtfeld leer abschicken →
  Label rot oben, Tip im Feld, Senden-Button rot mit Fehlertext), Versand
  mit `skip_mail: on` → Labels sinken zurück, Einstellungsseite speichern
  und Hilfe-Reiter öffnen, Block-Editor: CF7-Block markieren, Panel
  „Floating labels“ unter „Block“, Auswahl ändern, Feld „Class“ unter „Form
  attributes“ zeigt die Klasse; Shortcode-Block mit `[contact-form-7 …]`
  markieren, Auswahl ändern, `html_class` erscheint im Shortcode-Text; Seite
  speichern und Frontend prüfen; Design: Farbe wählen, Größe eintragen,
  speichern, Frontend und Inline-Style im Quelltext prüfen; Design je
  Platzierung: im Block-Panel eine Farbe und eine Größe setzen, speichern,
  Frontend: `style`-Attribut am Wrapper nur dieses Formulars.
- Assets: `python3 docs/assets-erzeugen.py` (Pillow, Noto Sans) zeichnet das
  Motiv „Feld bekommt Fokus, Label floatet auf die Rahmenlinie, Text wird
  getippt, Schleife“ mit den Default-Farben aus 8 und schreibt `icon.svg`
  (CSS-Keyframes, bei `prefers-reduced-motion` still), `icon-128x128.png`
  und `icon-256x256.png` sowie `banner-772x250.png` und
  `banner-1544x500.png` als APNG (wie Block Inserter Filter; WP.org liefert
  PNGs unverändert aus). `docs/screenshots-erzeugen.sh` legt das Formular
  „FL Screenshot“ (John Doe, john@example.com, leeres Pflichtfeld „Your
  message“) und drei Seiten `flcf7-shot-*` an, fotografiert Outlined,
  Filled und Minimal headless (1280 px, Twenty Twenty-Five, Senden-Button
  mit `class:wp-element-button`), baut den Fehlerfall aus dem gerenderten
  HTML nach (Formularklasse `invalid`, CF7-Fehlerklassen und Tip am
  Pflichtfeld; die Datei liegt kurz im Webroot, weil Schriften per CORS nur
  von derselben Herkunft laden), schneidet auf 780 px Breite zu und entfernt
  Seiten und Formular wieder. Icons und Banner haben einen dünnen schwarzen
  Rand mit gerundeten Ecken, außerhalb transparent.
  `node docs/screenshots-admin-erzeugen.js` erzeugt die Screenshots 5 und 6:
  Es legt ein temporäres Admin-Konto (`jgor-shottest`, Locale `en_US`,
  Anzeigename „Jane Doe“) und eine Entwurfsseite an, nimmt das Formular
  „Contact form 1“ (legt es nur an, wenn es fehlt), meldet sich mit
  playwright-core und `/usr/bin/chromium` headless an (1280 px, Query
  Monitor ausgeblendet) und fotografiert die Einstellungsseite als
  Ganzseiten-Aufnahme bis zum Ende der Farbfelder mit geöffnetem
  Farbwähler (Screenshot 5, 1280×1881) sowie den Block-Editor mit per
  `wp.blocks.createBlock()` eingefügtem CF7-Block, eingeklapptem CF7-Panel,
  geöffnetem Panel „Floating labels: colours“, geparktem Mauszeiger
  und zur Seitenleiste „Floating labels“ gescrollt (Screenshot 6,
  1280×1400); gespeichert wird nichts, danach entfernt es Seite, Konto und
  ein selbst angelegtes Formular wieder. playwright-core kommt aus einer
  globalen Installation oder aus dem npx-Cache des Playwright-MCP.
- Ohne `ext-dom` (per `wp eval` mit Klassen-Stub nicht simulierbar; Codepfad
  per PHPStan und Lesen prüfen): Hinweis im Admin, Formular unverändert.
- Gegenprobe auf der Hostsharing-Testsite `<hs-testsite>`
  (PHP 8.4, Produktiv-Stack) vor jedem Release. Stand nachziehen mit
  `local-sites-hostsharing.sh dateien … --ueberschreiben` und `datenbank …
  --ueberschreiben` (beides startet der Nutzer; `datenbank` bringt seit
  2.21 die Beispielseite (9.3) mit, bis dahin Seite „Contact“ und
  Testformulare; das Script sichert den Remote-Stand vorher). Prüfung nur lesend: per `ssh <hs-admin>` mit `php8.4
  /home/pacs/<hs-paket>/bin/wp --path=…/<webroot>` (`plugin list`, `plugin
  check`) und per Abruf der Kontaktseite (seit 29.10.2026 mit `-u
  jgorres:…`, Basic-Auth, 2); das Formular-HTML wird nach Normalisierung
  von URL und Unit-Tag mit der lokalen Seite verglichen.
  22.10.2026, nach dem Fehlerfall erneut mit `dateien`: alle zehn
  Plugin-Dateien md5-identisch, fünf Formulare identisch (Senden-Hülle und
  Fehlertext vorhanden), Plugin Check ohne Befund. 23.10.2026 nach Konzept
  2.7 erneut mit `dateien` (der `dateien`-Lauf braucht kein
  HSAdmin-Passwort und läuft aus dem Bash-Tool, Probelauf mit `--dry-run`
  zuerst): 16 Plugin-Dateien identisch, Plugin Check ohne Befund; mit
  `datenbank --ueberschreiben --ja` danach Option und sechs Formulare
  identisch (die Meldung „Site-URL: ?“ des Scripts ist harmlos, `siteurl`
  und `home` stimmen). `datenbank` ist nur
  nötig, wenn sich Seiten oder Formulare ändern; neue Optionsschlüssel
  kommen über die Defaults. 29.10.2026 nach Schritt 10 erneut beides
  (Details in §14, Schritt 10): 16 Plugin-Dateien identisch, Plugin Check
  remote ohne Befund, sechs Formulare und Kontaktseite identisch. Nach
  Schritt 12 (29.10.2026, 15:32) erneut `dateien`: 17 Dateien identisch,
  Plugin Check remote ohne Befund, Basic-Auth-Dateien erhalten (2). Die Warnung des Scripts zu `rewrite flush` ist
  harmlos: keine `.htaccess`, Permalinks `/index.php/…`.
- Testeinstellungen und Testformulare nach der Prüfung entfernen.

## 14. Reihenfolge der Umsetzung

1. Gerüst (Header, Konstanten, Bootstrap, Settings-Klasse, Hinweis ohne
   CF7), `stubs/cf7.php`, `composer install`, Commit. Erledigt am
   22.10.2026: GitHub-Remote (privat, Commit a8cdc4d), lokale Testsite und
   Hostsharing-Testsite (Abschnitt 2), Gerüst (Commit 4a1c2c2; `composer
   check` ohne Befund, lokal aktiviert, Sanitize, Filter-Sperre und Hinweis
   ohne CF7 per wp-cli geprüft).
2. Modul 2 Markup: Placeholder-Modus, dann Label-Modus, Ausnahmen, Filter.
   Prüfung per `wp eval`. Erledigt am 22.10.2026 (Commit 124beaa, Prüfscripte
   756f4c1): 42 Prüfungen PASS, `composer check` ohne Befund. Erweitert am
   22.10.2026 um die Steuerung je Formular und Platzierung (7.5): 58
   Prüfungen PASS. Verknüpftes Label beim zweiten Rendering desselben
   Formulars (7.2, Fund der Hostsharing-Gegenprobe) am 22.10.2026
   nachgezogen: 69 Prüfungen PASS.
3. Modul 3 CSS: Variante Outlined, dann Filled und Minimal, Fehlerzustand,
   Reduced Motion, RTL. Erledigt am 22.10.2026 (Commit aac696a), Sichtprüfung
   lokal; Browser-Matrix und Screenshots mit Schritt 5.
4. Modul 4 Einstellungsseite mit Sperren aus Code (9, 9.1). Erledigt am
   22.10.2026 (Commit 2082122): 33 Prüfungen PASS, `composer check` ohne
   Befund; Sichtprüfung der Seite im Browser steht aus.
5. Release-Vorbereitung, Stand 22.10.2026:
   1. `readme.txt` (Commit f39cea5), Plugin Check ohne Befund. Erledigt.
   2. Kontexthilfe (Commit 61cf91b), `verify-admin.php` 39 Prüfungen. Erledigt.
   3. `.pot` (Commit cf0d9cc), 74 Texte, seit dem Fehlerfall 78, seit
      Modul 5 89, seit den Design-Einstellungen 115, seit 2.6 140.
      Erledigt; vor jedem Release neu erzeugen.
   4. Assets (Commit dfd3c79): animiertes Icon, animierte Banner,
      Screenshots 1–4 per Script. Screenshots 5 (Einstellungsseite mit
      Farbwähler) und 6 (Panels im CF7-Block) am 23.10.2026 per
      `docs/screenshots-admin-erzeugen.js` erzeugt. Erledigt.
   5. `build.sh` und Plugin Check auf dem ZIP ohne Befund,
      Hostsharing-Gegenprobe bestanden. Fehlerfall am 22.10.2026 ergänzt
      (Commit ba9a53c: Tip im Feld, Label floatet, Senden-Button mit
      Fehlertext; 62 und 40 Prüfungen, Plugin Check ohne Befund);
      Hostsharing danach am 22.10.2026 erneut nachgezogen und geprüft
      (Dateien und Formular-HTML identisch, Plugin Check ohne Befund).
      Offen: Browser-Matrix in Firefox und Edge auf Silentium (Nutzer),
      dabei auch den Fehlerfall live prüfen.
   6. Veröffentlichung: Testformulare und Testseiten entfernen, Repo
      öffentlich stellen, Release 1.0.0 (Git-Tag, GitHub-Release, SVN nach
      README des Repos), Einreichung bei WordPress.org; danach GlotPress
      (de/default, de/formal).
6. Modul 5 Block-Seitenleiste (10), vorgezogen aus 1.1. Erledigt am
   22.10.2026 (Commit a820fd7, Shortcode-Block im Folge-Commit): 16 und 47
   Prüfungen PASS, `composer check` ohne Befund, Plugin Check auf dem ZIP
   ohne Befund. Screenshot 6 seit 23.10.2026 per Script (5.4). Offen:
   Sichtprüfung im Block-Editor (Nutzer). Hostsharing am 22.10.2026 mit `dateien` nachgezogen und lesend
   geprüft (13 Dateien identisch, `verify-editor.php` remote 16 PASS,
   Plugin Check ohne Befund).
7. Design-Einstellungen (9.2), vorgezogen aus 1.1. Erledigt am 22.10.2026
   (Commit b21db52): 26 und 50 Prüfungen PASS, `composer check` ohne
   Befund, Plugin Check auf dem ZIP ohne Befund. Offen: Sichtprüfung der
   Felder und des Farbwählers auf Silentium (Nutzer; Screenshot 5 zeigt
   sie seit 23.10.2026). Hostsharing am 22.10.2026 nachgezogen (Kontaktseite identisch bis
   auf `required_marker`); nach dem Fix in 7.2 erneut mit `dateien`
   nachziehen.
8. Design je Platzierung (10.1). Erledigt am 22.10.2026 (Commit cbc7ca7):
   82, 23 und 76 Prüfungen PASS, `composer check` ohne Befund, Plugin Check
   auf dem ZIP (14 Dateien) ohne Befund, `.pot` 120 Texte. Offen:
   Sichtprüfung der Panels auf Silentium (Nutzer; Screenshot 6 zeigt sie
   seit 23.10.2026), Hostsharing mit `dateien` nachziehen.
9. Nachbesserungen aus der Sichtprüfung des Nutzers vom 23.10.2026
   (Commits a89fbe0, b655073, 87c17f9; Konzept 2.6): Block-Panels ohne
   `Flex`-Hüllen und „Floating labels: colours“ als einklappbares
   `PanelBody` mit eigenen Farbzeilen (10, 10.1), Spalten fester Breite
   auf der Einstellungsseite und Pflichtstern als Default (9, 6),
   Fokus-Effekte Glow, Puls und umlaufendes Licht mit vier neuen
   Properties (6, 7.5, 8, 9.2, 10). Erledigt: Prüfscripte 88/54/29/27/86
   PASS, `composer check` und PHPCS auf `tests/` ohne Befund, `.pot` 140
   Texte, ZIP mit 16 Dateien und Plugin Check ohne Befund, Screenshots 5
   und 6 neu. Hostsharing am 23.10.2026 mit `dateien --ueberschreiben --ja`
   nachgezogen (Sicherung vorher, 3 neue und 11 geänderte Dateien): alle
   16 Plugin-Dateien md5-identisch, Plugin Check auf dem Server ohne
   Befund; danach auch `datenbank --ueberschreiben --ja` (lokale und
   Remote-DB gesichert, 36 URL-Ersetzungen): Option, Formulare und
   Kontaktseite (sechs Formulare) identisch, Site-URL korrekt. Offen: Sichtprüfung von
   Effekten, Spalten und Panels in
   Chromium, Firefox und Edge auf Silentium (Nutzer), Screenshots 1–4
   zeigen noch keinen Pflichtstern
   (bei Bedarf per `docs/screenshots-erzeugen.sh` neu).
10. Text-Modus (7.3) für bestehende Formulare mit der alten CF7-Vorlage,
    Konzept 2.8 vom 29.10.2026, vor der Veröffentlichung (5.6). Erledigt am
    29.10.2026 in vier Teilen: Modul 2 mit zwei Suchstufen, Option
    `mode_text` (6), `floating_labels: text` und `jgor-flcf7-mode-text`
    (7.5), Testformular (f) „FL Test F Old template“ (Commit 7e4568f,
    Konzept 2.9; Befund dabei: der CF7-Formatter trennt `Text<br />[tag]`
    in zwei Absätze, 5); Checkbox „Text before the field“ in „Label
    sources“, Sektions-Intro, Hilfe „Overview“ und Beispiele (9, Commit
    16b82f0); Eintrag „Text before the field only“ im Block-Panel (10, 14
    Platzierungsklassen, Commit a21c14b); readme (Beschreibung, Features,
    Per form/placement, Installation, FAQ „My forms come from an older
    Contact Form 7 …“), `.pot` 144 Texte, ZIP 16 Dateien, Plugin Check ohne
    Befund (Commit 20e5fda). Prüfscripte 111/55/29/27/88 PASS, `composer
    check` und PHPCS auf `tests/` ohne Befund; nach dem Update der lokalen
    Site auf CF7 6.2.4 alle PHP-Prüfscripte erneut ohne Befund. Hostsharing
    am 29.10.2026 nachgezogen: `dateien --ueberschreiben --ja` (Sicherung
    38 MB vorher, 15 neue und 517 geänderte Dateien, 0 Löschungen; bringt
    CF7 6.2.4 und Query Monitor 4.1.1 mit), alle 16 Plugin-Dateien
    md5-identisch, Plugin Check auf dem Server ohne Befund; `datenbank
    --ueberschreiben --ja` (lokale und Remote-DB gesichert, 36
    URL-Ersetzungen): Option mit `mode_text` false, Formular F (ID 71) und
    die Kontaktseite mit sechs CF7-Formularen (A–E und F) nach
    Normalisierung identisch mit lokal. Offen: Sichtprüfung des Text-Modus
    im Frontend (Nutzer; auf der Kontaktseite bleibt F ohne Labels, solange
    `mode_text` aus ist).
11. Reset-Buttons im Block-Editor (10, 10.1), Konzept 2.13 vom 29.10.2026
    auf Wunsch des Nutzers: „Reset this placement“ im Panel „Floating
    labels“ (Klassen der fünf Gruppen und alle Design-Werte), „Reset
    colours/sizes/animation/focus effect“ je Design-Panel (nur diese
    Gruppe), beide inaktiv ohne Werte; Helfer `withoutGroups()` und
    `designWithoutGroup()` auf `window.jgorFlcf7BlockEditor`, Prüfungen in
    `verify-editor-js.mjs` und `verify-editor.php` (Text-Domain-Zählung),
    readme („Per placement“, FAQ Block-Editor), Kontexthilfe
    (`text_per_placement()`), `.pot`, ZIP, Hostsharing `dateien`. Kein
    Server-Code. Erledigt am 29.10.2026 (Commit 87a3b8d und Folge-Commits
    für Hinweisabsatz und Doku, Konzept 2.14): `verify-editor-js.mjs` 99,
    `verify-editor.php` 28, dazu der Hinweisabsatz auf der Einstellungsseite
    (9, `verify-admin.php` 56), readme, Kontexthilfe, `.pot` 150 Texte,
    ZIP 16 Dateien, Plugin Check ohne Befund. Hostsharing mit Schritt 12
    nachgezogen (29.10.2026). Offen: Sichtprüfung der Buttons im
    Block-Editor (Nutzer).
12. Beispielseite (9.3), Konzept 2.15 vom 29.10.2026 auf Wunsch des
    Nutzers. Erledigt am 29.10.2026 (Commits 652b5fe Teil 1, Teil 2 und
    Doku-Commit, Konzept 2.16): Klasse `JGOR_FLCF7_Samples` mit Anlegen,
    Entfernen, Option, Box, Handler, Notice und Transient im
    Aktivierungshook, Bootstrap unter `is_admin()`, `uninstall.php`,
    Stubs, `phpcs.xml.dist`; `verify-samples.php` 37 und `verify-admin.php`
    58 Prüfungen, alle anderen Prüfscripte unverändert grün; readme
    (Features, Installation Schritt 5, FAQ „Is there a sample page …“,
    Requirements), Kontexthilfe „Overview“, `.pot` 192 Texte, ZIP 16 +
    1 = 17 Dateien, Plugin Check ohne Befund. Hostsharing am 29.10.2026 mit
    `dateien --ueberschreiben --ja` nachgezogen (Sicherung vorher, 1 neue
    und 7 geänderte Dateien, 17 Plugin-Dateien md5-identisch, Plugin Check
    remote ohne Befund, Basic-Auth erhalten); die Beispielseite kam am
    29.10.2026 per `datenbank --ueberschreiben --ja` mit (Workflow: Inhalte
    lokal anlegen, per `datenbank` übertragen, nichts per Hand auf dem
    Server; Seite 239 privat, Formulare 232–238). Offen: Sichtprüfung
    (Nutzer: Box, Notice
    nach erneuter Aktivierung, Beispielseite im Frontend und im
    Block-Editor).
13. Nachbesserung der Fokus-Effekte nach der Sichtprüfung des Nutzers auf
    der Beispielseite (29.10.2026, Konzept 2.18): das umlaufende Licht war
    hinter dem 2 px dicken Fokus-Outline von Twenty Twenty-Five unsichtbar
    (headless mit Chromium 156 bestätigt: Winkel animierte, Ring verdeckt),
    Glow und Puls in Rahmenblau kaum erkennbar. Ring `z-index: 1`, Label
    `z-index: 2`, Effektfarbe Default `#dc3232`, Glow mit Spread
    `effect-size / 4`, Puls zwischen `effect-size / 2` und `effect-size * 2`
    mit Spread (8); `DESIGN_DEFAULTS` angepasst, Prüfscripte 29/58/28
    weiter grün, Headless-Nachmessung: Glow `0 0 8px 2px #dc3232`, Puls
    10,7–14,8 px Blur, Ring sichtbar. Hostsharing am 29.10.2026 mit `dateien`
    nachgezogen. Zweite Sichtprüfung des Nutzers: Licht fällt kaum auf →
    Konzept 2.19: geschlossener gedimmter Ring mit hellem Kometen,
    Theme-Outline beim Lauflicht auf 30 % gedimmt, Ringbreite Default 3 px
    (8, `DESIGN_DEFAULTS`). Offen: Sichtprüfung (Nutzer), Hostsharing
    `dateien`.

## 15. Offen

- Feldtypen `date` (Chrome zeigt eine Formatmaske, Label müsste dauerhaft
  oben stehen) und `select` mit `include_blank` (Leerzustand nur über
  `:has( option[value=""]:checked )` erkennbar) als Erweiterung 1.1.
- `range`, `file`, `checkbox`, `radio`, `acceptance`, `quiz`, `captchar`:
  bewusst nie.
- Verknüpftes Label mit Inline-Markup: ändert der Filter
  `jgor_flcf7_label_text` den Text, bleibt nur Klartext (7.2). Bewusst so;
  ein Filter für HTML-Inhalt kommt nur bei Bedarf.
- `jgor-flcf7-textarea` ohne eigene CSS-Regel (8); bleibt als Haken für
  Themes und eine spätere Textarea-Optik.
- Design-Felder nehmen nur Hex-Farben an (der WordPress-Farbwähler kennt
  keine Transparenz); `rgba()`-Werte wie das Filled-Default bleiben dem CSS
  vorbehalten (9.2). Das gilt auch für die Farbzeilen im Block-Editor: eine
  Palettenfarbe, die kein Hex-Wert ist, wird verworfen.
- Umlaufendes Licht (8): ohne `@property` steht der Ring, ohne
  `mask-composite` fehlt er; bewusst ohne JavaScript-Fallback. Der Ring
  nimmt den Radius aus `--jgor-flcf7-radius`, bei der Variante Minimal
  kann er vom Radius des Themes abweichen.
- Komfort-Oberfläche für die Steuerung je Formular (Erweiterung 1.1):
  eigener Tab im CF7-Editor (`wpcf7_editor_panels`, Speichern auf
  `wpcf7_save_contact_form`) statt Additional Settings. Nur Oberfläche, der
  Mechanismus aus 7.5 bleibt; die Seitenleiste des CF7-Blocks ist seit
  Modul 5 (10) abgedeckt.
- Umstieg von DOMDocument auf `WP_HTML_Processor`, sobald der Core das
  Einfügen von Elementen unterstützt.
- Bedingtes Laden des Stylesheets (Erweiterung 1.1): Das Plugin folgt der
  Ladepolitik von CF7 (5, 8), und die lädt das CSS standardmäßig auf jeder
  Seite; einen Lazy-Enqueue beim Rendern kennt CF7 nicht. Skizze: Option
  „Stylesheet nur auf Seiten mit Formular laden“ (Default aus = CF7-Politik),
  Erkennung auf `wp_enqueue_scripts` am Singular-Post per `has_shortcode(
  $post->post_content, 'contact-form-7' )` und `has_block(
  'contact-form-7/contact-form-selector' )`; als Sicherheitsnetz enqueued
  Modul 2 beim ersten umgebauten Feld nach, wenn der Handle noch nicht
  eingebunden ist (`wp_style_is()`), das CSS landet dann im Footer
  (`print_late_styles()`, kurzer FOUC möglich). Formulare in Widgets, Elements
  oder Template-Parts erreicht nur das Sicherheitsnetz, deshalb bleibt der
  Default bei der CF7-Politik. Das Sicherheitsnetz braucht die Abhängigkeit
  `contact-form-7` weiterhin registriert; CF7 registriert den Handle auf
  `wp_enqueue_scripts` unabhängig von `wpcf7_load_css()`.
- Keine Umwandlung gespeicherter Vorlagen (etwa ein Knopf im CF7-Editor,
  der `<p>Text<br>[tag]</p>` in `<label>`-Form umschreibt): Der Text-Modus
  (7.3) erreicht dieselben Formulare zur Laufzeit, ohne Daten zu ändern
  und ohne Rückweg. Bewusst so.

## Änderungen

| Version | Datum | Änderung |
| --- | --- | --- |
| 1.0 | 21.10.2026 | Erste Fassung: Zweck, Eckdaten mit Namensprüfung, CF7-Grundlagen, vier Module, Prüfung, Reihenfolge |
| 1.1 | 22.10.2026 | Testumgebung: GitHub-Repo (privat), lokale Site `floating-labels-for-cf7.gorres.local`, Hostsharing-Site `<hs-testsite>` (<hs-admin>, DB `<hs-db>`, PHP 8.4); CF7 6.2.3 verlangt WordPress 7.1 und PHP 8.3 |
| 1.2 | 22.10.2026 | Gerüst umgesetzt (Commit 4a1c2c2): Hauptdatei, Bootstrap, Settings mit Filtern `jgor_flcf7_settings` und `jgor_flcf7_field_types`, Uninstall, CF7-Stubs; Entscheidung: `Requires at least` 6.5 und `Requires PHP` 8.1 bleiben |
| 1.3 | 22.10.2026 | Module 2 und 3 umgesetzt (Commits 124beaa, aac696a, 756f4c1), Konzept an den Code angeglichen: Form-Tag-Syntax (`nofloat` vor Werten in Anführungszeichen), Werte von `floating_labels`, Prüfregeln je Wrap, ID-Eindeutigkeit, Wrap-Klassen `jgor-flcf7-textarea` und `jgor-flcf7-marker`, CSS-Geometrie (Label auf der ersten Textzeile, Floaten per `scale()`, Properties `--jgor-flcf7-padding-y`, `-border-width`, `-background-filled`, `-label-scale` statt `-label-size-floated`), Sperr-Definition des Filters `jgor_flcf7_settings` (9.1), Prüfscripte in `tests/`, Verzeichnisbaum |
| 1.4 | 22.10.2026 | Entscheidung Design-Werte: 1.0 ohne Eingabefelder, die Einstellungsseite zeigt das CSS-Snippet mit allen Custom Properties (9); Eingabefelder mit `wp_add_inline_style()` als Erweiterung 1.1 vorgemerkt (14) |
| 1.5 | 22.10.2026 | Steuerung je Formular (Additional Settings `floating_labels_style`, `floating_labels_marker`, `floating_labels_types`), je Platzierung (`html_class`-Klassen `jgor-flcf7-off`, `-on`, `-mode-*`, `-style-*`, `-marker-on`, `-marker-off`) und Filter `jgor_flcf7_form_settings` mit Rangfolge (7.4); Modul 2 entsprechend erweitert, 58 Prüfungen; §9 Elternslug `wpcf7-dashboard`; §14 Editor-Tab und Block-Seitenleiste für 1.1 |
| 1.6 | 22.10.2026 | Modul 4 umgesetzt (Commit 2082122): §9 an den Code angeglichen (Elternslug-Ermittlung mit Fallback, fünf Sektionen, Textarea-Snippet aus `CSS_PROPERTIES`, Sperren mit Notice, „Settings saved“-Meldung, „Settings“-Link), §3 und §12 um `tests/verify-admin.php` (33 Prüfungen) und den `$GLOBALS`-Zähler der Prüfscripte, §6 Bootstrap, §13 Schritt 4 erledigt |
| 1.7 | 22.10.2026 | Release-Vorbereitung nachgezogen: `readme.txt`, Kontexthilfe (§9), `.pot`, Asset-Scripte `docs/assets-erzeugen.py` und `docs/screenshots-erzeugen.sh` mit `assets/` (§3, §12), Build und Plugin Check auf dem ZIP, Hostsharing-Gegenprobe mit `dateien` und `datenbank` (§12), §13 Schritt 5 in Teilschritte mit Stand; `verify-admin.php` 39 Prüfungen |
| 1.8 | 22.10.2026 | Fehlerfall (Commit ba9a53c): CF7-Hinweis im Feld auf der Textzeile (links bei leerem, rechts bei gefülltem Feld), Label floatet bei Fehler in allen Varianten, Senden-Button bei `form.invalid` in Fehlerfarbe mit Fehlertext über Hülle `jgor-flcf7-submit` (Modul 2) und Grid-Overlay (8); Einstellung `submit_error_text` mit Textfeld (6, 9), Filter `jgor_flcf7_submit_error_text` (7.4), Properties `--jgor-flcf7-submit-error-background` und `-color`; Assets mit Rand und gerundeten Ecken, Beispieltexte John Doe; Prüfungen 62/40, `.pot` 78 Texte; `use-floating-validation-tip` aus §14 erledigt |
| 1.9 | 22.10.2026 | Hostsharing-Gegenprobe nach dem Fehlerfall (§12, §13 5.5): Dateien und Formular-HTML identisch, Plugin Check ohne Befund; `datenbank` nur bei geänderten Seiten oder Formularen nötig |
| 2.0 | 22.10.2026 | Modul 5 Block-Seitenleiste (Commit a820fd7), vorgezogen aus 1.1: neuer Abschnitt 10, die bisherigen Abschnitte 10–14 werden 11–15 (Verweise angepasst); §3 Dateien, §4 „Kein JavaScript im Frontend“, §5 Block-Panels, §6 Bootstrap, §7.4 Panel als Weg für Schicht 3, §13 Prüfscripte `verify-editor.php` (15) und `verify-editor-js.mjs` (28), Option-Sicherung in `verify-markup.php` und `verify-admin.php`, Browser-Matrix um Fehlerfall und Block-Editor ergänzt, ZIP 12 Dateien; readme Screenshot 6; `.pot` 88 Texte |
| 2.1 | 22.10.2026 | Modul 5 auch für den Shortcode-Block `core/shortcode` mit `[contact-form-7 …]`: Panel liest und schreibt `html_class` im Shortcode-Text (`storeFor()`, `shortcodeHtmlClass()`, `withShortcodeHtmlClass()`); §5, §10, §13 (Prüfungen 16/47, Browser-Matrix), §14; readme und Kontexthilfe; `.pot` 89 Texte |
| 2.2 | 22.10.2026 | Design-Einstellungen (Commit b21db52), vorgezogen aus 1.1: neuer Abschnitt 9.2 (Option `design`, Sanitize je Typ, Felder mit Farbwähler, Inline-Style nur für Abweichungen, Property `--jgor-flcf7-font-size`); §3 Dateien, §4, §6 Optionstabelle, §8 Property und Ausgabe, §9 Sektionen, §13 `verify-design.php` (26) und `verify-admin.php` (50), ZIP 13 Dateien, §14 Schritt 7, §15 Design-Punkt ersetzt; readme, Kontexthilfe; `.pot` 115 Texte |
| 2.3 | 22.10.2026 | Fund der Hostsharing-Gegenprobe: CF7 gibt jede `id:`-Option nur einmal je Seitenaufruf aus, beim zweiten Rendering desselben Formulars fand Modul 2 das verknüpfte `<label for>` nicht; `fields()` liefert die `id:`-Option mit, `linked_label()` sucht damit, das Feld bekommt eine ID aus dem Unit-Tag (5, 7, 7.2); `verify-markup.php` 69 Prüfungen; §14 Schritte 2, 6, 7 |
| 2.4 | 22.10.2026 | Design je Platzierung im Block-Panel (Commit cbc7ca7): neuer Abschnitt 10.1 (Panels „Floating labels: colours/sizes/animation“, Block-Attribut `jgorFlcf7Design` per `register_block_type_args`, Shortcode-Attribut `jgor_flcf7_design`, `render_block_…` und `do_shortcode_tag`, `style` am Wrapper, neue Klasse `JGOR_FLCF7_Placement_Design`); §3 Dateien, §4, §5 Block-Rendering und Shortcode-Attribute, §6 Bootstrap, §7.4, §9.2, §10 Inline-Daten, §13 (`verify-markup.php` 82, `verify-editor.php` 23, `verify-editor-js.mjs` 76, ZIP 14 Dateien 52 KB, Browser-Matrix), §14 Schritt 8; readme, Kontexthilfe; `.pot` 120 Texte |
| 2.5 | 23.10.2026 | Screenshots 5 und 6 per `docs/screenshots-admin-erzeugen.js` (playwright-core + System-Chromium, temporäres Konto, Entwurfsseite, Aufräumen): §3 Verzeichnisbaum, §13 Assets und Hinweis zu Playwright auf Bodhran, §14 Schritte 5.4 (erledigt), 6, 7 und 8 |
| 2.6 | 23.10.2026 | Nachbesserungen aus der Sichtprüfung (Commits a89fbe0, b655073, 87c17f9): Block-Panels ohne `Flex`-Hüllen (Abstand des Inspectors), „Floating labels: colours“ als einklappbares `PanelBody` mit eigenen Farbzeilen (`useSettings`, `Dropdown`, `ColorPalette`) statt `PanelColorSettings` (ToolsPanel in WP 7.1), `assets/css/editor.css` (10, 10.1); Einstellungsseite mit `assets/css/admin.css` und 16em-Spalten für Typen, Farben, Maße, Animation und Effekt (9); `required_marker` Default true (6); Fokus-Effekte: Option `effect`, `floating_labels_effect`, `jgor-flcf7-effect-*`, Wrap-Klasse `jgor-flcf7-<effect>`, Properties `effect-color/-size/-width/-duration`, CSS mit `box-shadow`, Keyframes, `conic-gradient` + `@property`, Reduced Motion stehend (6, 7.4, 7.6, 8, 9.2, 10); §3, §4, §13 (Prüfungen 88/54/29/27/86, ZIP 16 Dateien 60 KB, Screenshots 5/6), §14 Schritt 9, §15; readme, Kontexthilfe; `.pot` 140 Texte |
| 2.7 | 23.10.2026 | Zweite Sichtprüfung: Zeile „Glow and running light“ mit vier Spalten (`jgor-flcf7-columns--4`, 9), 8 px Abstand zwischen Farbindikator und Name in den Farbzeilen (10.1), Panel „Floating labels“ statt „Floating Labels“ (10, readme, Kontexthilfe, Screenshot-Script); `.pot` neu |
| 2.8 | 29.10.2026 | Text-Modus für bestehende Formulare ohne Label und Placeholder (alte CF7-Vorlage `<p>Text<br>[tag]</p>`): neuer Abschnitt 7.3, die bisherigen 7.3–7.6 werden 7.4–7.7 (Verweise angepasst); Option `mode_text` Default aus (6), Schritt 5 in 7, `floating_labels: text` und `jgor-flcf7-mode-text` (7.5), Checkbox (9), Block-Panel (10), §14 Schritt 10 offen für 1.0; §15: Erweiterung 1.1 „Bedingtes Laden des Stylesheets“ (Option, Erkennung per `has_shortcode()`/`has_block()`, Sicherheitsnetz aus Modul 2, Default bleibt die CF7-Politik) und Entscheidung gegen das Umschreiben gespeicherter Vorlagen; keine Codeänderung |
| 2.9 | 29.10.2026 | Text-Modus an den Code angeglichen (Commit 7e4568f): CF7-Formatter trennt `Text<br />[tag]` in zwei Absätze (5), 7.3 mit zwei Suchstufen (`text_before_wrap()`, `text_in_previous_paragraph()`), `<label>` ohne `for` als Textquelle, Erklärabsatz-Heuristik, Grenzen korrigiert; §13 Testformular (f) und `verify-markup.php` 111; §14 Schritt 10 Teil 1 erledigt |
| 2.10 | 29.10.2026 | Text-Modus komplett (Commits 16b82f0, a21c14b, 20e5fda): §13 `verify-admin.php` 55 (14 Feldzeilen, Checkbox `mode_text`), `verify-editor.php` 14 Klassen, `verify-editor-js.mjs` 88, ZIP-Stand mit `.pot` 144 (`--slug`); §14 Schritt 10 erledigt bis auf Hostsharing und Sichtprüfung; §2 und §5 CF7 6.2.4 lokal (Security-Release ohne Einfluss, Prüfscripte ohne Befund) |
| 2.11 | 29.10.2026 | Hostsharing nach Schritt 10 mit `dateien` und `datenbank` nachgezogen (§13, §14; CF7 6.2.4 nun auch remote, §2); Zählung korrigiert: die Kontaktseite trägt sechs CF7-Formulare (A–E und F) |
| 2.12 | 29.10.2026 | Hostsharing-Testsite mit Basic-Auth (Benutzer `<basic-auth-user>`, `.htpasswd` außerhalb des Webroots, `<webroot>/.htaccess`), Site-env mit `HS_RSYNC_AUSSCHLUESSE_SITE` (neuer Schlüssel in Stufe 6), Gegenprobe per curl mit `-u` (§2, §13) |
| 2.13 | 29.10.2026 | Reset-Buttons im Block-Editor geplant (Wunsch des Nutzers): „Reset this placement“ im Panel „Floating labels“ (Klassen und Design-Werte), „Reset …“ je Design-Panel (nur diese Gruppe), inaktiv ohne Werte, Helfer `withoutGroups()`/`designWithoutGroup()` (10, 10.1), Prüfungen (13), §14 Schritt 11 offen |
| 2.14 | 29.10.2026 | Schritt 11 erledigt: Reset-Buttons (10, 10.1), Hinweisabsatz auf der Einstellungsseite und Reset-Satz in der Kontexthilfe (9), Prüfzähler 56/28/99 (13), readme, `.pot` 150, ZIP-Stand; offen Hostsharing `dateien` und Sichtprüfung |
| 2.15 | 29.10.2026 | Beispielseite geplant (Wunsch des Nutzers): neuer Abschnitt 9.3 (Seite `testpage-floating-labels-for-cf7` privat, sieben Formulare „FL Test … - may be deleted“ mit `skip_mail`, Platzierungsbeispiele, Option `jgor_flcf7_samples`, Box mit admin-post-Handler, Nachfrage nach der Aktivierung, Uninstall, Stubs); §3, §4, §6, §12, §13 `verify-samples.php`, §14 Schritt 12 offen |
| 2.16 | 29.10.2026 | Schritt 12 erledigt: Beispielseite umgesetzt (9.3 mit Befunden: Hash nach `save()`, `exit` nach Redirect, Action `jgor_flcf7_settings_page_top`, Ergebnisse `absent`/`failed`), Prüfzähler 37/58 (13), ZIP 17 Dateien, `.pot` 192; offen Hostsharing `dateien` und Sichtprüfung |
| 2.17 | 29.10.2026 | Hostsharing nach Schritt 11 und 12 mit `dateien` nachgezogen (§13, §14); offen nur noch Sichtprüfungen des Nutzers, Browser-Matrix, Push und Veröffentlichung |
| 2.18 | 29.10.2026 | Fokus-Effekte nachgebessert (8, §14 Schritt 13): Ring über Steuerelement und Theme-Outline (`z-index`), Label `z-index: 2`, Effektfarbe Default `#dc3232`, Glow mit Spread, Puls doppelt so stark |
| 2.19 | 29.10.2026 | Lauflicht auffälliger (8, §14 Schritt 13): geschlossener Ring in 30 % der Effektfarbe mit hellem Kometen, Theme-Outline beim Lauflicht auf 30 % gedimmt, Ringbreite Default 3 px; Hostsharing nach 2.18 nachgezogen |
| 2.20 | 29.10.2026 | Box „Sample page“: Überschrift ohne WordPress-Klasse `title` (floatete links), eigene Klasse mit Abstand per `admin.css` (9); Beispielseite per `datenbank` nach Hostsharing, Workflow-Hinweis (§14 Schritt 12) |
| 2.21 | 29.10.2026 | Testformulare räumen sich auf: `verify-markup.php` legt B, C, D, F und die Seite „Contact“ über `create-test-forms.php` selbst an und entfernt sie am Ende (`FLCF7_KEEP=1`), alte Reste lokal gelöscht und per `datenbank` nach Hostsharing; Hostsharing-Vergleich über die Beispielseite (13) |
| 2.22 | 29.10.2026 | Beispielseite: alte und heutige Vorlage als Code-Blöcke beim alten Formular (9.3), Prüfung in `verify-samples.php` |
| 2.23 | 29.10.2026 | Asset-Version = Plugin-Version plus `filemtime()` (`JGOR_FLCF7_Plugin::asset_version()`, 8, 10) gegen veraltete Browser-Caches; Prüfungen angepasst, `verify-admin.php` 59 |
| 2.24 | 29.10.2026 | Reset-Buttons als `secondary` mit Rahmen (10, 10.1), damit sie auch inaktiv als Buttons erkennbar sind; Screenshots 5 und 6 neu |

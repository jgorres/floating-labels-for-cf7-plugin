# Floating Labels for CF7 – Konzept und Aufbau

Version: 1.8 · Stand: 22.10.2026 · Plugin-Version: 1.0.0 (Module 1–4 umgesetzt, Release-Vorbereitung läuft)

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
| Geprüft gegen | CF7 6.2.3 (lokal und Hostsharing), Doku `https://contactform7.com/docs/`. CF7 6.2.3 verlangt für sich WordPress 7.1 und PHP 8.3; `Requires at least` 6.5 und `Requires PHP` 8.1 bleiben trotzdem (Entscheidung 22.10.2026): Das Plugin nutzt nur CF7-API, die es seit 5.x gibt, ältere CF7-Versionen laufen auf WP 6.5 / PHP 8.1 weiter |

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
│   ├── uninstall.php                 löscht die Option
│   ├── includes/
│   │   ├── class-jgor-flcf7-plugin.php    Bootstrap: CF7 vorhanden?, Hooks, Hinweis
│   │   ├── class-jgor-flcf7-settings.php  Defaults, Lesen und Sanitize der Option, Filter
│   │   ├── class-jgor-flcf7-markup.php    Modul 2: Umbau des Formular-HTML
│   │   ├── class-jgor-flcf7-assets.php    Modul 3: CSS einbinden
│   │   └── class-jgor-flcf7-admin.php     Modul 4: Einstellungsseite
│   ├── assets/css/floating-labels.css     Frontend (eine Datei, alle Varianten)
│   └── languages/floating-labels-for-cf7.pot  Übersetzungsvorlage (wp i18n make-pot)
├── tests/
│   ├── create-test-forms.php         Testformulare und Seite „Contact“ anlegen (wp eval-file)
│   ├── verify-markup.php             62 PASS/FAIL-Prüfungen für Modul 2 (wp eval-file)
│   └── verify-admin.php              40 PASS/FAIL-Prüfungen für Modul 4 (wp eval-file)
├── docs/
│   ├── konzept.md                    dieses Dokument
│   ├── assets-erzeugen.py            Icon (SVG und APNG) und Banner (APNG), Pillow
│   └── screenshots-erzeugen.sh       Screenshots 1–4 auf der lokalen Testsite, Chromium headless
├── assets/                           WP.org-Assets: icon.svg, icon-128x128.png, icon-256x256.png,
│                                     banner-772x250.png, banner-1544x500.png, screenshot-1..5.png
├── glotpress/
├── composer.json, phpcs.xml.dist, phpstan.neon.dist, phpstan-bootstrap.php, stubs/
└── build.sh, .distignore, .gitignore, LICENSE
```

`stubs/cf7.php` hält die CF7-Klassen und -Funktionen, die das Plugin
aufruft (`WPCF7_ContactForm`, `WPCF7_FormTag`, `wpcf7_enqueue_styles`), damit
PHPStan ohne CF7-Installation läuft. `tests/` liegt außerhalb von `plugin/`
und kommt nicht ins Release-ZIP; die Scripte laufen nur unter WP-CLI
(`defined( 'WP_CLI' )`) und nur auf lokalen Testsites (Abschnitt 12).

## 4. Grundsätze

- **Nichts kaputt machen:** Das Plugin ändert nur Felder, die es sicher
  erkennt. Alles andere (Checkboxen, Radios, Dateien, Select, Quiz, Captcha,
  Submit, eigenes HTML in der Vorlage) bleibt Byte für Byte so, wie CF7 es
  rendert. Schlägt der Umbau fehl oder ändert er kein Feld, geht das
  Original-HTML unverändert raus.
- **Kein JavaScript:** Zustände über `:focus`, `:placeholder-shown`,
  `:autofill`, `.wpcf7-not-valid` / `[aria-invalid="true"]`,
  `[aria-required]` und die Formularklasse `invalid`. CF7 setzt nach dem
  Versand `form.reset()`, dadurch sinken die Labels von selbst zurück. Das
  CSS enthält keine Inline-Anteile, das Plugin ist CSP-tauglich.
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
  Platzierung über das Shortcode-/Block-Attribut `html_class`, je Feld über
  eine Form-Tag-Option (`nofloat`); Rangfolge in 7.4. Das CSS folgt der
  Ladepolitik von CF7 über die Action `wpcf7_enqueue_styles`.
- **Theme-freundlich:** Farben, Maße und Dauer als Custom Properties
  `--jgor-flcf7-*`, gesetzt auf `.wpcf7`. Das Steuerelement erbt die Schrift
  des Themes (`font: inherit`). Die Variante „Minimal“ liefert nur
  Positionierung, Zustände und Label-Farben, die Optik des Feldes kommt dann
  aus dem Theme.
- **Entwickler-Filter:** `jgor_flcf7_settings` (Konfiguration aus Code),
  `jgor_flcf7_form_settings` (je Rendering), `jgor_flcf7_enabled` (je
  Formular), `jgor_flcf7_label_text` (je Feld), `jgor_flcf7_field_types`.
- **Schlank:** Eine Option (`jgor_flcf7_settings`, autoload), eine
  CSS-Datei, keine Tabellen, keine Transients, kein Cron. Der Umbau läuft
  einmal je gerendertem Formular auf einem kleinen HTML-Fragment.

## 5. Was CF7 liefert (Grundlagen)

Aus der CF7-Doku und dem Quelltext 6.2.3:

- **Form-Tags:** `[text* your-name placeholder "Your name"]`. Die Option
  `placeholder` (Alias `watermark`) gibt es für `text`, `email`, `url`,
  `tel`, `textarea`, `number`, `range`, `date` und `captchar`. CF7 schreibt
  den Wert in das Attribut `placeholder`, der Default-Wert bleibt leer.
  Optionen `id:` und `class:` setzen `id` und `class` des Steuerelements.
  Unbekannte Optionen wie `nofloat` nimmt der Parser an und ignoriert sie;
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
  Shortcode-Attributen `html_*`.

## 6. Modul 1: Grundgerüst (`class-jgor-flcf7-plugin.php`, `class-jgor-flcf7-settings.php`)

- Hauptdatei mit Header, Konstanten `JGOR_FLCF7_VERSION`, `JGOR_FLCF7_MIN_PHP`,
  `JGOR_FLCF7_FILE`, `JGOR_FLCF7_PATH`, `JGOR_FLCF7_URL`, Laden der Klassen
  aus `includes/` (mit `file_exists()`, fehlende Module werden übersprungen),
  Start auf `plugins_loaded` mit Priorität 20 (nach CF7), Aktivierungs-Hook
  mit PHP-Prüfung und `ensure_option()`.
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
  Module 2 und 3 werden im Bootstrap auf jeder Anfrage gestartet, Modul 4 nur
  bei `is_admin()`.
- `JGOR_FLCF7_Settings` (Singleton `instance()`): Konstanten `OPTION`,
  `GROUP`, `BASE_TYPES` (text, email, url, tel, number, textarea), `STYLES`;
  Methoden `field_types()`, `defaults()`, `stored()` (Option plus Defaults,
  ohne Filter), `all()` (wirksame Werte mit Filter, gecacht), `get()`,
  `locked_keys()`, `is_locked()`, `reset_cache()`, `ensure_option()`,
  `sanitize()`. Option `jgor_flcf7_settings` (Array, autoload). Werte:

| Schlüssel | Typ | Default | Bedeutung |
| --- | --- | --- | --- |
| `mode_placeholder` | bool | true | Placeholder-Modus (7.1) |
| `mode_label` | bool | true | Label-Modus (7.2) |
| `types` | array | text, email, url, tel, number, textarea | erlaubte Feldtypen (CF7-Basistyp) |
| `style` | string | `outlined` | `outlined`, `filled`, `minimal` |
| `load_css` | bool | true | Plugin-CSS einbinden |
| `required_marker` | bool | false | Stern hinter dem Label bei `aria-required` (Klasse `jgor-flcf7-marker` am Wrap) |
| `submit_error_text` | string | `Please check the highlighted fields` | Text auf dem Senden-Button bei Validierungsfehlern; leer = nur Farbwechsel. Konstante `DEFAULT_SUBMIT_ERROR_TEXT`, unübersetzt gespeichert und beim Rendern übersetzt, solange der Wert dem Default entspricht; `sanitize_text_field()`, höchstens 200 Zeichen |

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
  Multisite je Site, siehe 10).

## 7. Modul 2: Umbau des Markups (`class-jgor-flcf7-markup.php`)

Hook: `add_filter( 'wpcf7_form_elements', …, 20 )`. Der Filter ist nicht auf
das Frontend begrenzt. Ablauf je Formular:

1. Schneller Vorab-Test: kein String oder kein `wpcf7-form-control-wrap`
   per `strpos()` → unverändert zurück.
2. Formular über `WPCF7_ContactForm::get_current()`; kein Formular →
   unverändert. Wirksame Einstellungen für dieses Rendering bestimmen (7.4):
   Modi, Feldtypen, Variante und Pflichtstern aus der Option, übersteuert
   durch Additional Settings, `html_class` und Filter. Abgeschaltet, kein
   Modus aktiv oder kein Feldtyp erlaubt → unverändert.
3. Felder sammeln: `scan_form_tags()` → Name → Basistyp, nur die wirksamen
   Feldtypen. Ausgeschlossen: Tags mit
   `has_option( 'nofloat' )`, Tags ohne Namen, Namen mit zwei verschiedenen
   Basistypen (mehrdeutig). Leere Liste → unverändert.
4. HTML in DOMDocument laden (7.5), alle `span.wpcf7-form-control-wrap`
   (XPath) durchgehen. Je Wrap: `data-name` in der Feldliste, Klasse
   `jgor-flcf7-wrap` fehlt noch, Inhalt des Wraps ist genau ein
   `input`/`textarea` plus höchstens Whitespace und ein
   `span.wpcf7-not-valid-tip`. `input`-Typen `hidden`, `checkbox`, `radio`,
   `file`, `submit`, `button`, `reset`, `image`, `range`, `color` nie.
5. Label-Text bestimmen: Label-Modus (7.2) hat Vorrang (umschließendes
   Label, sonst verknüpftes Label), dann Placeholder-Modus (7.1). Kein Text →
   Feld überspringen. Text durch Filter `jgor_flcf7_label_text` (`$text`,
   `$name`, `$contact_form`); kein String oder leer → Feld bleibt
   unverändert. Whitespace (auch geschützte Leerzeichen) wird überall zu
   einem Leerzeichen zusammengezogen und getrimmt.
6. Umbau (7.6) mit der wirksamen Variante und dem wirksamen Pflichtstern.
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

In beiden Fällen wird ein vorhandener Placeholder ebenfalls auf ein
Leerzeichen gesetzt, sonst lägen Placeholder und Label übereinander.

### 7.3 Feld-ID

Hat das Steuerelement eine `id`, bleibt sie. Sonst
`{unit_tag}-fl-{name}`, bereinigt wie CF7 es für seine Fehler-IDs tut
(`[^0-9a-z_-]` entfernt; leere Basis → `jgor-flcf7-field`). Vor dem Umbau
werden alle IDs des Fragments gesammelt; kollidiert eine neue ID, hängt das
Plugin `-2`, `-3`, … an. Der Unit-Tag ist je Instanz eindeutig, auch wenn
dasselbe Formular zweimal auf einer Seite steht.

### 7.4 Steuerung: global, je Formular, je Platzierung, je Feld

Die wirksamen Werte eines Renderings entstehen in Schichten; jede spätere
Schicht übersteuert die frühere, ungültige Werte werden ignoriert:

1. **Option** (Modul 1): `mode_placeholder`, `mode_label`, `types`,
   `style`, `required_marker`.
2. **Additional Settings des Formulars** (Tab „Additional Settings“ im
   CF7-Editor, gelesen per `pref()`):

   ```
   floating_labels: off
   floating_labels: placeholder
   floating_labels_style: filled
   floating_labels_marker: on
   floating_labels_types: text email textarea
   ```

   `floating_labels: off` (auch `false`, `no`, `0`) schaltet das Formular
   ab, `placeholder` oder `label` lässt nur den genannten Modus zu, auch
   wenn die Option ihn abgeschaltet hat. `floating_labels_style` nimmt
   `outlined`, `filled`, `minimal`; `floating_labels_marker` nimmt `on`/`off`
   (auch `true`/`false`, `yes`/`no`, `1`/`0`); `floating_labels_types` ist
   eine Liste von Basistypen, durch Leerzeichen oder Kommas getrennt, nur
   Typen aus `field_types()` zählen, eine leere Liste schaltet alle Typen
   ab. Groß-/Kleinschreibung ist egal.
3. **`html_class` der Platzierung** (Shortcode `[contact-form-7 id="…"
   html_class="…"]`, im CF7-Block das Feld „Class“ im Panel „Form
   attributes“; gelesen per `shortcode_attr( 'html_class' )`). Erkannte
   Klassen: `jgor-flcf7-off`, `jgor-flcf7-on`, `jgor-flcf7-mode-placeholder`,
   `jgor-flcf7-mode-label`, `jgor-flcf7-style-outlined`,
   `jgor-flcf7-style-filled`, `jgor-flcf7-style-minimal`,
   `jgor-flcf7-marker-on`, `jgor-flcf7-marker-off`. Dasselbe Formular kann
   so je Seite anders aussehen. Die Namen unterscheiden sich absichtlich von
   den Wrap-Klassen aus 7.6: CF7 schreibt `html_class` auf das `<form>`, eine
   Wrap-Klasse dort würde die Varianten-Regeln des CSS auf alle Felder
   anwenden.
4. **Filter `jgor_flcf7_form_settings`** (`$settings`, `$contact_form`):
   erhält das Array `enabled`, `mode_placeholder`, `mode_label`, `types`,
   `style`, `required_marker`, `submit_error_text` nach den Schichten 1–3
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

### 7.5 Technik: DOMDocument

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
kann der Umbau darauf wechseln (Abschnitt 14).

### 7.6 Ergebnis-Markup

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
Pflichtstern (7.4) zusätzlich `jgor-flcf7-marker`. Die Varianten-Klasse sitzt
am Wrap, damit eine CSS-Datei alle Varianten abdeckt; die Marker-Klasse
schaltet den Pflichtstern im CSS, ohne dass das Plugin eine zweite CSS-Datei
oder Inline-CSS bräuchte.

## 8. Modul 3: CSS (`class-jgor-flcf7-assets.php`, `assets/css/floating-labels.css`)

- Einbinden auf `add_action( 'wpcf7_enqueue_styles', … )`:
  `wp_enqueue_style( 'jgor-flcf7', …, array( 'contact-form-7' ), JGOR_FLCF7_VERSION )`.
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

  Gegenüber 1.2 neu: `padding-y`, `border-width`, `background-filled`,
  `label-scale`; `--jgor-flcf7-label-size-floated` entfällt, weil das Label
  beim Floaten nicht die Schriftgröße wechselt, sondern per `transform:
  scale()` schrumpft (die em-basierten Abstände bleiben so stabil, und der
  Übergang läuft ohne Layout-Neuberechnung).
- **Gemeinsame Regeln:** `.jgor-flcf7-field { display: block }` (Ersatz für
  das umschließende Label); `.jgor-flcf7-wrap { display: block; position:
  relative }`; Steuerelement `display: block; box-sizing: border-box; width:
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
- `@media (prefers-reduced-motion: reduce)` schaltet die Transitionen von
  Label und Steuerelement ab. RTL über logische Eigenschaften
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
- Fünf Sektionen: „Label sources“ (Checkboxen `mode_label`,
  `mode_placeholder`), „Field types“ (Checkboxen aus `field_types()`,
  Beschriftung Text, Email, URL, Telephone, Number, Text area jeweils mit dem
  Form-Tag-Typ in Klammern, per Filter ergänzte Typen nur mit ihrem
  Schlüssel; alle abgewählt = Floating Labels aus), „Appearance“ (Radio für
  die Variante mit Kurzbeschreibung, Checkbox Stylesheet laden, Checkbox
  Pflichtstern, Textfeld „Submit button on errors“ für
  `submit_error_text`), „Per form, per placement, per field“ (Hinweistext zu
  den Steuerwegen aus 7.4 mit Codebeispielen für Additional Settings,
  `html_class`-Klassen und `nofloat` vor den Werten in Anführungszeichen,
  dazu die Filter) und „Design: CSS custom properties“ (unten).
- Abschnitt „Design“: keine Eingabefelder für Farben und Maße. Stattdessen
  eine schreibgeschützte Textarea (`large-text code`) mit dem CSS-Snippet aus
  8 (Selektor `.wpcf7`, alle Custom Properties mit ihren Defaults) zum
  Kopieren in Customizer → Zusätzliches CSS, Child-Theme, GeneratePress
  Element oder `theme.json`, plus Hinweis, wie ein einzelnes Formular über
  seinen Wrapper (`[id^="wpcf7-f123-"]`) oder eine `html_class`-Klasse
  gestylt wird. Properties und Defaults stehen in der Konstante
  `JGOR_FLCF7_Admin::CSS_PROPERTIES`, die den `.wpcf7`-Block der CSS
  spiegelt; `tests/verify-admin.php` prüft den Gleichstand. So bleiben
  Theme-Werte an einer Stelle, das Plugin gibt kein Inline-CSS aus und
  braucht keine weiteren Optionswerte. Eingabefelder sind für 1.1 vorgemerkt
  (14).
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
- Kein eigenes Admin-CSS/JS; die Seite nutzt die WordPress-Klassen
  `form-table`, `description`, `large-text code`, `notice`.

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
- `wp plugin check` auf dem gebauten ZIP: `TERM=xterm ./build.sh` prüft die
  Version in Header, Konstante, `Stable tag` und Changelog und packt
  `plugin/` ohne die `.distignore`-Einträge nach
  `dist/floating-labels-for-cf7-<version>.zip`; das ZIP in einen frischen
  Ordner entpacken und `wp plugin check <Ordner> --path=<Site>` laufen
  lassen (`Tested up to` nur in `readme.txt`, Kurzbeschreibung höchstens
  150 Zeichen). Stand 22.10.2026: 10 Dateien, 32 KB, ohne Befund; ebenso
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
  derselben Seite.

```bash
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/create-test-forms.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-markup.php
wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-admin.php
```

- `tests/verify-markup.php` rendert die Formulare per `form_html()` und
  prüft 62 Punkte (Exit-Code ungleich 0 bei Fehlern): Label- und
  Placeholder-Modus, Ersatz des umschließenden Labels, Label-Text aus der
  Vorlage, Varianten- und Textarea-Klasse, IDs aus dem Unit-Tag, `for` =
  `id`, `placeholder=" "`, unberührte Felder (Submit, `nofloat`, Select,
  Checkbox, Datei), erhaltene ARIA-Attribute, `floating_labels:
  off|placeholder|label`, Filter `jgor_flcf7_enabled` und
  `jgor_flcf7_label_text` (auch Rückgabe leer), Typen-Einstellung ohne
  `textarea`, Formular (d) byteidentisch zum Original (Unit-Tag
  normalisiert), zwei Instanzen mit verschiedenen IDs, UTF-8 und Entities,
  Steuerung je Formular (`floating_labels_style`, `_marker`, `_types`, leere
  Liste, ungültiger Wert), je Platzierung (`html_class`-Klassen samt
  Rangfolge über das Formular und `jgor-flcf7-on` gegen `floating_labels:
  off`), Filter `jgor_flcf7_form_settings` (Vorrang vor `html_class`,
  `enabled` false, ungültige Werte), Senden-Button (Hülle mit Fehlertext,
  Filter `jgor_flcf7_submit_error_text`, leerer Text ohne Fehler-Span,
  Option wird bereinigt).
  Das Script ändert die Option vorübergehend und stellt die Defaults danach
  wieder her. `FLCF7_DUMP=1` gibt das HTML der Formulare (a) und (c) aus.
- `tests/verify-admin.php` prüft Modul 4 ohne Browser (40 Punkte): Menü
  unter `wpcf7-dashboard`, `wpcf7` und dem Fallback samt Seiten-URL,
  Kontexthilfe (Hook, drei Reiter, escapete Beispiele, Snippet,
  Seitenleiste), fünf Sektionen und sieben Felder, das gerenderte Formular
  (Ziel `options.php`, Nonce, Defaults, `for`/`id`, escapete Codebeispiele,
  Snippet in der Textarea), gesperrte Werte (disabled, ohne `name`,
  Hinweise, Notice), Capability (`wp_die()` ohne `manage_options`),
  „Settings“-Link und den Gleichstand von `CSS_PROPERTIES` mit dem
  `.wpcf7`-Block der CSS. Das
  Script lädt `wp-admin/includes/plugin.php` und `template.php` und
  simuliert das CF7-Menü über `$admin_page_hooks` / `$_parent_pages`.
  Beide Prüfscripte zählen Fehler in `$GLOBALS`, weil `wp eval-file` die
  Datei in einem Funktionsscope ausführt; der Exit-Code ist bei Fehlern
  ungleich 0.
- Sichtprüfung auf Bodhran headless (`chromium --headless=new
  --ignore-certificate-errors --screenshot`), interaktiv im Browser auf
  Silentium (Frontend und Einstellungsseite
  `wp-admin/admin.php?page=wpcf7-floating-labels`). Browser-Matrix vor dem
  Release von Hand in Firefox und Edge auf Silentium (Playwright steht auf
  Bodhran ohne Browser nicht zur Verfügung): Ruhezustand, Fokus per Klick
  und per Tab, Eingabe, Autofill, Fehlerfall (Pflichtfeld leer abschicken →
  Label und Rahmen rot, Tip unter dem Feld), Versand mit `skip_mail: on` →
  Labels sinken zurück, Einstellungsseite speichern und Hilfe-Reiter öffnen.
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
  Rand mit gerundeten Ecken, außerhalb transparent. Screenshot 5
  (Einstellungsseite mit Hilfe-Reiter) entsteht von Hand im eingeloggten
  Browser.
- Ohne `ext-dom` (per `wp eval` mit Klassen-Stub nicht simulierbar; Codepfad
  per PHPStan und Lesen prüfen): Hinweis im Admin, Formular unverändert.
- Gegenprobe auf der Hostsharing-Testsite `<hs-testsite>`
  (PHP 8.4, Produktiv-Stack) vor jedem Release. Stand nachziehen mit
  `local-sites-hostsharing.sh dateien … --ueberschreiben` und `datenbank …
  --ueberschreiben` (beides startet der Nutzer; `datenbank` bringt Seite
  „Contact“ und Testformulare mit, das Script sichert den Remote-Stand
  vorher). Prüfung nur lesend: per `ssh <hs-admin>` mit `php8.4
  /home/pacs/<hs-paket>/bin/wp --path=…/<webroot>` (`plugin list`, `plugin
  check`) und per Abruf der Kontaktseite; das Formular-HTML wird nach
  Normalisierung von URL und Unit-Tag mit der lokalen Seite verglichen.
  22.10.2026: identisch, Plugin Check ohne Befund. Die Warnung des Scripts
  zu `rewrite flush` ist harmlos: keine `.htaccess`, Permalinks
  `/index.php/…`.
- Testeinstellungen und Testformulare nach der Prüfung entfernen.

## 13. Reihenfolge der Umsetzung

1. Gerüst (Header, Konstanten, Bootstrap, Settings-Klasse, Hinweis ohne
   CF7), `stubs/cf7.php`, `composer install`, Commit. Erledigt am
   22.10.2026: GitHub-Remote (privat, Commit a8cdc4d), lokale Testsite und
   Hostsharing-Testsite (Abschnitt 2), Gerüst (Commit 4a1c2c2; `composer
   check` ohne Befund, lokal aktiviert, Sanitize, Filter-Sperre und Hinweis
   ohne CF7 per wp-cli geprüft).
2. Modul 2 Markup: Placeholder-Modus, dann Label-Modus, Ausnahmen, Filter.
   Prüfung per `wp eval`. Erledigt am 22.10.2026 (Commit 124beaa, Prüfscripte
   756f4c1): 42 Prüfungen PASS, `composer check` ohne Befund. Erweitert am
   22.10.2026 um die Steuerung je Formular und Platzierung (7.4): 58
   Prüfungen PASS.
3. Modul 3 CSS: Variante Outlined, dann Filled und Minimal, Fehlerzustand,
   Reduced Motion, RTL. Erledigt am 22.10.2026 (Commit aac696a), Sichtprüfung
   lokal; Browser-Matrix und Screenshots mit Schritt 5.
4. Modul 4 Einstellungsseite mit Sperren aus Code (9, 9.1). Erledigt am
   22.10.2026 (Commit 2082122): 33 Prüfungen PASS, `composer check` ohne
   Befund; Sichtprüfung der Seite im Browser steht aus.
5. Release-Vorbereitung, Stand 22.10.2026:
   1. `readme.txt` (Commit f39cea5), Plugin Check ohne Befund. Erledigt.
   2. Kontexthilfe (Commit 61cf91b), `verify-admin.php` 39 Prüfungen. Erledigt.
   3. `.pot` (Commit cf0d9cc), 74 Texte, seit dem Fehlerfall 78. Erledigt;
      vor jedem Release neu erzeugen.
   4. Assets (Commit dfd3c79): animiertes Icon, animierte Banner,
      Screenshots 1–4 per Script. Offen: Screenshot 5 (Einstellungsseite,
      Nutzer im Browser).
   5. `build.sh` und Plugin Check auf dem ZIP ohne Befund,
      Hostsharing-Gegenprobe bestanden. Fehlerfall am 22.10.2026 ergänzt
      (Commit ba9a53c: Tip im Feld, Label floatet, Senden-Button mit
      Fehlertext; 62 und 40 Prüfungen, Plugin Check ohne Befund);
      Hostsharing danach erneut nachziehen. Offen: Browser-Matrix in
      Firefox und Edge auf Silentium (Nutzer), dabei auch den Fehlerfall
      live prüfen.
   6. Veröffentlichung: Testformulare und Testseiten entfernen, Repo
      öffentlich stellen, Release 1.0.0 (Git-Tag, GitHub-Release, SVN nach
      README des Repos), Einreichung bei WordPress.org; danach GlotPress
      (de/default, de/formal).

## 14. Offen

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
- Design-Einstellungen in der Oberfläche (Erweiterung 1.1): Abschnitt
  „Design“ mit Feldern für Farben (`sanitize_hex_color()`) und Maße (Zahl
  plus Einheiten-Whitelist `px`, `em`, `rem`, `%`, `s`), Ausgabe nur der vom
  Default abweichenden Properties per `wp_add_inline_style( 'jgor-flcf7',
  … )` am vorhandenen Handle. Kostet die strikte CSP-Tauglichkeit für Styles
  ohne `'unsafe-inline'`; darum abschaltbar („Design-Werte ausgeben“).
- Komfort-Oberflächen für die Steuerung je Formular und Platzierung
  (Erweiterung 1.1): eigener Tab im CF7-Editor (`wpcf7_editor_panels`,
  Speichern auf `wpcf7_save_contact_form`) statt Additional Settings, und
  eigene Felder in der Seitenleiste des CF7-Blocks (Editor-JS ohne
  Build-Schritt über `blocks.registerBlockType` und `editor.BlockEdit`,
  serverseitig schreibt `render_block_data` den Wert in `htmlClass`). Beides
  nur Oberfläche, der Mechanismus aus 7.4 bleibt.
- Umstieg von DOMDocument auf `WP_HTML_Processor`, sobald der Core das
  Einfügen von Elementen unterstützt.

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

=== Floating Labels for CF7 ===
Contributors: jgorres
Tags: contact form 7, floating labels, forms, labels, accessibility
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: contact-form-7
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns placeholders and labels of Contact Form 7 fields into floating labels. Pure CSS, no JavaScript.

== Description ==

A floating label sits inside the empty field like a placeholder and moves to the top edge, smaller, as soon as the field is focused or filled. The label stays visible the whole time and is a real `<label for>` linked to the field, so screen readers and the browser's autofill know what the field is for.

Floating Labels for CF7 rebuilds the markup Contact Form 7 renders on the server and ships the stylesheet for it. Nothing in your forms has to change: the plugin recognises the default Contact Form 7 template (`<label> Your name [text* your-name] </label>`) as well as fields with a `placeholder` option, and it leaves everything else exactly as Contact Form 7 renders it. Forms built with older Contact Form 7 versions, with loose text in front of the fields instead of labels or placeholders, get floating labels too once you switch on the option "Text before the field".

= Features =

* Works with existing forms: labels of the form template and placeholder options become floating labels automatically. Older templates with loose text in front of the fields (`<p>Your Name<br />[text* your-name]</p>`) are covered by the option "Text before the field".
* Three variants: Outlined (label floats onto the border), Filled (tinted box) and Minimal (your theme styles the field, the label floats above it).
* Focus effects: a glow, a pulsing glow or a light running around the border of the focused field, in a colour of your choice. Pure CSS; visitors who prefer reduced motion see them without movement.
* Pure CSS on the front end: focus, filled, autofill and error states come from CSS pseudo-classes. No JavaScript on your pages; the only inline styles are the design values you change (one short rule after the stylesheet, and a style attribute on forms with their own design values).
* Accessible: every rebuilt field gets a real `<label for>` with a matching `id`; the placeholder is replaced so screen readers do not hear two names. Contact Form 7's `aria-*` attributes stay untouched.
* Clear error state: the label of an invalid field floats up in the error colour, Contact Form 7's message appears inside the field (no layout jump), and the submit button turns red with a text of your choice until the next attempt.
* Control at every level: for the whole site, per form, per placement (page) and per field.
* Block editor: the Contact Form 7 block, and a Shortcode block with the `[contact-form-7]` shortcode, get "Floating labels" panels in the sidebar: the placement settings, and colours, sizes, animation and focus effect for that placement only; no classes to type, and a reset button in every panel.
* Design settings: colours with the WordPress colour picker, font size, padding, border, corner radius, the animation and the focus effect on the settings screen, or per placement in the block editor; every value is also a CSS custom property, so themes can set them too.
* Lightweight: one option, one stylesheet, no database tables, no cron, no external requests, no tracking.

= Field types =

Text, email, URL, telephone, number and textarea fields get floating labels. Checkboxes, radio buttons, selects, file uploads, quizzes and CAPTCHAs never do; they keep their original markup.

= Per form =

Add lines to the "Additional Settings" tab of a form:

`
floating_labels: off
floating_labels: placeholder
floating_labels: label
floating_labels: text
floating_labels_style: filled
floating_labels_effect: glow
floating_labels_marker: on
floating_labels_types: text email textarea
`

= Per placement =

The same form can look different on different pages. Select the Contact Form 7 block, or a Shortcode block with the `[contact-form-7]` shortcode, and open the panel "Floating labels" in the block sidebar: it offers on/off, the label source, the variant, the focus effect and the required marker for this placement. The choice is stored as classes in the block's "Class" field (panel "Form attributes") or in the `html_class` attribute of the shortcode, which you can also write by hand:

`
jgor-flcf7-off  jgor-flcf7-on
jgor-flcf7-mode-placeholder  jgor-flcf7-mode-label  jgor-flcf7-mode-text
jgor-flcf7-style-outlined  jgor-flcf7-style-filled  jgor-flcf7-style-minimal
jgor-flcf7-effect-none  jgor-flcf7-effect-glow  jgor-flcf7-effect-pulse  jgor-flcf7-effect-sweep
jgor-flcf7-marker-on  jgor-flcf7-marker-off
`

Example: `[contact-form-7 id="123" html_class="jgor-flcf7-style-filled"]`

The panels "Floating labels: colours", "Floating labels: sizes", "Floating labels: animation" and "Floating labels: focus effect" below it set colours, sizes, the animation and the look of the focus effect for this placement only. Leave a control empty to keep the value of the settings screen; the reset button at the end of each panel clears its values, and "Reset this placement" in the panel "Floating labels" clears the choices and all design values of this placement. The values are stored with the block, or in the `jgor_flcf7_design` attribute of the shortcode, which you can also write by hand (property names as on the settings screen, without the prefix):

`[contact-form-7 id="123" jgor_flcf7_design="border-color:#0a7d3e;font-size:1.1em"]`

= Per field =

Add the option `nofloat` to a form-tag, before any quoted value:

`[text* your-name nofloat placeholder "Your name"]`

= Colours and sizes =

Open Contact > Floating Labels, section Design: colours for the label, the border, the field background and the submit button on errors (WordPress colour picker), the font size of the field and of the label, padding, border width, corner radius, the scale of the floated label, the duration of the animation, and the colour, size, width and duration of the focus effect. Leave a field empty to keep the default. Only the values you change are printed, as CSS custom properties on the `.wpcf7` wrapper, right after the stylesheet. For one placement only, use the panels in the block editor (see "Per placement"); those values are printed as a style attribute on the wrapper of that form.

Themes can set the same properties in CSS, for example in Appearance > Customize > Additional CSS or a child theme:

`
.wpcf7 {
	--jgor-flcf7-label-size: 0.9em;
	--jgor-flcf7-border-color-active: #0a7d3e;
}
`

To style one form only, replace `.wpcf7` with the id of its wrapper, for example `[id^="wpcf7-f123-"]`. The settings screen shows the full list of properties with the values in effect.

= For developers =

* `jgor_flcf7_settings`: fix settings in code; the settings screen shows them disabled.
* `jgor_flcf7_form_settings`: change the effective settings per rendering (mode, types, variant, focus effect, required marker).
* `jgor_flcf7_enabled`: switch floating labels on or off per form.
* `jgor_flcf7_label_text`: change the label text per field.
* `jgor_flcf7_field_types`: extend the list of selectable field types.

= Requirements and privacy =

The plugin needs Contact Form 7 (5.0 or newer) and the PHP extension DOM, which is part of every standard PHP build. It stores one option in the WordPress database, sends no data anywhere and loads nothing from third-party servers.

Contact Form 7 is a trademark of Rock Lobster, Inc. This plugin is an independent add-on and is not affiliated with or endorsed by Rock Lobster, Inc.

== Installation ==

1. Install and activate Contact Form 7.
2. Upload the plugin folder to `wp-content/plugins/` or install the ZIP via Plugins > Add New > Upload Plugin.
3. Activate the plugin under Plugins.
4. Open Contact > Floating Labels to choose the variant, the focus effect, the field types, the text of the submit button on errors and, under Design, colours and sizes. These are the defaults for every form; forms (Additional Settings) and placements (block editor) can override them. The defaults work out of the box: every text-like field of every form gets a floating label, required fields an asterisk. Forms without labels and placeholders (older templates) need the option "Text before the field" under "Label sources".

== Frequently Asked Questions ==

= A field does not get a floating label. Why? =

Check these points in order: the field type is one of text, email, URL, telephone, number or textarea and ticked in the settings; the form-tag has no `nofloat` option; the form has no `floating_labels: off` line in its Additional Settings; the field has a label text, either a `<label>` that encloses the field or points at its `id`, or a `placeholder` option, or, with the option "Text before the field" switched on, loose text right in front of the field or in the paragraph just before it. Labels that contain more than one field or block-level HTML are left alone on purpose.

= My forms come from an older Contact Form 7 and have neither labels nor placeholders. =

The default template of Contact Form 7 before version 5.4 put the field name as loose text in front of the field: `<p>Your Name (required)<br />[text* your-name]</p>`. Switch on "Text before the field" under Contact > Floating Labels, section "Label sources", or add `floating_labels: text` to the Additional Settings of that form, or choose "Text before the field only" in the block sidebar for one placement. The text right in front of the field, or the paragraph just before it, becomes the floating label; the stored form is not changed. It is a heuristic, which is why it is off by default: check the result, add `nofloat` to fields that should keep their text, and use the filter `jgor_flcf7_label_text` to strip additions such as "(required)" from the label.

= How do I switch floating labels off for one form? =

Add `floating_labels: off` to the "Additional Settings" tab of that form. To switch them off for one page only, add the class `jgor-flcf7-off` to the "Class" field of the Contact Form 7 block or to the `html_class` attribute of the shortcode.

= Can one form use a different variant on one page? =

Yes. Add `jgor-flcf7-style-filled`, `jgor-flcf7-style-outlined` or `jgor-flcf7-style-minimal` to the "Class" field of the block or to `html_class` of the shortcode. Placement classes override the form's Additional Settings, which override the global settings.

= Can the focused field glow? =

Yes. Under Contact > Floating Labels, section Appearance, choose the focus effect: a glow, a pulsing glow or a light running around the border. Colour, size, width and duration are set under Design ("Glow and running light"). Per form, add `floating_labels_effect: glow` (or `pulse`, `sweep`, `none`) to the Additional Settings; per placement, use the panel in the block sidebar or the class `jgor-flcf7-effect-glow`. The effects are pure CSS and respect `prefers-reduced-motion`: the pulse becomes a steady glow, the running light a steady ring.

= How do I change the colours? =

On the settings screen under Design: pick the colours with the colour picker, enter sizes with their unit (for example 0.9em or 12px) and leave a field empty to keep the default. For one page only, select the block and use the panels "Floating labels: colours", "Floating labels: sizes", "Floating labels: animation" and "Floating labels: focus effect" in the block sidebar. Themes can set the same CSS custom properties; the snippet on the screen shows the values in effect.

= Does it work with the block editor? =

Yes. The Contact Form 7 block, and a Shortcode block that holds the `[contact-form-7]` shortcode, get the panel "Floating labels" (placement settings) and the panels "Floating labels: colours", "Floating labels: sizes", "Floating labels: animation" and "Floating labels: focus effect" (design values for this placement) in the sidebar. The choices are stored as classes in the block's "Class" field (panel "Form attributes") or in the `html_class` attribute of the shortcode, the design values with the block or in the `jgor_flcf7_design` attribute of the shortcode, so the content stays valid without the plugin. Every panel ends with a reset button: "Reset this placement" removes the choices and the design values of this placement, the design panels reset their own values only.

= Does the plugin load JavaScript? =

Not on your pages. All front-end states are CSS. The plugin adds one stylesheet, loaded after the Contact Form 7 stylesheet and following its loading policy (`WPCF7_LOAD_CSS`, filter `wpcf7_load_css`). The only scripts are a small one for the block editor and the colour picker on the settings screen, both loaded in the admin only.

= What happens on validation errors? =

Contact Form 7 marks the field; the label floats up and turns red together with the border, and the validation message appears inside the field, where the label rested, so the page does not jump. The submit button turns red and shows the text from the settings ("Please check the highlighted fields" by default; leave it empty to change only the colour) until the form is sent again.

= Why does the label of a textarea sit on the first line? =

So that it does not jump when Contact Form 7 appends its validation message to the field wrapper. Floated labels move to the top edge like for every other field.

= Which field types are not supported? =

Date fields (browsers show a format mask) and selects are planned for a later version. Checkboxes, radio buttons, acceptance, quiz, file and CAPTCHA fields never get floating labels.

= Is the plugin accessible? =

Every rebuilt field keeps or gets an `id` and a real `<label for>` pointing at it. The visible placeholder is replaced by a single space, so screen readers read one name only. Contact Form 7's `aria-required`, `aria-invalid` and `aria-describedby` attributes are preserved, and the stylesheet respects `prefers-reduced-motion` for the label transitions and the focus effects.

== Screenshots ==

1. Outlined variant: labels at rest and floated, with a focused field.
2. Filled variant.
3. Minimal variant with the theme's own field styling.
4. Validation error: label and border in the error colour, message below the field.
5. Settings screen under Contact > Floating Labels.
6. The panels "Floating labels" in the sidebar of the Contact Form 7 block (also shown for a Shortcode block with the shortcode).

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

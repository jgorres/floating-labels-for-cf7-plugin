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

Floating Labels for CF7 rebuilds the markup Contact Form 7 renders on the server and ships the stylesheet for it. Nothing in your forms has to change: the plugin recognises the default Contact Form 7 template (`<label> Your name [text* your-name] </label>`) as well as fields with a `placeholder` option, and it leaves everything else exactly as Contact Form 7 renders it.

= Features =

* Works with existing forms: labels of the form template and placeholder options become floating labels automatically.
* Three variants: Outlined (label floats onto the border), Filled (tinted box) and Minimal (your theme styles the field, the label floats above it).
* Pure CSS on the front end: focus, filled, autofill and error states come from CSS pseudo-classes. No JavaScript on your pages, no inline styles, Content Security Policy friendly.
* Accessible: every rebuilt field gets a real `<label for>` with a matching `id`; the placeholder is replaced so screen readers do not hear two names. Contact Form 7's `aria-*` attributes stay untouched.
* Clear error state: the label of an invalid field floats up in the error colour, Contact Form 7's message appears inside the field (no layout jump), and the submit button turns red with a text of your choice until the next attempt.
* Control at every level: for the whole site, per form, per placement (page) and per field.
* Block editor: the Contact Form 7 block, and a Shortcode block with the `[contact-form-7]` shortcode, get a "Floating Labels" panel in the sidebar for the placement settings; no classes to type.
* Theme friendly: colours, sizes and timing are CSS custom properties; the settings screen gives you the snippet to copy.
* Lightweight: one option, one stylesheet, no database tables, no cron, no external requests, no tracking.

= Field types =

Text, email, URL, telephone, number and textarea fields get floating labels. Checkboxes, radio buttons, selects, file uploads, quizzes and CAPTCHAs never do; they keep their original markup.

= Per form =

Add lines to the "Additional Settings" tab of a form:

`
floating_labels: off
floating_labels: placeholder
floating_labels: label
floating_labels_style: filled
floating_labels_marker: on
floating_labels_types: text email textarea
`

= Per placement =

The same form can look different on different pages. Select the Contact Form 7 block, or a Shortcode block with the `[contact-form-7]` shortcode, and open the panel "Floating Labels" in the block sidebar: it offers on/off, the label source, the variant and the required marker for this placement. The choice is stored as classes in the block's "Class" field (panel "Form attributes") or in the `html_class` attribute of the shortcode, which you can also write by hand:

`
jgor-flcf7-off  jgor-flcf7-on
jgor-flcf7-mode-placeholder  jgor-flcf7-mode-label
jgor-flcf7-style-outlined  jgor-flcf7-style-filled  jgor-flcf7-style-minimal
jgor-flcf7-marker-on  jgor-flcf7-marker-off
`

Example: `[contact-form-7 id="123" html_class="jgor-flcf7-style-filled"]`

= Per field =

Add the option `nofloat` to a form-tag, before any quoted value:

`[text* your-name nofloat placeholder "Your name"]`

= Colours and sizes =

Colours, sizes and timing are CSS custom properties on the `.wpcf7` wrapper. Copy the snippet from the settings screen into Appearance > Customize > Additional CSS or into your child theme and change the values you need, for example:

`
.wpcf7 {
	--jgor-flcf7-label-size: 0.9em;
	--jgor-flcf7-border-color-active: #0a7d3e;
}
`

To style one form only, replace `.wpcf7` with the id of its wrapper, for example `[id^="wpcf7-f123-"]`.

= For developers =

* `jgor_flcf7_settings`: fix settings in code; the settings screen shows them disabled.
* `jgor_flcf7_form_settings`: change the effective settings per rendering (mode, types, variant, required marker).
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
4. Open Contact > Floating Labels to choose the variant, the field types and the text of the submit button on errors. The defaults work out of the box: every text-like field of every form gets a floating label.

== Frequently Asked Questions ==

= A field does not get a floating label. Why? =

Check these points in order: the field type is one of text, email, URL, telephone, number or textarea and ticked in the settings; the form-tag has no `nofloat` option; the form has no `floating_labels: off` line in its Additional Settings; the field has a label text, either a `<label>` that encloses the field or points at its `id`, or a `placeholder` option. Labels that contain more than one field or block-level HTML are left alone on purpose.

= How do I switch floating labels off for one form? =

Add `floating_labels: off` to the "Additional Settings" tab of that form. To switch them off for one page only, add the class `jgor-flcf7-off` to the "Class" field of the Contact Form 7 block or to the `html_class` attribute of the shortcode.

= Can one form use a different variant on one page? =

Yes. Add `jgor-flcf7-style-filled`, `jgor-flcf7-style-outlined` or `jgor-flcf7-style-minimal` to the "Class" field of the block or to `html_class` of the shortcode. Placement classes override the form's Additional Settings, which override the global settings.

= How do I change the colours? =

Through CSS custom properties. The settings screen shows a snippet with every property and its default value; copy it into Appearance > Customize > Additional CSS or your child theme and change what you need.

= Does it work with the block editor? =

Yes. The Contact Form 7 block, and a Shortcode block that holds the `[contact-form-7]` shortcode, get a panel "Floating Labels" in the sidebar. The choices are stored as classes in the block's "Class" field (panel "Form attributes") or in the `html_class` attribute of the shortcode, so the content stays valid without the plugin.

= Does the plugin load JavaScript? =

Not on your pages. All front-end states are CSS. The plugin adds one stylesheet, loaded after the Contact Form 7 stylesheet and following its loading policy (`WPCF7_LOAD_CSS`, filter `wpcf7_load_css`). The only script is a small one for the block editor, loaded in the admin only.

= What happens on validation errors? =

Contact Form 7 marks the field; the label floats up and turns red together with the border, and the validation message appears inside the field, where the label rested, so the page does not jump. The submit button turns red and shows the text from the settings ("Please check the highlighted fields" by default; leave it empty to change only the colour) until the form is sent again.

= Why does the label of a textarea sit on the first line? =

So that it does not jump when Contact Form 7 appends its validation message to the field wrapper. Floated labels move to the top edge like for every other field.

= Which field types are not supported? =

Date fields (browsers show a format mask) and selects are planned for a later version. Checkboxes, radio buttons, acceptance, quiz, file and CAPTCHA fields never get floating labels.

= Is the plugin accessible? =

Every rebuilt field keeps or gets an `id` and a real `<label for>` pointing at it. The visible placeholder is replaced by a single space, so screen readers read one name only. Contact Form 7's `aria-required`, `aria-invalid` and `aria-describedby` attributes are preserved, and the stylesheet respects `prefers-reduced-motion`.

== Screenshots ==

1. Outlined variant: labels at rest and floated, with a focused field.
2. Filled variant.
3. Minimal variant with the theme's own field styling.
4. Validation error: label and border in the error colour, message below the field.
5. Settings screen under Contact > Floating Labels.
6. The panel "Floating Labels" in the sidebar of the Contact Form 7 block (also shown for a Shortcode block with the shortcode).

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

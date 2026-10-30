<?php
/**
 * Verifies module 2 (markup rebuild) against the test forms of
 * tests/create-test-forms.php. Prints one PASS/FAIL line per check and
 * exits with an error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-markup.php
 *
 * The test forms and the page "Contact" come from tests/create-test-forms.php,
 * which this script includes: created at the start, removed at the end
 * (set FLCF7_KEEP=1 in the environment to keep them for a look).
 *
 * Besides the rebuild itself the script covers the control layers of the
 * concept (section 7.5): additional settings per form (floating_labels_*),
 * html_class classes per placement (block field "Class" or shortcode
 * attribute) and the filters jgor_flcf7_form_settings, jgor_flcf7_enabled
 * and jgor_flcf7_label_text.
 *
 * Form F (the Contact Form 7 template before 5.4, no labels and no
 * placeholders) covers text mode (concept 7.3), which is off by default.
 * Form H covers date and select fields (concept 7.8): the wrap classes, the
 * emptied blank option, the label from first_as_label, and the selects the
 * module leaves alone (multiple, include_blank without a label).
 *
 * Form C is rendered a second time to cover the id option Contact Form 7
 * prints only once per request (same form twice on a page). The design
 * values per placement (shortcode attribute jgor_flcf7_design, block
 * attribute jgorFlcf7Design, style on the wrapper) are rendered through
 * do_shortcode() and render_block().
 *
 * Set FLCF7_DUMP=1 in the environment to print the rendered HTML of forms A
 * and C after the checks.
 *
 * The checks assume the default settings: the script saves the stored
 * option, runs on the defaults (and changes them temporarily for some
 * checks) and restores the saved option afterwards; the forms themselves
 * are not modified.
 *
 * @package Floating_Labels_For_CF7
 */

( defined( 'WP_CLI' ) && WP_CLI ) || exit;

// eval-file runs this file inside a function scope: the counter lives in $GLOBALS.
$GLOBALS['jgor_flcf7_failed'] = 0;

/**
 * Records one check.
 *
 * @param string $label  Check description.
 * @param bool   $ok     Result.
 * @param string $detail Optional detail shown on failure.
 * @return void
 */
function jgor_flcf7_check( string $label, bool $ok, string $detail = '' ): void {
	global $jgor_flcf7_failed;

	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( '' !== $detail && ! $ok ? ' -- ' . $detail : '' ) );

	if ( ! $ok ) {
		++$jgor_flcf7_failed;
	}
}

/**
 * Returns the ID of a contact form by title.
 *
 * @param string $title Form title.
 * @return int
 */
function jgor_flcf7_form_id( string $title ): int {
	$posts = get_posts(
		array(
			'post_type'   => 'wpcf7_contact_form',
			'title'       => $title,
			'numberposts' => 1,
			'post_status' => 'any',
		)
	);

	if ( ! $posts ) {
		WP_CLI::error( 'Form "' . $title . '" not found. Run tests/create-test-forms.php first.' );
	}

	return (int) $posts[0]->ID;
}

/**
 * Renders a form, optionally with the plugin disabled, with other additional
 * settings (in memory only, nothing is saved) or with shortcode attributes
 * such as html_class.
 *
 * @param int                         $id       Form ID.
 * @param bool                        $enabled  False renders with the filter jgor_flcf7_enabled returning false.
 * @param string|null                 $settings Additional settings to use instead of the stored ones.
 * @param string|array<string, mixed> $options  Options for form_html(), e.g. array( 'html_class' => '...' ).
 * @return string
 */
function jgor_flcf7_render( int $id, bool $enabled = true, ?string $settings = null, $options = '' ): string {
	$contact_form = WPCF7_ContactForm::get_instance( $id );

	if ( null !== $settings ) {
		$contact_form->set_properties( array( 'additional_settings' => $settings ) );
	}

	if ( ! $enabled ) {
		add_filter( 'jgor_flcf7_enabled', '__return_false' );
	}

	$html = $contact_form->form_html( $options );

	if ( ! $enabled ) {
		remove_filter( 'jgor_flcf7_enabled', '__return_false' );
	}

	return $html;
}

/**
 * Normalises self-closing slashes (the DOM round trip drops them).
 *
 * @param string $html HTML.
 * @return string
 */
function jgor_flcf7_norm( string $html ): string {
	return str_replace( ' />', '>', $html );
}

/**
 * Normalises the unit-tag counter, which differs between two renderings.
 *
 * @param string $html HTML.
 * @return string
 */
function jgor_flcf7_norm_unit( string $html ): string {
	return (string) preg_replace( '#-o\d+#', '-oN', $html );
}

/**
 * Extracts one field from its wrap to the end of the enclosing paragraph.
 *
 * @param string $html HTML.
 * @param string $name Field name.
 * @return string Empty when not found.
 */
function jgor_flcf7_wrap( string $html, string $name ): string {
	if ( ! preg_match( '#<span class="wpcf7-form-control-wrap[^"]*" data-name="' . preg_quote( $name, '#' ) . '">.*?</p>#s', $html, $m ) ) {
		return '';
	}

	return $m[0];
}

/**
 * Checks that every floating label follows a control with the id it points to.
 *
 * @param string $html HTML.
 * @return void
 */
function jgor_flcf7_labels_match( string $html ): void {
	preg_match_all( '#<label for="([^"]+)" class="jgor-flcf7-label">(.*?)</label>#s', $html, $m, PREG_SET_ORDER );

	foreach ( $m as $label ) {
		$for = $label[1];
		jgor_flcf7_check(
			'label for="' . $for . '" has a control with that id directly before it',
			(bool) preg_match( '#<(input|textarea|select)[^>]*\sid="' . preg_quote( $for, '#' ) . '"[^>]*>(?:[^<]*</textarea>|(?:\s*<option[^>]*>[^<]*</option>)*\s*</select>)?<label for="' . preg_quote( $for, '#' ) . '" class="jgor-flcf7-label"#s', $html )
		);
	}
}

// Test forms and the page "Contact": created here, removed at the end.
$GLOBALS['jgor_flcf7_test_forms_quiet'] = true;
require __DIR__ . '/create-test-forms.php';
jgor_flcf7_create_test_forms();

// Save the stored option and run on the defaults.
$jgor_flcf7_saved_option = get_option( 'jgor_flcf7_settings', false );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();

$jgor_flcf7_id_a = jgor_flcf7_form_id( 'Contact form 1' );
$jgor_flcf7_id_b = jgor_flcf7_form_id( 'FL Test B Placeholder' );
$jgor_flcf7_id_c = jgor_flcf7_form_id( 'FL Test C Mixed' );
$jgor_flcf7_id_d = jgor_flcf7_form_id( 'FL Test D Off' );
$jgor_flcf7_id_f = jgor_flcf7_form_id( 'FL Test F Old template' );
$jgor_flcf7_id_h = jgor_flcf7_form_id( 'FL Test H Date and select' );

// (a) Standard template, label mode.
$jgor_flcf7_a = jgor_flcf7_render( $jgor_flcf7_id_a );
jgor_flcf7_check( 'A: 4 floating labels', 4 === preg_match_all( '#<label[^>]*class="jgor-flcf7-label"#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: 4 placeholders set to one space', 4 === preg_match_all( '#placeholder=" "#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: enclosing labels replaced by span.jgor-flcf7-field', 4 === preg_match_all( '#<span class="jgor-flcf7-field">#', $jgor_flcf7_a ) && 4 === preg_match_all( '#<label#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: no <br> left inside the field spans', ! preg_match( '#<span class="jgor-flcf7-field">\s*<br#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: label text taken from template', str_contains( $jgor_flcf7_a, '>Your name</label>' ) && str_contains( $jgor_flcf7_a, '>Your message (optional)</label>' ) );
jgor_flcf7_check( 'A: variant class on wrap', 4 === preg_match_all( '#class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: textarea wrap marked, marker class behind it (required marker on by default)', 1 === preg_match_all( '#jgor-flcf7-outlined jgor-flcf7-textarea jgor-flcf7-marker"#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: ids from unit tag', (bool) preg_match( '#id="wpcf7-f' . $jgor_flcf7_id_a . '-o\d+-fl-your-name"#', $jgor_flcf7_a ) );
jgor_flcf7_labels_match( $jgor_flcf7_a );
jgor_flcf7_check( 'A: submit untouched', str_contains( $jgor_flcf7_a, '<input class="wpcf7-form-control wpcf7-submit has-spinner" type="submit" value="Submit">' ) );
jgor_flcf7_check( 'A: submit wrapped with the default error text', (bool) preg_match( '#<span class="jgor-flcf7-submit"><input class="wpcf7-form-control wpcf7-submit has-spinner" type="submit" value="Submit"><span class="jgor-flcf7-submit-error" aria-hidden="true">Please check the highlighted fields</span></span>#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: aria attributes kept', str_contains( $jgor_flcf7_a, 'aria-required="true" aria-invalid="false"' ) );
jgor_flcf7_check( 'A with floating_labels: placeholder -> untouched (no placeholders)', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_a, true, 'floating_labels: placeholder' ), 'jgor-flcf7' ) );
jgor_flcf7_check( 'A with filter jgor_flcf7_enabled=false -> untouched', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_a, false ), 'jgor-flcf7' ) );

// (b) Placeholder template.
$jgor_flcf7_b = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'B: 3 floating labels from placeholders', 3 === preg_match_all( '#<label[^>]*class="jgor-flcf7-label"#', $jgor_flcf7_b ) );
jgor_flcf7_check( 'B: texts', str_contains( $jgor_flcf7_b, '>Your name</label>' ) && str_contains( $jgor_flcf7_b, '>Your email</label>' ) && str_contains( $jgor_flcf7_b, '>Your message</label>' ) );
jgor_flcf7_check( 'B: no original placeholder text left', ! str_contains( $jgor_flcf7_b, 'placeholder="Your' ) );
jgor_flcf7_labels_match( $jgor_flcf7_b );
jgor_flcf7_check( 'B with floating_labels: label -> untouched (no labels)', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels: label' ), 'jgor-flcf7' ) );

// (c) Mixed.
$jgor_flcf7_c  = jgor_flcf7_render( $jgor_flcf7_id_c );
$jgor_flcf7_c0 = jgor_flcf7_render( $jgor_flcf7_id_c, false );
jgor_flcf7_check( 'C: linked label moved into wrap with for=fl-subject', (bool) preg_match( '#<input[^>]*id="fl-subject"[^>]*placeholder=" "[^>]*><label for="fl-subject" class="jgor-flcf7-label">Subject</label></span>#', $jgor_flcf7_c ) );
jgor_flcf7_check( 'C: <br> after moved label removed, paragraph kept', (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-subject">#', $jgor_flcf7_c ) );
jgor_flcf7_check( 'C: nofloat field untouched', str_contains( $jgor_flcf7_c, 'placeholder="Nickname"' ) && ! preg_match( '#data-name="your-nick"><input[^>]*jgor-flcf7#', $jgor_flcf7_c ) );
jgor_flcf7_check( 'C: enclosing label with <strong> -> text "Your website"', str_contains( $jgor_flcf7_c, '>Your website</label>' ) );
jgor_flcf7_check( 'C: tel and number via placeholder', str_contains( $jgor_flcf7_c, '>Phone</label>' ) && str_contains( $jgor_flcf7_c, '>Age</label>' ) );
foreach ( array( 'your-choice', 'your-cb', 'your-file' ) as $jgor_flcf7_name ) {
	$jgor_flcf7_wrap_c  = jgor_flcf7_wrap( $jgor_flcf7_c, $jgor_flcf7_name );
	$jgor_flcf7_wrap_c0 = jgor_flcf7_norm( jgor_flcf7_wrap( $jgor_flcf7_c0, $jgor_flcf7_name ) );
	jgor_flcf7_check(
		'C: ' . $jgor_flcf7_name . ' identical to original (modulo self-closing slash)',
		'' !== $jgor_flcf7_wrap_c && $jgor_flcf7_wrap_c0 === $jgor_flcf7_wrap_c
	);
}
jgor_flcf7_check( 'C: exactly 4 floating labels', 4 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_c ) );
jgor_flcf7_labels_match( $jgor_flcf7_c );

// (d) Off via additional settings: byte-identical to the disabled output.
$jgor_flcf7_d  = jgor_flcf7_render( $jgor_flcf7_id_d );
$jgor_flcf7_d0 = jgor_flcf7_render( $jgor_flcf7_id_d, false );
jgor_flcf7_check( 'D: floating_labels: off -> byte-identical to original (unit tag normalised)', jgor_flcf7_norm_unit( $jgor_flcf7_d ) === jgor_flcf7_norm_unit( $jgor_flcf7_d0 ) && ! str_contains( $jgor_flcf7_d, 'jgor-flcf7' ) );

// (e) Same form twice: distinct ids.
preg_match_all( '#id="(wpcf7-f' . $jgor_flcf7_id_b . '-o\d+-fl-your-name)"#', jgor_flcf7_render( $jgor_flcf7_id_b ) . jgor_flcf7_render( $jgor_flcf7_id_b ), $jgor_flcf7_m );
jgor_flcf7_check( 'E: two instances, two different ids', 2 === count( $jgor_flcf7_m[1] ) && $jgor_flcf7_m[1][0] !== $jgor_flcf7_m[1][1] );

// (c) a second time: Contact Form 7 prints the id option "fl-subject" only
// once per request, so this rendering has no id on the control; the plugin
// finds the linked label through the option and gives the control a fresh id.
$jgor_flcf7_c2 = jgor_flcf7_render( $jgor_flcf7_id_c );
jgor_flcf7_check( 'C twice: Contact Form 7 drops id="fl-subject" in the second rendering (precondition)', ! str_contains( $jgor_flcf7_c2, 'id="fl-subject"' ) );
jgor_flcf7_check( 'C twice: linked label found through the id option, moved behind the control with a unit-tag id', (bool) preg_match( '#<input[^>]*id="(wpcf7-f' . $jgor_flcf7_id_c . '-o\d+-fl-your-subject)"[^>]*placeholder=" "[^>]*><label for="\1" class="jgor-flcf7-label">Subject</label></span>#', $jgor_flcf7_c2 ) );
jgor_flcf7_check( 'C twice: <br> removed, 4 floating labels as in the first rendering', (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-subject">#', $jgor_flcf7_c2 ) && 4 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_c2 ) );
jgor_flcf7_labels_match( $jgor_flcf7_c2 );

// (f) Old template without labels and placeholders (concept 7.3): text mode, off by default.
$jgor_flcf7_f  = jgor_flcf7_render( $jgor_flcf7_id_f );
$jgor_flcf7_f0 = jgor_flcf7_render( $jgor_flcf7_id_f, false );
jgor_flcf7_check( 'F: mode_text off by default -> byte-identical to original (unit tag normalised)', jgor_flcf7_norm_unit( $jgor_flcf7_f ) === jgor_flcf7_norm_unit( $jgor_flcf7_f0 ) && ! str_contains( $jgor_flcf7_f, 'jgor-flcf7' ) );
$jgor_flcf7_f = jgor_flcf7_render( $jgor_flcf7_id_f, true, 'floating_labels: text' );
jgor_flcf7_check( 'F: floating_labels: text -> 7 floating labels', 7 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_f ) );
jgor_flcf7_check( 'F: label texts from the loose text in front of the fields', str_contains( $jgor_flcf7_f, '>Your Name (required)</label>' ) && str_contains( $jgor_flcf7_f, '>Your Email (required)</label>' ) && str_contains( $jgor_flcf7_f, '>Phone</label>' ) && str_contains( $jgor_flcf7_f, '>Company</label>' ) && str_contains( $jgor_flcf7_f, '>Your Message</label>' ) && str_contains( $jgor_flcf7_f, '>Website</label>' ) && str_contains( $jgor_flcf7_f, '>Nickname</label>' ) );
jgor_flcf7_check( 'F: text paragraphs from the old template removed (Contact Form 7 renders Text<br />[tag] as two paragraphs), wrap paragraphs kept', ! preg_match( '#<p>\s*(?:Your Name \(required\)|Your Email \(required\)|Phone|Company|Your Message)\s*</p>#', $jgor_flcf7_f ) && (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-name">#', $jgor_flcf7_f ) && (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-company">#', $jgor_flcf7_f ) );
jgor_flcf7_check( 'F: 9 paragraphs left of 14', 9 === preg_match_all( '#<p>#', $jgor_flcf7_f ) );
jgor_flcf7_check( 'F: explanatory paragraph untouched', (bool) preg_match( '#<p>\s*Please describe your request in a few sentences\.\s*</p>#', $jgor_flcf7_f ) );
jgor_flcf7_check( 'F: <label> without for replaced by the floating label', ! str_contains( $jgor_flcf7_f, '<label>Nickname</label>' ) && (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-nick">#', $jgor_flcf7_f ) );
jgor_flcf7_check( 'F: text without <br> ("Website") removed from the paragraph', (bool) preg_match( '#<p>\s*<span class="wpcf7-form-control-wrap jgor-flcf7-wrap[^"]*" data-name="your-site">#', $jgor_flcf7_f ) && ! str_contains( $jgor_flcf7_f, '<p>Website' ) );
jgor_flcf7_labels_match( $jgor_flcf7_f );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_f, true, null, array( 'html_class' => 'jgor-flcf7-mode-text' ) );
jgor_flcf7_check( 'F: placement jgor-flcf7-mode-text -> rebuilt', 7 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_f, true, 'floating_labels: text', array( 'html_class' => 'jgor-flcf7-mode-label' ) );
jgor_flcf7_check( 'F: placement jgor-flcf7-mode-label overrides floating_labels: text -> untouched', ! preg_match( '#jgor-flcf7-(wrap|control|label)#', $jgor_flcf7_p ) );
jgor_flcf7_check( 'A with floating_labels: text -> untouched (enclosing labels are not loose text)', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_a, true, 'floating_labels: text' ), 'jgor-flcf7' ) );
jgor_flcf7_check( 'B with floating_labels: text -> untouched (no text in front of the fields)', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels: text' ), 'jgor-flcf7' ) );
$jgor_flcf7_option              = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['mode_text'] = true;
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
JGOR_FLCF7_Settings::instance()->reset_cache();
jgor_flcf7_check( 'F: option mode_text on -> rebuilt', 7 === preg_match_all( '#class="jgor-flcf7-label"#', jgor_flcf7_render( $jgor_flcf7_id_f ) ) );
jgor_flcf7_check( 'A: option mode_text on -> label mode still comes first (4 field spans)', 4 === preg_match_all( '#<span class="jgor-flcf7-field">#', jgor_flcf7_render( $jgor_flcf7_id_a ) ) );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();
$jgor_flcf7_f9 = function ( $settings ) {
	$settings['mode_text'] = true;
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f9 );
jgor_flcf7_check( 'F: filter jgor_flcf7_form_settings mode_text=true -> rebuilt', 7 === preg_match_all( '#class="jgor-flcf7-label"#', jgor_flcf7_render( $jgor_flcf7_id_f ) ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f9 );
$jgor_flcf7_f10 = function ( $text ) {
	return str_replace( ' (required)', '', $text );
};
add_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f10 );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_f, true, 'floating_labels: text' );
jgor_flcf7_check( 'F: filter jgor_flcf7_label_text strips "(required)"', str_contains( $jgor_flcf7_p, '>Your Name</label>' ) && ! str_contains( $jgor_flcf7_p, '(required)' ) );
remove_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f10 );

// (h) Date and select fields (concept 7.8).
$jgor_flcf7_h = jgor_flcf7_render( $jgor_flcf7_id_h );
jgor_flcf7_check( 'H: 5 floating labels (date, three selects, text); multiple select and label-less include_blank select untouched', 5 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_h ) );
jgor_flcf7_check( 'H: date wrap carries jgor-flcf7-date, placeholder replaced, label from the placeholder', (bool) preg_match( '#<span class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined jgor-flcf7-date jgor-flcf7-marker" data-name="your-date"><input(?=[^>]*type="date")(?=[^>]*placeholder=" ")(?=[^>]*jgor-flcf7-control)[^>]*><label for="[^"]+" class="jgor-flcf7-label">Preferred date</label></span>#', $jgor_flcf7_h ), jgor_flcf7_wrap( $jgor_flcf7_h, 'your-date' ) );
jgor_flcf7_check( 'H: select with include_blank in a label: field span, wrap jgor-flcf7-select, no placeholder attribute, blank option emptied, label "Topic"', (bool) preg_match( '#<span class="jgor-flcf7-field"><span class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined jgor-flcf7-select jgor-flcf7-marker" data-name="your-topic"><select(?![^>]*placeholder)[^>]*jgor-flcf7-control[^>]*>\s*<option value=""></option>\s*<option value="Sales">Sales</option>\s*<option value="Support">Support</option>\s*</select><label for="[^"]+" class="jgor-flcf7-label">Topic</label></span></span>#', $jgor_flcf7_h ), jgor_flcf7_wrap( $jgor_flcf7_h, 'your-topic' ) );
jgor_flcf7_check( 'H: required select with first_as_label: label text from the first option, that option emptied', (bool) preg_match( '#data-name="your-reason"><select(?=[^>]*aria-required="true")[^>]*jgor-flcf7-control[^>]*>\s*<option value=""></option>\s*<option value="Question">Question</option>#', $jgor_flcf7_h ) && str_contains( $jgor_flcf7_h, '>Reason for contact</label>' ), jgor_flcf7_wrap( $jgor_flcf7_h, 'your-reason' ) );
jgor_flcf7_check( 'H: select without blank option: first option kept, label "Country"', (bool) preg_match( '#data-name="your-country"><select[^>]*jgor-flcf7-control[^>]*>\s*<option value="Germany">Germany</option>#', $jgor_flcf7_h ) && str_contains( $jgor_flcf7_h, '>Country</label>' ) );
jgor_flcf7_check( 'H: multiple select untouched, enclosing label kept', (bool) preg_match( '#<label>\s*Languages\s*(?:<br\s*/?>)?\s*<span class="wpcf7-form-control-wrap" data-name="your-langs"><select[^>]*multiple#', $jgor_flcf7_h ) && ! preg_match( '#data-name="your-langs"><select[^>]*jgor-flcf7#', $jgor_flcf7_h ), jgor_flcf7_wrap( $jgor_flcf7_h, 'your-langs' ) );
jgor_flcf7_check( 'H: include_blank select without a label untouched, generic option text kept', (bool) preg_match( '#data-name="your-plain"><select(?![^>]*jgor-flcf7)[^>]*>\s*<option value="">(?:&\#8212;|—)Please choose an option(?:&\#8212;|—)</option>#u', $jgor_flcf7_h ), jgor_flcf7_wrap( $jgor_flcf7_h, 'your-plain' ) );
jgor_flcf7_labels_match( $jgor_flcf7_h );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_h, true, 'floating_labels_types: text' );
jgor_flcf7_check( 'H: floating_labels_types: text -> date and selects untouched', 1 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-select' ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-date' ) && str_contains( $jgor_flcf7_p, 'placeholder="Preferred date"' ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_h, true, 'floating_labels: label' );
jgor_flcf7_check( 'H: floating_labels: label -> only the two selects with a label rebuilt, first_as_label text kept elsewhere', 2 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) && str_contains( $jgor_flcf7_p, '<option value="">Reason for contact</option>' ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-date' ) );

// Per form: additional settings (concept 7.5).
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, "floating_labels_style: filled\nfloating_labels_marker: on" );
jgor_flcf7_check( 'form: floating_labels_style: filled -> filled wraps, no outlined', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-filled#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-outlined' ) );
jgor_flcf7_check( 'form: floating_labels_marker: on -> marker class on every wrap', 3 === preg_match_all( '#jgor-flcf7-marker"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_types: text' );
jgor_flcf7_check( 'form: floating_labels_types: text -> only the text field rebuilt', 1 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) && str_contains( $jgor_flcf7_p, 'placeholder="Your email"' ) && str_contains( $jgor_flcf7_p, 'placeholder="Your message"' ) );
jgor_flcf7_check( 'form: empty floating_labels_types -> untouched', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_types:' ), 'jgor-flcf7' ) );
jgor_flcf7_check( 'form: unknown floating_labels_style ignored -> outlined', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-outlined#', jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_style: fancy' ) ) );

// Per placement: html_class (block field "Class", shortcode attribute html_class).
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, null, array( 'html_class' => 'jgor-flcf7-style-minimal jgor-flcf7-marker-on' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-style-minimal -> minimal wraps', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-minimal#', $jgor_flcf7_p ) );
jgor_flcf7_check( 'placement: html_class stays on the form only (one occurrence)', 1 === preg_match_all( '#jgor-flcf7-style-minimal#', $jgor_flcf7_p ) && (bool) preg_match( '#<form[^>]*class="[^"]*jgor-flcf7-style-minimal#', $jgor_flcf7_p ) );
jgor_flcf7_check( 'placement: jgor-flcf7-marker-on -> marker class', 3 === preg_match_all( '#jgor-flcf7-marker"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, null, array( 'html_class' => 'jgor-flcf7-off' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-off -> no rebuild', ! preg_match( '#jgor-flcf7-(wrap|control|label)#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_style: filled', array( 'html_class' => 'jgor-flcf7-style-outlined' ) );
jgor_flcf7_check( 'placement wins over form: outlined despite floating_labels_style: filled', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-outlined#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-filled' ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_d, true, null, array( 'html_class' => 'jgor-flcf7-on' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-on overrides floating_labels: off of the form', 3 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, null, array( 'html_class' => 'jgor-flcf7-mode-label' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-mode-label on a placeholder form -> no rebuild', ! preg_match( '#jgor-flcf7-(wrap|control|label)#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_marker: on', array( 'html_class' => 'jgor-flcf7-marker-off' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-marker-off overrides floating_labels_marker: on', 3 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-marker"' ) );

// Focus effect: none by default, per form, per placement, through the filter; unknown values ignored.
jgor_flcf7_check( 'effect: no effect class by default', ! preg_match( '#jgor-flcf7-(?:glow|pulse|sweep)#', $jgor_flcf7_a ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_effect: glow' );
jgor_flcf7_check( 'form: floating_labels_effect: glow -> class jgor-flcf7-glow behind the marker on every wrap', 3 === preg_match_all( '#jgor-flcf7-marker jgor-flcf7-glow"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_effect: glow', array( 'html_class' => 'jgor-flcf7-effect-sweep' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-effect-sweep overrides floating_labels_effect: glow', 3 === preg_match_all( '#jgor-flcf7-sweep"#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-glow' ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_effect: pulse', array( 'html_class' => 'jgor-flcf7-effect-none' ) );
jgor_flcf7_check( 'placement: jgor-flcf7-effect-none -> no effect class', ! preg_match( '#jgor-flcf7-(?:glow|pulse|sweep)#', $jgor_flcf7_p ) && 3 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, 'floating_labels_effect: spin', array( 'html_class' => 'jgor-flcf7-effect-blink' ) );
jgor_flcf7_check( 'effect: unknown values in form and placement ignored', ! preg_match( '#jgor-flcf7-(?:glow|pulse|sweep|spin|blink)#', $jgor_flcf7_p ) );
$jgor_flcf7_fe = function ( $settings ) {
	$settings['effect'] = 'pulse';
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_fe );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, null, array( 'html_class' => 'jgor-flcf7-effect-glow' ) );
jgor_flcf7_check( 'filter jgor_flcf7_form_settings: effect wins over html_class', 3 === preg_match_all( '#jgor-flcf7-pulse"#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-glow' ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_fe );

// Filter jgor_flcf7_form_settings.
$jgor_flcf7_f3 = function ( $settings ) {
	$settings['style'] = 'filled';
	$settings['types'] = array( 'textarea' );
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f3 );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b, true, null, array( 'html_class' => 'jgor-flcf7-style-minimal' ) );
jgor_flcf7_check( 'filter jgor_flcf7_form_settings wins over html_class and limits the types', 1 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_p ) && 1 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-filled jgor-flcf7-textarea#', $jgor_flcf7_p ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f3 );
$jgor_flcf7_f4 = function ( $settings ) {
	$settings['enabled'] = false;
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f4 );
jgor_flcf7_check( 'filter jgor_flcf7_form_settings enabled=false -> untouched', ! str_contains( jgor_flcf7_render( $jgor_flcf7_id_b ), 'jgor-flcf7' ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f4 );
$jgor_flcf7_f5 = function ( $settings ) {
	$settings['style']           = 'bogus';
	$settings['types']           = 'text';
	$settings['required_marker'] = 'maybe';
	$settings['effect']          = 'spin';
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f5 );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'filter jgor_flcf7_form_settings with invalid values -> ignored (defaults keep: outlined, marker on, no effect)', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-outlined#', $jgor_flcf7_p ) && 3 === preg_match_all( '#jgor-flcf7-marker"#', $jgor_flcf7_p ) && ! preg_match( '#jgor-flcf7-(?:glow|pulse|sweep|spin)#', $jgor_flcf7_p ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f5 );

// Submit error text: filter, form settings filter and empty setting (colour only).
$jgor_flcf7_f6 = function ( $text ) {
	return 'Oops: ' . $text;
};
add_filter( 'jgor_flcf7_submit_error_text', $jgor_flcf7_f6 );
jgor_flcf7_check( 'filter jgor_flcf7_submit_error_text applied', str_contains( jgor_flcf7_render( $jgor_flcf7_id_b ), '<span class="jgor-flcf7-submit-error" aria-hidden="true">Oops: Please check the highlighted fields</span>' ) );
remove_filter( 'jgor_flcf7_submit_error_text', $jgor_flcf7_f6 );
$jgor_flcf7_f7 = function ( $settings ) {
	$settings['submit_error_text'] = '';
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f7 );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'empty submit error text -> wrapper without error span', (bool) preg_match( '#<span class="jgor-flcf7-submit"><input[^>]*type="submit"[^>]*></span>#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-submit-error' ) );
remove_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f7 );
$jgor_flcf7_option                      = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['submit_error_text'] = '  <b>Check</b> again  ';
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
JGOR_FLCF7_Settings::instance()->reset_cache();
jgor_flcf7_check( 'submit error text from the option is sanitized', str_contains( jgor_flcf7_render( $jgor_flcf7_id_b ), '>Check again</span>' ) );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();

// Filter jgor_flcf7_label_text.
$jgor_flcf7_f1 = function ( $text, $name ) {
	return 'your-name' === $name ? $text . ' *' : $text;
};
$jgor_flcf7_f2 = function ( $text, $name ) {
	return 'your-email' === $name ? '' : $text;
};
add_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f1, 10, 2 );
jgor_flcf7_check( 'filter jgor_flcf7_label_text applied', str_contains( jgor_flcf7_render( $jgor_flcf7_id_b ), '>Your name *</label>' ) );
add_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f2, 10, 2 );
$jgor_flcf7_f = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'filter returning empty string leaves field untouched', str_contains( $jgor_flcf7_f, 'placeholder="Your email"' ) && 2 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_f ) );
remove_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f1, 10 );
remove_filter( 'jgor_flcf7_label_text', $jgor_flcf7_f2, 10 );

// Settings: type list without textarea, then defaults restored.
$jgor_flcf7_option          = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['types'] = array( 'text', 'email' );
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
JGOR_FLCF7_Settings::instance()->reset_cache();
$jgor_flcf7_t = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'types without textarea -> textarea untouched', str_contains( $jgor_flcf7_t, 'placeholder="Your message"' ) && 2 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_t ) );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();

// UTF-8 and entities (in memory only).
$jgor_flcf7_u_form = WPCF7_ContactForm::get_instance( $jgor_flcf7_id_b );
$jgor_flcf7_u_form->set_properties( array( 'form' => '[text* name placeholder "Straße & Ort – Prüfung"]' ) );
jgor_flcf7_check( 'UTF-8 and entities in label text', str_contains( $jgor_flcf7_u_form->form_html(), '>Straße &amp; Ort – Prüfung</label>' ) );

// Design values per placement (concept 10.1): helpers.
jgor_flcf7_check(
	'placement design: parse() keeps known valid values in stylesheet order, normalised',
	array(
		'label-scale' => '0.8',
		'duration'    => '200ms',
	) === JGOR_FLCF7_Placement_Design::parse( 'duration: 200MS ; label-scale:0.80;bogus:1px;radius:round;font-size:' )
);
jgor_flcf7_check( 'placement design: parse() of non-strings and junk -> empty', array() === JGOR_FLCF7_Placement_Design::parse( null ) && array() === JGOR_FLCF7_Placement_Design::parse( 'no pairs here' ) );
jgor_flcf7_check(
	'placement design: serialize() and style()',
	'label-scale:0.8;duration:200ms' === JGOR_FLCF7_Placement_Design::serialize(
		array(
			'label-scale' => '0.8',
			'duration'    => '200ms',
		)
	) && '--jgor-flcf7-label-scale: 0.8; --jgor-flcf7-duration: 200ms;' === JGOR_FLCF7_Placement_Design::style(
		array(
			'label-scale' => '0.8',
			'duration'    => '200ms',
		)
	)
);

// Shortcode attribute -> style on the wrapper div.wpcf7.
$jgor_flcf7_pd = do_shortcode( '[contact-form-7 id="' . $jgor_flcf7_id_c . '" jgor_flcf7_design="label-color:#ABCDEF; font-size:1.2em;radius:round;bogus:1px;padding-x:"]' );
jgor_flcf7_check( 'placement design: valid values as custom properties on div.wpcf7, invalid and unknown dropped', str_contains( $jgor_flcf7_pd, '<div class="wpcf7 no-js" style="--jgor-flcf7-font-size: 1.2em; --jgor-flcf7-label-color: #abcdef;" id="wpcf7-f' . $jgor_flcf7_id_c . '-' ), substr( $jgor_flcf7_pd, 0, 200 ) );
jgor_flcf7_check( 'placement design: form still rebuilt', 4 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_pd ) && 1 === preg_match_all( '# style="#', $jgor_flcf7_pd ) );
$jgor_flcf7_pd = do_shortcode( '[contact-form-7 id="' . $jgor_flcf7_id_c . '" jgor_flcf7_design="radius:round"]' );
jgor_flcf7_check( 'placement design: only invalid values -> no style attribute', ! str_contains( $jgor_flcf7_pd, ' style="' ) && str_contains( $jgor_flcf7_pd, '<div class="wpcf7 no-js" id="' ) );
$jgor_flcf7_pd = do_shortcode( '[contact-form-7 id="' . $jgor_flcf7_id_c . '"]' );
jgor_flcf7_check( 'placement design: without the attribute -> no style attribute', ! str_contains( $jgor_flcf7_pd, ' style="' ) );

// Block attribute -> shortcode attribute at render time -> style on the wrapper.
$jgor_flcf7_block    = '<!-- wp:contact-form-7/contact-form-selector {"id":' . $jgor_flcf7_id_c . ',"title":"FL Test C Mixed","jgorFlcf7Design":{"border-color":"#00FF00","padding-x":"2em","bogus":"1px","radius":"round"}} --><div class="wp-block-contact-form-7-contact-form-selector">[contact-form-7 id="' . $jgor_flcf7_id_c . '" title="FL Test C Mixed"]</div><!-- /wp:contact-form-7/contact-form-selector -->';
$jgor_flcf7_rendered = render_block( parse_blocks( $jgor_flcf7_block )[0] );
jgor_flcf7_check( 'placement design: block attribute carried into the shortcode text at render time', str_contains( $jgor_flcf7_rendered, '[contact-form-7 jgor_flcf7_design="padding-x:2em;border-color:#00ff00" id="' . $jgor_flcf7_id_c . '" title="FL Test C Mixed"]' ), $jgor_flcf7_rendered );
$jgor_flcf7_pd = do_shortcode( $jgor_flcf7_rendered );
jgor_flcf7_check( 'placement design: block -> style on the wrapper', str_contains( $jgor_flcf7_pd, '<div class="wpcf7 no-js" style="--jgor-flcf7-padding-x: 2em; --jgor-flcf7-border-color: #00ff00;"' ) );
$jgor_flcf7_rendered = render_block( parse_blocks( str_replace( ',"jgorFlcf7Design":{"border-color":"#00FF00","padding-x":"2em","bogus":"1px","radius":"round"}', '', $jgor_flcf7_block ) )[0] );
jgor_flcf7_check( 'placement design: block without the attribute -> content untouched', ! str_contains( $jgor_flcf7_rendered, 'jgor_flcf7_design' ) && str_contains( $jgor_flcf7_rendered, '[contact-form-7 id="' . $jgor_flcf7_id_c . '" title="FL Test C Mixed"]' ) );
$jgor_flcf7_rendered = render_block( parse_blocks( str_replace( '[contact-form-7 id=', '[contact-form-7 jgor_flcf7_design="radius:9px" id=', $jgor_flcf7_block ) )[0] );
jgor_flcf7_check( 'placement design: shortcode attribute already present -> kept, block attribute not added', 1 === preg_match_all( '#jgor_flcf7_design=#', $jgor_flcf7_rendered ) && str_contains( $jgor_flcf7_rendered, 'jgor_flcf7_design="radius:9px"' ) );
$jgor_flcf7_rendered = render_block( parse_blocks( str_replace( '{"border-color":"#00FF00","padding-x":"2em","bogus":"1px","radius":"round"}', '{"radius":"round"}', $jgor_flcf7_block ) )[0] );
jgor_flcf7_check( 'placement design: block attribute with invalid values only -> content untouched', ! str_contains( $jgor_flcf7_rendered, 'jgor_flcf7_design' ) );

// Nothing without the stylesheet.
$jgor_flcf7_option             = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['load_css'] = false;
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
JGOR_FLCF7_Settings::instance()->reset_cache();
$jgor_flcf7_pd = do_shortcode( '[contact-form-7 id="' . $jgor_flcf7_id_c . '" jgor_flcf7_design="label-color:#abcdef"]' );
jgor_flcf7_check( 'placement design: nothing with load_css off', ! str_contains( $jgor_flcf7_pd, ' style="' ) );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();

// Safety net of load_css_forms_only: a rebuilt form enqueues the stylesheet.
$jgor_flcf7_option                        = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['load_css_forms_only'] = true;
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
JGOR_FLCF7_Settings::instance()->reset_cache();
wp_dequeue_style( JGOR_FLCF7_Assets::HANDLE );
wp_deregister_style( JGOR_FLCF7_Assets::HANDLE );
jgor_flcf7_render( $jgor_flcf7_id_d );
jgor_flcf7_check( 'safety net: nothing for a form that is not rebuilt (floating_labels: off)', ! wp_style_is( JGOR_FLCF7_Assets::HANDLE, 'enqueued' ) );
jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'safety net: stylesheet enqueued after a rebuild with load_css_forms_only on', wp_style_is( JGOR_FLCF7_Assets::HANDLE, 'enqueued' ) );
wp_dequeue_style( JGOR_FLCF7_Assets::HANDLE );
wp_deregister_style( JGOR_FLCF7_Assets::HANDLE );
update_option( 'jgor_flcf7_settings', JGOR_FLCF7_Settings::instance()->defaults() );
JGOR_FLCF7_Settings::instance()->reset_cache();
jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'safety net: nothing with load_css_forms_only off (stylesheet follows the Contact Form 7 policy)', ! wp_style_is( JGOR_FLCF7_Assets::HANDLE, 'enqueued' ) );

// Restore the option as it was before the run.
if ( false === $jgor_flcf7_saved_option ) {
	delete_option( 'jgor_flcf7_settings' );
	JGOR_FLCF7_Settings::instance()->ensure_option();
} else {
	update_option( 'jgor_flcf7_settings', $jgor_flcf7_saved_option );
}
JGOR_FLCF7_Settings::instance()->reset_cache();

if ( getenv( 'FLCF7_DUMP' ) ) {
	WP_CLI::log( "\n----- A -----\n" . $jgor_flcf7_a . "\n----- C -----\n" . $jgor_flcf7_c );
}

// Remove the test forms and the page unless they should stay for a look.
if ( ! getenv( 'FLCF7_KEEP' ) ) {
	WP_CLI::log( 'Removed ' . jgor_flcf7_remove_test_forms() . ' test posts (FLCF7_KEEP=1 keeps them).' );
}

if ( $GLOBALS['jgor_flcf7_failed'] > 0 ) {
	WP_CLI::error( $GLOBALS['jgor_flcf7_failed'] . ' check(s) failed.' );
}
WP_CLI::success( 'All checks passed.' );

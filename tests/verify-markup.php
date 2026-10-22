<?php
/**
 * Verifies module 2 (markup rebuild) against the test forms of
 * tests/create-test-forms.php. Prints one PASS/FAIL line per check and
 * exits with an error when a check fails.
 *
 * Usage, on a local test site only (run create-test-forms.php first):
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-markup.php
 *
 * Besides the rebuild itself the script covers the control layers of the
 * concept (section 7.4): additional settings per form (floating_labels_*),
 * html_class classes per placement (block field "Class" or shortcode
 * attribute) and the filters jgor_flcf7_form_settings, jgor_flcf7_enabled
 * and jgor_flcf7_label_text.
 *
 * Set FLCF7_DUMP=1 in the environment to print the rendered HTML of forms A
 * and C after the checks.
 *
 * The script changes the plugin option temporarily (type list) and restores
 * the defaults afterwards; the forms themselves are not modified.
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
			(bool) preg_match( '#<(input|textarea)[^>]*\sid="' . preg_quote( $for, '#' ) . '"[^>]*>(?:[^<]*</textarea>)?<label for="' . preg_quote( $for, '#' ) . '" class="jgor-flcf7-label"#s', $html )
		);
	}
}

$jgor_flcf7_id_a = jgor_flcf7_form_id( 'Contact form 1' );
$jgor_flcf7_id_b = jgor_flcf7_form_id( 'FL Test B Placeholder' );
$jgor_flcf7_id_c = jgor_flcf7_form_id( 'FL Test C Mixed' );
$jgor_flcf7_id_d = jgor_flcf7_form_id( 'FL Test D Off' );

// (a) Standard template, label mode.
$jgor_flcf7_a = jgor_flcf7_render( $jgor_flcf7_id_a );
jgor_flcf7_check( 'A: 4 floating labels', 4 === preg_match_all( '#<label[^>]*class="jgor-flcf7-label"#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: 4 placeholders set to one space', 4 === preg_match_all( '#placeholder=" "#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: enclosing labels replaced by span.jgor-flcf7-field', 4 === preg_match_all( '#<span class="jgor-flcf7-field">#', $jgor_flcf7_a ) && 4 === preg_match_all( '#<label#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: no <br> left inside the field spans', ! preg_match( '#<span class="jgor-flcf7-field">\s*<br#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: label text taken from template', str_contains( $jgor_flcf7_a, '>Your name</label>' ) && str_contains( $jgor_flcf7_a, '>Your message (optional)</label>' ) );
jgor_flcf7_check( 'A: variant class on wrap', 4 === preg_match_all( '#class="wpcf7-form-control-wrap jgor-flcf7-wrap jgor-flcf7-outlined#', $jgor_flcf7_a ) );
jgor_flcf7_check( 'A: textarea wrap marked', 1 === preg_match_all( '#jgor-flcf7-outlined jgor-flcf7-textarea"#', $jgor_flcf7_a ) );
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
	jgor_flcf7_check(
		'C: ' . $jgor_flcf7_name . ' identical to original (modulo self-closing slash)',
		'' !== jgor_flcf7_wrap( $jgor_flcf7_c, $jgor_flcf7_name ) && jgor_flcf7_norm( jgor_flcf7_wrap( $jgor_flcf7_c0, $jgor_flcf7_name ) ) === jgor_flcf7_wrap( $jgor_flcf7_c, $jgor_flcf7_name )
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

// Per form: additional settings (concept 7.4).
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
	return $settings;
};
add_filter( 'jgor_flcf7_form_settings', $jgor_flcf7_f5 );
$jgor_flcf7_p = jgor_flcf7_render( $jgor_flcf7_id_b );
jgor_flcf7_check( 'filter jgor_flcf7_form_settings with invalid values -> ignored', 3 === preg_match_all( '#jgor-flcf7-wrap jgor-flcf7-outlined#', $jgor_flcf7_p ) && ! str_contains( $jgor_flcf7_p, 'jgor-flcf7-marker"' ) );
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
delete_option( 'jgor_flcf7_settings' );
JGOR_FLCF7_Settings::instance()->ensure_option();
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
delete_option( 'jgor_flcf7_settings' );
JGOR_FLCF7_Settings::instance()->ensure_option();
JGOR_FLCF7_Settings::instance()->reset_cache();

// UTF-8 and entities (in memory only).
$jgor_flcf7_u_form = WPCF7_ContactForm::get_instance( $jgor_flcf7_id_b );
$jgor_flcf7_u_form->set_properties( array( 'form' => '[text* name placeholder "Straße & Ort – Prüfung"]' ) );
jgor_flcf7_check( 'UTF-8 and entities in label text', str_contains( $jgor_flcf7_u_form->form_html(), '>Straße &amp; Ort – Prüfung</label>' ) );

if ( getenv( 'FLCF7_DUMP' ) ) {
	WP_CLI::log( "\n----- A -----\n" . $jgor_flcf7_a . "\n----- C -----\n" . $jgor_flcf7_c );
}

if ( $GLOBALS['jgor_flcf7_failed'] > 0 ) {
	WP_CLI::error( $GLOBALS['jgor_flcf7_failed'] . ' check(s) failed.' );
}
WP_CLI::success( 'All checks passed.' );

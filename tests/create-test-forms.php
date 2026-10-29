<?php
/**
 * Creates the test forms of the concept (section 12) on a local test site
 * and puts them on a page "Contact".
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/create-test-forms.php
 *
 * Idempotent: forms are looked up by title and updated, the page by slug.
 * Form A is the form Contact Form 7 creates on activation ("Contact form 1");
 * when it is missing it is created from the Contact Form 7 template.
 *
 * Forms:
 *   A  Contact Form 7 default template      enclosing labels (label mode)
 *   B  FL Test B Placeholder                placeholder options only
 *   C  FL Test C Mixed                      linked label, nofloat, enclosing
 *                                           label with <strong>, tel, number,
 *                                           select, checkbox, file
 *   D  FL Test D Off                        B with "floating_labels: off"
 *   E  B once more                          same form twice on one page
 *   F  FL Test F Old template               no labels, no placeholders: the
 *                                           template of Contact Form 7 before
 *                                           5.4 (<p>Text<br />[tag]</p>), two
 *                                           fields without <p>, text and tag
 *                                           on one line, a paragraph of
 *                                           explanation, a <label> without
 *                                           for (text mode)
 *
 * Contact Form 7 syntax: options without quotes (nofloat, id:, min:) must
 * precede the quoted values, otherwise the tag is printed as plain text.
 *
 * @package Floating_Labels_For_CF7
 */

( defined( 'WP_CLI' ) && WP_CLI ) || exit;

/**
 * Creates or updates a contact form by title.
 *
 * @param string $title    Form title.
 * @param string $form     Form template.
 * @param string $settings Additional settings.
 * @return int Post ID of the form.
 */
function jgor_flcf7_test_form( string $title, string $form, string $settings = '' ): int {
	$existing = get_posts(
		array(
			'post_type'   => 'wpcf7_contact_form',
			'title'       => $title,
			'numberposts' => 1,
			'post_status' => 'any',
		)
	);

	$contact_form = $existing
		? WPCF7_ContactForm::get_instance( $existing[0]->ID )
		: WPCF7_ContactForm::get_template(
			array(
				'title'  => $title,
				'locale' => 'en_US',
			)
		);

	$contact_form->set_properties(
		array(
			'form'                => $form,
			'additional_settings' => $settings,
		)
	);
	$contact_form->save();

	return (int) $contact_form->id();
}

/**
 * Returns the block markup for one heading plus one form shortcode.
 *
 * @param string $heading Heading text.
 * @param int    $id      Form ID.
 * @return string
 */
function jgor_flcf7_test_block( string $heading, int $id ): string {
	return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . "</h2><!-- /wp:heading -->\n"
		. '<!-- wp:shortcode -->[contact-form-7 id="' . $id . '"]' . "<!-- /wp:shortcode -->\n";
}

$jgor_flcf7_form_b = "[text* your-name placeholder \"Your name\"]\n\n"
	. "[email* your-email placeholder \"Your email\"]\n\n"
	. "[textarea your-message placeholder \"Your message\"]\n\n"
	. '[submit "Send"]';

$jgor_flcf7_form_c = "<label for=\"fl-subject\">Subject</label>\n[text* your-subject id:fl-subject]\n\n"
	. "[text your-nick nofloat placeholder \"Nickname\"]\n\n"
	. "<label> Your <strong>website</strong>\n    [url your-site] </label>\n\n"
	. "[tel your-phone placeholder \"Phone\"]\n\n"
	. "[number your-age min:0 max:120 placeholder \"Age\"]\n\n"
	. "[select your-choice \"One\" \"Two\"]\n\n"
	. "[checkbox your-cb \"A\" \"B\"]\n\n"
	. "[file your-file]\n\n"
	. '[submit "Send"]';

$jgor_flcf7_form_f = "<p>Your Name (required)<br />\n    [text* your-name] </p>\n\n"
	. "<p>Your Email (required)<br />\n    [email* your-email] </p>\n\n"
	. "Phone<br />\n[tel your-phone]<br />\nCompany<br />\n[text your-company]\n\n"
	. "<p>Please describe your request in a few sentences.</p>\n\n"
	. "<p>Your Message<br />\n    [textarea your-message] </p>\n\n"
	. "<p>Website [url your-site]</p>\n\n"
	. "<label>Nickname</label>\n[text your-nick]\n\n"
	. '<p>[submit "Send"]</p>';

// Form A: the default form of Contact Form 7, created from its template if missing.
$jgor_flcf7_default = get_posts(
	array(
		'post_type'   => 'wpcf7_contact_form',
		'title'       => 'Contact form 1',
		'numberposts' => 1,
		'post_status' => 'any',
	)
);
if ( $jgor_flcf7_default ) {
	$jgor_flcf7_a = (int) $jgor_flcf7_default[0]->ID;
} else {
	$jgor_flcf7_template = WPCF7_ContactForm::get_template(
		array(
			'title'  => 'Contact form 1',
			'locale' => 'en_US',
		)
	);
	$jgor_flcf7_template->save();
	$jgor_flcf7_a = (int) $jgor_flcf7_template->id();
}

$jgor_flcf7_b = jgor_flcf7_test_form( 'FL Test B Placeholder', $jgor_flcf7_form_b );
$jgor_flcf7_c = jgor_flcf7_test_form( 'FL Test C Mixed', $jgor_flcf7_form_c );
$jgor_flcf7_d = jgor_flcf7_test_form( 'FL Test D Off', $jgor_flcf7_form_b, 'floating_labels: off' );
$jgor_flcf7_f = jgor_flcf7_test_form( 'FL Test F Old template', $jgor_flcf7_form_f );

$jgor_flcf7_content = jgor_flcf7_test_block( 'A: Standard template (label mode)', $jgor_flcf7_a )
	. jgor_flcf7_test_block( 'B: Placeholder template', $jgor_flcf7_b )
	. jgor_flcf7_test_block( 'C: Mixed (linked label, nofloat, enclosing label, select, checkbox, file)', $jgor_flcf7_c )
	. jgor_flcf7_test_block( 'D: floating_labels: off', $jgor_flcf7_d )
	. jgor_flcf7_test_block( 'E: B again (same form twice)', $jgor_flcf7_b )
	. jgor_flcf7_test_block( 'F: Old template (text in front of the fields, text mode)', $jgor_flcf7_f );

$jgor_flcf7_page = get_page_by_path( 'contact', OBJECT, 'page' );
if ( $jgor_flcf7_page instanceof WP_Post ) {
	$jgor_flcf7_page_id = (int) $jgor_flcf7_page->ID;
	wp_update_post(
		array(
			'ID'           => $jgor_flcf7_page_id,
			'post_content' => $jgor_flcf7_content,
		)
	);
} else {
	$jgor_flcf7_page_id = (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Contact',
			'post_name'    => 'contact',
			'post_content' => $jgor_flcf7_content,
		)
	);
}

WP_CLI::log(
	wp_json_encode(
		array(
			'a'    => $jgor_flcf7_a,
			'b'    => $jgor_flcf7_b,
			'c'    => $jgor_flcf7_c,
			'd'    => $jgor_flcf7_d,
			'f'    => $jgor_flcf7_f,
			'page' => get_permalink( $jgor_flcf7_page_id ),
		)
	)
);
WP_CLI::success( 'Test forms and page "Contact" are in place.' );

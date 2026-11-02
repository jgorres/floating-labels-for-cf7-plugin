<?php
/**
 * Verifies module 6 (sample page and forms) without a browser. Prints one
 * PASS/FAIL line per check and exits with an error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-samples.php
 *
 * Creates the sample page and forms, checks page, forms, block content and
 * the rendered output, creates again (no duplicates), trashes the page by
 * hand and creates again (leftovers removed), removes everything and checks
 * that nothing is left. Leftovers of an earlier run are removed first. A
 * sample page that existed before the run is created again at the end (new
 * ids), so the site keeps it. The settings option is saved and restored
 * around the run.
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
 * Returns the ids of every contact form whose title carries the sample prefix and suffix.
 *
 * @return int[]
 */
function jgor_flcf7_sample_forms(): array {
	$posts = get_posts(
		array(
			'post_type'   => JGOR_FLCF7_Samples::FORM_POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	$ids = array();
	foreach ( $posts as $id ) {
		$post  = get_post( (int) $id );
		$title = $post instanceof WP_Post ? $post->post_title : '';
		if ( str_starts_with( $title, JGOR_FLCF7_Samples::TITLE_PREFIX ) && str_ends_with( $title, JGOR_FLCF7_Samples::TITLE_SUFFIX ) ) {
			$ids[] = (int) $id;
		}
	}

	return $ids;
}

/**
 * Returns the rendered <form> elements of one contact form, in page order.
 *
 * @param string $html Rendered page.
 * @param int    $id   Contact form id.
 * @return string[]
 */
function jgor_flcf7_forms_of( string $html, int $id ): array {
	preg_match_all( '#<form[^>]*>.*?</form>#s', $html, $m );

	return array_values(
		array_filter(
			$m[0],
			static function ( string $form ) use ( $id ): bool {
				return str_contains( $form, 'wpcf7-f' . $id . '-' );
			}
		)
	);
}

/**
 * Returns a contact form by id.
 *
 * @param int $id Contact form id.
 * @return WPCF7_ContactForm
 */
function jgor_flcf7_cf( int $id ): WPCF7_ContactForm {
	$form = WPCF7_ContactForm::get_instance( $id );
	if ( ! $form instanceof WPCF7_ContactForm ) {
		WP_CLI::error( 'Contact form ' . $id . ' not found.' );
	}

	return $form;
}

$jgor_flcf7_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
if ( ! $jgor_flcf7_admins ) {
	WP_CLI::error( 'No administrator found on this site.' );
}
wp_set_current_user( (int) $jgor_flcf7_admins[0]->ID );

$jgor_flcf7_settings     = JGOR_FLCF7_Settings::instance();
$jgor_flcf7_saved_option = get_option( 'jgor_flcf7_settings', false );
update_option( 'jgor_flcf7_settings', $jgor_flcf7_settings->defaults() );
$jgor_flcf7_settings->reset_cache();

$jgor_flcf7_samples = new JGOR_FLCF7_Samples();

// A sample page the user created stays available: it is removed for the
// run and created again at the end (with new ids).
$jgor_flcf7_had_samples = $jgor_flcf7_samples->is_created();

// Leftovers of an earlier run.
if ( false !== get_option( JGOR_FLCF7_Samples::OPTION, false ) ) {
	$jgor_flcf7_samples->remove();
}
foreach ( jgor_flcf7_sample_forms() as $jgor_flcf7_id ) {
	wp_delete_post( $jgor_flcf7_id, true );
}
$jgor_flcf7_old_page = get_page_by_path( JGOR_FLCF7_Samples::PAGE_SLUG, OBJECT, 'page' );
if ( $jgor_flcf7_old_page instanceof WP_Post ) {
	wp_delete_post( $jgor_flcf7_old_page->ID, true );
}
jgor_flcf7_check( 'precondition: no sample forms, no page, no option', empty( jgor_flcf7_sample_forms() ) && null === get_page_by_path( JGOR_FLCF7_Samples::PAGE_SLUG, OBJECT, 'page' ) && false === get_option( JGOR_FLCF7_Samples::OPTION, false ) && ! $jgor_flcf7_samples->is_created() );

// Box and notice before anything exists.
ob_start();
$jgor_flcf7_samples->render_box();
$jgor_flcf7_box = (string) ob_get_clean();
jgor_flcf7_check( 'box before creating: create button, hidden action and do=create, nonce, admin-post target', str_contains( $jgor_flcf7_box, '<div class="card jgor-flcf7-samples">' ) && str_contains( $jgor_flcf7_box, 'action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' ) && str_contains( $jgor_flcf7_box, 'name="action" value="jgor_flcf7_samples"' ) && str_contains( $jgor_flcf7_box, 'name="do" value="create"' ) && (bool) preg_match( '#name="_wpnonce" value="[0-9a-f]+"#', $jgor_flcf7_box ) && str_contains( $jgor_flcf7_box, 'Create sample page and forms' ) && ! str_contains( $jgor_flcf7_box, 'Remove sample page' ) );
set_transient( JGOR_FLCF7_Samples::TRANSIENT, 1, 60 );
ob_start();
$jgor_flcf7_samples->render_notice();
$jgor_flcf7_notice = (string) ob_get_clean();
jgor_flcf7_check( 'notice after activation: info notice with create and dismiss links carrying the nonce', str_contains( $jgor_flcf7_notice, 'notice-info' ) && str_contains( $jgor_flcf7_notice, 'jgor-flcf7-notice' ) && (bool) preg_match( '#admin-post\.php\?action=jgor_flcf7_samples&(?:amp;)?do=create&(?:amp;)?_wpnonce=[0-9a-f]+#', $jgor_flcf7_notice ) && (bool) preg_match( '#do=dismiss&(?:amp;)?_wpnonce=[0-9a-f]+#', $jgor_flcf7_notice ) && str_contains( $jgor_flcf7_notice, 'Not now' ) );
wp_set_current_user( 0 );
ob_start();
$jgor_flcf7_samples->render_notice();
$jgor_flcf7_notice_anon = (string) ob_get_clean();
wp_set_current_user( (int) $jgor_flcf7_admins[0]->ID );
jgor_flcf7_check( 'notice: nothing without manage_options', '' === $jgor_flcf7_notice_anon );
jgor_flcf7_check( 'perform(): dismiss drops the transient, unknown action reported', 'dismissed' === $jgor_flcf7_samples->perform( 'dismiss' ) && false === get_transient( JGOR_FLCF7_Samples::TRANSIENT ) && 'unknown' === $jgor_flcf7_samples->perform( 'bogus' ) );

// Handler: nonce and capability stop the request through wp_die().
$jgor_flcf7_die = static function () {
	return static function ( $message ) {
		throw new RuntimeException( esc_html( wp_strip_all_tags( is_string( $message ) ? $message : 'wp_die' ) ) );
	};
};
add_filter( 'wp_die_handler', $jgor_flcf7_die );
$jgor_flcf7_died = '';
try {
	$jgor_flcf7_samples->handle();
} catch ( RuntimeException $jgor_flcf7_e ) {
	$jgor_flcf7_died = $jgor_flcf7_e->getMessage();
}
jgor_flcf7_check( 'handle(): dies without a nonce', str_contains( $jgor_flcf7_died, 'expired' ), $jgor_flcf7_died );
wp_set_current_user( 0 );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the test supplies the nonce the handler checks.
$_REQUEST['_wpnonce'] = wp_create_nonce( JGOR_FLCF7_Samples::ACTION );
$jgor_flcf7_died      = '';
try {
	$jgor_flcf7_samples->handle();
} catch ( RuntimeException $jgor_flcf7_e ) {
	$jgor_flcf7_died = $jgor_flcf7_e->getMessage();
}
unset( $_REQUEST['_wpnonce'] );
wp_set_current_user( (int) $jgor_flcf7_admins[0]->ID );
jgor_flcf7_check( 'handle(): dies without manage_options even with a valid nonce', str_contains( $jgor_flcf7_died, 'not allowed' ), $jgor_flcf7_died );
remove_filter( 'wp_die_handler', $jgor_flcf7_die );

// Create through perform(): capabilities of the administrator, transient dropped.
set_transient( JGOR_FLCF7_Samples::TRANSIENT, 1, 60 );
jgor_flcf7_check( 'perform( create ): created, transient dropped', 'created' === $jgor_flcf7_samples->perform( 'create' ) && false === get_transient( JGOR_FLCF7_Samples::TRANSIENT ) );
$jgor_flcf7_stored = $jgor_flcf7_samples->stored();
$jgor_flcf7_page   = get_post( $jgor_flcf7_stored['page'] );
jgor_flcf7_check( 'page: private, slug, title, comments closed', $jgor_flcf7_page instanceof WP_Post && 'page' === $jgor_flcf7_page->post_type && 'private' === $jgor_flcf7_page->post_status && JGOR_FLCF7_Samples::PAGE_SLUG === $jgor_flcf7_page->post_name && 'Testpage Floating Labels for CF7' === $jgor_flcf7_page->post_title && 'closed' === $jgor_flcf7_page->comment_status );
jgor_flcf7_check( 'page: is_created() and page_id()', $jgor_flcf7_samples->is_created() && $jgor_flcf7_samples->page_id() === $jgor_flcf7_stored['page'] );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the autoload flag, which no API exposes.
$jgor_flcf7_autoload = (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", JGOR_FLCF7_Samples::OPTION ) );
jgor_flcf7_check( 'option: stored without autoload', in_array( $jgor_flcf7_autoload, array( 'off', 'no' ), true ), $jgor_flcf7_autoload );

$jgor_flcf7_forms  = jgor_flcf7_sample_forms();
$jgor_flcf7_sorted = $jgor_flcf7_stored['forms'];
sort( $jgor_flcf7_forms );
sort( $jgor_flcf7_sorted );
$jgor_flcf7_published = true;
foreach ( $jgor_flcf7_stored['forms'] as $jgor_flcf7_id ) {
	$jgor_flcf7_published = $jgor_flcf7_published && 'publish' === get_post_status( $jgor_flcf7_id );
}
jgor_flcf7_check( 'forms: eight published contact forms with prefix and suffix, ids stored', 8 === count( $jgor_flcf7_forms ) && $jgor_flcf7_forms === $jgor_flcf7_sorted && $jgor_flcf7_published );
$jgor_flcf7_titles = array_map(
	static function ( int $id ): string {
		$post = get_post( $id );

		return $post instanceof WP_Post ? $post->post_title : '';
	},
	$jgor_flcf7_stored['forms']
);
jgor_flcf7_check(
	'forms: titles in the order of the page',
	array( 'FL Test Default template - may be deleted', 'FL Test Placeholders - may be deleted', 'FL Test Old template - may be deleted', 'FL Test Filled and glow - may be deleted', 'FL Test Minimal and running light - may be deleted', 'FL Test Mixed - may be deleted', 'FL Test Date and select - may be deleted', 'FL Test Off - may be deleted' ) === $jgor_flcf7_titles,
	implode( ' | ', $jgor_flcf7_titles )
);
list( $jgor_flcf7_f_default, $jgor_flcf7_f_placeholders, $jgor_flcf7_f_old, $jgor_flcf7_f_filled, $jgor_flcf7_f_minimal, $jgor_flcf7_f_mixed, $jgor_flcf7_f_dateselect, $jgor_flcf7_f_off ) = $jgor_flcf7_stored['forms'];
$jgor_flcf7_skip = true;
foreach ( $jgor_flcf7_stored['forms'] as $jgor_flcf7_id ) {
	$jgor_flcf7_skip = $jgor_flcf7_skip && jgor_flcf7_cf( $jgor_flcf7_id )->is_true( 'skip_mail' );
}
jgor_flcf7_check( 'forms: every form has skip_mail: on', $jgor_flcf7_skip );
jgor_flcf7_check(
	'forms: additional settings per form',
	'text' === jgor_flcf7_cf( $jgor_flcf7_f_old )->pref( 'jgor_floating_labels' )
	&& 'filled' === jgor_flcf7_cf( $jgor_flcf7_f_filled )->pref( 'jgor_floating_labels_style' ) && 'glow' === jgor_flcf7_cf( $jgor_flcf7_f_filled )->pref( 'jgor_floating_labels_effect' )
	&& 'minimal' === jgor_flcf7_cf( $jgor_flcf7_f_minimal )->pref( 'jgor_floating_labels_style' ) && 'sweep' === jgor_flcf7_cf( $jgor_flcf7_f_minimal )->pref( 'jgor_floating_labels_effect' ) && 'off' === jgor_flcf7_cf( $jgor_flcf7_f_minimal )->pref( 'jgor_floating_labels_marker' )
	&& 'pulse' === jgor_flcf7_cf( $jgor_flcf7_f_mixed )->pref( 'jgor_floating_labels_effect' )
	&& 'off' === jgor_flcf7_cf( $jgor_flcf7_f_off )->pref( 'jgor_floating_labels' )
	&& null === jgor_flcf7_cf( $jgor_flcf7_f_default )->pref( 'jgor_floating_labels' )
);
jgor_flcf7_check(
	'forms: Contact Form 7 template kept for "Default template" and "Filled and glow", own templates for the others',
	str_contains( (string) jgor_flcf7_cf( $jgor_flcf7_f_default )->prop( 'form' ), '[text* your-name autocomplete:name]' )
	&& str_contains( (string) jgor_flcf7_cf( $jgor_flcf7_f_filled )->prop( 'form' ), '[text* your-name autocomplete:name]' )
	&& str_contains( (string) jgor_flcf7_cf( $jgor_flcf7_f_old )->prop( 'form' ), 'Your Name (required)<br />' )
	&& str_contains( (string) jgor_flcf7_cf( $jgor_flcf7_f_mixed )->prop( 'form' ), 'jgor_nofloat' )
	&& str_contains( (string) jgor_flcf7_cf( $jgor_flcf7_f_placeholders )->prop( 'form' ), 'placeholder "Your name"' )
);

// Box and notice while the page exists.
ob_start();
$jgor_flcf7_samples->render_box();
$jgor_flcf7_box = (string) ob_get_clean();
jgor_flcf7_check( 'box after creating: remove button, view and edit links of the page', str_contains( $jgor_flcf7_box, 'name="do" value="remove"' ) && str_contains( $jgor_flcf7_box, 'Remove sample page and forms' ) && str_contains( $jgor_flcf7_box, esc_url( (string) get_permalink( $jgor_flcf7_stored['page'] ) ) ) && str_contains( $jgor_flcf7_box, 'post=' . $jgor_flcf7_stored['page'] . '&#038;action=edit' ) && ! str_contains( $jgor_flcf7_box, 'value="create"' ) );
set_transient( JGOR_FLCF7_Samples::TRANSIENT, 1, 60 );
ob_start();
$jgor_flcf7_samples->render_notice();
$jgor_flcf7_notice = (string) ob_get_clean();
delete_transient( JGOR_FLCF7_Samples::TRANSIENT );
jgor_flcf7_check( 'notice: nothing while the sample page exists', '' === $jgor_flcf7_notice );

// Block content.
$jgor_flcf7_content = (string) $jgor_flcf7_page->post_content;
jgor_flcf7_check( 'content: old template section shows both templates as code blocks', 2 === substr_count( $jgor_flcf7_content, '<!-- wp:code -->' ) && str_contains( $jgor_flcf7_content, '<pre class="wp-block-code"><code>&lt;p&gt;Your Name (required)&lt;br /&gt;' ) && str_contains( $jgor_flcf7_content, '<code>&lt;label&gt; Your name' ) && str_contains( $jgor_flcf7_content, 'autocomplete:name] &lt;/label&gt;</code></pre>' ) );
jgor_flcf7_check( 'content: intro, ten headings, nine Contact Form 7 blocks and one Shortcode block', 10 === substr_count( $jgor_flcf7_content, '<!-- wp:heading -->' ) && 9 === substr_count( $jgor_flcf7_content, '<!-- wp:contact-form-7/contact-form-selector ' ) && 1 === substr_count( $jgor_flcf7_content, '<!-- wp:shortcode -->' ) && str_contains( $jgor_flcf7_content, 'created by Floating Labels for CF7' ) );
$jgor_flcf7_blocks  = array_values(
	array_filter(
		parse_blocks( $jgor_flcf7_content ),
		static function ( array $block ): bool {
			return 'contact-form-7/contact-form-selector' === $block['blockName'];
		}
	)
);
$jgor_flcf7_hash    = jgor_flcf7_cf( $jgor_flcf7_f_default )->hash();
$jgor_flcf7_attrs0  = $jgor_flcf7_blocks[0]['attrs'] ?? array();
$jgor_flcf7_attrs7  = $jgor_flcf7_blocks[8]['attrs'] ?? array();
$jgor_flcf7_id0     = $jgor_flcf7_attrs0['id'] ?? 0;
$jgor_flcf7_hash0   = $jgor_flcf7_attrs0['hash'] ?? '';
$jgor_flcf7_design7 = $jgor_flcf7_attrs7['jgorFlcf7Design'] ?? null;
jgor_flcf7_check( 'content: blocks carry id, hash and title; the shortcode uses the hash as Contact Form 7 saves it', 9 === count( $jgor_flcf7_blocks ) && $jgor_flcf7_id0 === $jgor_flcf7_f_default && $jgor_flcf7_hash0 === $jgor_flcf7_hash && 'FL Test Default template - may be deleted' === ( $jgor_flcf7_blocks[0]['attrs']['title'] ?? '' ) && str_contains( $jgor_flcf7_blocks[0]['innerHTML'], '<div class="wp-block-contact-form-7-contact-form-selector">[contact-form-7 id="' . $jgor_flcf7_hash . '" title="FL Test Default template - may be deleted"]</div>' ) );
$jgor_flcf7_design_expected = array(
	'border-color' => '#0a7d3e',
	'font-size'    => '1.1em',
);
jgor_flcf7_check( 'content: placement block with htmlClass and jgorFlcf7Design, html_class in its shortcode', 'jgor-flcf7-style-filled jgor-flcf7-effect-glow' === ( $jgor_flcf7_blocks[8]['attrs']['htmlClass'] ?? '' ) && $jgor_flcf7_design7 === $jgor_flcf7_design_expected && str_contains( $jgor_flcf7_blocks[8]['innerHTML'], ' html_class="jgor-flcf7-style-filled jgor-flcf7-effect-glow"]' ) );
jgor_flcf7_check( 'content: Shortcode block with html_class and jgor_flcf7_design', str_contains( $jgor_flcf7_content, "<!-- wp:shortcode -->\n[contact-form-7 id=\"" . $jgor_flcf7_hash . '" title="FL Test Default template - may be deleted" html_class="jgor-flcf7-style-minimal" jgor_flcf7_design="border-color:#0a7d3e;font-size:1.1em"]' ) );

// Rendered output: blocks, shortcodes, module 2 and the design per placement.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the core content filter, applied on purpose.
$jgor_flcf7_html = (string) apply_filters( 'the_content', $jgor_flcf7_content );
jgor_flcf7_check( 'rendered: ten forms', 10 === preg_match_all( '#<form[^>]*class="wpcf7-form#', $jgor_flcf7_html ) );
$jgor_flcf7_old        = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_old );
$jgor_flcf7_off        = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_off );
$jgor_flcf7_filled     = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_filled );
$jgor_flcf7_minimal    = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_minimal );
$jgor_flcf7_mixed      = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_mixed );
$jgor_flcf7_default    = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_default );
$jgor_flcf7_dateselect = jgor_flcf7_forms_of( $jgor_flcf7_html, $jgor_flcf7_f_dateselect );
jgor_flcf7_check( 'rendered: date and select form: five labels, date and select wraps, two blank options emptied, first option without blank kept, label from first_as_label', 1 === count( $jgor_flcf7_dateselect ) && 5 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_dateselect[0] ) && 1 === preg_match_all( '#jgor-flcf7-date#', $jgor_flcf7_dateselect[0] ) && 3 === preg_match_all( '#jgor-flcf7-select#', $jgor_flcf7_dateselect[0] ) && 2 === preg_match_all( '#<option value="">\s*</option>#', $jgor_flcf7_dateselect[0] ) && str_contains( $jgor_flcf7_dateselect[0], '<option value="Germany">Germany</option>' ) && str_contains( $jgor_flcf7_dateselect[0], '>Reason for contact</label>' ) );
jgor_flcf7_check( 'rendered: old template gets four floating labels through jgor_floating_labels: text', 1 === count( $jgor_flcf7_old ) && 4 === preg_match_all( '#class="jgor-flcf7-label"#', $jgor_flcf7_old[0] ) );
jgor_flcf7_check( 'rendered: switched-off form untouched', 1 === count( $jgor_flcf7_off ) && ! str_contains( $jgor_flcf7_off[0], 'jgor-flcf7' ) );
jgor_flcf7_check( 'rendered: filled variant with glow', 1 === count( $jgor_flcf7_filled ) && str_contains( $jgor_flcf7_filled[0], 'jgor-flcf7-filled' ) && str_contains( $jgor_flcf7_filled[0], 'jgor-flcf7-glow' ) );
jgor_flcf7_check( 'rendered: minimal variant with running light and without marker', 1 === count( $jgor_flcf7_minimal ) && str_contains( $jgor_flcf7_minimal[0], 'jgor-flcf7-minimal' ) && str_contains( $jgor_flcf7_minimal[0], 'jgor-flcf7-sweep' ) && ! str_contains( $jgor_flcf7_minimal[0], 'jgor-flcf7-marker"' ) );
jgor_flcf7_check( 'rendered: mixed form with pulse, jgor_nofloat field and inline label', 1 === count( $jgor_flcf7_mixed ) && str_contains( $jgor_flcf7_mixed[0], 'jgor-flcf7-pulse' ) && str_contains( $jgor_flcf7_mixed[0], 'placeholder="Nickname (jgor_nofloat)"' ) && str_contains( $jgor_flcf7_mixed[0], '>Your website</label>' ) );
jgor_flcf7_check( 'rendered: default form three times: plain, block placement (filled, glow), shortcode placement (minimal)', 3 === count( $jgor_flcf7_default ) && str_contains( $jgor_flcf7_default[0], 'jgor-flcf7-outlined' ) && ! str_contains( $jgor_flcf7_default[0], 'jgor-flcf7-glow' ) && str_contains( $jgor_flcf7_default[1], 'jgor-flcf7-filled' ) && str_contains( $jgor_flcf7_default[1], 'jgor-flcf7-glow' ) && str_contains( $jgor_flcf7_default[2], 'jgor-flcf7-minimal' ) );
jgor_flcf7_check( 'rendered: design values of both placements as a style on their wrappers', 2 === substr_count( $jgor_flcf7_html, 'style="--jgor-flcf7-font-size: 1.1em; --jgor-flcf7-border-color: #0a7d3e;"' ) );

// Idempotent.
jgor_flcf7_check( 'create() again: exists, still eight forms', 'exists' === $jgor_flcf7_samples->create() && 8 === count( jgor_flcf7_sample_forms() ) );

// Page deleted by hand (trashed): leftovers are removed on the next create().
wp_trash_post( $jgor_flcf7_stored['page'] );
jgor_flcf7_check( 'trashed page: no longer created, option still there', ! $jgor_flcf7_samples->is_created() && false !== get_option( JGOR_FLCF7_Samples::OPTION, false ) );
jgor_flcf7_check( 'create() after trashing: created again, old page and forms removed', 'created' === $jgor_flcf7_samples->create() && null === get_post( $jgor_flcf7_stored['page'] ) && 8 === count( jgor_flcf7_sample_forms() ) && ! in_array( $jgor_flcf7_f_default, jgor_flcf7_sample_forms(), true ) );
$jgor_flcf7_stored2 = $jgor_flcf7_samples->stored();
jgor_flcf7_check( 'new page and eight new forms stored', $jgor_flcf7_stored2['page'] !== $jgor_flcf7_stored['page'] && $jgor_flcf7_samples->is_created() && 8 === count( $jgor_flcf7_stored2['forms'] ) );

// Remove.
jgor_flcf7_check( 'perform( remove ): removed, nothing left', 'removed' === $jgor_flcf7_samples->perform( 'remove' ) && null === get_post( $jgor_flcf7_stored2['page'] ) && empty( jgor_flcf7_sample_forms() ) && false === get_option( JGOR_FLCF7_Samples::OPTION, false ) && ! $jgor_flcf7_samples->is_created() );
jgor_flcf7_check( 'remove() again: absent', 'absent' === $jgor_flcf7_samples->remove() );

// Safety: a post of another type stored in the option is never deleted.
$jgor_flcf7_foreign = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'FL foreign post',
	)
);
update_option(
	JGOR_FLCF7_Samples::OPTION,
	array(
		'page'  => 0,
		'forms' => array( $jgor_flcf7_foreign ),
	),
	false
);
$jgor_flcf7_samples->remove();
jgor_flcf7_check( 'remove(): a post of another type in the option is left alone, option dropped', get_post( $jgor_flcf7_foreign ) instanceof WP_Post && false === get_option( JGOR_FLCF7_Samples::OPTION, false ) );
wp_delete_post( (int) $jgor_flcf7_foreign, true );

// Safety: a page or a contact form that is not a sample post (reused id, edited option) is never deleted.
$jgor_flcf7_foreign_page    = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'FL foreign page',
		'post_name'   => 'fl-foreign-page',
	)
);
$jgor_flcf7_foreign_form    = WPCF7_ContactForm::get_template( array( 'title' => 'FL foreign form' ) );
$jgor_flcf7_foreign_form_id = (int) $jgor_flcf7_foreign_form->save();
update_option(
	JGOR_FLCF7_Samples::OPTION,
	array(
		'page'  => $jgor_flcf7_foreign_page,
		'forms' => array( $jgor_flcf7_foreign_form_id ),
	),
	false
);
$jgor_flcf7_samples->remove();
jgor_flcf7_check( 'remove(): a page and a form that are not sample posts are left alone', get_post( $jgor_flcf7_foreign_page ) instanceof WP_Post && get_post( $jgor_flcf7_foreign_form_id ) instanceof WP_Post && false === get_option( JGOR_FLCF7_Samples::OPTION, false ) );
jgor_flcf7_check(
	'is_sample_post(): slug of the page (also with a numeric suffix) and title prefix/suffix of a form, nothing else',
	JGOR_FLCF7_Samples::is_sample_post(
		new WP_Post(
			(object) array(
				'post_type'  => 'page',
				'post_name'  => JGOR_FLCF7_Samples::PAGE_SLUG . '-2',
				'post_title' => 'x',
			)
		)
	)
	&& JGOR_FLCF7_Samples::is_sample_post(
		new WP_Post(
			(object) array(
				'post_type'  => JGOR_FLCF7_Samples::FORM_POST_TYPE,
				'post_name'  => 'x',
				'post_title' => JGOR_FLCF7_Samples::TITLE_PREFIX . 'Anything' . JGOR_FLCF7_Samples::TITLE_SUFFIX,
			)
		)
	)
	&& ! JGOR_FLCF7_Samples::is_sample_post( get_post( $jgor_flcf7_foreign_page ) )
	&& ! JGOR_FLCF7_Samples::is_sample_post( get_post( $jgor_flcf7_foreign_form_id ) )
	&& ! JGOR_FLCF7_Samples::is_sample_post(
		new WP_Post(
			(object) array(
				'post_type'  => 'post',
				'post_name'  => JGOR_FLCF7_Samples::PAGE_SLUG,
				'post_title' => JGOR_FLCF7_Samples::TITLE_PREFIX . 'x' . JGOR_FLCF7_Samples::TITLE_SUFFIX,
			)
		)
	)
);
wp_delete_post( (int) $jgor_flcf7_foreign_page, true );
wp_delete_post( $jgor_flcf7_foreign_form_id, true );

// Give the user back the sample page that existed before the run.
if ( $jgor_flcf7_had_samples ) {
	WP_CLI::log( 'Sample page created again: ' . $jgor_flcf7_samples->create() . ' (page ' . $jgor_flcf7_samples->page_id() . ').' );
}

// Restore the settings option as it was before the run.
if ( false === $jgor_flcf7_saved_option ) {
	delete_option( 'jgor_flcf7_settings' );
	$jgor_flcf7_settings->ensure_option();
} else {
	update_option( 'jgor_flcf7_settings', $jgor_flcf7_saved_option );
}
$jgor_flcf7_settings->reset_cache();

if ( $GLOBALS['jgor_flcf7_failed'] > 0 ) {
	WP_CLI::error( $GLOBALS['jgor_flcf7_failed'] . ' check(s) failed.' );
}
WP_CLI::success( 'All checks passed.' );

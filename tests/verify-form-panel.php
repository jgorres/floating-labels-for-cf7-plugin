<?php
/**
 * Verifies module 7 (the tab "Floating labels" in the Contact Form 7 form
 * editor) without a browser. Prints one PASS/FAIL line per check and exits
 * with an error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-form-panel.php
 *
 * Checks: the panel appended through wpcf7_editor_panels (position, title
 * with and without the line counter), the rendered panel (selects with the
 * value of the additional settings, "Default" for unknown values, the type
 * checkboxes and the limit), saving through wpcf7_save_contact_form() with a
 * simulated post (lines replaced and appended, other lines kept, defaults
 * remove lines, an empty limit writes an empty types line, invalid values
 * ignored), the second layer (no nonce, wrong nonce, a user without the
 * capability), the context "copy" and a post without the panel, and the
 * stylesheet on the editor pages.
 *
 * Creates one form and one subscriber and deletes both again; the settings
 * option is saved and restored around the run.
 *
 * @package Floating_Labels_For_CF7
 */

( defined( 'WP_CLI' ) && WP_CLI ) || exit;

require_once ABSPATH . 'wp-admin/includes/user.php';

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
 * Renders the panel for a form into a string.
 *
 * @param JGOR_FLCF7_Form_Panel $panel Module instance.
 * @param WPCF7_ContactForm     $form  The form.
 * @return string
 */
function jgor_flcf7_render_panel( JGOR_FLCF7_Form_Panel $panel, WPCF7_ContactForm $form ): string {
	ob_start();
	$panel->render( $form );

	return (string) ob_get_clean();
}

/**
 * Saves a form through wpcf7_save_contact_form() as the editor would and
 * returns the additional settings stored afterwards.
 *
 * @param int                       $id       Form id.
 * @param string                    $settings Additional settings as posted from the textarea.
 * @param array<string, mixed>|null $panel    Posted panel values, null when the panel was not posted.
 * @param string|null               $nonce    Nonce, null for none.
 * @param string                    $context  Save context.
 * @return string
 */
function jgor_flcf7_save_as_editor( int $id, string $settings, ?array $panel, ?string $nonce, string $context = 'save' ): string {
	$data = array(
		'id'                  => $id,
		'additional_settings' => $settings,
	);
	if ( null !== $panel ) {
		$data[ JGOR_FLCF7_Form_Panel::FIELD ] = $panel;
	}
	if ( null !== $nonce ) {
		$data['_wpnonce'] = $nonce;
	}

	$result = wpcf7_save_contact_form( $data, $context );
	if ( 'save' !== $context ) {
		return $result instanceof WPCF7_ContactForm ? (string) $result->prop( 'additional_settings' ) : '';
	}

	$saved = WPCF7_ContactForm::get_instance( $id );

	return $saved instanceof WPCF7_ContactForm ? (string) $saved->prop( 'additional_settings' ) : '';
}

$jgor_flcf7_settings = JGOR_FLCF7_Settings::instance();
$jgor_flcf7_panel    = new JGOR_FLCF7_Form_Panel( $jgor_flcf7_settings );
$jgor_flcf7_panel->init();

// Save the stored option and run on the defaults.
$jgor_flcf7_saved_option = get_option( 'jgor_flcf7_settings', false );
update_option( 'jgor_flcf7_settings', $jgor_flcf7_settings->defaults() );
$jgor_flcf7_settings->reset_cache();

$jgor_flcf7_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( empty( $jgor_flcf7_admins ) ) {
	WP_CLI::error( 'No administrator found.' );
}
$jgor_flcf7_admin_id = (int) $jgor_flcf7_admins[0];
wp_set_current_user( $jgor_flcf7_admin_id );

// A form of its own, with one foreign line, one unknown value and one known value.
$jgor_flcf7_three = "skip_mail: on\nfloating_labels: maybe\nfloating_labels_style: filled";
$jgor_flcf7_form  = WPCF7_ContactForm::get_template( array( 'title' => 'FL Test Panel - may be deleted' ) );
$jgor_flcf7_form->set_properties( array( 'additional_settings' => $jgor_flcf7_three ) );
$jgor_flcf7_id = (int) $jgor_flcf7_form->save();
if ( $jgor_flcf7_id <= 0 ) {
	WP_CLI::error( 'Could not create the test form.' );
}
$jgor_flcf7_form = WPCF7_ContactForm::get_instance( $jgor_flcf7_id );

// Panel registration.
$jgor_flcf7_existing = array(
	'additional-settings-panel' => array(
		'title'    => 'Additional Settings (1)',
		'callback' => 'wpcf7_editor_panel_additional_settings',
	),
);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the Contact Form 7 hook, applied on purpose.
$jgor_flcf7_panels = apply_filters( 'wpcf7_editor_panels', $jgor_flcf7_existing );
jgor_flcf7_check( 'panel: appended behind Additional Settings with a callable callback', array( 'additional-settings-panel', JGOR_FLCF7_Form_Panel::PANEL_ID ) === array_keys( $jgor_flcf7_panels ) && is_callable( $jgor_flcf7_panels[ JGOR_FLCF7_Form_Panel::PANEL_ID ]['callback'] ?? null ) );
jgor_flcf7_check( 'panel: title counts the lines of this form (two of three)', 'Floating labels (2)' === ( $jgor_flcf7_panels[ JGOR_FLCF7_Form_Panel::PANEL_ID ]['title'] ?? '' ), (string) ( $jgor_flcf7_panels[ JGOR_FLCF7_Form_Panel::PANEL_ID ]['title'] ?? '' ) );
$jgor_flcf7_form->set_properties( array( 'additional_settings' => 'skip_mail: on' ) );
$jgor_flcf7_panels = apply_filters( 'wpcf7_editor_panels', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the Contact Form 7 hook, applied on purpose.
jgor_flcf7_check( 'panel: title without counter when no line is set', 'Floating labels' === ( $jgor_flcf7_panels[ JGOR_FLCF7_Form_Panel::PANEL_ID ]['title'] ?? '' ) );
$jgor_flcf7_form->set_properties( array( 'additional_settings' => $jgor_flcf7_three ) );

// Rendering.
$jgor_flcf7_html = jgor_flcf7_render_panel( $jgor_flcf7_panel, $jgor_flcf7_form );
jgor_flcf7_check( 'render: heading, fieldset and legend', str_contains( $jgor_flcf7_html, '<h2>Floating labels</h2>' ) && str_contains( $jgor_flcf7_html, '<fieldset class="' . JGOR_FLCF7_Form_Panel::PANEL_CLASS . '">' ) && str_contains( $jgor_flcf7_html, 'saved as lines in the tab &quot;Additional Settings&quot;' ) );
jgor_flcf7_check( 'render: four selects named mode, style, effect, marker', 4 === preg_match_all( '#<select id="jgor-flcf7-form-(mode|style|effect|marker)" name="jgor_flcf7_form\[\1\]">#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: every select starts with "Default (settings page)"', 4 === preg_match_all( '#<select[^>]*>\s*<option value=""[^>]*>Default \(settings page\)</option>#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: unknown value "maybe" -> Default selected for the mode', (bool) preg_match( '#name="jgor_flcf7_form\[mode\]">\s*<option value="" selected=\'selected\'>#', $jgor_flcf7_html ) && ! preg_match( '#name="jgor_flcf7_form\[mode\]">(?:(?!</select>).)*<option value="(?:off|placeholder|label|text)" selected#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: style filled selected', (bool) preg_match( '#<option value="filled" selected=\'selected\'>Filled</option>#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: effect and marker on Default', (bool) preg_match( '#name="jgor_flcf7_form\[effect\]">\s*<option value="" selected#', $jgor_flcf7_html ) && (bool) preg_match( '#name="jgor_flcf7_form\[marker\]">\s*<option value="" selected#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: limit unticked, 8 type checkboxes all ticked (types of the settings screen), column layout', (bool) preg_match( '#name="jgor_flcf7_form\[limit\]" value="1"\s*>#', $jgor_flcf7_html ) && ! preg_match( '#name="jgor_flcf7_form\[limit\]" value="1"\s+checked#', $jgor_flcf7_html ) && 8 === preg_match_all( '#name="jgor_flcf7_form\[types\]\[\]" value="[a-z]+"\s+checked=\'checked\'#', $jgor_flcf7_html ) && str_contains( $jgor_flcf7_html, '<fieldset class="' . JGOR_FLCF7_Admin::COLUMNS_CLASS . '">' ) && str_contains( $jgor_flcf7_html, 'Select (select)' ) );
jgor_flcf7_check( 'render: description names the lines', str_contains( $jgor_flcf7_html, 'floating_labels, floating_labels_style, floating_labels_effect, floating_labels_marker, floating_labels_types.' ) );

$jgor_flcf7_form->set_properties( array( 'additional_settings' => "floating_labels: NO\nfloating_labels_marker: yes\nfloating_labels_effect: sweep\nfloating_labels_types: text, EMAIL bogus" ) );
$jgor_flcf7_html = jgor_flcf7_render_panel( $jgor_flcf7_panel, $jgor_flcf7_form );
jgor_flcf7_check( 'render: "NO" -> Off, "yes" -> On, sweep selected (spellings module 2 accepts)', (bool) preg_match( '#<option value="off" selected=\'selected\'>Off</option>#', $jgor_flcf7_html ) && (bool) preg_match( '#<option value="on" selected=\'selected\'>On</option>#', $jgor_flcf7_html ) && (bool) preg_match( '#<option value="sweep" selected=\'selected\'>Running light</option>#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'render: types line -> limit ticked, only text and email ticked', (bool) preg_match( '#name="jgor_flcf7_form\[limit\]" value="1"\s+checked#', $jgor_flcf7_html ) && 2 === preg_match_all( '#name="jgor_flcf7_form\[types\]\[\]" value="[a-z]+"\s+checked=\'checked\'#', $jgor_flcf7_html ) && (bool) preg_match( '#value="text"\s+checked#', $jgor_flcf7_html ) && (bool) preg_match( '#value="email"\s+checked#', $jgor_flcf7_html ) );
$jgor_flcf7_form->set_properties( array( 'additional_settings' => 'floating_labels_types:' ) );
$jgor_flcf7_html = jgor_flcf7_render_panel( $jgor_flcf7_panel, $jgor_flcf7_form );
jgor_flcf7_check( 'render: empty types line -> limit ticked, nothing ticked', (bool) preg_match( '#name="jgor_flcf7_form\[limit\]" value="1"\s+checked#', $jgor_flcf7_html ) && 0 === preg_match_all( '#name="jgor_flcf7_form\[types\]\[\]" value="[a-z]+"\s+checked#', $jgor_flcf7_html ) );
ob_start();
$jgor_flcf7_panel->render( 'not a form' );
jgor_flcf7_check( 'render: nothing for a non-form argument', '' === ob_get_clean() );

// Saving as the editor does it.
$jgor_flcf7_nonce = wp_create_nonce( 'wpcf7-save-contact-form_' . $jgor_flcf7_id );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor(
	$jgor_flcf7_id,
	$jgor_flcf7_three,
	array(
		'mode'   => 'placeholder',
		'style'  => 'minimal',
		'effect' => 'glow',
		'marker' => 'off',
		'limit'  => '1',
		'types'  => array( 'email', 'text', 'bogus', 7 ),
	),
	$jgor_flcf7_nonce
);
jgor_flcf7_check( 'save: own lines replaced and appended in order, foreign line kept, types in the order of the settings', "skip_mail: on\nfloating_labels: placeholder\nfloating_labels_style: minimal\nfloating_labels_effect: glow\nfloating_labels_marker: off\nfloating_labels_types: text email" === $jgor_flcf7_saved, $jgor_flcf7_saved );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor(
	$jgor_flcf7_id,
	$jgor_flcf7_saved,
	array(
		'mode'   => '',
		'style'  => '',
		'effect' => '',
		'marker' => '',
	),
	$jgor_flcf7_nonce
);
jgor_flcf7_check( 'save: defaults everywhere and no limit -> only the foreign line', 'skip_mail: on' === $jgor_flcf7_saved, $jgor_flcf7_saved );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor(
	$jgor_flcf7_id,
	"skip_mail: on\n\ndemo_mode: on",
	array(
		'mode'   => 'fancy',
		'style'  => '<b>',
		'effect' => array( 'glow' ),
		'marker' => 'maybe',
		'limit'  => '1',
	),
	$jgor_flcf7_nonce
);
jgor_flcf7_check( 'save: invalid values ignored, limit without types -> empty types line, blank line inside kept', "skip_mail: on\n\ndemo_mode: on\nfloating_labels_types:" === $jgor_flcf7_saved, $jgor_flcf7_saved );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor( $jgor_flcf7_id, "Floating_Labels : off\nskip_mail: on", array( 'mode' => 'text' ), $jgor_flcf7_nonce );
jgor_flcf7_check( 'save: an own line in other spelling is replaced too', "skip_mail: on\nfloating_labels: text" === $jgor_flcf7_saved, $jgor_flcf7_saved );

// Second layer: nonce and capability.
$jgor_flcf7_saved = jgor_flcf7_save_as_editor( $jgor_flcf7_id, $jgor_flcf7_three, array( 'mode' => 'off' ), null );
jgor_flcf7_check( 'save: without nonce the lines stay as posted', $jgor_flcf7_three === $jgor_flcf7_saved, $jgor_flcf7_saved );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor( $jgor_flcf7_id, $jgor_flcf7_three, array( 'mode' => 'off' ), 'deadbeef00' );
jgor_flcf7_check( 'save: with a wrong nonce the lines stay as posted', $jgor_flcf7_three === $jgor_flcf7_saved );
$jgor_flcf7_subscriber = wp_insert_user(
	array(
		'user_login' => 'jgor_flcf7_panel_test',
		'user_pass'  => wp_generate_password( 24 ),
		'role'       => 'subscriber',
	)
);
if ( is_wp_error( $jgor_flcf7_subscriber ) ) {
	WP_CLI::error( 'Could not create the test user: ' . $jgor_flcf7_subscriber->get_error_message() );
}
wp_set_current_user( (int) $jgor_flcf7_subscriber );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor( $jgor_flcf7_id, $jgor_flcf7_three, array( 'mode' => 'off' ), wp_create_nonce( 'wpcf7-save-contact-form_' . $jgor_flcf7_id ) );
jgor_flcf7_check( 'save: a user with a valid nonce but without wpcf7_edit_contact_form changes nothing', $jgor_flcf7_three === $jgor_flcf7_saved );
wp_set_current_user( $jgor_flcf7_admin_id );
wp_delete_user( (int) $jgor_flcf7_subscriber );

// Context "copy" and a post without the panel.
$jgor_flcf7_copy = jgor_flcf7_save_as_editor( $jgor_flcf7_id, $jgor_flcf7_three, array( 'mode' => 'off' ), $jgor_flcf7_nonce, 'copy' );
jgor_flcf7_check( 'save: context "copy" leaves the lines alone', $jgor_flcf7_three === $jgor_flcf7_copy, $jgor_flcf7_copy );
$jgor_flcf7_saved = jgor_flcf7_save_as_editor( $jgor_flcf7_id, $jgor_flcf7_three, null, $jgor_flcf7_nonce );
jgor_flcf7_check( 'save: without the panel in the post (REST, WP-CLI) the lines stay as posted', $jgor_flcf7_three === $jgor_flcf7_saved );

// Stylesheet on the editor pages only: the action is hooked on wpcf7_admin_load.
do_action( 'wpcf7_admin_load', 'wpcf7-dashboard', '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the Contact Form 7 hook, fired on purpose.
jgor_flcf7_check( 'assets: nothing hooked on the dashboard', false === has_action( 'admin_enqueue_scripts', array( $jgor_flcf7_panel, 'enqueue_assets' ) ) );
do_action( 'wpcf7_admin_load', 'wpcf7', 'edit' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the Contact Form 7 hook, fired on purpose.
jgor_flcf7_check( 'assets: enqueue hooked on the editor page', 10 === has_action( 'admin_enqueue_scripts', array( $jgor_flcf7_panel, 'enqueue_assets' ) ) );
remove_action( 'admin_enqueue_scripts', array( $jgor_flcf7_panel, 'enqueue_assets' ) );
$jgor_flcf7_panel->enqueue_assets();
$jgor_flcf7_style = wp_styles()->query( JGOR_FLCF7_Admin::SCRIPT_HANDLE );
jgor_flcf7_check( 'assets: admin.css enqueued with the file version', wp_style_is( JGOR_FLCF7_Admin::SCRIPT_HANDLE, 'enqueued' ) && $jgor_flcf7_style instanceof _WP_Dependency && is_string( $jgor_flcf7_style->src ) && str_ends_with( $jgor_flcf7_style->src, '/assets/css/admin.css' ) && JGOR_FLCF7_Plugin::asset_version( 'assets/css/admin.css' ) === $jgor_flcf7_style->ver );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, no URL.
$jgor_flcf7_css = (string) file_get_contents( JGOR_FLCF7_PATH . 'assets/css/admin.css' );
jgor_flcf7_check( 'assets: admin.css lays the panel rows and its columns out', str_contains( $jgor_flcf7_css, '.' . JGOR_FLCF7_Form_Panel::PANEL_CLASS . ' .' . JGOR_FLCF7_Admin::COLUMNS_CLASS ) && str_contains( $jgor_flcf7_css, '.' . JGOR_FLCF7_Form_Panel::PANEL_CLASS . ' .' . JGOR_FLCF7_Form_Panel::ROW_CLASS . ' {' ) );

// Clean up.
wp_delete_post( $jgor_flcf7_id, true );
wp_set_current_user( 0 );
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

<?php
/**
 * Verifies module 4 (settings screen) without a browser. Prints one PASS/FAIL
 * line per check and exits with an error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-admin.php
 *
 * WP-CLI is not an admin request, so the script loads the admin includes the
 * Settings API needs, starts the module by hand and renders the page into a
 * buffer. Checks: menu placement (Contact Form 7 parent and the fallback),
 * registered sections and fields, the rendered form (names, nonce, defaults,
 * escaping), settings fixed through the filter jgor_flcf7_settings, the
 * capability check, the "Settings" action link, the contextual help tabs,
 * and that the CSS snippet matches the custom properties in
 * assets/css/floating-labels.css.
 *
 * The checks assume the default settings: the script saves the stored
 * option, runs on the defaults and restores the saved option afterwards.
 *
 * @package Floating_Labels_For_CF7
 */

( defined( 'WP_CLI' ) && WP_CLI ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

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
 * Renders the settings page into a string.
 *
 * @param JGOR_FLCF7_Admin $admin Module instance.
 * @return string
 */
function jgor_flcf7_render_page( JGOR_FLCF7_Admin $admin ): string {
	ob_start();
	$admin->render_page();

	return (string) ob_get_clean();
}

/**
 * Returns the submenu entries registered under a parent slug.
 *
 * @param string $parent Parent slug.
 * @return array<int, array<int, string>>
 */
function jgor_flcf7_submenu( string $parent ): array {
	global $submenu;

	return isset( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ? $submenu[ $parent ] : array();
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

$jgor_flcf7_settings = JGOR_FLCF7_Settings::instance();

// Save the stored option and run on the defaults.
$jgor_flcf7_saved_option = get_option( 'jgor_flcf7_settings', false );
update_option( 'jgor_flcf7_settings', $jgor_flcf7_settings->defaults() );
$jgor_flcf7_settings->reset_cache();
$jgor_flcf7_admin = new JGOR_FLCF7_Admin( $jgor_flcf7_settings );

/**
 * Pretends that a top-level menu is registered, as add_menu_page() would.
 *
 * @param string|null $slug Top-level slug, null for none.
 * @return void
 */
function jgor_flcf7_fake_parent( ?string $slug ): void {
	$GLOBALS['admin_page_hooks'] = null === $slug ? array() : array( $slug => 'contact' );
	$GLOBALS['_parent_pages']    = null === $slug ? array() : array( $slug => false );
	$GLOBALS['submenu']          = array();
}

// Menu: Contact Form 7 parent (6.2.3 slug), then the fallback under Settings.
jgor_flcf7_fake_parent( 'wpcf7-dashboard' );
$jgor_flcf7_admin->add_menu();
$jgor_flcf7_entries = jgor_flcf7_submenu( 'wpcf7-dashboard' );
jgor_flcf7_check( 'menu: submenu added under wpcf7-dashboard', 1 === count( $jgor_flcf7_entries ) && JGOR_FLCF7_Admin::PAGE_SLUG === $jgor_flcf7_entries[0][2] );
jgor_flcf7_check( 'menu: capability manage_options', isset( $jgor_flcf7_entries[0][1] ) && 'manage_options' === $jgor_flcf7_entries[0][1] );
jgor_flcf7_check( 'menu: page url points at the parent', str_contains( $jgor_flcf7_admin->page_url(), 'admin.php?page=' . JGOR_FLCF7_Admin::PAGE_SLUG ) );

jgor_flcf7_fake_parent( 'wpcf7' );
$jgor_flcf7_admin->add_menu();
jgor_flcf7_check( 'menu: older Contact Form 7 parent wpcf7 used when wpcf7-dashboard is missing', 1 === count( jgor_flcf7_submenu( 'wpcf7' ) ) );

jgor_flcf7_fake_parent( null );
$jgor_flcf7_admin->add_menu();
jgor_flcf7_check( 'menu: falls back to Settings without a Contact Form 7 menu', 1 === count( jgor_flcf7_submenu( 'options-general.php' ) ) );
jgor_flcf7_check( 'menu: page url under Settings', str_contains( $jgor_flcf7_admin->page_url(), 'options-general.php?page=' . JGOR_FLCF7_Admin::PAGE_SLUG ) );

// Back to the normal case for the remaining checks.
jgor_flcf7_fake_parent( 'wpcf7-dashboard' );
$jgor_flcf7_admin->add_menu();

// Contextual help: registered on the load hook of the page, three tabs and a sidebar.
$jgor_flcf7_hook = get_plugin_page_hookname( JGOR_FLCF7_Admin::PAGE_SLUG, 'wpcf7-dashboard' );
jgor_flcf7_check( 'help: add_help_tabs() hooked to load-' . $jgor_flcf7_hook, false !== has_action( 'load-' . $jgor_flcf7_hook, array( $jgor_flcf7_admin, 'add_help_tabs' ) ) );
set_current_screen( $jgor_flcf7_hook );
$jgor_flcf7_admin->add_help_tabs();
$jgor_flcf7_screen = get_current_screen();
$jgor_flcf7_tabs   = $jgor_flcf7_screen instanceof WP_Screen ? $jgor_flcf7_screen->get_help_tabs() : array();
jgor_flcf7_check( 'help: three tabs', 3 === count( $jgor_flcf7_tabs ) && array( 'jgor-flcf7-help-overview', 'jgor-flcf7-help-controls', 'jgor-flcf7-help-design' ) === array_keys( $jgor_flcf7_tabs ), implode( ', ', array_keys( $jgor_flcf7_tabs ) ) );
jgor_flcf7_check( 'help: controls tab carries the escaped examples', isset( $jgor_flcf7_tabs['jgor-flcf7-help-controls']['content'] ) && str_contains( $jgor_flcf7_tabs['jgor-flcf7-help-controls']['content'], 'nofloat placeholder &quot;Your name&quot;]' ) && str_contains( $jgor_flcf7_tabs['jgor-flcf7-help-controls']['content'], 'floating_labels_types: text email textarea' ) && str_contains( $jgor_flcf7_tabs['jgor-flcf7-help-controls']['content'], 'jgor-flcf7-style-minimal' ) );
jgor_flcf7_check( 'help: design tab carries the css snippet', isset( $jgor_flcf7_tabs['jgor-flcf7-help-design']['content'] ) && str_contains( $jgor_flcf7_tabs['jgor-flcf7-help-design']['content'], '--jgor-flcf7-duration: 0.15s;' ) );
jgor_flcf7_check( 'help: overview tab escapes <label>', isset( $jgor_flcf7_tabs['jgor-flcf7-help-overview']['content'] ) && str_contains( $jgor_flcf7_tabs['jgor-flcf7-help-overview']['content'], 'a &lt;label&gt; that encloses' ) );
jgor_flcf7_check( 'help: sidebar links to the plugin page and the Contact Form 7 docs', $jgor_flcf7_screen instanceof WP_Screen && str_contains( $jgor_flcf7_screen->get_help_sidebar(), 'https://github.com/jgorres/floating-labels-for-cf7-plugin' ) && str_contains( $jgor_flcf7_screen->get_help_sidebar(), 'https://contactform7.com/additional-settings/' ) );

// Sections and fields.
$jgor_flcf7_admin->register_fields();
$jgor_flcf7_sections = isset( $GLOBALS['wp_settings_sections'][ JGOR_FLCF7_Admin::PAGE_SLUG ] ) ? $GLOBALS['wp_settings_sections'][ JGOR_FLCF7_Admin::PAGE_SLUG ] : array();
$jgor_flcf7_fields   = isset( $GLOBALS['wp_settings_fields'][ JGOR_FLCF7_Admin::PAGE_SLUG ] ) ? $GLOBALS['wp_settings_fields'][ JGOR_FLCF7_Admin::PAGE_SLUG ] : array();
jgor_flcf7_check( 'fields: 5 sections registered', 5 === count( $jgor_flcf7_sections ), (string) count( $jgor_flcf7_sections ) );
$jgor_flcf7_field_ids = array();
foreach ( $jgor_flcf7_fields as $jgor_flcf7_section_fields ) {
	$jgor_flcf7_field_ids = array_merge( $jgor_flcf7_field_ids, array_keys( $jgor_flcf7_section_fields ) );
}
sort( $jgor_flcf7_field_ids );
jgor_flcf7_check(
	'fields: one field per setting key',
	array( 'jgor_flcf7_load_css', 'jgor_flcf7_mode_label', 'jgor_flcf7_mode_placeholder', 'jgor_flcf7_required_marker', 'jgor_flcf7_style', 'jgor_flcf7_submit_error_text', 'jgor_flcf7_types' ) === $jgor_flcf7_field_ids,
	implode( ', ', $jgor_flcf7_field_ids )
);

// Rendered page with the default settings.
$jgor_flcf7_html = jgor_flcf7_render_page( $jgor_flcf7_admin );
jgor_flcf7_check( 'page: posts to options.php', str_contains( $jgor_flcf7_html, '<form action="options.php" method="post">' ) );
jgor_flcf7_check( 'page: option_page and nonce from settings_fields()', (bool) preg_match( '#name=[\'"]option_page[\'"] value=[\'"]jgor_flcf7[\'"]#', $jgor_flcf7_html ) && (bool) preg_match( '#name=[\'"]_wpnonce[\'"]#', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: submit button', str_contains( $jgor_flcf7_html, 'id="submit"' ) );
jgor_flcf7_check( 'page: both mode checkboxes checked by default', (bool) preg_match( '#name="jgor_flcf7_settings\[mode_label\]"[^>]*checked#s', $jgor_flcf7_html ) && (bool) preg_match( '#name="jgor_flcf7_settings\[mode_placeholder\]"[^>]*checked#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: 6 type checkboxes, all checked', 6 === preg_match_all( '#name="jgor_flcf7_settings\[types\]\[\]"[^>]*checked#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: textarea type label', str_contains( $jgor_flcf7_html, 'Text area (textarea)' ) );
jgor_flcf7_check( 'page: outlined radio checked, 3 radios', 3 === preg_match_all( '#type="radio"[^>]*name="jgor_flcf7_settings\[style\]"#s', $jgor_flcf7_html ) && (bool) preg_match( '#value="outlined"[^>]*name="jgor_flcf7_settings\[style\]"[^>]*checked#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: submit error text input with the default', (bool) preg_match( '#<input type="text" class="regular-text" id="jgor-flcf7-submit-error-text" value="Please check the highlighted fields"\s+name="jgor_flcf7_settings\[submit_error_text\]"#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: load_css checked, required_marker unchecked', (bool) preg_match( '#name="jgor_flcf7_settings\[load_css\]"[^>]*checked#s', $jgor_flcf7_html ) && ! preg_match( '#name="jgor_flcf7_settings\[required_marker\]"[^>]*checked#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: no disabled controls and no locked notice by default', ! str_contains( $jgor_flcf7_html, 'disabled=' ) && ! str_contains( $jgor_flcf7_html, 'fixed in code' ) );
jgor_flcf7_check( 'page: label ids match for attributes', (bool) preg_match( '#<label for="jgor-flcf7-mode-label">\s*<input type="checkbox" id="jgor-flcf7-mode-label"#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'page: control examples escaped', str_contains( $jgor_flcf7_html, 'nofloat placeholder &quot;Your name&quot;]' ) && str_contains( $jgor_flcf7_html, 'html_class=&quot;jgor-flcf7-style-filled&quot;' ) && str_contains( $jgor_flcf7_html, 'floating_labels_types: text email textarea' ) );
jgor_flcf7_check( 'page: description with <label> escaped', str_contains( $jgor_flcf7_html, 'The &lt;label&gt; that encloses the field' ) );
jgor_flcf7_check( 'page: css snippet in a readonly textarea', (bool) preg_match( '#<textarea id="jgor-flcf7-css-snippet"[^>]*readonly>\.wpcf7 \{\n\t--jgor-flcf7-padding-x: 0\.75em;\n#s', $jgor_flcf7_html ) && str_contains( $jgor_flcf7_html, "\t--jgor-flcf7-submit-error-color: #fff;\n}</textarea>" ) );
jgor_flcf7_check( 'page: no settings-saved notice without the flag', ! str_contains( $jgor_flcf7_html, 'Settings saved.' ) );

// Settings fixed in code: disabled controls without name attributes.
$jgor_flcf7_lock = function ( $settings ) {
	$settings['style']    = 'minimal';
	$settings['load_css'] = false;
	$settings['types']    = array( 'text', 'email' );
	return $settings;
};
add_filter( 'jgor_flcf7_settings', $jgor_flcf7_lock );
$jgor_flcf7_settings->reset_cache();
$jgor_flcf7_html = jgor_flcf7_render_page( $jgor_flcf7_admin );
jgor_flcf7_check( 'locked: notice at the top', str_contains( $jgor_flcf7_html, 'fixed in code through the filter jgor_flcf7_settings. They are shown disabled.' ) );
jgor_flcf7_check( 'locked: style radios disabled without name, minimal checked', 0 === preg_match_all( '#name="jgor_flcf7_settings\[style\]"#', $jgor_flcf7_html ) && (bool) preg_match( '#value="minimal"\s+checked=\'checked\'\s+disabled=\'disabled\'#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'locked: load_css disabled, unchecked, without name', ! str_contains( $jgor_flcf7_html, 'name="jgor_flcf7_settings[load_css]"' ) && (bool) preg_match( '#id="jgor-flcf7-load-css" value="1"\s+disabled=\'disabled\'#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'locked: type checkboxes disabled without name, 2 checked', 0 === preg_match_all( '#name="jgor_flcf7_settings\[types\]\[\]"#', $jgor_flcf7_html ) && 2 === preg_match_all( '#id="jgor-flcf7-type-[a-z]+" value="[a-z]+"\s+checked=\'checked\'\s+disabled=\'disabled\'#s', $jgor_flcf7_html ) );
jgor_flcf7_check( 'locked: free settings keep their name', str_contains( $jgor_flcf7_html, 'name="jgor_flcf7_settings[mode_label]"' ) && str_contains( $jgor_flcf7_html, 'name="jgor_flcf7_settings[required_marker]"' ) );
jgor_flcf7_check( 'locked: 3 notes below the locked settings', 3 === preg_match_all( '#Fixed in code through the filter jgor_flcf7_settings; the value shown is the one in effect\.#', $jgor_flcf7_html ) );
remove_filter( 'jgor_flcf7_settings', $jgor_flcf7_lock );
$jgor_flcf7_settings->reset_cache();

// Capability: a visitor without manage_options is stopped by wp_die().
$jgor_flcf7_die = static function () {
	return static function ( $message ) {
		throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
	};
};
add_filter( 'wp_die_handler', $jgor_flcf7_die );
wp_set_current_user( 0 );
$jgor_flcf7_died = '';
try {
	ob_start();
	$jgor_flcf7_admin->render_page();
	ob_end_clean();
} catch ( RuntimeException $jgor_flcf7_e ) {
	ob_end_clean();
	$jgor_flcf7_died = $jgor_flcf7_e->getMessage();
}
remove_filter( 'wp_die_handler', $jgor_flcf7_die );
wp_set_current_user( (int) $jgor_flcf7_admins[0]->ID );
jgor_flcf7_check( 'capability: render_page() dies without manage_options', str_contains( $jgor_flcf7_died, 'not allowed' ), $jgor_flcf7_died );

// Action link on the Plugins screen.
$jgor_flcf7_links = $jgor_flcf7_admin->action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );
jgor_flcf7_check( 'plugins screen: Settings link first', 2 === count( $jgor_flcf7_links ) && str_contains( (string) reset( $jgor_flcf7_links ), 'page=' . JGOR_FLCF7_Admin::PAGE_SLUG ) && str_contains( (string) reset( $jgor_flcf7_links ), '>Settings</a>' ) );
wp_set_current_user( 0 );
jgor_flcf7_check( 'plugins screen: no link without manage_options', 1 === count( $jgor_flcf7_admin->action_links( array( 'deactivate' => 'x' ) ) ) );
wp_set_current_user( (int) $jgor_flcf7_admins[0]->ID );

// CSS snippet mirrors the .wpcf7 rule of the stylesheet.
$jgor_flcf7_css = (string) file_get_contents( JGOR_FLCF7_PATH . 'assets/css/floating-labels.css' );
preg_match( '#^\.wpcf7 \{\n(.*?)\n\}#ms', $jgor_flcf7_css, $jgor_flcf7_block );
$jgor_flcf7_in_css = array();
if ( isset( $jgor_flcf7_block[1] ) ) {
	preg_match_all( '#^\s*(--jgor-flcf7-[a-z-]+):\s*(.+?);\s*$#m', $jgor_flcf7_block[1], $jgor_flcf7_decls, PREG_SET_ORDER );
	foreach ( $jgor_flcf7_decls as $jgor_flcf7_decl ) {
		$jgor_flcf7_in_css[ $jgor_flcf7_decl[1] ] = $jgor_flcf7_decl[2];
	}
}
jgor_flcf7_check( 'css: snippet properties equal the .wpcf7 rule of the stylesheet', JGOR_FLCF7_Admin::CSS_PROPERTIES === $jgor_flcf7_in_css, wp_json_encode( array_diff_assoc( $jgor_flcf7_in_css, JGOR_FLCF7_Admin::CSS_PROPERTIES ) ) . ' / ' . wp_json_encode( array_diff_assoc( JGOR_FLCF7_Admin::CSS_PROPERTIES, $jgor_flcf7_in_css ) ) );
jgor_flcf7_check( 'css: snippet has one line per property', count( JGOR_FLCF7_Admin::CSS_PROPERTIES ) + 2 === count( explode( "\n", $jgor_flcf7_admin->css_snippet() ) ) );

// Restore the option as it was before the run.
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

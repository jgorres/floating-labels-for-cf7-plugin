<?php
/**
 * Verifies module 5 (block editor panel) without a browser: the enqueue of
 * the editor script and stylesheet. Prints one PASS/FAIL line per check and
 * exits with an error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-editor.php
 *
 * Checks: the action hook, the enqueued script (source, dependencies,
 * version, footer, translations, inline data with the design properties),
 * the enqueued stylesheet, the script file itself (block name, filter, every
 * placement class of the concept, design attributes, colour palette, text
 * domain), the block attribute jgorFlcf7Design declared on the Contact Form
 * 7 block and exposed to the editor, and that nothing is enqueued while the
 * Contact Form 7 block is not registered. The JavaScript logic is covered by
 * tests/verify-editor-js.mjs (Node), the server side of the design values by
 * tests/verify-markup.php.
 *
 * Nothing is saved; the block registry is restored afterwards.
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

$jgor_flcf7_handle = JGOR_FLCF7_Editor::HANDLE;
$jgor_flcf7_block  = JGOR_FLCF7_Editor::BLOCK_NAME;

jgor_flcf7_check( 'class JGOR_FLCF7_Editor loaded by the main file', class_exists( 'JGOR_FLCF7_Editor' ) );
jgor_flcf7_check( 'Contact Form 7 block registered on this site', WP_Block_Type_Registry::get_instance()->is_registered( $jgor_flcf7_block ) );

$jgor_flcf7_editor = new JGOR_FLCF7_Editor( JGOR_FLCF7_Settings::instance() );
$jgor_flcf7_editor->init();
jgor_flcf7_check( 'hook: enqueue() on enqueue_block_editor_assets', false !== has_action( 'enqueue_block_editor_assets', array( $jgor_flcf7_editor, 'enqueue' ) ) );

// Enqueue with the block registered.
$jgor_flcf7_editor->enqueue();
$jgor_flcf7_scripts = wp_scripts();
$jgor_flcf7_reg     = $jgor_flcf7_scripts->registered[ $jgor_flcf7_handle ] ?? null;
jgor_flcf7_check( 'script: enqueued', wp_script_is( $jgor_flcf7_handle, 'enqueued' ) );
jgor_flcf7_check( 'script: source is assets/js/block-editor.js', $jgor_flcf7_reg instanceof _WP_Dependency && is_string( $jgor_flcf7_reg->src ) && str_ends_with( $jgor_flcf7_reg->src, '/assets/js/block-editor.js' ) );
jgor_flcf7_check( 'script: dependencies', $jgor_flcf7_reg instanceof _WP_Dependency && JGOR_FLCF7_Editor::DEPENDENCIES === $jgor_flcf7_reg->deps, $jgor_flcf7_reg instanceof _WP_Dependency ? implode( ',', $jgor_flcf7_reg->deps ) : 'not registered' );
jgor_flcf7_check( 'script: version is the plugin version plus the file time', $jgor_flcf7_reg instanceof _WP_Dependency && JGOR_FLCF7_Plugin::asset_version( 'assets/js/block-editor.js' ) === $jgor_flcf7_reg->ver );
jgor_flcf7_check( 'script: printed in the footer', 1 === (int) $jgor_flcf7_scripts->get_data( $jgor_flcf7_handle, 'group' ) );
jgor_flcf7_check( 'script: translations set for the text domain', $jgor_flcf7_reg instanceof _WP_Dependency && 'floating-labels-for-cf7' === $jgor_flcf7_reg->textdomain && JGOR_FLCF7_PATH . 'languages' === $jgor_flcf7_reg->translations_path );

// The stylesheet of the colour rows, same handle and version.
$jgor_flcf7_style = wp_styles()->registered[ $jgor_flcf7_handle ] ?? null;
jgor_flcf7_check( 'style: enqueued with the script', wp_style_is( $jgor_flcf7_handle, 'enqueued' ) );
jgor_flcf7_check( 'style: source is assets/css/editor.css, depends on wp-components, plugin version', $jgor_flcf7_style instanceof _WP_Dependency && is_string( $jgor_flcf7_style->src ) && str_ends_with( $jgor_flcf7_style->src, '/assets/css/editor.css' ) && JGOR_FLCF7_Editor::STYLE_DEPENDENCIES === $jgor_flcf7_style->deps && JGOR_FLCF7_Plugin::asset_version( 'assets/css/editor.css' ) === $jgor_flcf7_style->ver );
$jgor_flcf7_css_file = JGOR_FLCF7_PATH . 'assets/css/editor.css';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, no URL.
$jgor_flcf7_css = file_exists( $jgor_flcf7_css_file ) ? (string) file_get_contents( $jgor_flcf7_css_file ) : '';
jgor_flcf7_check( 'style: assets/css/editor.css exists and styles the colour rows', str_contains( $jgor_flcf7_css, '.jgor-flcf7-color__toggle' ) && str_contains( $jgor_flcf7_css, '.jgor-flcf7-color__panel' ) );

// Inline data: the design properties for the panels, printed before the script.
$jgor_flcf7_before = $jgor_flcf7_scripts->get_data( $jgor_flcf7_handle, 'before' );
$jgor_flcf7_before = is_array( $jgor_flcf7_before ) ? implode( "\n", array_filter( $jgor_flcf7_before, 'is_string' ) ) : '';
$jgor_flcf7_data   = null;
if ( preg_match( '#window\.jgorFlcf7EditorData = (\{.*\});#s', $jgor_flcf7_before, $jgor_flcf7_m ) ) {
	$jgor_flcf7_data = json_decode( $jgor_flcf7_m[1], true );
}
jgor_flcf7_check( 'data: window.jgorFlcf7EditorData printed before the script as JSON', is_array( $jgor_flcf7_data ) && isset( $jgor_flcf7_data['design'] ) && is_array( $jgor_flcf7_data['design'] ) );
$jgor_flcf7_design = is_array( $jgor_flcf7_data ) && isset( $jgor_flcf7_data['design'] ) && is_array( $jgor_flcf7_data['design'] ) ? $jgor_flcf7_data['design'] : array();
$jgor_flcf7_keys   = array_column( $jgor_flcf7_design, 'key' );
sort( $jgor_flcf7_keys );
$jgor_flcf7_all = array_keys( JGOR_FLCF7_Settings::DESIGN_TYPES );
sort( $jgor_flcf7_all );
jgor_flcf7_check( 'data: every design property exactly once', $jgor_flcf7_keys === $jgor_flcf7_all, implode( ',', $jgor_flcf7_keys ) );
$jgor_flcf7_groups = array_count_values( array_column( $jgor_flcf7_design, 'group' ) );
jgor_flcf7_check( 'data: groups colors (10), sizes (6), motion (2), effect (4) in the order of the settings screen', 10 === ( $jgor_flcf7_groups['colors'] ?? 0 ) && 6 === ( $jgor_flcf7_groups['sizes'] ?? 0 ) && 2 === ( $jgor_flcf7_groups['motion'] ?? 0 ) && 4 === ( $jgor_flcf7_groups['effect'] ?? 0 ) && 'label-color' === ( $jgor_flcf7_design[0]['key'] ?? '' ) && 'duration' === ( $jgor_flcf7_design[17]['key'] ?? '' ) && 'effect-duration' === ( $jgor_flcf7_design[21]['key'] ?? '' ) );
$jgor_flcf7_first = $jgor_flcf7_design[0] ?? array();
jgor_flcf7_check( 'data: type, translated label and the value in effect as placeholder', 'color' === ( $jgor_flcf7_first['type'] ?? '' ) && 'Label' === ( $jgor_flcf7_first['label'] ?? '' ) && ( $jgor_flcf7_first['placeholder'] ?? '' ) === JGOR_FLCF7_Settings::instance()->design()['label-color'] );

// Block attribute of the design values, declared on the server and exposed to the editor.
$jgor_flcf7_type = WP_Block_Type_Registry::get_instance()->get_registered( $jgor_flcf7_block );
jgor_flcf7_check( 'attribute: jgorFlcf7Design (object) declared on the Contact Form 7 block', $jgor_flcf7_type instanceof WP_Block_Type && is_array( $jgor_flcf7_type->attributes ) && array( 'type' => 'object' ) === ( $jgor_flcf7_type->attributes[ JGOR_FLCF7_Placement_Design::BLOCK_ATTRIBUTE ] ?? null ) );
$jgor_flcf7_server = get_block_editor_server_block_settings();
jgor_flcf7_check( 'attribute: part of the server block settings the editor bootstraps', isset( $jgor_flcf7_server[ $jgor_flcf7_block ]['attributes'][ JGOR_FLCF7_Placement_Design::BLOCK_ATTRIBUTE ] ) && isset( $jgor_flcf7_server[ $jgor_flcf7_block ]['attributes']['htmlClass'] ) );

// The script file.
$jgor_flcf7_file = JGOR_FLCF7_PATH . 'assets/js/block-editor.js';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, no URL.
$jgor_flcf7_js = file_exists( $jgor_flcf7_file ) ? (string) file_get_contents( $jgor_flcf7_file ) : '';
jgor_flcf7_check( 'file: assets/js/block-editor.js exists', '' !== $jgor_flcf7_js );
jgor_flcf7_check( 'file: targets the Contact Form 7 block and the filter editor.BlockEdit', str_contains( $jgor_flcf7_js, "'" . $jgor_flcf7_block . "'" ) && str_contains( $jgor_flcf7_js, "'editor.BlockEdit'" ) );
jgor_flcf7_check( 'file: targets the Shortcode block with a [contact-form-7] shortcode', str_contains( $jgor_flcf7_js, "'core/shortcode'" ) && str_contains( $jgor_flcf7_js, 'contact-form-7\\b' ) );
$jgor_flcf7_classes = array( 'jgor-flcf7-on', 'jgor-flcf7-off', 'jgor-flcf7-mode-placeholder', 'jgor-flcf7-mode-label', 'jgor-flcf7-mode-text', 'jgor-flcf7-marker-on', 'jgor-flcf7-marker-off' );
foreach ( JGOR_FLCF7_Settings::STYLES as $jgor_flcf7_style_name ) {
	$jgor_flcf7_classes[] = JGOR_FLCF7_Markup::HTML_CLASS_STYLE_PREFIX . $jgor_flcf7_style_name;
}
foreach ( JGOR_FLCF7_Settings::EFFECTS as $jgor_flcf7_effect_name ) {
	$jgor_flcf7_classes[] = JGOR_FLCF7_Markup::HTML_CLASS_EFFECT_PREFIX . $jgor_flcf7_effect_name;
}
$jgor_flcf7_missing = array();
foreach ( $jgor_flcf7_classes as $jgor_flcf7_class ) {
	if ( ! str_contains( $jgor_flcf7_js, "'" . $jgor_flcf7_class . "'" ) ) {
		$jgor_flcf7_missing[] = $jgor_flcf7_class;
	}
}
jgor_flcf7_check( 'file: every placement class of the concept (14) is offered', 14 === count( $jgor_flcf7_classes ) && empty( $jgor_flcf7_missing ), implode( ', ', $jgor_flcf7_missing ) );
jgor_flcf7_check( 'file: design attributes, data global and colour palette used', str_contains( $jgor_flcf7_js, "'" . JGOR_FLCF7_Placement_Design::BLOCK_ATTRIBUTE . "'" ) && str_contains( $jgor_flcf7_js, "'" . JGOR_FLCF7_Placement_Design::SHORTCODE_ATTRIBUTE . "'" ) && str_contains( $jgor_flcf7_js, 'window.' . JGOR_FLCF7_Editor::DATA_GLOBAL ) && str_contains( $jgor_flcf7_js, 'wp.components.ColorPalette' ) && str_contains( $jgor_flcf7_js, 'wp.blockEditor.useSettings' ) );
jgor_flcf7_check( 'file: reset buttons with their helpers', str_contains( $jgor_flcf7_js, "'Reset this placement'" ) && str_contains( $jgor_flcf7_js, "'Reset colours'" ) && str_contains( $jgor_flcf7_js, 'function withoutGroups(' ) && str_contains( $jgor_flcf7_js, 'function designWithoutGroup(' ) && str_contains( $jgor_flcf7_js, "className: 'jgor-flcf7-reset'" ) );
jgor_flcf7_check( 'file: collapsible panels only, no PanelColorSettings and no Flex wrappers', ! str_contains( $jgor_flcf7_js, 'PanelColorSettings' ) && ! str_contains( $jgor_flcf7_js, 'wp.components.Flex' ) );
// __( 'text' ) and _x( 'text', 'context' ) without the domain, and the number of calls against the number of domain arguments.
jgor_flcf7_check( 'file: every text carries the text domain', 0 === preg_match( '/\b__\(\s*\'(?:[^\'\\\\]|\\\\.)*\'\s*\)/', $jgor_flcf7_js ) && 0 === preg_match( '/\b_x\(\s*\'(?:[^\'\\\\]|\\\\.)*\'\s*,\s*\'(?:[^\'\\\\]|\\\\.)*\'\s*\)/', $jgor_flcf7_js ) && preg_match_all( '/\b(?:__|_x)\(/', $jgor_flcf7_js ) === preg_match_all( "/, 'floating-labels-for-cf7' \)/", $jgor_flcf7_js ) );

// Without the block: nothing is enqueued.
wp_dequeue_script( $jgor_flcf7_handle );
wp_deregister_script( $jgor_flcf7_handle );
wp_dequeue_style( $jgor_flcf7_handle );
wp_deregister_style( $jgor_flcf7_handle );
$jgor_flcf7_type = unregister_block_type( $jgor_flcf7_block );
$jgor_flcf7_editor->enqueue();
jgor_flcf7_check( 'without the block: script and style neither enqueued nor registered', ! wp_script_is( $jgor_flcf7_handle, 'enqueued' ) && ! wp_script_is( $jgor_flcf7_handle, 'registered' ) && ! wp_style_is( $jgor_flcf7_handle, 'enqueued' ) && ! wp_style_is( $jgor_flcf7_handle, 'registered' ) );
if ( $jgor_flcf7_type instanceof WP_Block_Type ) {
	register_block_type( $jgor_flcf7_type );
}
jgor_flcf7_check( 'block registry restored', WP_Block_Type_Registry::get_instance()->is_registered( $jgor_flcf7_block ) );

if ( $GLOBALS['jgor_flcf7_failed'] > 0 ) {
	WP_CLI::error( $GLOBALS['jgor_flcf7_failed'] . ' check(s) failed.' );
}
WP_CLI::success( 'All checks passed.' );

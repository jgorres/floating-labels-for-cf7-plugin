<?php
/**
 * Verifies module 5 (block editor panel) without a browser: the enqueue of
 * the editor script. Prints one PASS/FAIL line per check and exits with an
 * error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-editor.php
 *
 * Checks: the action hook, the enqueued script (source, dependencies,
 * version, footer, translations), the script file itself (block name,
 * filter, every placement class of the concept) and that nothing is
 * enqueued while the Contact Form 7 block is not registered. The JavaScript
 * logic is covered by tests/verify-editor-js.mjs (Node).
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

$jgor_flcf7_editor = new JGOR_FLCF7_Editor();
$jgor_flcf7_editor->init();
jgor_flcf7_check( 'hook: enqueue() on enqueue_block_editor_assets', false !== has_action( 'enqueue_block_editor_assets', array( $jgor_flcf7_editor, 'enqueue' ) ) );

// Enqueue with the block registered.
$jgor_flcf7_editor->enqueue();
$jgor_flcf7_scripts = wp_scripts();
$jgor_flcf7_reg     = $jgor_flcf7_scripts->registered[ $jgor_flcf7_handle ] ?? null;
jgor_flcf7_check( 'script: enqueued', wp_script_is( $jgor_flcf7_handle, 'enqueued' ) );
jgor_flcf7_check( 'script: source is assets/js/block-editor.js', $jgor_flcf7_reg instanceof _WP_Dependency && is_string( $jgor_flcf7_reg->src ) && str_ends_with( $jgor_flcf7_reg->src, '/assets/js/block-editor.js' ) );
jgor_flcf7_check( 'script: dependencies', $jgor_flcf7_reg instanceof _WP_Dependency && JGOR_FLCF7_Editor::DEPENDENCIES === $jgor_flcf7_reg->deps, $jgor_flcf7_reg instanceof _WP_Dependency ? implode( ',', $jgor_flcf7_reg->deps ) : 'not registered' );
jgor_flcf7_check( 'script: version is the plugin version', $jgor_flcf7_reg instanceof _WP_Dependency && JGOR_FLCF7_VERSION === $jgor_flcf7_reg->ver );
jgor_flcf7_check( 'script: printed in the footer', 1 === (int) $jgor_flcf7_scripts->get_data( $jgor_flcf7_handle, 'group' ) );
jgor_flcf7_check( 'script: translations set for the text domain', $jgor_flcf7_reg instanceof _WP_Dependency && 'floating-labels-for-cf7' === $jgor_flcf7_reg->textdomain && JGOR_FLCF7_PATH . 'languages' === $jgor_flcf7_reg->translations_path );

// The script file.
$jgor_flcf7_file = JGOR_FLCF7_PATH . 'assets/js/block-editor.js';
$jgor_flcf7_js   = file_exists( $jgor_flcf7_file ) ? (string) file_get_contents( $jgor_flcf7_file ) : '';
jgor_flcf7_check( 'file: assets/js/block-editor.js exists', '' !== $jgor_flcf7_js );
jgor_flcf7_check( 'file: targets the Contact Form 7 block and the filter editor.BlockEdit', str_contains( $jgor_flcf7_js, "'" . $jgor_flcf7_block . "'" ) && str_contains( $jgor_flcf7_js, "'editor.BlockEdit'" ) );
jgor_flcf7_check( 'file: targets the Shortcode block with a [contact-form-7] shortcode', str_contains( $jgor_flcf7_js, "'core/shortcode'" ) && str_contains( $jgor_flcf7_js, 'contact-form-7\\b' ) );
$jgor_flcf7_classes = array( 'jgor-flcf7-on', 'jgor-flcf7-off', 'jgor-flcf7-mode-placeholder', 'jgor-flcf7-mode-label', 'jgor-flcf7-marker-on', 'jgor-flcf7-marker-off' );
foreach ( JGOR_FLCF7_Settings::STYLES as $jgor_flcf7_style ) {
	$jgor_flcf7_classes[] = JGOR_FLCF7_Markup::HTML_CLASS_STYLE_PREFIX . $jgor_flcf7_style;
}
$jgor_flcf7_missing = array();
foreach ( $jgor_flcf7_classes as $jgor_flcf7_class ) {
	if ( ! str_contains( $jgor_flcf7_js, "'" . $jgor_flcf7_class . "'" ) ) {
		$jgor_flcf7_missing[] = $jgor_flcf7_class;
	}
}
jgor_flcf7_check( 'file: every placement class of the concept (9) is offered', empty( $jgor_flcf7_missing ), implode( ', ', $jgor_flcf7_missing ) );
jgor_flcf7_check( 'file: every text carries the text domain', 0 === preg_match( '/__\(\s*\'(?:[^\'\\\\]|\\\\.)*\'\s*\)/', $jgor_flcf7_js ) && preg_match_all( '/__\(/', $jgor_flcf7_js ) === preg_match_all( "/, 'floating-labels-for-cf7' \)/", $jgor_flcf7_js ) );

// Without the block: nothing is enqueued.
wp_dequeue_script( $jgor_flcf7_handle );
wp_deregister_script( $jgor_flcf7_handle );
$jgor_flcf7_type = unregister_block_type( $jgor_flcf7_block );
$jgor_flcf7_editor->enqueue();
jgor_flcf7_check( 'without the block: script neither enqueued nor registered', ! wp_script_is( $jgor_flcf7_handle, 'enqueued' ) && ! wp_script_is( $jgor_flcf7_handle, 'registered' ) );
if ( $jgor_flcf7_type instanceof WP_Block_Type ) {
	register_block_type( $jgor_flcf7_type );
}
jgor_flcf7_check( 'block registry restored', WP_Block_Type_Registry::get_instance()->is_registered( $jgor_flcf7_block ) );

if ( $GLOBALS['jgor_flcf7_failed'] > 0 ) {
	WP_CLI::error( $GLOBALS['jgor_flcf7_failed'] . ' check(s) failed.' );
}
WP_CLI::success( 'All checks passed.' );

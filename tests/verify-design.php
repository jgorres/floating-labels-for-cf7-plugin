<?php
/**
 * Verifies the design settings (colours, sizes, timing) of modules 1 and 3
 * without a browser. Prints one PASS/FAIL line per check and exits with an
 * error when a check fails.
 *
 * Usage, on a local test site only:
 *
 *   wp --path=/var/www/local-sites/floating-labels-for-cf7.gorres.local eval-file tests/verify-design.php
 *
 * Checks: the property lists, sanitizing per type (colour, length, number,
 * time), the option sanitize callback, the values in effect and the
 * overrides, the inline style module 3 adds after the stylesheet (and its
 * absence with default values or without the stylesheet), the lock through
 * the filter jgor_flcf7_settings, and the font-size hook in the stylesheet.
 *
 * The script saves the stored option, runs on the defaults, changes the
 * option temporarily and restores the saved option afterwards.
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
 * Returns the inline style module 3 registered after the stylesheet.
 *
 * @return string Joined "after" data, or '' when none.
 */
function jgor_flcf7_inline_after(): string {
	$after = wp_styles()->get_data( JGOR_FLCF7_Assets::HANDLE, 'after' );

	return is_array( $after ) ? implode( "\n", $after ) : '';
}

/**
 * Removes the stylesheet from the queue and registry so enqueue() starts fresh.
 *
 * @return void
 */
function jgor_flcf7_reset_style(): void {
	wp_dequeue_style( JGOR_FLCF7_Assets::HANDLE );
	wp_deregister_style( JGOR_FLCF7_Assets::HANDLE );
}

$jgor_flcf7_settings = JGOR_FLCF7_Settings::instance();

// Save the stored option and run on the defaults.
$jgor_flcf7_saved_option = get_option( 'jgor_flcf7_settings', false );
update_option( 'jgor_flcf7_settings', $jgor_flcf7_settings->defaults() );
$jgor_flcf7_settings->reset_cache();

// Property lists.
jgor_flcf7_check( 'properties: 22 defaults and 22 types with the same keys', 22 === count( JGOR_FLCF7_Settings::DESIGN_DEFAULTS ) && array_keys( JGOR_FLCF7_Settings::DESIGN_DEFAULTS ) === array_keys( JGOR_FLCF7_Settings::DESIGN_TYPES ) );
jgor_flcf7_check( 'properties: css_defaults() carries the prefix', isset( JGOR_FLCF7_Settings::css_defaults()['--jgor-flcf7-font-size'] ) && '1em' === JGOR_FLCF7_Settings::css_defaults()['--jgor-flcf7-font-size'] );
$jgor_flcf7_counts = array_count_values( JGOR_FLCF7_Settings::DESIGN_TYPES );
jgor_flcf7_check( 'properties: 11 colours, 8 lengths, 1 number, 2 times', 11 === $jgor_flcf7_counts['color'] && 8 === $jgor_flcf7_counts['length'] && 1 === $jgor_flcf7_counts['number'] && 2 === $jgor_flcf7_counts['time'] );

// Sanitizing per type.
$jgor_flcf7_s = 'JGOR_FLCF7_Settings::sanitize_design_value';
jgor_flcf7_check( 'color: #ABC -> #abc, #1a73e8 kept', '#abc' === $jgor_flcf7_s( 'label-color', ' #ABC ' ) && '#1a73e8' === $jgor_flcf7_s( 'border-color', '#1a73e8' ) );
jgor_flcf7_check( 'color: names, rgba, short and broken hex rejected', '' === $jgor_flcf7_s( 'label-color', 'red' ) && '' === $jgor_flcf7_s( 'label-color', 'rgba(0,0,0,0.5)' ) && '' === $jgor_flcf7_s( 'label-color', '#12' ) && '' === $jgor_flcf7_s( 'label-color', '#ggg' ) && '' === $jgor_flcf7_s( 'label-color', '#1234' ) );
jgor_flcf7_check( 'length: px, em, rem, %, 0 accepted', '12px' === $jgor_flcf7_s( 'padding-x', '12px' ) && '1.5em' === $jgor_flcf7_s( 'font-size', '1.5EM' ) && '.5rem' === $jgor_flcf7_s( 'radius', '.5rem' ) && '100%' === $jgor_flcf7_s( 'label-size', '100%' ) && '0' === $jgor_flcf7_s( 'border-width', '0' ) );
jgor_flcf7_check( 'length: unitless, negative, spaced, double rejected', '' === $jgor_flcf7_s( 'padding-x', '12' ) && '' === $jgor_flcf7_s( 'padding-x', '-1px' ) && '' === $jgor_flcf7_s( 'padding-x', '1 em' ) && '' === $jgor_flcf7_s( 'padding-x', '1em 2em' ) && '' === $jgor_flcf7_s( 'padding-x', '1vw' ) );
jgor_flcf7_check( 'number: 0.8 kept, 1 and 0.5 normalised, out of range rejected', '0.8' === $jgor_flcf7_s( 'label-scale', '0.80' ) && '1' === $jgor_flcf7_s( 'label-scale', '1.0' ) && '0.5' === $jgor_flcf7_s( 'label-scale', '0.5' ) && '' === $jgor_flcf7_s( 'label-scale', '0.4' ) && '' === $jgor_flcf7_s( 'label-scale', '1.2' ) && '' === $jgor_flcf7_s( 'label-scale', 'abc' ) );
jgor_flcf7_check( 'time: s and ms accepted, bare number rejected', '0.3s' === $jgor_flcf7_s( 'duration', '0.3s' ) && '200ms' === $jgor_flcf7_s( 'duration', '200MS' ) && '' === $jgor_flcf7_s( 'duration', '1' ) && '' === $jgor_flcf7_s( 'duration', '1min' ) );
jgor_flcf7_check( 'effect properties: colour, lengths and time sanitized like the others', '#abc' === $jgor_flcf7_s( 'effect-color', '#ABC' ) && '12px' === $jgor_flcf7_s( 'effect-size', '12px' ) && '0' === $jgor_flcf7_s( 'effect-width', '0' ) && '2s' === $jgor_flcf7_s( 'effect-duration', '2S' ) && '' === $jgor_flcf7_s( 'effect-duration', 'fast' ) && '' === $jgor_flcf7_s( 'effect-size', '8' ) );
jgor_flcf7_check( 'effect setting: choice sanitized, default none', 'none' === $jgor_flcf7_settings->defaults()['effect'] && 'sweep' === $jgor_flcf7_settings->sanitize( array( 'effect' => 'SWEEP' ) )['effect'] && 'none' === $jgor_flcf7_settings->sanitize( array( 'effect' => 'spin' ) )['effect'] && 'none' === $jgor_flcf7_settings->sanitize( array() )['effect'] );
jgor_flcf7_check( 'unknown property, empty and non-scalar -> empty', '' === $jgor_flcf7_s( 'bogus', '#fff' ) && '' === $jgor_flcf7_s( 'label-color', '' ) && '' === $jgor_flcf7_s( 'label-color', array( '#fff' ) ) && '' === $jgor_flcf7_s( 'label-color', null ) );

// Option sanitize callback.
$jgor_flcf7_clean = $jgor_flcf7_settings->sanitize(
	array(
		'design' => array(
			'label-color' => '#ABCDEF',
			'padding-x'   => '2em',
			'label-scale' => '2',
			'duration'    => '',
			'bogus'       => '#000',
			'radius'      => 'round',
		),
	)
);
jgor_flcf7_check(
	'sanitize(): only valid, non-empty, known design values kept',
	array(
		'padding-x'   => '2em',
		'label-color' => '#abcdef',
	) === $jgor_flcf7_clean['design'],
	wp_json_encode( $jgor_flcf7_clean['design'] )
);
jgor_flcf7_check( 'sanitize(): non-array design -> empty array', array() === $jgor_flcf7_settings->sanitize( array( 'design' => 'x' ) )['design'] );
jgor_flcf7_check( 'defaults(): design is an empty array', array() === $jgor_flcf7_settings->defaults()['design'] );

// Values in effect and overrides.
jgor_flcf7_check( 'design(): defaults while nothing is set', JGOR_FLCF7_Settings::DESIGN_DEFAULTS === $jgor_flcf7_settings->design() );
jgor_flcf7_check( 'design_overrides(): empty while nothing is set', array() === $jgor_flcf7_settings->design_overrides() );

$jgor_flcf7_assets = new JGOR_FLCF7_Assets( $jgor_flcf7_settings );
jgor_flcf7_check( 'inline_css(): empty while nothing is set', '' === $jgor_flcf7_assets->inline_css() );
jgor_flcf7_reset_style();
$jgor_flcf7_assets->enqueue();
jgor_flcf7_check( 'enqueue(): stylesheet enqueued, no inline style with defaults', wp_style_is( JGOR_FLCF7_Assets::HANDLE, 'enqueued' ) && '' === jgor_flcf7_inline_after() );

$jgor_flcf7_option           = get_option( 'jgor_flcf7_settings' );
$jgor_flcf7_option['design'] = array(
	'label-color' => '#123456',
	'font-size'   => '1.2em',
	'radius'      => '4px',
	'label-scale' => '0.75',
);
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
$jgor_flcf7_settings->reset_cache();
jgor_flcf7_check( 'design(): stored values on top of the defaults', '#123456' === $jgor_flcf7_settings->design()['label-color'] && '1.2em' === $jgor_flcf7_settings->design()['font-size'] && '0.75em' === $jgor_flcf7_settings->design()['padding-x'] );
jgor_flcf7_check(
	'design_overrides(): values equal to the default are left out',
	array(
		'font-size'   => '1.2em',
		'label-color' => '#123456',
	) === $jgor_flcf7_settings->design_overrides(),
	wp_json_encode( $jgor_flcf7_settings->design_overrides() )
);
jgor_flcf7_check( 'inline_css(): one .wpcf7 rule with the overrides only', '.wpcf7 { --jgor-flcf7-font-size: 1.2em; --jgor-flcf7-label-color: #123456; }' === $jgor_flcf7_assets->inline_css(), $jgor_flcf7_assets->inline_css() );
jgor_flcf7_reset_style();
$jgor_flcf7_assets->enqueue();
jgor_flcf7_check( 'enqueue(): inline style added after the stylesheet', str_contains( jgor_flcf7_inline_after(), '--jgor-flcf7-label-color: #123456;' ) );

// Invalid stored values (option edited by hand) are ignored at output time.
$jgor_flcf7_option['design'] = array(
	'label-color' => 'red; } body { display: none',
	'font-size'   => '1.2em',
);
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
$jgor_flcf7_settings->reset_cache();
jgor_flcf7_check( 'inline_css(): invalid stored value dropped, valid one kept', '.wpcf7 { --jgor-flcf7-font-size: 1.2em; }' === $jgor_flcf7_assets->inline_css(), $jgor_flcf7_assets->inline_css() );

// Without the stylesheet nothing is printed.
$jgor_flcf7_option['load_css'] = false;
update_option( 'jgor_flcf7_settings', $jgor_flcf7_option );
$jgor_flcf7_settings->reset_cache();
jgor_flcf7_reset_style();
$jgor_flcf7_assets->enqueue();
jgor_flcf7_check( 'enqueue(): nothing with load_css off', ! wp_style_is( JGOR_FLCF7_Assets::HANDLE, 'enqueued' ) && '' === jgor_flcf7_inline_after() );

// Lock through the filter.
update_option( 'jgor_flcf7_settings', $jgor_flcf7_settings->defaults() );
$jgor_flcf7_settings->reset_cache();
$jgor_flcf7_lock = function ( $settings ) {
	$settings['design'] = array(
		'border-color' => '#00FF00',
		'padding-x'    => 'wide',
	);
	return $settings;
};
add_filter( 'jgor_flcf7_settings', $jgor_flcf7_lock );
$jgor_flcf7_settings->reset_cache();
jgor_flcf7_check( 'filter: design locked, filter value sanitized', $jgor_flcf7_settings->is_locked( 'design' ) && array( 'border-color' => '#00ff00' ) === $jgor_flcf7_settings->get( 'design' ) );
jgor_flcf7_check( 'filter: sanitize() keeps the stored (empty) design while locked', array() === $jgor_flcf7_settings->sanitize( array( 'design' => array( 'radius' => '9px' ) ) )['design'] );
remove_filter( 'jgor_flcf7_settings', $jgor_flcf7_lock );
$jgor_flcf7_settings->reset_cache();

// Stylesheet: the font-size property is defined and applied to the wrap.
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, no URL.
$jgor_flcf7_css = (string) file_get_contents( JGOR_FLCF7_PATH . 'assets/css/floating-labels.css' );
jgor_flcf7_check( 'css: focus effect rules (glow, pulse keyframes, sweep ring with @property and mask, reduced motion)', str_contains( $jgor_flcf7_css, '.wpcf7 .jgor-flcf7-glow .jgor-flcf7-control:focus {' ) && str_contains( $jgor_flcf7_css, '@keyframes jgor-flcf7-pulse' ) && str_contains( $jgor_flcf7_css, '@property --jgor-flcf7-angle' ) && str_contains( $jgor_flcf7_css, '.wpcf7 .jgor-flcf7-sweep:focus-within::before {' ) && str_contains( $jgor_flcf7_css, 'mask-composite: exclude;' ) && 2 === preg_match_all( '#animation: none;#', $jgor_flcf7_css ) );
jgor_flcf7_check( 'css: --jgor-flcf7-font-size defined and used on the wrap', str_contains( $jgor_flcf7_css, "\t--jgor-flcf7-font-size: 1em;\n" ) && (bool) preg_match( '#\.wpcf7 \.jgor-flcf7-wrap \{[^}]*font-size: var\(--jgor-flcf7-font-size\);#s', $jgor_flcf7_css ) );

// Restore the option as it was before the run.
jgor_flcf7_reset_style();
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

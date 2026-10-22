<?php
/**
 * PHPStan bootstrap: constants the plugin defines at runtime.
 *
 * This file is only loaded by PHPStan, never by WordPress. The values are
 * placeholders; all that matters is that the constants count as defined
 * during analysis.
 *
 * @package Floating_Labels_For_CF7
 */

// Plugin Floating Labels for CF7 (defined in plugin/floating-labels-for-cf7.php).
if ( ! defined( 'JGOR_FLCF7_VERSION' ) ) {
	define( 'JGOR_FLCF7_VERSION', '1.0.0' );
}
if ( ! defined( 'JGOR_FLCF7_MIN_PHP' ) ) {
	define( 'JGOR_FLCF7_MIN_PHP', '8.1' );
}
if ( ! defined( 'JGOR_FLCF7_FILE' ) ) {
	define( 'JGOR_FLCF7_FILE', __DIR__ . '/plugin/floating-labels-for-cf7.php' );
}
if ( ! defined( 'JGOR_FLCF7_PATH' ) ) {
	define( 'JGOR_FLCF7_PATH', __DIR__ . '/plugin/' );
}
if ( ! defined( 'JGOR_FLCF7_URL' ) ) {
	define( 'JGOR_FLCF7_URL', 'https://example.local/wp-content/plugins/floating-labels-for-cf7/' );
}

// Contact Form 7 constants the plugin reads (defined by Contact Form 7).
if ( ! defined( 'WPCF7_VERSION' ) ) {
	define( 'WPCF7_VERSION', '6.2.2' );
}

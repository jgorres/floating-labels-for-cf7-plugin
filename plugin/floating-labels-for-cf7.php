<?php
/**
 * Plugin Name:       Floating Labels for CF7
 * Plugin URI:        https://github.com/jgorres/floating-labels-for-cf7-plugin
 * Description:       Turns placeholders and labels of Contact Form 7 fields into floating labels. Pure CSS, no JavaScript.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  contact-form-7
 * Author:            Jörn Gorres
 * Author URI:        https://joern.gorres.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       floating-labels-for-cf7
 * Domain Path:       /languages
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 *
 * JGOR_FLCF7_VERSION must be kept in sync with the "Version" header above on
 * every release; build.sh refuses to package the plugin otherwise.
 */
define( 'JGOR_FLCF7_VERSION', '1.0.0' );
define( 'JGOR_FLCF7_MIN_PHP', '8.1' );
define( 'JGOR_FLCF7_FILE', __FILE__ );
define( 'JGOR_FLCF7_PATH', plugin_dir_path( __FILE__ ) );
define( 'JGOR_FLCF7_URL', plugin_dir_url( __FILE__ ) );

/**
 * Includes the class files from includes/.
 *
 * Every file holds exactly one class and covers one module of the concept.
 * The file_exists() check keeps a partial deployment from fataling the site
 * and lets the bootstrap start only the modules that are present.
 *
 * @return void
 */
function jgor_flcf7_includes() {
	$files = array(
		'class-jgor-flcf7-settings.php',
		'class-jgor-flcf7-markup.php',
		'class-jgor-flcf7-assets.php',
		'class-jgor-flcf7-admin.php',
		'class-jgor-flcf7-editor.php',
		'class-jgor-flcf7-placement-design.php',
		'class-jgor-flcf7-samples.php',
		'class-jgor-flcf7-form-panel.php',
		'class-jgor-flcf7-plugin.php',
	);

	foreach ( $files as $file ) {
		$path = JGOR_FLCF7_PATH . 'includes/' . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
jgor_flcf7_includes();

/**
 * Starts the plugin once all plugins are loaded.
 *
 * Contact Form 7 loads its classes while its main file is included, so on
 * "plugins_loaded" the check for WPCF7_ContactForm is reliable regardless of
 * the plugin order. Priority 20 runs after Contact Form 7's own hook on
 * priority 10.
 *
 * @return void
 */
function jgor_flcf7_boot() {
	if ( class_exists( 'JGOR_FLCF7_Plugin' ) ) {
		JGOR_FLCF7_Plugin::instance()->init();
	}
}
add_action( 'plugins_loaded', 'jgor_flcf7_boot', 20 );

/**
 * Runs on plugin activation.
 *
 * WordPress already honours the "Requires PHP" header, the explicit check is a
 * second layer for installations that bypass the plugin screen (WP-CLI, code).
 * The dependency on Contact Form 7 is enforced by the "Requires Plugins"
 * header; without it the plugin shows an admin notice (see the bootstrap).
 *
 * @return void
 */
function jgor_flcf7_activate() {
	if ( version_compare( PHP_VERSION, JGOR_FLCF7_MIN_PHP, '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html(
				sprintf(
					/* translators: %s: required PHP version */
					__( 'This plugin requires PHP %s or newer.', 'floating-labels-for-cf7' ),
					JGOR_FLCF7_MIN_PHP
				)
			),
			esc_html__( 'Plugin activation stopped', 'floating-labels-for-cf7' ),
			array( 'back_link' => true )
		);
	}

	if ( class_exists( 'JGOR_FLCF7_Settings' ) ) {
		JGOR_FLCF7_Settings::instance()->ensure_option();
	}

	// Asks once, on the next admin screen, whether to create the sample page.
	if ( class_exists( 'JGOR_FLCF7_Samples' ) ) {
		set_transient( JGOR_FLCF7_Samples::TRANSIENT, 1, HOUR_IN_SECONDS );
	}
}
register_activation_hook( __FILE__, 'jgor_flcf7_activate' );

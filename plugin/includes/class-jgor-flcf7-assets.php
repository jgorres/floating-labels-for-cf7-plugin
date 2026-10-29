<?php
/**
 * Module 3: loads the front-end stylesheet and the design values.
 *
 * The stylesheet follows the loading policy of Contact Form 7: it is enqueued
 * from the action wpcf7_enqueue_styles, which Contact Form 7 fires when it
 * loads its own stylesheet (wpcf7_load_css(), constant WPCF7_LOAD_CSS, or a
 * manual call of wpcf7_enqueue_styles() in the template). The dependency on
 * the handle "contact-form-7" keeps the plugin CSS behind the Contact Form 7
 * CSS in the output order.
 *
 * Design values changed on the settings screen are printed as one short
 * inline style right after the stylesheet: a .wpcf7 rule with only the custom
 * properties that differ from the defaults. Nothing is printed while every
 * value is the default.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the floating-label stylesheet.
 */
final class JGOR_FLCF7_Assets {

	/**
	 * Style handle.
	 */
	public const HANDLE = 'jgor-flcf7';

	/**
	 * Path of the stylesheet below the plugin directory.
	 */
	private const STYLE_PATH = 'assets/css/floating-labels.css';

	/**
	 * Plugin settings.
	 *
	 * @var JGOR_FLCF7_Settings
	 */
	private JGOR_FLCF7_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param JGOR_FLCF7_Settings $settings Plugin settings.
	 */
	public function __construct( JGOR_FLCF7_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers the action.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpcf7_enqueue_styles', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the stylesheet and the changed design values unless the
	 * setting "load_css" is off.
	 *
	 * Right-to-left scripts need no separate file: the stylesheet uses
	 * logical properties throughout.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->settings->get( 'load_css' ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			JGOR_FLCF7_URL . self::STYLE_PATH,
			array( 'contact-form-7' ),
			JGOR_FLCF7_Plugin::asset_version( self::STYLE_PATH )
		);

		$css = $this->inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( self::HANDLE, $css );
		}
	}

	/**
	 * Builds the inline style with the design values that differ from the
	 * defaults, or '' when every value is the default.
	 *
	 * Every value passed the sanitizing of the settings (a strict pattern per
	 * type), so the rule can be printed as is. Printed right after the
	 * stylesheet, it overrides the defaults of the .wpcf7 rule.
	 *
	 * @return string CSS rule, or ''.
	 */
	public function inline_css(): string {
		$overrides = $this->settings->design_overrides();
		if ( empty( $overrides ) ) {
			return '';
		}

		$declarations = array();
		foreach ( $overrides as $key => $value ) {
			$declarations[] = JGOR_FLCF7_Settings::PROPERTY_PREFIX . $key . ': ' . $value . ';';
		}

		return '.wpcf7 { ' . implode( ' ', $declarations ) . ' }';
	}
}

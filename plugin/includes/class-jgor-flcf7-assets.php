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
 * Contact Form 7 loads its stylesheet on every page. With the setting
 * "load_css_forms_only" the plugin stylesheet is enqueued only on single
 * posts and pages whose content holds the Contact Form 7 block or shortcode.
 * Forms rendered elsewhere (widgets, template parts, archives) are caught by
 * a safety net: module 2 calls ensure_enqueued() after it rebuilt a form, and
 * WordPress then prints the stylesheet late, in the footer.
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
	 * Handle of the Contact Form 7 stylesheet the plugin CSS follows.
	 */
	private const CF7_HANDLE = 'contact-form-7';

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
	 * setting "load_css" is off or, with "load_css_forms_only", the queried
	 * single post or page holds no form.
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

		if ( $this->settings->get( 'load_css_forms_only' ) && ! $this->page_has_form() ) {
			return;
		}

		$this->enqueue_style();
	}

	/**
	 * Safety net of "load_css_forms_only": enqueues the stylesheet when a form
	 * was rebuilt on a page the detection in enqueue() did not cover.
	 *
	 * Module 2 calls this right after a rebuild, usually after the head was
	 * printed; WordPress then prints the stylesheet in the footer. Nothing
	 * happens while the setting is off (the stylesheet follows the Contact
	 * Form 7 policy and is on the page already), while "load_css" is off, or
	 * while Contact Form 7 does not load CSS on this site at all. Not in the
	 * admin, where Contact Form 7 renders forms for previews.
	 *
	 * @return void
	 */
	public function ensure_enqueued(): void {
		if ( is_admin() || wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		if ( ! $this->settings->get( 'load_css' ) || ! $this->settings->get( 'load_css_forms_only' ) ) {
			return;
		}

		if ( ! function_exists( 'wpcf7_load_css' ) || ! wpcf7_load_css() ) {
			return;
		}

		$this->enqueue_style();
	}

	/**
	 * Whether the queried single post or page holds a Contact Form 7 block or
	 * shortcode in its content.
	 *
	 * A Shortcode block with [contact-form-7] counts too: the shortcode text
	 * is part of the content. Forms in widgets, template parts or archives
	 * are not detected here; the safety net ensure_enqueued() covers them.
	 *
	 * @return bool
	 */
	private function page_has_form(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( (string) $post->post_content, 'contact-form-7' )
			|| has_block( 'contact-form-7/contact-form-selector', $post );
	}

	/**
	 * Enqueues the stylesheet with the inline style of the changed design
	 * values after it.
	 *
	 * The dependency on the Contact Form 7 stylesheet is declared only while
	 * that handle is registered: a dependency that is not registered would
	 * keep the plugin stylesheet from being printed at all.
	 *
	 * @return void
	 */
	private function enqueue_style(): void {
		wp_enqueue_style(
			self::HANDLE,
			JGOR_FLCF7_URL . self::STYLE_PATH,
			wp_style_is( self::CF7_HANDLE, 'registered' ) ? array( self::CF7_HANDLE ) : array(),
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

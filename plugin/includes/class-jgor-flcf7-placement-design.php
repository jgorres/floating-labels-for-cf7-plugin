<?php
/**
 * Module 5, server side of the design values per placement.
 *
 * The block editor panels (assets/js/block-editor.js) store colours, sizes
 * and timing for one placement of a form: in the Contact Form 7 block as the
 * block attribute jgorFlcf7Design, declared here through the filter
 * register_block_type_args so the editor serialises it (the shortcode the
 * block saves stays untouched, and the content stays valid without the
 * plugin); in a Shortcode block or a hand-written shortcode as the attribute
 * jgor_flcf7_design="label-color:#123456;font-size:1.1em".
 *
 * At render time the block attribute is carried into the shortcode text of
 * the block (filter render_block_{name}, which runs before shortcodes are
 * processed; nothing is saved), and the shortcode attribute becomes a style
 * attribute on the wrapper div.wpcf7 (filter do_shortcode_tag): one CSS
 * custom property per value, exactly where the stylesheet sets its defaults.
 * Every value passes JGOR_FLCF7_Settings::sanitize_design_value(), invalid
 * or unknown values are dropped, and nothing is printed while the stylesheet
 * is switched off (setting load_css).
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declares, carries and prints the design values of one placement.
 */
final class JGOR_FLCF7_Placement_Design {

	/**
	 * Name of the Contact Form 7 block.
	 */
	public const BLOCK_NAME = 'contact-form-7/contact-form-selector';

	/**
	 * Block attribute that holds the design values (object property => value).
	 */
	public const BLOCK_ATTRIBUTE = 'jgorFlcf7Design';

	/**
	 * Shortcode attribute that holds the design values ("key:value;key:value").
	 */
	public const SHORTCODE_ATTRIBUTE = 'jgor_flcf7_design';

	/**
	 * Shortcode tag of Contact Form 7.
	 */
	private const SHORTCODE_TAG = 'contact-form-7';

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
	 * Registers the filters.
	 *
	 * The filter register_block_type_args runs when Contact Form 7 registers
	 * its block on "init"; the plugin boots on "plugins_loaded", so the
	 * filter is in place.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'register_block_type_args', array( $this, 'register_block_attribute' ), 10, 2 );
		add_filter( 'render_block_' . self::BLOCK_NAME, array( $this, 'filter_block' ), 10, 2 );
		add_filter( 'do_shortcode_tag', array( $this, 'filter_shortcode_output' ), 10, 3 );
	}

	/**
	 * Declares the block attribute on the Contact Form 7 block.
	 *
	 * Server-side attributes reach the editor through
	 * get_block_editor_server_block_settings(); the block registers itself on
	 * the client without an attribute list of its own, so the declaration
	 * here is what the editor uses to serialise the attribute.
	 *
	 * @param mixed $args       Block type arguments.
	 * @param mixed $block_name Block name.
	 * @return mixed Arguments, with the attribute added for the Contact Form 7 block.
	 */
	public function register_block_attribute( $args, $block_name ) {
		if ( self::BLOCK_NAME !== $block_name || ! is_array( $args ) ) {
			return $args;
		}

		$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
		if ( ! isset( $attributes[ self::BLOCK_ATTRIBUTE ] ) ) {
			$attributes[ self::BLOCK_ATTRIBUTE ] = array( 'type' => 'object' );
		}
		$args['attributes'] = $attributes;

		return $args;
	}

	/**
	 * Carries the design values of a Contact Form 7 block into its shortcode
	 * text, as the shortcode attribute, for this rendering only.
	 *
	 * The block has no server-side renderer, so the filter sees the saved
	 * content with the shortcode still unprocessed. A shortcode that already
	 * carries the attribute is left alone.
	 *
	 * @param mixed $content Rendered block content.
	 * @param mixed $block   Parsed block (attrs, innerHTML, ...).
	 * @return mixed Content with the attribute added to the first shortcode.
	 */
	public function filter_block( $content, $block ) {
		if ( ! is_string( $content ) || ! is_array( $block ) || ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			return $content;
		}

		$design = self::sanitize( $block['attrs'][ self::BLOCK_ATTRIBUTE ] ?? null );
		if ( empty( $design ) ) {
			return $content;
		}

		// The serialised values contain only characters the sanitizing
		// allows (letters, digits, "#", ".", "%", ":" and ";").
		$attribute = ' ' . self::SHORTCODE_ATTRIBUTE . '="' . self::serialize( $design ) . '"';
		$result    = preg_replace(
			'/\[' . self::SHORTCODE_TAG . '(?=[\s\]\/])(?![^\]]*\b' . self::SHORTCODE_ATTRIBUTE . '=)/',
			'[' . self::SHORTCODE_TAG . $attribute,
			$content,
			1
		);

		return is_string( $result ) ? $result : $content;
	}

	/**
	 * Prints the design values of a placement as custom properties on the
	 * wrapper div.wpcf7 of the rendered form.
	 *
	 * @param mixed $output Shortcode output.
	 * @param mixed $tag    Shortcode tag.
	 * @param mixed $attr   Shortcode attributes as parsed by WordPress (names lower-cased), or '' without attributes.
	 * @return mixed Output with the style attribute, or the original output.
	 */
	public function filter_shortcode_output( $output, $tag, $attr ) {
		if ( self::SHORTCODE_TAG !== $tag || ! is_string( $output ) || ! is_array( $attr ) || ! isset( $attr[ self::SHORTCODE_ATTRIBUTE ] ) ) {
			return $output;
		}

		if ( ! $this->settings->get( 'load_css' ) ) {
			return $output;
		}

		$design = self::parse( $attr[ self::SHORTCODE_ATTRIBUTE ] );
		if ( empty( $design ) ) {
			return $output;
		}

		// The first div.wpcf7 is the wrapper Contact Form 7 prints; the tag
		// processor finds it whatever the order or quoting of its attributes,
		// escapes the attribute value itself and puts the new attribute right
		// after the tag name.
		$processor = new WP_HTML_Tag_Processor( $output );
		if ( $processor->next_tag(
			array(
				'tag_name'   => 'DIV',
				'class_name' => 'wpcf7',
			)
		) ) {
			$processor->set_attribute( 'style', self::style( $design ) );

			return $processor->get_updated_html();
		}

		return $output;
	}

	/**
	 * Parses the shortcode attribute "key:value;key:value".
	 *
	 * @param mixed $raw Attribute value.
	 * @return array<string, string> Clean values, see sanitize().
	 */
	public static function parse( $raw ): array {
		if ( ! is_string( $raw ) ) {
			return array();
		}

		$values = array();
		foreach ( explode( ';', $raw ) as $pair ) {
			$parts = explode( ':', $pair, 2 );
			if ( 2 === count( $parts ) ) {
				$values[ trim( $parts[0] ) ] = $parts[1];
			}
		}

		return self::sanitize( $values );
	}

	/**
	 * Keeps only known design properties with valid values, in the order of
	 * the stylesheet.
	 *
	 * @param mixed $values Array property => raw value (block attribute or parsed shortcode attribute).
	 * @return array<string, string>
	 */
	public static function sanitize( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_keys( JGOR_FLCF7_Settings::DESIGN_TYPES ) as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$value = JGOR_FLCF7_Settings::sanitize_design_value( $key, $values[ $key ] );
			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Serialises clean design values as "key:value;key:value".
	 *
	 * @param array<string, string> $design Clean values, see sanitize().
	 * @return string
	 */
	public static function serialize( array $design ): string {
		$pairs = array();
		foreach ( $design as $key => $value ) {
			$pairs[] = $key . ':' . $value;
		}

		return implode( ';', $pairs );
	}

	/**
	 * Builds the value of the style attribute: one custom property per value.
	 *
	 * @param array<string, string> $design Clean values, see sanitize().
	 * @return string
	 */
	public static function style( array $design ): string {
		$declarations = array();
		foreach ( $design as $key => $value ) {
			$declarations[] = JGOR_FLCF7_Settings::declaration( $key, $value );
		}

		return implode( ' ', $declarations );
	}
}

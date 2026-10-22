<?php
/**
 * Module 5: the panels "Floating Labels" in the sidebar of the Contact Form 7
 * block and of a Shortcode block with the shortcode.
 *
 * Enqueues a small editor script (assets/js/block-editor.js, no build step)
 * on enqueue_block_editor_assets. The script hooks into the filter
 * editor.BlockEdit and adds the panel "Floating Labels" with four selects
 * (on/off, label source, variant, required marker), stored as placement
 * classes of the concept (section 7.4) in the block attribute htmlClass or
 * the shortcode attribute html_class, and the panels "Floating labels:
 * colours", "sizes" and "animation" with the design values of this
 * placement, stored in the block attribute jgorFlcf7Design or the shortcode
 * attribute jgor_flcf7_design (server side: JGOR_FLCF7_Placement_Design).
 *
 * The list of design properties (key, group, type, translated label and the
 * value in effect on the settings screen as placeholder) is handed to the
 * script as window.jgorFlcf7EditorData, so labels and defaults live in PHP
 * only.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the block editor script.
 */
final class JGOR_FLCF7_Editor {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'jgor-flcf7-editor';

	/**
	 * Name of the Contact Form 7 block the panel is added to.
	 */
	public const BLOCK_NAME = 'contact-form-7/contact-form-selector';

	/**
	 * Script dependencies: the WordPress packages the script reads from the
	 * global "wp" object.
	 *
	 * @var string[]
	 */
	public const DEPENDENCIES = array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' );

	/**
	 * Path of the script below the plugin directory.
	 */
	private const SCRIPT_PATH = 'assets/js/block-editor.js';

	/**
	 * Global the script reads its data from.
	 */
	public const DATA_GLOBAL = 'jgorFlcf7EditorData';

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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the script when the Contact Form 7 block is registered.
	 *
	 * Without the block (an older Contact Form 7, or the block removed by
	 * code) the panel would have nothing to attach to, so nothing is loaded.
	 * Translations of the script come from the languages directory of the
	 * plugin or from the language packs of translate.wordpress.org.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			JGOR_FLCF7_URL . self::SCRIPT_PATH,
			self::DEPENDENCIES,
			JGOR_FLCF7_VERSION,
			array( 'in_footer' => true )
		);

		wp_set_script_translations( self::HANDLE, 'floating-labels-for-cf7', JGOR_FLCF7_PATH . 'languages' );

		$json = wp_json_encode( $this->inline_data() );
		if ( is_string( $json ) ) {
			wp_add_inline_script( self::HANDLE, 'window.' . self::DATA_GLOBAL . ' = ' . $json . ';', 'before' );
		}
	}

	/**
	 * Data for the script: the design properties as the panels need them.
	 *
	 * One entry per CSS custom property, in the order of the rows of the
	 * settings screen (JGOR_FLCF7_Admin::DESIGN_GROUPS): key without prefix,
	 * group ("colors", "sizes", "motion"), value type, translated label and
	 * the value in effect on the settings screen, shown as placeholder.
	 *
	 * @return array{design: array<int, array{key: string, group: string, type: string, label: string, placeholder: string}>}
	 */
	public function inline_data(): array {
		$effective = $this->settings->design();
		$design    = array();

		foreach ( JGOR_FLCF7_Admin::DESIGN_GROUPS as $group => $keys ) {
			foreach ( $keys as $key ) {
				$design[] = array(
					'key'         => $key,
					'group'       => (string) substr( $group, strlen( 'design_' ) ),
					'type'        => JGOR_FLCF7_Settings::DESIGN_TYPES[ $key ],
					'label'       => JGOR_FLCF7_Admin::design_label( $key ),
					'placeholder' => $effective[ $key ] ?? '',
				);
			}
		}

		return array( 'design' => $design );
	}
}

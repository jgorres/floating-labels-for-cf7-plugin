<?php
/**
 * Module 5: the panel "Floating Labels" in the sidebar of the Contact Form 7 block.
 *
 * Enqueues a small editor script (assets/js/block-editor.js, no build step)
 * on enqueue_block_editor_assets. The script hooks into the filter
 * editor.BlockEdit and adds a panel to the Contact Form 7 block with four
 * selects: on/off, label source, variant and required marker. Every choice is
 * stored as one of the placement classes of the concept (section 7.4) in the
 * block attribute htmlClass, the attribute the block itself shows as "Class"
 * in its panel "Form attributes". Nothing is stored anywhere else: the server
 * side keeps reading html_class, and without the plugin the classes are
 * harmless.
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
	}
}

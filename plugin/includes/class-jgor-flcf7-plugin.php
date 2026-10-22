<?php
/**
 * Bootstrap: checks the requirements and wires the modules to WordPress.
 *
 * Every module is a class of its own in includes/. This class decides which
 * of them run and hands them the settings: the markup rebuild (module 2) and
 * the stylesheet (module 3) on every request, the settings screen (module 4)
 * and the panel in the Contact Form 7 block (module 5) in the admin only.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starts the modules of the plugin.
 */
final class JGOR_FLCF7_Plugin {

	/**
	 * Single instance.
	 *
	 * @var JGOR_FLCF7_Plugin|null
	 */
	private static ?JGOR_FLCF7_Plugin $instance = null;

	/**
	 * Guards against a second init() call.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Requirements found missing by init(), see missing_requirements().
	 *
	 * @var string[]
	 */
	private array $missing = array();

	/**
	 * Returns the single instance.
	 *
	 * @return JGOR_FLCF7_Plugin
	 */
	public static function instance(): JGOR_FLCF7_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Checks the requirements and starts the modules.
	 *
	 * The settings option is always registered on "init" so its sanitize
	 * callback also applies to update_option() in code and WP-CLI. Without
	 * Contact Form 7 or the DOM extension the plugin only shows an admin
	 * notice: there is nothing to rebuild, and the CSS stays unloaded because
	 * it would have nothing to style.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		add_action( 'init', array( $this, 'register_setting' ) );

		$this->missing = $this->missing_requirements();

		if ( ! empty( $this->missing ) ) {
			add_action( 'admin_notices', array( $this, 'render_notice' ) );
			return;
		}

		$settings = JGOR_FLCF7_Settings::instance();

		// Module 2: markup rebuild. Runs on the front end and in the admin
		// alike, because Contact Form 7 renders previews in the admin too.
		( new JGOR_FLCF7_Markup( $settings ) )->init();

		// Module 3: stylesheet, enqueued from wpcf7_enqueue_styles.
		( new JGOR_FLCF7_Assets( $settings ) )->init();

		// Module 4: settings screen and plugin action link; module 5: the
		// panel in the sidebar of the Contact Form 7 block. Admin only.
		if ( is_admin() ) {
			( new JGOR_FLCF7_Admin( $settings ) )->init();
			( new JGOR_FLCF7_Editor() )->init();
		}
	}

	/**
	 * Returns the requirements that are not met.
	 *
	 * Keys: "cf7" when Contact Form 7 is not active, "dom" when the PHP
	 * extension DOM (DOMDocument) is missing. An empty array means the plugin
	 * can work.
	 *
	 * @return string[]
	 */
	public function missing_requirements(): array {
		$missing = array();

		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			$missing[] = 'cf7';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			$missing[] = 'dom';
		}

		return $missing;
	}

	/**
	 * Prints one admin notice per missing requirement.
	 *
	 * Only users who can manage plugins see the notices; everybody else can
	 * do nothing about them.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$messages = array(
			'cf7' => __( 'Floating Labels for CF7 requires the plugin Contact Form 7. Install and activate Contact Form 7 to use floating labels.', 'floating-labels-for-cf7' ),
			'dom' => __( 'Floating Labels for CF7 requires the PHP extension DOM (ext-dom), which is missing on this server. Forms are rendered without floating labels.', 'floating-labels-for-cf7' ),
		);

		foreach ( $this->missing as $key ) {
			if ( ! isset( $messages[ $key ] ) ) {
				continue;
			}

			wp_admin_notice(
				esc_html( $messages[ $key ] ),
				array(
					'type'               => 'error',
					'additional_classes' => array( 'jgor-flcf7-notice' ),
				)
			);
		}
	}

	/**
	 * Registers the settings option with its sanitize callback.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			JGOR_FLCF7_Settings::GROUP,
			JGOR_FLCF7_Settings::OPTION,
			array(
				'type'              => 'array',
				'description'       => __( 'Settings of Floating Labels for CF7.', 'floating-labels-for-cf7' ),
				'sanitize_callback' => array( JGOR_FLCF7_Settings::instance(), 'sanitize' ),
				'default'           => JGOR_FLCF7_Settings::instance()->defaults(),
				'show_in_rest'      => false,
			)
		);
	}
}

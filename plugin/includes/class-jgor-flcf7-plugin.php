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

		// Module 3: stylesheet, enqueued from wpcf7_enqueue_styles; module 2
		// calls its safety net after a rebuild (setting "load_css_forms_only").
		$assets = new JGOR_FLCF7_Assets( $settings );
		$assets->init();

		// Module 2: markup rebuild. Runs on the front end and in the admin
		// alike, because Contact Form 7 renders previews in the admin too.
		( new JGOR_FLCF7_Markup( $settings, $assets ) )->init();

		// Module 5, server side: declares the block attribute of the design
		// values per placement and prints them on the rendered form. Runs
		// everywhere, like module 2.
		( new JGOR_FLCF7_Placement_Design( $settings ) )->init();

		// Module 4: settings screen and plugin action link; module 5: the
		// panels in the sidebar of the Contact Form 7 block. Admin only.
		if ( is_admin() ) {
			( new JGOR_FLCF7_Admin( $settings ) )->init();
			( new JGOR_FLCF7_Editor( $settings ) )->init();

			// Module 6: the sample page (box on the settings screen, notice
			// after the activation, admin-post handler).
			( new JGOR_FLCF7_Samples() )->init();

			// Module 7: the tab "Floating labels" in the Contact Form 7 form
			// editor, writing the per-form lines of the additional settings.
			( new JGOR_FLCF7_Form_Panel( $settings ) )->init();
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
	/**
	 * Returns the version string for an enqueued asset of the plugin: the
	 * plugin version plus the modification time of the file, so browsers
	 * and caches fetch a changed file even while the plugin version stays.
	 *
	 * @param string $relative_path Path below the plugin directory, e.g. assets/css/floating-labels.css.
	 * @return string JGOR_FLCF7_VERSION, with ".<timestamp>" appended when the file is readable.
	 */
	public static function asset_version( string $relative_path ): string {
		$file = JGOR_FLCF7_PATH . $relative_path;
		$time = file_exists( $file ) ? filemtime( $file ) : false;

		return false === $time ? JGOR_FLCF7_VERSION : JGOR_FLCF7_VERSION . '.' . $time;
	}
}

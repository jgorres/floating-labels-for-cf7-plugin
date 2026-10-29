<?php
/**
 * Module 4: the settings screen.
 *
 * Adds a submenu "Floating Labels" to the Contact Form 7 menu and renders the
 * plugin settings with the WordPress Settings API. The option itself is
 * registered by the bootstrap (register_setting() with the sanitize callback
 * of JGOR_FLCF7_Settings), so saving goes through options.php with its nonce
 * and capability checks.
 *
 * Settings fixed in code through the filter jgor_flcf7_settings are shown
 * disabled and without a name attribute: they are not submitted, and the
 * sanitize callback keeps their stored value.
 *
 * The section "Design" offers one field per CSS custom property of the
 * stylesheet (colours with the WordPress colour picker, sizes, scale,
 * durations); an empty field keeps the stylesheet default. Module 3 prints
 * only the changed values. A read-only snippet with the values in effect
 * stays on the screen for themes (concept, section 9). The "Help" tab of the
 * screen repeats the control layers and the design notes as contextual help.
 *
 * Groups of small controls (the field types, the colours, the sizes, the
 * floated-label values, the focus effect values) are laid out in columns of
 * equal width by the stylesheet assets/css/admin.css, loaded on this screen
 * only together with the colour picker script.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and wires the settings screen.
 */
final class JGOR_FLCF7_Admin {

	/**
	 * Slug of the settings page.
	 */
	public const PAGE_SLUG = 'wpcf7-floating-labels';

	/**
	 * Handle of the settings screen script (colour picker) and stylesheet.
	 */
	public const SCRIPT_HANDLE = 'jgor-flcf7-admin';

	/**
	 * Path of the settings screen script below the plugin directory.
	 */
	private const SCRIPT_PATH = 'assets/js/admin.js';

	/**
	 * Path of the settings screen stylesheet below the plugin directory.
	 */
	private const STYLE_PATH = 'assets/css/admin.css';

	/**
	 * Class of the fieldsets the stylesheet lays out in columns.
	 */
	public const COLUMNS_CLASS = 'jgor-flcf7-columns';

	/**
	 * Modifier class that lets a column group grow to four columns; the
	 * other groups stop at three.
	 */
	public const COLUMNS_FOUR_CLASS = 'jgor-flcf7-columns--4';

	/**
	 * Design groups laid out in up to four columns (one per property).
	 *
	 * @var string[]
	 */
	private const FOUR_COLUMN_GROUPS = array( 'design_effect' );

	/**
	 * Parent menu slugs of Contact Form 7, newest first: "wpcf7-dashboard"
	 * since the dashboard was introduced, "wpcf7" before. The first one that
	 * is registered wins; without either, the page goes under Settings.
	 *
	 * @var string[]
	 */
	private const PARENT_SLUGS = array( 'wpcf7-dashboard', 'wpcf7' );

	/**
	 * Parent used when no Contact Form 7 menu is registered.
	 */
	private const FALLBACK_PARENT = 'options-general.php';

	/**
	 * Design properties per field row of the section "Design", keyed by the
	 * "key" argument of the row. Every property of
	 * JGOR_FLCF7_Settings::DESIGN_TYPES appears exactly once.
	 *
	 * @var array<string, string[]>
	 */
	public const DESIGN_GROUPS = array(
		'design_colors' => array( 'label-color', 'label-color-active', 'label-color-error', 'border-color', 'border-color-active', 'border-color-error', 'background', 'background-filled', 'submit-error-background', 'submit-error-color' ),
		'design_sizes'  => array( 'font-size', 'label-size', 'padding-x', 'padding-y', 'border-width', 'radius' ),
		'design_motion' => array( 'label-scale', 'duration' ),
		'design_effect' => array( 'effect-color', 'effect-size', 'effect-width', 'effect-duration' ),
	);

	/**
	 * Plugin settings.
	 *
	 * @var JGOR_FLCF7_Settings
	 */
	private JGOR_FLCF7_Settings $settings;

	/**
	 * Parent slug the page was added to, empty before add_menu() ran.
	 *
	 * @var string
	 */
	private string $parent = '';

	/**
	 * Hook suffix of the page, empty before add_menu() ran.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Constructor.
	 *
	 * @param JGOR_FLCF7_Settings $settings Plugin settings.
	 */
	public function __construct( JGOR_FLCF7_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers the hooks.
	 *
	 * The action admin_menu at priority 20 runs after Contact Form 7 registers
	 * its menu (priority 9), so the parent exists when the submenu is added.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( JGOR_FLCF7_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the submenu page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$this->parent = $this->parent_slug();

		$hook = add_submenu_page(
			$this->parent,
			__( 'Floating Labels for CF7', 'floating-labels-for-cf7' ),
			__( 'Floating Labels', 'floating-labels-for-cf7' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( is_string( $hook ) && '' !== $hook ) {
			$this->hook = $hook;
			add_action( 'load-' . $hook, array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Enqueues the colour picker, the screen script and the screen
	 * stylesheet on the settings page only.
	 *
	 * @param string $hook_suffix Hook suffix of the current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			JGOR_FLCF7_URL . self::SCRIPT_PATH,
			array( 'wp-color-picker' ),
			JGOR_FLCF7_VERSION,
			array( 'in_footer' => true )
		);
		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			JGOR_FLCF7_URL . self::STYLE_PATH,
			array( 'wp-color-picker' ),
			JGOR_FLCF7_VERSION
		);
	}

	/**
	 * Adds the contextual help ("Help" tab) of the settings page.
	 *
	 * Runs on load-{hook} of the page, when the current screen exists.
	 *
	 * @return void
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'jgor-flcf7-help-overview',
				'title'   => __( 'Overview', 'floating-labels-for-cf7' ),
				'content' => $this->help_overview(),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'jgor-flcf7-help-controls',
				'title'   => __( 'Per form and placement', 'floating-labels-for-cf7' ),
				'content' => $this->help_controls(),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'jgor-flcf7-help-design',
				'title'   => __( 'Design', 'floating-labels-for-cf7' ),
				'content' => $this->help_design(),
			)
		);
		$screen->set_help_sidebar( $this->help_sidebar() );
	}

	/**
	 * Content of the help tab "Overview".
	 *
	 * @return string HTML.
	 */
	private function help_overview(): string {
		return '<p>' . esc_html__( 'A floating label sits inside the empty field like a placeholder and moves to the top edge, smaller, as soon as the field is focused or filled. The plugin rebuilds the markup Contact Form 7 renders and ships the stylesheet; your pages need no JavaScript for it.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p>' . esc_html__( 'Text, email, URL, telephone, number and textarea fields get floating labels. Checkboxes, radio buttons, selects, file uploads, quizzes and CAPTCHAs keep their original markup.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p>' . esc_html__( 'The label text comes from the form template (a <label> that encloses the field or points at its id) or from the placeholder option of the form-tag. A label in the template wins over a placeholder. With the option "Text before the field", loose text in front of the field, or in the paragraph just before it, is used when there is neither; that covers the default template of Contact Form 7 before version 5.4.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p>' . esc_html__( 'Fields the plugin does not recognise are left exactly as Contact Form 7 renders them.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p>' . esc_html__( 'The box "Sample page" at the top of this screen creates a private page with forms that show the options, and removes it again together with its forms.', 'floating-labels-for-cf7' ) . '</p>';
	}

	/**
	 * Content of the help tab "Per form and placement".
	 *
	 * @return string HTML.
	 */
	private function help_controls(): string {
		return '<p>' . esc_html__( 'The settings of this screen apply to every form. Override them per form, per placement or per field; later layers win.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p><strong>' . esc_html__( 'Per form', 'floating-labels-for-cf7' ) . '</strong><br>' . esc_html__( 'Add lines to the "Additional Settings" tab of the form:', 'floating-labels-for-cf7' ) . '</p>'
			. '<pre><code>' . esc_html( $this->example_additional_settings() ) . '</code></pre>'
			. '<p><strong>' . esc_html__( 'Per placement', 'floating-labels-for-cf7' ) . '</strong><br>' . esc_html( $this->text_per_placement() ) . '</p>'
			. '<pre><code>' . esc_html( $this->example_html_classes() ) . '</code></pre>'
			. '<p><strong>' . esc_html__( 'Per field', 'floating-labels-for-cf7' ) . '</strong><br>' . esc_html__( 'Add the option nofloat to the form-tag, before any quoted value:', 'floating-labels-for-cf7' ) . '</p>'
			. '<pre><code>' . esc_html( $this->example_nofloat() ) . '</code></pre>';
	}

	/**
	 * Content of the help tab "Design".
	 *
	 * @return string HTML.
	 */
	private function help_design(): string {
		return '<p>' . esc_html__( 'Colours, sizes, timing and the look of the focus effect are set in the section "Design" of this screen. Leave a field empty to keep the default; only the values you change are printed, as CSS custom properties on the .wpcf7 wrapper, right after the stylesheet.', 'floating-labels-for-cf7' ) . '</p>'
			. '<p>' . esc_html__( 'Themes can set the same properties in CSS, for example in Appearance > Customize > Additional CSS or a child theme. The snippet shows every property with the value in effect:', 'floating-labels-for-cf7' ) . '</p>'
			. '<pre><code>' . esc_html( $this->css_snippet() ) . '</code></pre>'
			. '<p>' . esc_html__( 'To style one form only, replace .wpcf7 with the id of its wrapper, for example [id^="wpcf7-f123-"], or with a class you add through the html_class attribute.', 'floating-labels-for-cf7' ) . '</p>';
	}

	/**
	 * Sidebar of the help tabs: links to the plugin page and the Contact Form 7 docs.
	 *
	 * @return string HTML.
	 */
	private function help_sidebar(): string {
		return '<p><strong>' . esc_html__( 'For more information:', 'floating-labels-for-cf7' ) . '</strong></p>'
			. '<p><a href="' . esc_url( 'https://github.com/jgorres/floating-labels-for-cf7-plugin' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Plugin page and source code', 'floating-labels-for-cf7' ) . '</a></p>'
			. '<p><a href="' . esc_url( 'https://contactform7.com/additional-settings/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Contact Form 7: Additional Settings', 'floating-labels-for-cf7' ) . '</a></p>'
			. '<p><a href="' . esc_url( 'https://contactform7.com/text-fields/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Contact Form 7: form-tag options', 'floating-labels-for-cf7' ) . '</a></p>';
	}

	/**
	 * Text about the placement controls, shared by the screen and the help.
	 *
	 * @return string Plain text.
	 */
	private function text_per_placement(): string {
		return __( 'Select the Contact Form 7 block, or a Shortcode block with the [contact-form-7] shortcode, and open the panel "Floating labels" in the block sidebar. It stores the choice as classes in the "Class" field of the block (panel "Form attributes") or in the html_class attribute of the shortcode, so one form can look different on different pages. A reset button at the end of each panel takes the placement back to the settings of the site and of the form. The panels "Floating labels: colours", "Floating labels: sizes", "Floating labels: animation" and "Floating labels: focus effect" below it hold design values for this placement only, stored with the block or in the jgor_flcf7_design attribute of the shortcode:', 'floating-labels-for-cf7' );
	}

	/**
	 * Example lines for the "Additional Settings" tab of a form (concept 7.5).
	 *
	 * @return string Plain text.
	 */
	private function example_additional_settings(): string {
		return "floating_labels: off\nfloating_labels: placeholder\nfloating_labels: label\nfloating_labels: text\nfloating_labels_style: filled\nfloating_labels_effect: glow\nfloating_labels_marker: on\nfloating_labels_types: text email textarea";
	}

	/**
	 * Example classes for the html_class attribute and example design values
	 * for the jgor_flcf7_design attribute of a placement (concept 7.5, 10.1).
	 *
	 * @return string Plain text.
	 */
	private function example_html_classes(): string {
		return "jgor-flcf7-off  jgor-flcf7-on\njgor-flcf7-mode-placeholder  jgor-flcf7-mode-label  jgor-flcf7-mode-text\njgor-flcf7-style-outlined  jgor-flcf7-style-filled  jgor-flcf7-style-minimal\njgor-flcf7-effect-none  jgor-flcf7-effect-glow  jgor-flcf7-effect-pulse  jgor-flcf7-effect-sweep\njgor-flcf7-marker-on  jgor-flcf7-marker-off\n\n[contact-form-7 id=\"123\" html_class=\"jgor-flcf7-style-filled\"]\n[contact-form-7 id=\"123\" jgor_flcf7_design=\"border-color:#0a7d3e;font-size:1.1em\"]";
	}

	/**
	 * Example form-tag with the option nofloat (concept 7.5).
	 *
	 * @return string Plain text.
	 */
	private function example_nofloat(): string {
		return '[text* your-name nofloat placeholder "Your name"]';
	}

	/**
	 * Returns the parent slug for the submenu.
	 *
	 * @return string
	 */
	private function parent_slug(): string {
		global $admin_page_hooks;

		if ( is_array( $admin_page_hooks ) ) {
			foreach ( self::PARENT_SLUGS as $slug ) {
				if ( isset( $admin_page_hooks[ $slug ] ) ) {
					return $slug;
				}
			}
		}

		return self::FALLBACK_PARENT;
	}

	/**
	 * Returns the URL of the settings page.
	 *
	 * @return string
	 */
	public function page_url(): string {
		$url = menu_page_url( self::PAGE_SLUG, false );

		if ( '' === $url ) {
			$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		}

		return $url;
	}

	/**
	 * Adds a "Settings" link to the plugin row on the Plugins screen.
	 *
	 * @param mixed $links Existing action links.
	 * @return array<int|string, string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		array_unshift(
			$links,
			'<a href="' . esc_url( $this->page_url() ) . '">' . esc_html__( 'Settings', 'floating-labels-for-cf7' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Registers the sections and fields of the settings page.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		$page = self::PAGE_SLUG;

		add_settings_section( 'jgor_flcf7_sources', __( 'Label sources', 'floating-labels-for-cf7' ), array( $this, 'render_sources_intro' ), $page );
		add_settings_field( 'jgor_flcf7_mode_label', __( 'Form template labels', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_sources', $this->field_args( 'mode_label' ) );
		add_settings_field( 'jgor_flcf7_mode_placeholder', __( 'Placeholders', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_sources', $this->field_args( 'mode_placeholder' ) );
		add_settings_field( 'jgor_flcf7_mode_text', __( 'Text before the field', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_sources', $this->field_args( 'mode_text' ) );

		add_settings_section( 'jgor_flcf7_types', __( 'Field types', 'floating-labels-for-cf7' ), array( $this, 'render_types_intro' ), $page );
		add_settings_field( 'jgor_flcf7_types', __( 'Fields with floating labels', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_types', array( 'key' => 'types' ) );

		add_settings_section( 'jgor_flcf7_appearance', __( 'Appearance', 'floating-labels-for-cf7' ), '__return_null', $page );
		add_settings_field( 'jgor_flcf7_style', __( 'Variant', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', array( 'key' => 'style' ) );
		add_settings_field( 'jgor_flcf7_effect', __( 'Focus effect', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', array( 'key' => 'effect' ) );
		add_settings_field( 'jgor_flcf7_load_css', __( 'Stylesheet', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', $this->field_args( 'load_css' ) );
		add_settings_field( 'jgor_flcf7_required_marker', __( 'Required fields', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', $this->field_args( 'required_marker' ) );
		add_settings_field( 'jgor_flcf7_submit_error_text', __( 'Submit button on errors', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', $this->field_args( 'submit_error_text' ) );

		add_settings_section( 'jgor_flcf7_design', __( 'Design', 'floating-labels-for-cf7' ), array( $this, 'render_design_intro' ), $page );
		add_settings_field( 'jgor_flcf7_design_colors', __( 'Colours', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_design', array( 'key' => 'design_colors' ) );
		add_settings_field( 'jgor_flcf7_design_sizes', __( 'Sizes', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_design', array( 'key' => 'design_sizes' ) );
		add_settings_field( 'jgor_flcf7_design_motion', __( 'Floated label and animation', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_design', array( 'key' => 'design_motion' ) );
		add_settings_field( 'jgor_flcf7_design_effect', __( 'Glow and running light', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_design', array( 'key' => 'design_effect' ) );
		add_settings_field( 'jgor_flcf7_design_snippet', __( 'CSS for themes', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_design', array( 'key' => 'design_snippet' ) );

		add_settings_section( 'jgor_flcf7_controls', __( 'Per form, per placement, per field', 'floating-labels-for-cf7' ), array( $this, 'render_controls' ), $page );
	}

	/**
	 * Returns the arguments of add_settings_field() for a single-control field.
	 *
	 * "label_for" makes the row heading a <label> for the control.
	 *
	 * @param string $key Setting key.
	 * @return array<string, string>
	 */
	private function field_args( string $key ): array {
		return array(
			'key'       => $key,
			'label_for' => $this->field_id( $key ),
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'floating-labels-for-cf7' ), 403 );
		}

		// options.php redirects back with this flag; WordPress prints the
		// "Settings saved." notice itself only for pages under Settings.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no action is taken.
		if ( isset( $_GET['settings-updated'] ) && self::FALLBACK_PARENT !== $this->parent ) {
			add_settings_error( JGOR_FLCF7_Settings::GROUP, 'jgor_flcf7_saved', __( 'Settings saved.', 'floating-labels-for-cf7' ), 'success' );
		}

		// Result of the sample page actions of module 6, set by its redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no action is taken.
		if ( isset( $_GET['jgor_flcf7_samples'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no action is taken.
			$this->add_samples_notice( sanitize_key( wp_unslash( $_GET['jgor_flcf7_samples'] ) ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Floating Labels for CF7', 'floating-labels-for-cf7' ); ?></h1>
			<?php settings_errors( JGOR_FLCF7_Settings::GROUP ); ?>
			<p><?php esc_html_e( 'These are the defaults for every Contact Form 7 form on this site. Override them per form in the "Additional Settings" tab of the form, or per placement in the block editor: select the Contact Form 7 block (or a Shortcode block with the shortcode of the form) and open the panels "Floating labels" in the block sidebar. The section "Per form, per placement, per field" at the end of this screen has the details.', 'floating-labels-for-cf7' ); ?></p>
			<?php
			/**
			 * Fires on the settings screen between the intro and the settings form.
			 *
			 * Module 6 prints the box "Sample page" here.
			 */
			do_action( 'jgor_flcf7_settings_page_top' );
			?>
			<?php if ( ! empty( $this->settings->locked_keys() ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Some settings are fixed in code through the filter jgor_flcf7_settings. They are shown disabled.', 'floating-labels-for-cf7' ); ?></p>
				</div>
			<?php endif; ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( JGOR_FLCF7_Settings::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Turns the result of a sample page action (module 6) into a notice.
	 *
	 * @param string $result Result key from the redirect.
	 * @return void
	 */
	private function add_samples_notice( string $result ): void {
		$messages = array(
			'created' => array( __( 'Sample page and forms created.', 'floating-labels-for-cf7' ), 'success' ),
			'removed' => array( __( 'Sample page and forms removed.', 'floating-labels-for-cf7' ), 'success' ),
			'exists'  => array( __( 'The sample page already exists.', 'floating-labels-for-cf7' ), 'info' ),
			'absent'  => array( __( 'There is no sample page to remove.', 'floating-labels-for-cf7' ), 'info' ),
			'missing' => array( __( 'Contact Form 7 is not active, so the sample forms cannot be created.', 'floating-labels-for-cf7' ), 'error' ),
			'failed'  => array( __( 'The sample page could not be created. Check that you may create pages and contact forms.', 'floating-labels-for-cf7' ), 'error' ),
		);

		if ( isset( $messages[ $result ] ) ) {
			add_settings_error( JGOR_FLCF7_Settings::GROUP, 'jgor_flcf7_samples', $messages[ $result ][0], $messages[ $result ][1] );
		}
	}

	/**
	 * Intro of the section "Label sources".
	 *
	 * @return void
	 */
	public function render_sources_intro(): void {
		echo '<p>' . esc_html__( 'Where the text of a floating label comes from. All sources can be active: a label in the form template wins over a placeholder, and loose text is used only when there is neither.', 'floating-labels-for-cf7' ) . '</p>';
	}

	/**
	 * Intro of the section "Field types".
	 *
	 * @return void
	 */
	public function render_types_intro(): void {
		echo '<p>' . esc_html__( 'Only fields of these Contact Form 7 types get a floating label. Checkboxes, radio buttons, selects, files and quizzes never do.', 'floating-labels-for-cf7' ) . '</p>';
	}

	/**
	 * Intro of the section "Design".
	 *
	 * @return void
	 */
	public function render_design_intro(): void {
		echo '<p>' . esc_html__( 'Colours, sizes and timing of the fields. Leave a field empty to keep the default; only the values you change are printed, as CSS custom properties on the .wpcf7 wrapper, right after the stylesheet.', 'floating-labels-for-cf7' ) . '</p>';
	}

	/**
	 * Renders one field, chosen by the "key" argument of add_settings_field().
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$key = isset( $args['key'] ) && is_string( $args['key'] ) ? $args['key'] : '';

		switch ( $key ) {
			case 'mode_label':
				$this->render_checkbox(
					$key,
					__( 'Use the text of a label in the form template', 'floating-labels-for-cf7' ),
					__( 'The <label> that encloses the field, as in the default template, or a <label for="..."> that points at the id of the field.', 'floating-labels-for-cf7' )
				);
				break;

			case 'mode_placeholder':
				$this->render_checkbox(
					$key,
					__( 'Use the placeholder of the field', 'floating-labels-for-cf7' ),
					__( 'The placeholder option of the form-tag becomes the label text; the placeholder itself is replaced by a single space.', 'floating-labels-for-cf7' )
				);
				break;

			case 'mode_text':
				$this->render_checkbox(
					$key,
					__( 'Use loose text in front of the field', 'floating-labels-for-cf7' ),
					__( 'For templates without a label and without a placeholder, such as the default template of Contact Form 7 before version 5.4: the text right in front of the field, or the paragraph just before it, becomes the label text. A heuristic, off by default; check the result and add the option nofloat to fields that should keep their text.', 'floating-labels-for-cf7' )
				);
				break;

			case 'types':
				$this->render_types();
				break;

			case 'style':
				$this->render_style();
				break;

			case 'effect':
				$this->render_effect();
				break;

			case 'load_css':
				$this->render_checkbox(
					$key,
					__( 'Load the stylesheet of the plugin', 'floating-labels-for-cf7' ),
					__( 'Untick when your theme styles the classes jgor-flcf7-* itself; the design values below are then not printed either. The stylesheet follows the loading policy of Contact Form 7.', 'floating-labels-for-cf7' )
				);
				break;

			case 'required_marker':
				$this->render_checkbox(
					$key,
					__( 'Append an asterisk to the label of required fields', 'floating-labels-for-cf7' ),
					__( 'Shown through CSS behind the label of fields with aria-required="true".', 'floating-labels-for-cf7' )
				);
				break;

			case 'submit_error_text':
				$this->render_text(
					$key,
					__( 'Shown on the submit button, in the error colour, while the form has validation errors; the label of each invalid field floats up and the message appears inside the field. Leave empty to change only the colour of the button.', 'floating-labels-for-cf7' )
				);
				break;

			case 'design_colors':
				$this->render_design_group( $key, __( 'Pick a colour or leave the field empty to keep the default shown as "Default" in the picker. The Filled variant uses a light grey, rgba(0, 0, 0, 0.06), as its default background.', 'floating-labels-for-cf7' ) );
				break;

			case 'design_sizes':
				$this->render_design_group( $key, __( 'A number with px, em, rem or %, for example 0.9em. The font size of the field also scales the label and the padding; 1em keeps the size of the surrounding text.', 'floating-labels-for-cf7' ) );
				break;

			case 'design_motion':
				$this->render_design_group( $key, __( 'Scale of the floated label between 0.5 and 1; duration of the transitions with s or ms.', 'floating-labels-for-cf7' ) );
				break;

			case 'design_effect':
				$this->render_design_group( $key, __( 'Used by the focus effect chosen under Appearance: the colour of the glow and of the running light, the size of the glow, the width of the running light, and the duration of one pulse or one round with s or ms.', 'floating-labels-for-cf7' ) );
				break;

			case 'design_snippet':
				$this->render_design_snippet();
				break;
		}
	}

	/**
	 * Renders a single-line text input for a string setting.
	 *
	 * @param string $key         Setting key.
	 * @param string $description Description below the input.
	 * @return void
	 */
	private function render_text( string $key, string $description ): void {
		$locked = $this->settings->is_locked( $key );
		$id     = $this->field_id( $key );
		$value  = $this->settings->get( $key );
		$value  = is_string( $value ) ? $value : '';
		?>
		<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"
			<?php $this->print_name( $this->field_name( $key ), $locked ); ?>
			<?php disabled( $locked ); ?>>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
		$this->render_locked_note( $locked );
	}

	/**
	 * Renders a checkbox for a boolean setting.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Text next to the checkbox.
	 * @param string $description Description below the checkbox.
	 * @return void
	 */
	private function render_checkbox( string $key, string $label, string $description ): void {
		$locked = $this->settings->is_locked( $key );
		$id     = $this->field_id( $key );
		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" value="1"
				<?php $this->print_name( $this->field_name( $key ), $locked ); ?>
				<?php checked( (bool) $this->settings->get( $key ) ); ?>
				<?php disabled( $locked ); ?>>
			<?php echo esc_html( $label ); ?>
		</label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
		$this->render_locked_note( $locked );
	}

	/**
	 * Renders the checkboxes of the field types, laid out in columns.
	 *
	 * @return void
	 */
	private function render_types(): void {
		$locked  = $this->settings->is_locked( 'types' );
		$current = $this->settings->get( 'types' );
		$current = is_array( $current ) ? $current : array();
		?>
		<fieldset class="<?php echo esc_attr( self::COLUMNS_CLASS ); ?>" <?php disabled( $locked ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Fields with floating labels', 'floating-labels-for-cf7' ); ?></legend>
			<?php foreach ( $this->settings->field_types() as $type ) : ?>
				<?php $id = $this->field_id( 'type-' . $type ); ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $type ); ?>"
						<?php $this->print_name( $this->field_name( 'types' ) . '[]', $locked ); ?>
						<?php checked( in_array( $type, $current, true ) ); ?>
						<?php disabled( $locked ); ?>>
					<?php echo esc_html( $this->type_label( $type ) ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Untick every type to switch floating labels off without losing the other settings.', 'floating-labels-for-cf7' ); ?></p>
		<?php
		$this->render_locked_note( $locked );
	}

	/**
	 * Renders the radio buttons of the variant.
	 *
	 * @return void
	 */
	private function render_style(): void {
		// One entry per value of JGOR_FLCF7_Settings::STYLES: label and description.
		$this->render_radios(
			'style',
			__( 'Variant', 'floating-labels-for-cf7' ),
			array(
				'outlined' => array(
					__( 'Outlined', 'floating-labels-for-cf7' ),
					__( 'Border all round; the label floats onto the border line.', 'floating-labels-for-cf7' ),
				),
				'filled'   => array(
					__( 'Filled', 'floating-labels-for-cf7' ),
					__( 'Tinted box with a bottom line; the label floats inside the box.', 'floating-labels-for-cf7' ),
				),
				'minimal'  => array(
					__( 'Minimal', 'floating-labels-for-cf7' ),
					__( 'No colours, borders or backgrounds; your theme styles the field, the label floats above it.', 'floating-labels-for-cf7' ),
				),
			),
			''
		);
	}

	/**
	 * Renders the radio buttons of the focus effect.
	 *
	 * @return void
	 */
	private function render_effect(): void {
		// One entry per value of JGOR_FLCF7_Settings::EFFECTS: label and description.
		$this->render_radios(
			'effect',
			__( 'Focus effect', 'floating-labels-for-cf7' ),
			array(
				'none'  => array(
					__( 'None', 'floating-labels-for-cf7' ),
					__( 'The border changes colour only.', 'floating-labels-for-cf7' ),
				),
				'glow'  => array(
					__( 'Glow', 'floating-labels-for-cf7' ),
					__( 'A soft glow around the focused field.', 'floating-labels-for-cf7' ),
				),
				'pulse' => array(
					__( 'Pulsing glow', 'floating-labels-for-cf7' ),
					__( 'The glow swells and fades while the field has focus.', 'floating-labels-for-cf7' ),
				),
				'sweep' => array(
					__( 'Running light', 'floating-labels-for-cf7' ),
					__( 'A light runs around the border of the focused field.', 'floating-labels-for-cf7' ),
				),
			),
			__( 'Pure CSS, shown while a field has focus. Colour, size, width and duration are set under Design. Visitors who prefer reduced motion see the glow and the running light without movement.', 'floating-labels-for-cf7' )
		);
	}

	/**
	 * Renders a group of radio buttons for a choice setting.
	 *
	 * @param string                               $key         Setting key.
	 * @param string                               $legend      Legend of the fieldset (screen readers).
	 * @param array<string, array{string, string}> $choices Value => [label, description].
	 * @param string                               $description Description below the group, '' for none.
	 * @return void
	 */
	private function render_radios( string $key, string $legend, array $choices, string $description ): void {
		$locked  = $this->settings->is_locked( $key );
		$current = (string) $this->settings->get( $key );
		?>
		<fieldset <?php disabled( $locked ); ?>>
			<legend class="screen-reader-text"><?php echo esc_html( $legend ); ?></legend>
			<?php foreach ( $choices as $value => $texts ) : ?>
				<?php $id = $this->field_id( $key . '-' . $value ); ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="radio" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"
						<?php $this->print_name( $this->field_name( $key ), $locked ); ?>
						<?php checked( $current === $value ); ?>
						<?php disabled( $locked ); ?>>
					<strong><?php echo esc_html( $texts[0] ); ?></strong>
					<span class="description"><?php echo esc_html( $texts[1] ); ?></span>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<?php if ( '' !== $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
		$this->render_locked_note( $locked );
	}

	/**
	 * Renders one group of design fields (a row of the section "Design"),
	 * laid out in columns: three at most, four for the groups in
	 * FOUR_COLUMN_GROUPS.
	 *
	 * Colour fields carry the class jgor-flcf7-color for the colour picker
	 * and the default as data-default-color (hex defaults only); size and
	 * time fields show the default as placeholder; the scale is a number
	 * input. All fields of the group are disabled without a name when the
	 * design is fixed in code.
	 *
	 * @param string $group       Key of DESIGN_GROUPS.
	 * @param string $description Description below the group.
	 * @return void
	 */
	private function render_design_group( string $group, string $description ): void {
		$locked = $this->settings->is_locked( 'design' );
		$stored = $this->settings->get( 'design' );
		$stored = is_array( $stored ) ? $stored : array();
		$keys   = self::DESIGN_GROUPS[ $group ] ?? array();
		$class  = self::COLUMNS_CLASS . ( in_array( $group, self::FOUR_COLUMN_GROUPS, true ) ? ' ' . self::COLUMNS_FOUR_CLASS : '' );
		?>
		<fieldset class="<?php echo esc_attr( $class ); ?>" <?php disabled( $locked ); ?>>
			<legend class="screen-reader-text"><?php echo esc_html( $this->design_group_label( $group ) ); ?></legend>
			<?php foreach ( $keys as $key ) : ?>
				<?php
				$id      = $this->field_id( 'design-' . $key );
				$name    = $this->field_name( 'design' ) . '[' . $key . ']';
				$value   = isset( $stored[ $key ] ) && is_string( $stored[ $key ] ) ? $stored[ $key ] : '';
				$default = JGOR_FLCF7_Settings::DESIGN_DEFAULTS[ $key ];
				$type    = JGOR_FLCF7_Settings::DESIGN_TYPES[ $key ];
				?>
				<p>
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( self::design_label( $key ) ); ?></label><br>
					<?php if ( 'color' === $type ) : ?>
						<input type="text" class="jgor-flcf7-color" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"
							<?php if ( str_starts_with( $default, '#' ) ) : ?>
								data-default-color="<?php echo esc_attr( $default ); ?>"
							<?php endif; ?>
							<?php $this->print_name( $name, $locked ); ?>
							<?php disabled( $locked ); ?>>
					<?php elseif ( 'number' === $type ) : ?>
						<input type="number" class="small-text" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0.5" max="1" step="0.01" placeholder="<?php echo esc_attr( $default ); ?>"
							<?php $this->print_name( $name, $locked ); ?>
							<?php disabled( $locked ); ?>>
					<?php else : ?>
						<input type="text" class="small-text code" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $default ); ?>"
							<?php $this->print_name( $name, $locked ); ?>
							<?php disabled( $locked ); ?>>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
		$this->render_locked_note( $locked );
	}

	/**
	 * Renders the read-only snippet with the CSS custom properties in effect.
	 *
	 * @return void
	 */
	private function render_design_snippet(): void {
		?>
		<label for="jgor-flcf7-css-snippet" class="screen-reader-text"><?php esc_html_e( 'CSS custom properties in effect', 'floating-labels-for-cf7' ); ?></label>
		<textarea id="jgor-flcf7-css-snippet" class="large-text code" rows="<?php echo esc_attr( (string) ( count( JGOR_FLCF7_Settings::DESIGN_DEFAULTS ) + 2 ) ); ?>" readonly><?php echo esc_textarea( $this->css_snippet() ); ?></textarea>
		<p class="description"><?php esc_html_e( 'The values in effect as CSS custom properties, for a child theme or Appearance > Customize > Additional CSS. To style one form only, replace .wpcf7 with the id of its wrapper, for example [id^="wpcf7-f123-"], or with a class you add through the html_class attribute.', 'floating-labels-for-cf7' ); ?></p>
		<?php
	}

	/**
	 * Prints the note below a setting that is fixed in code.
	 *
	 * @param bool $locked Whether the setting is locked.
	 * @return void
	 */
	private function render_locked_note( bool $locked ): void {
		if ( ! $locked ) {
			return;
		}

		echo '<p class="description"><em>' . esc_html__( 'Fixed in code through the filter jgor_flcf7_settings; the value shown is the one in effect.', 'floating-labels-for-cf7' ) . '</em></p>';
	}

	/**
	 * Renders the section about the control layers (concept, section 7.5).
	 *
	 * @return void
	 */
	public function render_controls(): void {
		?>
		<p><?php esc_html_e( 'The settings above apply to every form. Override them per form, per placement or per field; later layers win.', 'floating-labels-for-cf7' ); ?></p>

		<p><strong><?php esc_html_e( 'Per form', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Add lines to the "Additional Settings" tab of the form:', 'floating-labels-for-cf7' ); ?></p>
		<pre><code><?php echo esc_html( $this->example_additional_settings() ); ?></code></pre>

		<p><strong><?php esc_html_e( 'Per placement', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php echo esc_html( $this->text_per_placement() ); ?></p>
		<pre><code><?php echo esc_html( $this->example_html_classes() ); ?></code></pre>

		<p><strong><?php esc_html_e( 'Per field', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Add the option nofloat to the form-tag, before any quoted value:', 'floating-labels-for-cf7' ); ?></p>
		<pre><code><?php echo esc_html( $this->example_nofloat() ); ?></code></pre>

		<p><strong><?php esc_html_e( 'For developers', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Filters: jgor_flcf7_settings (fix settings in code), jgor_flcf7_form_settings (per rendering), jgor_flcf7_enabled (per form), jgor_flcf7_label_text (per field), jgor_flcf7_field_types (selectable types).', 'floating-labels-for-cf7' ); ?></p>
		<?php
	}

	/**
	 * Builds the CSS snippet with every custom property and the value in effect.
	 *
	 * @return string
	 */
	public function css_snippet(): string {
		$lines = array( '.wpcf7 {' );

		foreach ( $this->settings->design() as $key => $value ) {
			$lines[] = "\t" . JGOR_FLCF7_Settings::PROPERTY_PREFIX . $key . ': ' . $value . ';';
		}

		$lines[] = '}';

		return implode( "\n", $lines );
	}

	/**
	 * Returns the heading of a design group (legend of its fieldset).
	 *
	 * @param string $group Key of DESIGN_GROUPS.
	 * @return string
	 */
	private function design_group_label( string $group ): string {
		switch ( $group ) {
			case 'design_colors':
				return __( 'Colours', 'floating-labels-for-cf7' );
			case 'design_sizes':
				return __( 'Sizes', 'floating-labels-for-cf7' );
			case 'design_motion':
				return __( 'Floated label and animation', 'floating-labels-for-cf7' );
			case 'design_effect':
				return __( 'Glow and running light', 'floating-labels-for-cf7' );
		}

		return $group;
	}

	/**
	 * Returns the human-readable label of a design property.
	 *
	 * Also used by module 5 for the panels of the block editor.
	 *
	 * @param string $key Property name without prefix.
	 * @return string
	 */
	public static function design_label( string $key ): string {
		$labels = array(
			'label-color'             => __( 'Label', 'floating-labels-for-cf7' ),
			'label-color-active'      => __( 'Label of the focused field', 'floating-labels-for-cf7' ),
			'label-color-error'       => __( 'Label of an invalid field', 'floating-labels-for-cf7' ),
			'border-color'            => __( 'Border', 'floating-labels-for-cf7' ),
			'border-color-active'     => __( 'Border of the focused field', 'floating-labels-for-cf7' ),
			'border-color-error'      => __( 'Border of an invalid field', 'floating-labels-for-cf7' ),
			'background'              => __( 'Field background (Outlined)', 'floating-labels-for-cf7' ),
			'background-filled'       => __( 'Field background (Filled)', 'floating-labels-for-cf7' ),
			'submit-error-background' => __( 'Submit button on errors', 'floating-labels-for-cf7' ),
			'submit-error-color'      => __( 'Text of the submit button on errors', 'floating-labels-for-cf7' ),
			'font-size'               => __( 'Font size of the field', 'floating-labels-for-cf7' ),
			'label-size'              => __( 'Font size of the label at rest', 'floating-labels-for-cf7' ),
			'padding-x'               => __( 'Horizontal padding', 'floating-labels-for-cf7' ),
			'padding-y'               => __( 'Vertical padding', 'floating-labels-for-cf7' ),
			'border-width'            => __( 'Border width', 'floating-labels-for-cf7' ),
			'radius'                  => __( 'Corner radius', 'floating-labels-for-cf7' ),
			'label-scale'             => __( 'Scale of the floated label', 'floating-labels-for-cf7' ),
			'duration'                => __( 'Transition duration', 'floating-labels-for-cf7' ),
			'effect-color'            => __( 'Colour of the focus effect', 'floating-labels-for-cf7' ),
			'effect-size'             => __( 'Size of the glow', 'floating-labels-for-cf7' ),
			'effect-width'            => __( 'Width of the running light', 'floating-labels-for-cf7' ),
			'effect-duration'         => __( 'Duration of one pulse or round', 'floating-labels-for-cf7' ),
		);

		return $labels[ $key ] ?? $key;
	}

	/**
	 * Returns the human-readable label of a field type.
	 *
	 * Types added through the filter jgor_flcf7_field_types are shown by
	 * their form-tag type.
	 *
	 * @param string $type Contact Form 7 base type.
	 * @return string
	 */
	private function type_label( string $type ): string {
		$labels = array(
			'text'     => __( 'Text', 'floating-labels-for-cf7' ),
			'email'    => __( 'Email', 'floating-labels-for-cf7' ),
			'url'      => __( 'URL', 'floating-labels-for-cf7' ),
			'tel'      => __( 'Telephone', 'floating-labels-for-cf7' ),
			'number'   => __( 'Number', 'floating-labels-for-cf7' ),
			'textarea' => __( 'Text area', 'floating-labels-for-cf7' ),
		);

		if ( ! isset( $labels[ $type ] ) ) {
			return $type;
		}

		return $labels[ $type ] . ' (' . $type . ')';
	}

	/**
	 * Returns the id attribute of a form control.
	 *
	 * @param string $key Setting key or other suffix.
	 * @return string
	 */
	private function field_id( string $key ): string {
		return 'jgor-flcf7-' . str_replace( '_', '-', $key );
	}

	/**
	 * Returns the name attribute value of a form control.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function field_name( string $key ): string {
		return JGOR_FLCF7_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Prints the name attribute of a form control, or nothing for a locked
	 * setting so the browser does not submit it.
	 *
	 * @param string $name   Name attribute value.
	 * @param bool   $locked Whether the setting is fixed in code.
	 * @return void
	 */
	private function print_name( string $name, bool $locked ): void {
		if ( $locked ) {
			return;
		}

		echo ' name="' . esc_attr( $name ) . '"';
	}
}

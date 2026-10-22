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
 * Colours and sizes have no fields: the screen shows the CSS custom
 * properties of the stylesheet as a read-only snippet to copy into the theme
 * (concept, section 9).
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
	 * CSS custom properties of the stylesheet with their default values.
	 *
	 * Mirrors the .wpcf7 rule at the top of assets/css/floating-labels.css;
	 * tests/verify-admin.php checks that both stay in sync.
	 *
	 * @var array<string, string>
	 */
	public const CSS_PROPERTIES = array(
		'--jgor-flcf7-padding-x'           => '0.75em',
		'--jgor-flcf7-padding-y'           => '0.75em',
		'--jgor-flcf7-label-color'         => '#5f6368',
		'--jgor-flcf7-label-color-active'  => '#1a73e8',
		'--jgor-flcf7-label-color-error'   => '#dc3232',
		'--jgor-flcf7-label-size'          => '1em',
		'--jgor-flcf7-label-scale'         => '0.75',
		'--jgor-flcf7-border-width'        => '1px',
		'--jgor-flcf7-border-color'        => '#80868b',
		'--jgor-flcf7-border-color-active' => '#1a73e8',
		'--jgor-flcf7-border-color-error'  => '#dc3232',
		'--jgor-flcf7-radius'              => '4px',
		'--jgor-flcf7-background'          => '#fff',
		'--jgor-flcf7-background-filled'   => 'rgba(0, 0, 0, 0.06)',
		'--jgor-flcf7-duration'            => '0.15s',
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
		add_filter( 'plugin_action_links_' . plugin_basename( JGOR_FLCF7_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the submenu page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$this->parent = $this->parent_slug();

		add_submenu_page(
			$this->parent,
			__( 'Floating Labels for CF7', 'floating-labels-for-cf7' ),
			__( 'Floating Labels', 'floating-labels-for-cf7' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
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

		add_settings_section( 'jgor_flcf7_types', __( 'Field types', 'floating-labels-for-cf7' ), array( $this, 'render_types_intro' ), $page );
		add_settings_field( 'jgor_flcf7_types', __( 'Fields with floating labels', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_types', array( 'key' => 'types' ) );

		add_settings_section( 'jgor_flcf7_appearance', __( 'Appearance', 'floating-labels-for-cf7' ), '__return_null', $page );
		add_settings_field( 'jgor_flcf7_style', __( 'Variant', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', array( 'key' => 'style' ) );
		add_settings_field( 'jgor_flcf7_load_css', __( 'Stylesheet', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', $this->field_args( 'load_css' ) );
		add_settings_field( 'jgor_flcf7_required_marker', __( 'Required fields', 'floating-labels-for-cf7' ), array( $this, 'render_field' ), $page, 'jgor_flcf7_appearance', $this->field_args( 'required_marker' ) );

		add_settings_section( 'jgor_flcf7_controls', __( 'Per form, per placement, per field', 'floating-labels-for-cf7' ), array( $this, 'render_controls' ), $page );
		add_settings_section( 'jgor_flcf7_design', __( 'Design: CSS custom properties', 'floating-labels-for-cf7' ), array( $this, 'render_design' ), $page );
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Floating Labels for CF7', 'floating-labels-for-cf7' ); ?></h1>
			<?php settings_errors( JGOR_FLCF7_Settings::GROUP ); ?>
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
	 * Intro of the section "Label sources".
	 *
	 * @return void
	 */
	public function render_sources_intro(): void {
		echo '<p>' . esc_html__( 'Where the text of a floating label comes from. Both sources can be active; a label in the form template wins over a placeholder.', 'floating-labels-for-cf7' ) . '</p>';
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

			case 'types':
				$this->render_types();
				break;

			case 'style':
				$this->render_style();
				break;

			case 'load_css':
				$this->render_checkbox(
					$key,
					__( 'Load the stylesheet of the plugin', 'floating-labels-for-cf7' ),
					__( 'Untick when your theme styles the classes jgor-flcf7-* itself. The stylesheet follows the loading policy of Contact Form 7.', 'floating-labels-for-cf7' )
				);
				break;

			case 'required_marker':
				$this->render_checkbox(
					$key,
					__( 'Append an asterisk to the label of required fields', 'floating-labels-for-cf7' ),
					__( 'Shown through CSS behind the label of fields with aria-required="true".', 'floating-labels-for-cf7' )
				);
				break;
		}
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
	 * Renders the checkboxes of the field types.
	 *
	 * @return void
	 */
	private function render_types(): void {
		$locked  = $this->settings->is_locked( 'types' );
		$current = $this->settings->get( 'types' );
		$current = is_array( $current ) ? $current : array();
		?>
		<fieldset <?php disabled( $locked ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Fields with floating labels', 'floating-labels-for-cf7' ); ?></legend>
			<?php foreach ( $this->settings->field_types() as $type ) : ?>
				<?php $id = $this->field_id( 'type-' . $type ); ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $type ); ?>"
						<?php $this->print_name( $this->field_name( 'types' ) . '[]', $locked ); ?>
						<?php checked( in_array( $type, $current, true ) ); ?>
						<?php disabled( $locked ); ?>>
					<?php echo esc_html( $this->type_label( $type ) ); ?>
				</label><br>
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
		$locked  = $this->settings->is_locked( 'style' );
		$current = (string) $this->settings->get( 'style' );

		// One entry per value of JGOR_FLCF7_Settings::STYLES: label and description.
		$styles = array(
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
		);
		?>
		<fieldset <?php disabled( $locked ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Variant', 'floating-labels-for-cf7' ); ?></legend>
			<?php foreach ( $styles as $style => $texts ) : ?>
				<?php $id = $this->field_id( 'style-' . $style ); ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="radio" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $style ); ?>"
						<?php $this->print_name( $this->field_name( 'style' ), $locked ); ?>
						<?php checked( $current === $style ); ?>
						<?php disabled( $locked ); ?>>
					<strong><?php echo esc_html( $texts[0] ); ?></strong>
					<span class="description"><?php echo esc_html( $texts[1] ); ?></span>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<?php
		$this->render_locked_note( $locked );
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
	 * Renders the section about the control layers (concept, section 7.4).
	 *
	 * @return void
	 */
	public function render_controls(): void {
		?>
		<p><?php esc_html_e( 'The settings above apply to every form. Override them per form, per placement or per field; later layers win.', 'floating-labels-for-cf7' ); ?></p>

		<p><strong><?php esc_html_e( 'Per form', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Add lines to the "Additional Settings" tab of the form:', 'floating-labels-for-cf7' ); ?></p>
		<pre><code><?php echo esc_html( "floating_labels: off\nfloating_labels: placeholder\nfloating_labels: label\nfloating_labels_style: filled\nfloating_labels_marker: on\nfloating_labels_types: text email textarea" ); ?></code></pre>

		<p><strong><?php esc_html_e( 'Per placement', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Add classes to the "Class" field of the Contact Form 7 block (panel "Form attributes") or to the html_class attribute of the shortcode. The same form can then look different on different pages:', 'floating-labels-for-cf7' ); ?></p>
		<pre><code><?php echo esc_html( "jgor-flcf7-off  jgor-flcf7-on\njgor-flcf7-mode-placeholder  jgor-flcf7-mode-label\njgor-flcf7-style-outlined  jgor-flcf7-style-filled  jgor-flcf7-style-minimal\njgor-flcf7-marker-on  jgor-flcf7-marker-off\n\n[contact-form-7 id=\"123\" html_class=\"jgor-flcf7-style-filled\"]" ); ?></code></pre>

		<p><strong><?php esc_html_e( 'Per field', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Add the option nofloat to the form-tag, before any quoted value:', 'floating-labels-for-cf7' ); ?></p>
		<pre><code><?php echo esc_html( '[text* your-name nofloat placeholder "Your name"]' ); ?></code></pre>

		<p><strong><?php esc_html_e( 'For developers', 'floating-labels-for-cf7' ); ?></strong><br>
		<?php esc_html_e( 'Filters: jgor_flcf7_settings (fix settings in code), jgor_flcf7_form_settings (per rendering), jgor_flcf7_enabled (per form), jgor_flcf7_label_text (per field), jgor_flcf7_field_types (selectable types).', 'floating-labels-for-cf7' ); ?></p>
		<?php
	}

	/**
	 * Renders the section with the CSS custom properties.
	 *
	 * @return void
	 */
	public function render_design(): void {
		?>
		<p><?php esc_html_e( 'Colours, sizes and timing are CSS custom properties on the .wpcf7 wrapper. Copy the snippet into Appearance > Customize > Additional CSS or into your child theme and change the values you need; properties you leave out keep their defaults.', 'floating-labels-for-cf7' ); ?></p>
		<p><?php esc_html_e( 'To style one form only, replace .wpcf7 with the id of its wrapper, for example [id^="wpcf7-f123-"], or with a class you add through the html_class attribute.', 'floating-labels-for-cf7' ); ?></p>
		<label for="jgor-flcf7-css-snippet" class="screen-reader-text"><?php esc_html_e( 'CSS snippet with the default values', 'floating-labels-for-cf7' ); ?></label>
		<textarea id="jgor-flcf7-css-snippet" class="large-text code" rows="<?php echo esc_attr( (string) ( count( self::CSS_PROPERTIES ) + 2 ) ); ?>" readonly><?php echo esc_textarea( $this->css_snippet() ); ?></textarea>
		<?php
	}

	/**
	 * Builds the CSS snippet with every custom property and its default.
	 *
	 * @return string
	 */
	public function css_snippet(): string {
		$lines = array( '.wpcf7 {' );

		foreach ( self::CSS_PROPERTIES as $property => $default ) {
			$lines[] = "\t" . $property . ': ' . $default . ';';
		}

		$lines[] = '}';

		return implode( "\n", $lines );
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

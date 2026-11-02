<?php
/**
 * Module 7: the tab "Floating labels" in the Contact Form 7 form editor.
 *
 * A comfort front for the control per form (concept 7.5, layer 2): the tab
 * offers the choices that the additional settings floating_labels,
 * floating_labels_style, floating_labels_effect, floating_labels_marker and
 * floating_labels_types accept, and saves them as exactly those lines in the
 * additional settings of the form. Nothing else changes: module 2 keeps
 * reading the lines through pref(), lines written by hand stay visible and
 * editable in the tab "Additional Settings", and other lines (skip_mail,
 * demo_mode, ...) are left untouched.
 *
 * The panel is added through the filter wpcf7_editor_panels and rendered
 * inside the form Contact Form 7 posts to itself. Saving hooks into the
 * action wpcf7_save_contact_form, which Contact Form 7 fires after it set
 * the posted properties and before it saves the post; the posted data
 * arrives as the unslashed request Contact Form 7 passes along. Contact
 * Form 7 has verified the nonce and the capability by then; both are
 * checked again here as a second layer, and a failure leaves the lines as
 * they are. Admin only, no JavaScript.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the panel "Floating labels" of the form editor.
 */
final class JGOR_FLCF7_Form_Panel {

	/**
	 * Id of the panel (tab) in the Contact Form 7 editor.
	 */
	public const PANEL_ID = 'jgor-flcf7-panel';

	/**
	 * Name of the posted field group: jgor_flcf7_form[<key>].
	 */
	public const FIELD = 'jgor_flcf7_form';

	/**
	 * Class of the fieldset that holds the panel controls.
	 */
	public const PANEL_CLASS = 'jgor-flcf7-form-panel';

	/**
	 * Class of a control row inside the panel.
	 */
	public const ROW_CLASS = 'jgor-flcf7-form-panel__row';

	/**
	 * Prefix of the nonce action Contact Form 7 uses for saving a form; the
	 * form id (or -1 for a new form) follows.
	 */
	private const NONCE_ACTION = 'wpcf7-save-contact-form_';

	/**
	 * Pattern of the additional-settings lines this panel owns.
	 *
	 * Anchored like the parser of Contact Form 7 (additional_setting():
	 * name at the start of the line, no leading whitespace, exact case),
	 * so the panel owns exactly the lines the renderer reads; an indented
	 * or differently cased line is left alone.
	 */
	private const LINE_PATTERN = '/^floating_labels(?:_style|_effect|_marker|_types)?[\t ]*:/';

	/**
	 * Values of the line "floating_labels" the panel offers (concept 7.5).
	 *
	 * @var string[]
	 */
	private const MODES = array( 'off', 'placeholder', 'label', 'text' );

	/**
	 * Spellings of "off" module 2 accepts for the line "floating_labels".
	 *
	 * @var string[]
	 */
	private const OFF_WORDS = array( 'off', 'false', 'no', '0' );

	/**
	 * Spellings of "on" module 2 accepts for the line "floating_labels_marker".
	 *
	 * @var string[]
	 */
	private const ON_WORDS = array( 'on', 'true', 'yes', '1' );

	/**
	 * Values of the line "floating_labels_marker" the panel offers.
	 *
	 * @var string[]
	 */
	private const MARKERS = array( 'on', 'off' );

	/**
	 * Admin pages of Contact Form 7 on which the stylesheet is needed: the
	 * editor of an existing form and the "Add New" screen.
	 *
	 * @var string[]
	 */
	private const EDITOR_PAGES = array( 'wpcf7', 'wpcf7-new' );

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
	 * Registers the panel, the save handler and the stylesheet.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wpcf7_editor_panels', array( $this, 'add_panel' ) );
		add_action( 'wpcf7_save_contact_form', array( $this, 'save' ), 10, 3 );
		add_action( 'wpcf7_admin_load', array( $this, 'on_admin_load' ), 10, 2 );
	}

	/**
	 * Filter callback for wpcf7_editor_panels: appends the panel.
	 *
	 * The title counts the lines of this form the panel owns, like Contact
	 * Form 7 counts the additional settings in its own tab title.
	 *
	 * @param mixed $panels Panels registered so far (id => title and callback).
	 * @return array<string, array{title: string, callback: callable}>
	 */
	public function add_panel( $panels ): array {
		$panels = is_array( $panels ) ? $panels : array();

		$count = 0;
		$form  = wpcf7_get_current_contact_form();
		if ( $form instanceof WPCF7_ContactForm ) {
			$count = count( $this->split_lines( $this->additional_settings( $form ) )['ours'] );
		}

		$panels[ self::PANEL_ID ] = array(
			'title'    => $count > 0
				/* translators: %d: number of floating-label settings set for this form */
				? sprintf( __( 'Floating labels (%d)', 'floating-labels-for-cf7' ), $count )
				: __( 'Floating labels', 'floating-labels-for-cf7' ),
			'callback' => array( $this, 'render' ),
		);

		return $panels;
	}

	/**
	 * Renders the panel content (called by the Contact Form 7 editor inside
	 * its own form, which carries the nonce).
	 *
	 * @param mixed $form The contact form being edited.
	 * @return void
	 */
	public function render( $form ): void {
		if ( ! $form instanceof WPCF7_ContactForm ) {
			return;
		}

		$values = $this->values_of( $form );
		?>
		<h2><?php esc_html_e( 'Floating labels', 'floating-labels-for-cf7' ); ?></h2>
		<fieldset class="<?php echo esc_attr( self::PANEL_CLASS ); ?>">
			<legend>
				<?php
				$settings_link = '<a href="' . esc_url( JGOR_FLCF7_Admin::page_url() ) . '">' . esc_html__( 'Floating Labels', 'floating-labels-for-cf7' ) . '</a>';
				echo wp_kses(
					sprintf(
						/* translators: %s: link "Floating Labels" to the settings screen */
						__( 'These choices apply to this form only and override the defaults of Contact > %s. They are saved as lines in the tab "Additional Settings", where you can edit them as well; a placement in the block editor overrides them in turn.', 'floating-labels-for-cf7' ),
						$settings_link
					),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</legend>
			<?php
			$this->render_select(
				'mode',
				__( 'Floating labels', 'floating-labels-for-cf7' ),
				array(
					'off'         => __( 'Off', 'floating-labels-for-cf7' ),
					'placeholder' => __( 'Placeholder only', 'floating-labels-for-cf7' ),
					'label'       => __( 'Label only', 'floating-labels-for-cf7' ),
					'text'        => __( 'Text before the field only', 'floating-labels-for-cf7' ),
				),
				$values['mode']
			);
			$this->render_select(
				'style',
				__( 'Variant', 'floating-labels-for-cf7' ),
				array(
					'outlined' => __( 'Outlined', 'floating-labels-for-cf7' ),
					'filled'   => __( 'Filled', 'floating-labels-for-cf7' ),
					'minimal'  => __( 'Minimal', 'floating-labels-for-cf7' ),
				),
				$values['style']
			);
			$this->render_select(
				'effect',
				__( 'Focus effect', 'floating-labels-for-cf7' ),
				array(
					'none'  => __( 'None', 'floating-labels-for-cf7' ),
					'glow'  => __( 'Glow', 'floating-labels-for-cf7' ),
					'pulse' => __( 'Pulsing glow', 'floating-labels-for-cf7' ),
					'sweep' => __( 'Running light', 'floating-labels-for-cf7' ),
				),
				$values['effect']
			);
			$this->render_select(
				'marker',
				__( 'Marker for required fields', 'floating-labels-for-cf7' ),
				array(
					'on'  => __( 'On', 'floating-labels-for-cf7' ),
					'off' => __( 'Off', 'floating-labels-for-cf7' ),
				),
				$values['marker']
			);
			?>
			<p class="<?php echo esc_attr( self::ROW_CLASS ); ?>">
				<label for="jgor-flcf7-form-limit">
					<input type="checkbox" id="jgor-flcf7-form-limit" name="<?php echo esc_attr( self::FIELD ); ?>[limit]" value="1"<?php checked( $values['limit'] ); ?>>
					<?php esc_html_e( 'Limit the field types for this form', 'floating-labels-for-cf7' ); ?>
				</label>
			</p>
			<fieldset class="<?php echo esc_attr( JGOR_FLCF7_Admin::COLUMNS_CLASS ); ?>">
				<legend class="screen-reader-text"><?php esc_html_e( 'Field types', 'floating-labels-for-cf7' ); ?></legend>
				<?php foreach ( $this->settings->field_types() as $type ) : ?>
					<p>
						<label for="jgor-flcf7-form-type-<?php echo esc_attr( $type ); ?>">
							<input type="checkbox" id="jgor-flcf7-form-type-<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>[types][]" value="<?php echo esc_attr( $type ); ?>"<?php checked( in_array( $type, $values['types'], true ) ); ?>>
							<?php echo esc_html( JGOR_FLCF7_Admin::type_label( $type ) ); ?>
						</label>
					</p>
				<?php endforeach; ?>
			</fieldset>
			<p class="description"><?php esc_html_e( 'Without the limit, the field types ticked on the settings screen apply. The lines this tab writes: floating_labels, floating_labels_style, floating_labels_effect, floating_labels_marker, floating_labels_types.', 'floating-labels-for-cf7' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Action callback for wpcf7_save_contact_form: writes the choices of the
	 * panel as lines into the additional settings before Contact Form 7
	 * saves the form.
	 *
	 * Runs only for the context "save" and only when the panel was posted
	 * (REST and WP-CLI callers do not post it). The nonce and the capability
	 * are verified again; on failure the lines stay as they are. Contact
	 * Form 7 passes the unslashed request as $data, so the posted values are
	 * read from there and sanitized through whitelists.
	 *
	 * @param mixed $contact_form The contact form being saved.
	 * @param mixed $data         Posted data, see wpcf7_save_contact_form().
	 * @param mixed $context      "save" or "copy".
	 * @return void
	 */
	public function save( $contact_form, $data, $context ): void {
		if ( 'save' !== $context || ! $contact_form instanceof WPCF7_ContactForm || ! is_array( $data ) ) {
			return;
		}

		if ( ! isset( $data[ self::FIELD ] ) || ! is_array( $data[ self::FIELD ] ) ) {
			return;
		}

		$id    = isset( $data['id'] ) ? (int) $data['id'] : -1;
		$nonce = isset( $data['_wpnonce'] ) && is_string( $data['_wpnonce'] ) ? $data['_wpnonce'] : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION . $id ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- meta capability of Contact Form 7, mapped to its read-write capability.
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
			return;
		}

		$lines = $this->lines_from_input( $data[ self::FIELD ] );
		$split = $this->split_lines( $this->additional_settings( $contact_form ) );

		$contact_form->set_properties(
			array(
				'additional_settings' => trim( implode( "\n", array_merge( $split['others'], $lines ) ) ),
			)
		);
	}

	/**
	 * Action callback for wpcf7_admin_load: enqueues the stylesheet on the
	 * editor pages of Contact Form 7.
	 *
	 * Contact Form 7 fires the action on its own admin pages with the page
	 * slug, which keeps this independent of menu titles and hook suffixes.
	 *
	 * @param mixed $page   Admin page slug.
	 * @param mixed $action Current action.
	 * @return void
	 */
	public function on_admin_load( $page, $action ): void {
		unset( $action );

		if ( ! is_string( $page ) || ! in_array( $page, self::EDITOR_PAGES, true ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues the admin stylesheet (the column layout of the field types).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			JGOR_FLCF7_Admin::SCRIPT_HANDLE,
			JGOR_FLCF7_URL . JGOR_FLCF7_Admin::STYLE_PATH,
			array(),
			JGOR_FLCF7_Plugin::asset_version( JGOR_FLCF7_Admin::STYLE_PATH )
		);
	}

	/**
	 * Renders one select with "Default (settings page)" as its first entry.
	 *
	 * @param string                $key     Field key (mode, style, effect, marker).
	 * @param string                $label   Visible label.
	 * @param array<string, string> $choices Value => label.
	 * @param string                $current Current value, '' for the default.
	 * @return void
	 */
	private function render_select( string $key, string $label, array $choices, string $current ): void {
		$id = 'jgor-flcf7-form-' . $key;
		?>
		<p class="<?php echo esc_attr( self::ROW_CLASS ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::FIELD . '[' . $key . ']' ); ?>">
				<option value=""<?php selected( $current, '' ); ?>><?php esc_html_e( 'Default (settings page)', 'floating-labels-for-cf7' ); ?></option>
				<?php foreach ( $choices as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $current, $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Reads the current choices of a form from its additional settings, the
	 * way module 2 reads them (first line per key, unknown values ignored).
	 *
	 * Without a types line the type checkboxes show the types of the
	 * settings screen as the starting point, with the limit unticked.
	 *
	 * @param WPCF7_ContactForm $form The contact form.
	 * @return array{mode: string, style: string, effect: string, marker: string, limit: bool, types: string[]}
	 */
	private function values_of( WPCF7_ContactForm $form ): array {
		$mode = $this->pref( $form, 'floating_labels' );
		if ( in_array( $mode, self::OFF_WORDS, true ) ) {
			$mode = 'off';
		} elseif ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = '';
		}

		$marker = $this->pref( $form, 'floating_labels_marker' );
		if ( in_array( $marker, self::ON_WORDS, true ) ) {
			$marker = 'on';
		} elseif ( in_array( $marker, self::OFF_WORDS, true ) ) {
			$marker = 'off';
		} else {
			$marker = '';
		}

		$style  = $this->pref( $form, 'floating_labels_style' );
		$effect = $this->pref( $form, 'floating_labels_effect' );

		$types_line = $form->pref( 'floating_labels_types' );
		$limit      = is_string( $types_line );
		$types      = $limit ? $this->parse_types( $types_line ) : $this->settings->get( 'types' );

		return array(
			'mode'   => $mode,
			'style'  => in_array( $style, JGOR_FLCF7_Settings::STYLES, true ) ? $style : '',
			'effect' => in_array( $effect, JGOR_FLCF7_Settings::EFFECTS, true ) ? $effect : '',
			'marker' => $marker,
			'limit'  => $limit,
			'types'  => is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array(),
		);
	}

	/**
	 * Returns the first value of an additional setting, lower-cased and
	 * trimmed, or '' when the line is missing.
	 *
	 * @param WPCF7_ContactForm $form The contact form.
	 * @param string            $name Setting name.
	 * @return string
	 */
	private function pref( WPCF7_ContactForm $form, string $name ): string {
		$value = $form->pref( $name );

		return is_string( $value ) ? strtolower( trim( $value ) ) : '';
	}

	/**
	 * Parses a types line (space or comma separated) into known base types.
	 *
	 * @param string $line Value of the line floating_labels_types.
	 * @return string[]
	 */
	private function parse_types( string $line ): array {
		$parts = preg_split( '/[\s,]+/', strtolower( trim( $line ) ) );
		$parts = is_array( $parts ) ? array_map( 'sanitize_key', $parts ) : array();

		return array_values( array_intersect( $this->settings->field_types(), $parts ) );
	}

	/**
	 * Builds the additional-settings lines from the posted panel values.
	 *
	 * Only whitelisted values produce a line; the default entry produces
	 * none. With the limit ticked, the types line lists the ticked known
	 * types; an empty list is a valid line that switches every type off.
	 *
	 * @param array<mixed> $input Posted values of the panel.
	 * @return string[] Lines without line breaks.
	 */
	private function lines_from_input( array $input ): array {
		$lines = array();

		$mode = $this->choice( $input, 'mode', self::MODES );
		if ( '' !== $mode ) {
			$lines[] = 'floating_labels: ' . $mode;
		}

		$style = $this->choice( $input, 'style', JGOR_FLCF7_Settings::STYLES );
		if ( '' !== $style ) {
			$lines[] = 'floating_labels_style: ' . $style;
		}

		$effect = $this->choice( $input, 'effect', JGOR_FLCF7_Settings::EFFECTS );
		if ( '' !== $effect ) {
			$lines[] = 'floating_labels_effect: ' . $effect;
		}

		$marker = $this->choice( $input, 'marker', self::MARKERS );
		if ( '' !== $marker ) {
			$lines[] = 'floating_labels_marker: ' . $marker;
		}

		if ( ! empty( $input['limit'] ) ) {
			$raw   = isset( $input['types'] ) && is_array( $input['types'] ) ? array_filter( $input['types'], 'is_string' ) : array();
			$types = array_values( array_intersect( $this->settings->field_types(), array_map( 'sanitize_key', $raw ) ) );

			$lines[] = rtrim( 'floating_labels_types: ' . implode( ' ', $types ) );
		}

		return $lines;
	}

	/**
	 * Returns a posted value when it is one of the allowed choices, else ''.
	 *
	 * @param array<mixed> $input   Posted values of the panel.
	 * @param string       $key     Field key.
	 * @param string[]     $allowed Allowed values.
	 * @return string
	 */
	private function choice( array $input, string $key, array $allowed ): string {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
			return '';
		}

		$value = sanitize_key( $input[ $key ] );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Returns the additional settings of a form as a string.
	 *
	 * @param WPCF7_ContactForm $form The contact form.
	 * @return string
	 */
	private function additional_settings( WPCF7_ContactForm $form ): string {
		$settings = $form->prop( 'additional_settings' );

		return is_string( $settings ) ? $settings : '';
	}

	/**
	 * Splits additional settings into the lines this panel owns and the rest.
	 *
	 * @param string $text Additional settings.
	 * @return array{ours: string[], others: string[]}
	 */
	private function split_lines( string $text ): array {
		$ours   = array();
		$others = array();

		// Only surrounding line breaks are trimmed: an indented first line must
		// keep its indent, Contact Form 7 does not read it and neither do we.
		foreach ( explode( "\n", trim( $text, "\r\n" ) ) as $line ) {
			$line = rtrim( $line, "\r" );

			if ( preg_match( self::LINE_PATTERN, $line ) ) {
				$ours[] = $line;
			} else {
				$others[] = $line;
			}
		}

		return array(
			'ours'   => $ours,
			'others' => $others,
		);
	}
}

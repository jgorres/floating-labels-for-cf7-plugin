<?php
/**
 * Settings: defaults, access and sanitizing of the single plugin option.
 *
 * All modules read their configuration through this class. The option is one
 * array under the key jgor_flcf7_settings; unknown keys never survive
 * sanitizing, missing keys fall back to the defaults.
 *
 * Settings can be fixed in code through the filter jgor_flcf7_settings
 * (see all()). A key whose filtered value differs from the stored value
 * counts as locked: the settings screen shows it disabled, and saving the
 * screen keeps the stored value for it, so the filter keeps winning.
 *
 * Design values (colours, sizes, timing) live under the key "design": one
 * entry per CSS custom property of the stylesheet, without the prefix
 * --jgor-flcf7-. Only values that passed sanitizing are stored; a missing
 * entry means "default", and module 3 prints only the values that differ
 * from the defaults.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds and sanitizes the plugin settings.
 */
final class JGOR_FLCF7_Settings {

	/**
	 * Name of the option that stores all settings.
	 */
	public const OPTION = 'jgor_flcf7_settings';

	/**
	 * Settings group for register_setting() and settings_fields().
	 */
	public const GROUP = 'jgor_flcf7';

	/**
	 * Contact Form 7 base types the plugin handles out of the box.
	 *
	 * Types that render a text-like control with a placeholder attribute.
	 * "date" and "select" are deliberately left out for 1.0 (see the concept,
	 * section 15); checkbox, radio, file, range, quiz and captcha never fit.
	 *
	 * @var string[]
	 */
	public const BASE_TYPES = array( 'text', 'email', 'url', 'tel', 'number', 'textarea' );

	/**
	 * Available CSS variants.
	 *
	 * @var string[]
	 */
	public const STYLES = array( 'outlined', 'filled', 'minimal' );

	/**
	 * Default text shown on the submit button while the form has errors.
	 *
	 * Stored untranslated; the markup module translates it at render time as
	 * long as the stored value still equals this default.
	 */
	public const DEFAULT_SUBMIT_ERROR_TEXT = 'Please check the highlighted fields';

	/**
	 * Maximum length of the submit error text.
	 */
	private const SUBMIT_ERROR_TEXT_MAX = 200;

	/**
	 * Prefix of the CSS custom properties of the stylesheet.
	 */
	public const PROPERTY_PREFIX = '--jgor-flcf7-';

	/**
	 * CSS custom properties of the stylesheet (without prefix) with their
	 * default values, in the order of the .wpcf7 rule at the top of
	 * assets/css/floating-labels.css; tests/verify-admin.php checks that
	 * both stay in sync.
	 *
	 * @var array<string, string>
	 */
	public const DESIGN_DEFAULTS = array(
		'padding-x'               => '0.75em',
		'padding-y'               => '0.75em',
		'font-size'               => '1em',
		'label-color'             => '#5f6368',
		'label-color-active'      => '#1a73e8',
		'label-color-error'       => '#dc3232',
		'label-size'              => '1em',
		'label-scale'             => '0.75',
		'border-width'            => '1px',
		'border-color'            => '#80868b',
		'border-color-active'     => '#1a73e8',
		'border-color-error'      => '#dc3232',
		'radius'                  => '4px',
		'background'              => '#fff',
		'background-filled'       => 'rgba(0, 0, 0, 0.06)',
		'duration'                => '0.15s',
		'submit-error-background' => '#dc3232',
		'submit-error-color'      => '#fff',
	);

	/**
	 * Value type of every design property: "color" (hex colour), "length"
	 * (number with px, em, rem or %), "number" (0.5 to 1) or "time" (number
	 * with s or ms).
	 *
	 * @var array<string, string>
	 */
	public const DESIGN_TYPES = array(
		'padding-x'               => 'length',
		'padding-y'               => 'length',
		'font-size'               => 'length',
		'label-color'             => 'color',
		'label-color-active'      => 'color',
		'label-color-error'       => 'color',
		'label-size'              => 'length',
		'label-scale'             => 'number',
		'border-width'            => 'length',
		'border-color'            => 'color',
		'border-color-active'     => 'color',
		'border-color-error'      => 'color',
		'radius'                  => 'length',
		'background'              => 'color',
		'background-filled'       => 'color',
		'duration'                => 'time',
		'submit-error-background' => 'color',
		'submit-error-color'      => 'color',
	);

	/**
	 * Single instance.
	 *
	 * @var JGOR_FLCF7_Settings|null
	 */
	private static ?JGOR_FLCF7_Settings $instance = null;

	/**
	 * Settings merged with the defaults and the filter, loaded on first access.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settings = null;

	/**
	 * Keys whose effective value comes from the filter jgor_flcf7_settings.
	 *
	 * @var string[]
	 */
	private array $locked = array();

	/**
	 * Returns the single instance.
	 *
	 * @return JGOR_FLCF7_Settings
	 */
	public static function instance(): JGOR_FLCF7_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Field types the plugin may rebuild, as offered on the settings screen.
	 *
	 * Starts from BASE_TYPES and passes through the filter
	 * jgor_flcf7_field_types. A type added by the filter becomes selectable;
	 * it is only rebuilt once it is ticked in the settings (or set through
	 * the filter jgor_flcf7_settings). Invalid filter results fall back to
	 * BASE_TYPES.
	 *
	 * @return string[] Sanitized, unique base types.
	 */
	public function field_types(): array {
		/**
		 * Filters the Contact Form 7 base types the plugin can turn into
		 * floating-label fields.
		 *
		 * Return the list of base types (as in the form-tag, e.g. "text" for
		 * "text*") the settings screen offers. Types outside this list are
		 * never rebuilt, whatever the stored settings say.
		 *
		 * @param string[] $types Base types, by default BASE_TYPES.
		 */
		$filtered = apply_filters( 'jgor_flcf7_field_types', self::BASE_TYPES );

		if ( ! is_array( $filtered ) ) {
			return self::BASE_TYPES;
		}

		$clean = array();
		foreach ( $filtered as $type ) {
			if ( ! is_string( $type ) ) {
				continue;
			}

			$type = sanitize_key( $type );
			if ( '' !== $type && ! in_array( $type, $clean, true ) ) {
				$clean[] = $type;
			}
		}

		return empty( $clean ) ? self::BASE_TYPES : $clean;
	}

	/**
	 * Default values of every setting.
	 *
	 * Changing a default here changes the behaviour of sites that never
	 * saved the settings screen. The default type list is BASE_TYPES minus
	 * whatever the filter jgor_flcf7_field_types removed. An empty design
	 * array means: every custom property keeps its stylesheet default.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'mode_placeholder'  => true,
			'mode_label'        => true,
			'types'             => array_values( array_intersect( self::BASE_TYPES, $this->field_types() ) ),
			'style'             => 'outlined',
			'load_css'          => true,
			'required_marker'   => true,
			'submit_error_text' => self::DEFAULT_SUBMIT_ERROR_TEXT,
			'design'            => array(),
		);
	}

	/**
	 * Returns the stored settings merged with the defaults, without the filter.
	 *
	 * @return array<string, mixed>
	 */
	public function stored(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $this->defaults(), $stored );
	}

	/**
	 * Returns the effective settings: stored values, defaults for missing
	 * keys, and values fixed in code through the filter.
	 *
	 * Every value the filter returns passes the same sanitizing as a form
	 * submit, with the stored value as fallback, so a typo in code cannot
	 * switch the plugin into an undefined state.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored         = $this->stored();
		$this->settings = $stored;
		$this->locked   = array();

		/**
		 * Filters the settings after they are read.
		 *
		 * Lets a site fix settings in code, for example in a must-use plugin
		 * or the theme. Return the array with the keys to override; keys
		 * that are left out keep their stored value. Overridden keys are
		 * shown disabled on the settings screen.
		 *
		 * @param array<string, mixed> $settings Stored settings merged with the defaults.
		 */
		$filtered = apply_filters( 'jgor_flcf7_settings', $stored );

		if ( is_array( $filtered ) ) {
			foreach ( $stored as $key => $value ) {
				if ( ! array_key_exists( $key, $filtered ) ) {
					continue;
				}

				$clean = $this->sanitize_value( $key, $filtered[ $key ], $value );
				if ( $clean !== $value ) {
					$this->settings[ $key ] = $clean;
					$this->locked[]         = $key;
				}
			}
		}

		return $this->settings;
	}

	/**
	 * Returns the keys whose value is fixed by the filter jgor_flcf7_settings.
	 *
	 * @return string[]
	 */
	public function locked_keys(): array {
		$this->all();

		return $this->locked;
	}

	/**
	 * Whether a setting is fixed by the filter jgor_flcf7_settings.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function is_locked( string $key ): bool {
		return in_array( $key, $this->locked_keys(), true );
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting key as listed in defaults().
	 * @return mixed Value, or null for an unknown key.
	 */
	public function get( string $key ) {
		$settings = $this->all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Returns the design values in effect: the stylesheet defaults with the
	 * stored (and filtered) values on top, keyed without prefix.
	 *
	 * @return array<string, string>
	 */
	public function design(): array {
		$design = $this->get( 'design' );
		$design = is_array( $design ) ? $design : array();
		$values = self::DESIGN_DEFAULTS;

		foreach ( $design as $key => $value ) {
			if ( ! is_string( $key ) || ! isset( $values[ $key ] ) ) {
				continue;
			}

			$clean = self::sanitize_design_value( $key, $value );
			if ( '' !== $clean ) {
				$values[ $key ] = $clean;
			}
		}

		return $values;
	}

	/**
	 * Returns the design values that differ from the stylesheet defaults.
	 *
	 * Module 3 prints exactly these as an inline style.
	 *
	 * @return array<string, string>
	 */
	public function design_overrides(): array {
		$overrides = array();

		foreach ( $this->design() as $key => $value ) {
			if ( self::DESIGN_DEFAULTS[ $key ] !== $value ) {
				$overrides[ $key ] = $value;
			}
		}

		return $overrides;
	}

	/**
	 * Returns the stylesheet defaults keyed by the full custom property name.
	 *
	 * @return array<string, string>
	 */
	public static function css_defaults(): array {
		$defaults = array();

		foreach ( self::DESIGN_DEFAULTS as $key => $value ) {
			$defaults[ self::PROPERTY_PREFIX . $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Drops the cached settings so the next read hits the option again.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->settings = null;
		$this->locked   = array();
	}

	/**
	 * Writes the defaults if the option does not exist yet.
	 *
	 * Called on activation. Existing settings are left untouched so a
	 * deactivate/activate cycle keeps the configuration.
	 *
	 * @return void
	 */
	public function ensure_option(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $this->defaults(), '', true );
		}
	}

	/**
	 * Sanitizes a raw settings array.
	 *
	 * Used as the sanitize callback of register_setting(), so it runs for the
	 * settings form as well as for update_option() in code and WP-CLI. Only
	 * known keys survive, every value is coerced to the type and range of its
	 * default; invalid values fall back to the default.
	 *
	 * A key that is fixed in code keeps its stored value: its field is
	 * disabled on the screen and not submitted, and the filter would override
	 * a new value anyway.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed> Clean settings.
	 */
	public function sanitize( $value ): array {
		$defaults = $this->defaults();
		$raw      = is_array( $value ) ? $value : array();
		$clean    = array();

		$this->reset_cache();
		$current = $this->stored();
		$locked  = $this->locked_keys();

		foreach ( $defaults as $key => $fallback ) {
			if ( in_array( $key, $locked, true ) ) {
				$clean[ $key ] = $this->sanitize_value( $key, $current[ $key ] ?? null, $fallback );
			} else {
				$clean[ $key ] = $this->sanitize_value( $key, $raw[ $key ] ?? null, $fallback );
			}
		}

		$this->reset_cache();

		return $clean;
	}

	/**
	 * Sanitizes one setting according to its key.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $raw      Raw value, null when missing.
	 * @param mixed  $fallback Default value.
	 * @return mixed Clean value.
	 */
	private function sanitize_value( string $key, $raw, $fallback ) {
		switch ( $key ) {
			case 'types':
				return $this->sanitize_types( $raw );

			case 'style':
				return $this->sanitize_choice( $raw, self::STYLES, $fallback );

			case 'submit_error_text':
				return $this->sanitize_text( $raw );

			case 'design':
				return $this->sanitize_design( $raw );
		}

		if ( is_bool( $fallback ) ) {
			return $this->sanitize_bool( $raw );
		}

		return $fallback;
	}

	/**
	 * Coerces a checkbox or boolean-like value to bool.
	 *
	 * A missing value (unticked checkbox) is false.
	 *
	 * @param mixed $raw Raw value.
	 * @return bool
	 */
	private function sanitize_bool( $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) || is_int( $raw ) ) {
			return in_array( (string) $raw, array( '1', 'true', 'on', 'yes' ), true );
		}

		return false;
	}

	/**
	 * Keeps a value only if it is one of the allowed choices.
	 *
	 * @param mixed    $raw      Raw value.
	 * @param string[] $allowed  Allowed values.
	 * @param mixed    $fallback Fallback.
	 * @return string
	 */
	private function sanitize_choice( $raw, array $allowed, $fallback ): string {
		$raw = is_string( $raw ) ? sanitize_key( $raw ) : '';

		return in_array( $raw, $allowed, true ) ? $raw : (string) $fallback;
	}

	/**
	 * Sanitizes a free text setting: plain text, trimmed, limited in length.
	 *
	 * An empty string is a valid choice (the submit button only changes
	 * colour on errors).
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	private function sanitize_text( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( $raw ), 0, self::SUBMIT_ERROR_TEXT_MAX );
	}

	/**
	 * Keeps only known design properties with valid, non-empty values.
	 *
	 * @param mixed $raw Raw value, an array property => value.
	 * @return array<string, string>
	 */
	private function sanitize_design( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_keys( self::DESIGN_TYPES ) as $key ) {
			$value = self::sanitize_design_value( $key, $raw[ $key ] ?? null );
			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes one design value according to the type of its property.
	 *
	 * Colours: "#" and three or six hex digits, lower-cased. Lengths: a
	 * number with px, em, rem or %, or a plain 0. Numbers: 0.5 to 1, at most
	 * two decimals. Times: a number with s or ms. Anything else, including an
	 * unknown property, gives '', which means "default".
	 *
	 * @param string $key Property name without prefix.
	 * @param mixed  $raw Raw value.
	 * @return string Clean value, or '' for the default.
	 */
	public static function sanitize_design_value( string $key, $raw ): string {
		if ( ! isset( self::DESIGN_TYPES[ $key ] ) || ! is_scalar( $raw ) ) {
			return '';
		}

		$value = strtolower( trim( (string) $raw ) );
		if ( '' === $value ) {
			return '';
		}

		$type = self::DESIGN_TYPES[ $key ];

		if ( 'color' === $type ) {
			return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
		}

		if ( 'length' === $type ) {
			return preg_match( '/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|em|rem|%))$/', $value ) ? $value : '';
		}

		if ( 'number' === $type ) {
			if ( ! is_numeric( $value ) ) {
				return '';
			}
			$number = (float) $value;
			if ( 0.5 > $number || 1 < $number ) {
				return '';
			}
			return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
		}

		// Remaining type: time.
		return preg_match( '/^(?:\d+(?:\.\d+)?|\.\d+)(?:s|ms)$/', $value ) ? $value : '';
	}

	/**
	 * Keeps only known field types, in the order of field_types().
	 *
	 * An empty list is a valid choice: it switches the rebuild off for every
	 * type without touching the two mode switches.
	 *
	 * @param mixed $raw Raw value, an array of type names.
	 * @return string[]
	 */
	private function sanitize_types( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$wanted = array();
		foreach ( $raw as $type ) {
			if ( is_string( $type ) ) {
				$wanted[] = sanitize_key( $type );
			}
		}

		return array_values( array_intersect( $this->field_types(), $wanted ) );
	}
}

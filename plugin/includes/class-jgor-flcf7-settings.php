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
	 * section 14); checkbox, radio, file, range, quiz and captcha never fit.
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
	 * whatever the filter jgor_flcf7_field_types removed.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'mode_placeholder' => true,
			'mode_label'       => true,
			'types'            => array_values( array_intersect( self::BASE_TYPES, $this->field_types() ) ),
			'style'            => 'outlined',
			'load_css'         => true,
			'required_marker'  => false,
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

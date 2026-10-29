<?php
/**
 * Module 2: rebuilds the form markup rendered by Contact Form 7.
 *
 * Hooks into the filter wpcf7_form_elements and turns every text-like field
 * the plugin recognises into a floating-label field: the control gets an id
 * and the class jgor-flcf7-control, its placeholder becomes a single space,
 * and a <label for> with the label text follows the control inside the
 * span.wpcf7-form-control-wrap. The label text comes from the enclosing or
 * linked <label> of the form template (label mode), from the placeholder
 * option of the form-tag (placeholder mode) or, for templates with neither
 * (the Contact Form 7 template before 5.4: <p>Text<br />[tag]</p>), from the
 * loose text in front of the field or in the paragraph before it (text
 * mode, off by default; see preceding_text()). A linked <label for> is found
 * through the id of the control or, when Contact Form 7 dropped that id
 * because the same form is rendered a second time on the page, through the
 * id option of the form-tag; the control then gets a fresh id and the label
 * points at it.
 *
 * What applies to one rendering is decided in layers, see form_settings():
 * the plugin settings, the additional settings of the form, the classes in
 * the html_class attribute of the shortcode or block, and filters.
 *
 * When at least one field was rebuilt, the submit button is wrapped in a
 * span.jgor-flcf7-submit (the input itself stays untouched) so the
 * stylesheet can colour it and lay an error text over it while the form
 * carries the class "invalid" that Contact Form 7 sets on validation errors.
 *
 * Nothing else changes: fields the module does not recognise keep their
 * markup, and when the rebuild fails at any point the original HTML is
 * returned untouched. The rebuild uses DOMDocument (ext-dom); the bootstrap
 * only starts this module when the extension is present.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns Contact Form 7 fields into floating-label fields.
 */
final class JGOR_FLCF7_Markup {

	/**
	 * Class added to the span.wpcf7-form-control-wrap of a rebuilt field.
	 */
	public const WRAP_CLASS = 'jgor-flcf7-wrap';

	/**
	 * Class added to the input or textarea of a rebuilt field.
	 */
	public const CONTROL_CLASS = 'jgor-flcf7-control';

	/**
	 * Class of the floating label.
	 */
	public const LABEL_CLASS = 'jgor-flcf7-label';

	/**
	 * Class of the span that replaces an enclosing <label> of the template.
	 */
	public const FIELD_CLASS = 'jgor-flcf7-field';

	/**
	 * Class added to the wrap when the control is a textarea.
	 */
	public const TEXTAREA_CLASS = 'jgor-flcf7-textarea';

	/**
	 * Class added to the wrap when the required marker is on.
	 */
	public const MARKER_CLASS = 'jgor-flcf7-marker';

	/**
	 * Class of the span that wraps the submit button.
	 */
	public const SUBMIT_CLASS = 'jgor-flcf7-submit';

	/**
	 * Class of the span with the error text laid over the submit button.
	 */
	public const SUBMIT_ERROR_CLASS = 'jgor-flcf7-submit-error';

	/**
	 * Prefix of the html_class classes that pick a variant for one placement
	 * (jgor-flcf7-style-outlined, jgor-flcf7-style-filled, jgor-flcf7-style-minimal).
	 */
	public const HTML_CLASS_STYLE_PREFIX = 'jgor-flcf7-style-';

	/**
	 * Prefix of the html_class classes that pick a focus effect for one
	 * placement (jgor-flcf7-effect-none, -glow, -pulse, -sweep). The wrap
	 * gets the class jgor-flcf7-<effect>, like the variant, unless the
	 * effect is "none".
	 */
	public const HTML_CLASS_EFFECT_PREFIX = 'jgor-flcf7-effect-';

	/**
	 * Id of the wrapper element the HTML fragment is parsed into.
	 */
	private const ROOT_ID = 'jgor-flcf7-root';

	/**
	 * Input types that never get a floating label, whatever the form-tag says.
	 *
	 * @var string[]
	 */
	private const BLOCKED_INPUT_TYPES = array( 'hidden', 'checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'image', 'range', 'color' );

	/**
	 * Inline elements whose text may be part of the text of an enclosing label.
	 *
	 * @var string[]
	 */
	private const INLINE_TAGS = array( 'strong', 'em', 'b', 'i', 'span', 'small', 'abbr', 'code', 'sup', 'sub', 'mark', 'u', 's' );

	/**
	 * Elements whose loose text in front of a wrap text mode may use: the
	 * paragraphs wpautop makes, a <div> of the template, or the fragment root
	 * (a <div>) when wpautop is off.
	 *
	 * @var string[]
	 */
	private const TEXT_PARENT_TAGS = array( 'p', 'div' );

	/**
	 * Plugin settings.
	 *
	 * @var JGOR_FLCF7_Settings
	 */
	private JGOR_FLCF7_Settings $settings;

	/**
	 * Ids present in the form being rebuilt, including the ones generated so far.
	 *
	 * @var array<string, true>
	 */
	private array $used_ids = array();

	/**
	 * Constructor.
	 *
	 * @param JGOR_FLCF7_Settings $settings Plugin settings.
	 */
	public function __construct( JGOR_FLCF7_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers the filter.
	 *
	 * Priority 20: Contact Form 7 applies wpautop before the filter runs, so
	 * the order among filters does not matter; 20 leaves room for other
	 * plugins that want to see the markup before the rebuild.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wpcf7_form_elements', array( $this, 'filter_form_elements' ), 20 );
	}

	/**
	 * Filter callback for wpcf7_form_elements: rebuilds the fields of the form.
	 *
	 * Every early return hands back the HTML exactly as received.
	 *
	 * @param mixed $html Form HTML with the form-tags replaced.
	 * @return mixed Rebuilt HTML, or the original value.
	 */
	public function filter_form_elements( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, 'wpcf7-form-control-wrap' ) ) {
			return $html;
		}

		$form = WPCF7_ContactForm::get_current();
		if ( ! $form instanceof WPCF7_ContactForm ) {
			return $html;
		}

		$config = $this->form_settings( $form );
		if ( null === $config ) {
			return $html;
		}

		$fields = $this->fields( $form, $config['types'] );
		if ( empty( $fields ) ) {
			return $html;
		}

		$rebuilt = $this->rebuild( $html, $form, $fields, $config );

		return null === $rebuilt ? $html : $rebuilt;
	}

	/**
	 * Determines the settings that apply to one rendering of a form.
	 *
	 * Layers, lowest first: the plugin settings; the additional settings of
	 * the form (floating_labels, floating_labels_style, floating_labels_effect,
	 * floating_labels_marker, floating_labels_types); the classes in the
	 * html_class attribute of the shortcode or block (jgor-flcf7-off,
	 * jgor-flcf7-on, jgor-flcf7-mode-*, jgor-flcf7-style-*, jgor-flcf7-effect-*,
	 * jgor-flcf7-marker-on, jgor-flcf7-marker-off); the filter
	 * jgor_flcf7_form_settings; the filters jgor_flcf7_enabled and
	 * jgor_flcf7_submit_error_text. Returns null when the form is skipped:
	 * disabled, no mode active or no field type allowed.
	 *
	 * @param WPCF7_ContactForm $form The contact form being rendered.
	 * @return array{placeholder: bool, label: bool, text: bool, types: string[], style: string, effect: string, marker: bool, submit_error: string}|null
	 */
	private function form_settings( WPCF7_ContactForm $form ): ?array {
		$known = $this->settings->field_types();
		$types = $this->settings->get( 'types' );

		$submit_error = $this->settings->get( 'submit_error_text' );
		$submit_error = is_string( $submit_error ) ? $submit_error : '';
		if ( JGOR_FLCF7_Settings::DEFAULT_SUBMIT_ERROR_TEXT === $submit_error ) {
			$submit_error = __( 'Please check the highlighted fields', 'floating-labels-for-cf7' );
		}

		$current = array(
			'enabled'           => true,
			'mode_placeholder'  => (bool) $this->settings->get( 'mode_placeholder' ),
			'mode_label'        => (bool) $this->settings->get( 'mode_label' ),
			'mode_text'         => (bool) $this->settings->get( 'mode_text' ),
			'types'             => is_array( $types ) ? array_values( array_intersect( $known, $types ) ) : array(),
			'style'             => (string) $this->settings->get( 'style' ),
			'effect'            => (string) $this->settings->get( 'effect' ),
			'required_marker'   => (bool) $this->settings->get( 'required_marker' ),
			'submit_error_text' => $submit_error,
		);

		$current = $this->apply_additional_settings( $form, $current, $known );
		$current = $this->apply_html_class( $form, $current );

		/**
		 * Filters the settings that apply to one rendering of a contact form.
		 *
		 * Runs after the additional settings of the form and the classes of
		 * the shortcode or block have been read. Keys: enabled (bool),
		 * mode_placeholder (bool), mode_label (bool), mode_text (bool), types (string[] of
		 * Contact Form 7 base types), style ("outlined", "filled" or
		 * "minimal"), effect ("none", "glow", "pulse" or "sweep"),
		 * required_marker (bool) and submit_error_text (string, empty for
		 * colour only). Invalid values are ignored.
		 *
		 * @param array<string, mixed> $settings     Effective settings for this rendering.
		 * @param WPCF7_ContactForm    $contact_form The contact form being rendered.
		 */
		$filtered = apply_filters( 'jgor_flcf7_form_settings', $current, $form );
		if ( is_array( $filtered ) ) {
			$current = $this->merge_form_settings( $current, $filtered, $known );
		}

		/**
		 * Filters whether a contact form gets floating labels.
		 *
		 * Runs last, after the additional settings of the form, the classes
		 * of the shortcode or block and the filter jgor_flcf7_form_settings,
		 * so code can override all of them in both directions.
		 *
		 * @param bool              $enabled      Whether the form gets floating labels.
		 * @param WPCF7_ContactForm $contact_form The contact form being rendered.
		 */
		$enabled = (bool) apply_filters( 'jgor_flcf7_enabled', $current['enabled'], $form );

		if ( ! $enabled || ( ! $current['mode_placeholder'] && ! $current['mode_label'] && ! $current['mode_text'] ) || empty( $current['types'] ) ) {
			return null;
		}

		/**
		 * Filters the text shown on the submit button while the form has
		 * validation errors.
		 *
		 * Return an empty string to change only the colour of the button.
		 *
		 * @param string            $text         Text from the settings, translated while it is the default.
		 * @param WPCF7_ContactForm $contact_form The contact form being rendered.
		 */
		$submit_error = apply_filters( 'jgor_flcf7_submit_error_text', $current['submit_error_text'], $form );

		return array(
			'placeholder'  => $current['mode_placeholder'],
			'label'        => $current['mode_label'],
			'text'         => $current['mode_text'],
			'types'        => $current['types'],
			'style'        => $current['style'],
			'effect'       => $current['effect'],
			'marker'       => $current['required_marker'],
			'submit_error' => is_string( $submit_error ) ? sanitize_text_field( $submit_error ) : '',
		);
	}

	/**
	 * Applies the additional settings of the form (tab "Additional Settings").
	 *
	 * Lines: "floating_labels: off" (also false, no, 0), "floating_labels:
	 * placeholder", "floating_labels: label" or "floating_labels: text" (only
	 * that mode),
	 * "floating_labels_style: outlined|filled|minimal",
	 * "floating_labels_effect: none|glow|pulse|sweep",
	 * "floating_labels_marker: on|off" and "floating_labels_types: text email"
	 * (space or comma separated base types; an empty list switches every
	 * type off). Unknown values are ignored.
	 *
	 * @param WPCF7_ContactForm    $form    The contact form being rendered.
	 * @param array<string, mixed> $current Settings so far.
	 * @param string[]             $known   Base types the plugin may rebuild.
	 * @return array<string, mixed>
	 */
	private function apply_additional_settings( WPCF7_ContactForm $form, array $current, array $known ): array {
		$pref = $form->pref( 'floating_labels' );
		if ( is_string( $pref ) ) {
			switch ( strtolower( trim( $pref ) ) ) {
				case 'off':
				case 'false':
				case 'no':
				case '0':
					$current['enabled'] = false;
					break;

				case 'placeholder':
					$current['mode_placeholder'] = true;
					$current['mode_label']       = false;
					$current['mode_text']        = false;
					break;

				case 'label':
					$current['mode_placeholder'] = false;
					$current['mode_label']       = true;
					$current['mode_text']        = false;
					break;

				case 'text':
					$current['mode_placeholder'] = false;
					$current['mode_label']       = false;
					$current['mode_text']        = true;
					break;
			}
		}

		$pref = $form->pref( 'floating_labels_style' );
		if ( is_string( $pref ) ) {
			$style = sanitize_key( $pref );
			if ( in_array( $style, JGOR_FLCF7_Settings::STYLES, true ) ) {
				$current['style'] = $style;
			}
		}

		$pref = $form->pref( 'floating_labels_effect' );
		if ( is_string( $pref ) ) {
			$effect = sanitize_key( $pref );
			if ( in_array( $effect, JGOR_FLCF7_Settings::EFFECTS, true ) ) {
				$current['effect'] = $effect;
			}
		}

		$pref = $form->pref( 'floating_labels_marker' );
		if ( is_string( $pref ) ) {
			$marker = $this->to_bool( $pref );
			if ( null !== $marker ) {
				$current['required_marker'] = $marker;
			}
		}

		$pref = $form->pref( 'floating_labels_types' );
		if ( is_string( $pref ) ) {
			$current['types'] = $this->parse_types( $pref, $known );
		}

		return $current;
	}

	/**
	 * Applies the classes of the html_class attribute of the shortcode or block.
	 *
	 * The Contact Form 7 block offers the attribute as "Class" in its panel
	 * "Form attributes"; the shortcode takes html_class="...". Recognised
	 * classes: jgor-flcf7-off, jgor-flcf7-on, jgor-flcf7-mode-placeholder,
	 * jgor-flcf7-mode-label, jgor-flcf7-mode-text, jgor-flcf7-style-outlined, jgor-flcf7-style-filled,
	 * jgor-flcf7-style-minimal, jgor-flcf7-effect-none, jgor-flcf7-effect-glow,
	 * jgor-flcf7-effect-pulse, jgor-flcf7-effect-sweep, jgor-flcf7-marker-on,
	 * jgor-flcf7-marker-off.
	 * They differ from the classes the rebuild puts on the wrap on purpose:
	 * Contact Form 7 prints html_class on the <form>, where a wrap class
	 * would match the variant rules of the stylesheet for every field.
	 * shortcode_attr() exists since Contact Form 7 5.0.
	 *
	 * @param WPCF7_ContactForm    $form    The contact form being rendered.
	 * @param array<string, mixed> $current Settings so far.
	 * @return array<string, mixed>
	 */
	private function apply_html_class( WPCF7_ContactForm $form, array $current ): array {
		$classes = $form->shortcode_attr( 'html_class' );
		if ( ! is_string( $classes ) || '' === trim( $classes ) ) {
			return $current;
		}

		$tokens = preg_split( '/\s+/', strtolower( trim( $classes ) ) );
		if ( ! is_array( $tokens ) ) {
			return $current;
		}

		foreach ( $tokens as $token ) {
			switch ( $token ) {
				case 'jgor-flcf7-off':
					$current['enabled'] = false;
					break;

				case 'jgor-flcf7-on':
					$current['enabled'] = true;
					break;

				case 'jgor-flcf7-mode-placeholder':
					$current['mode_placeholder'] = true;
					$current['mode_label']       = false;
					$current['mode_text']        = false;
					break;

				case 'jgor-flcf7-mode-label':
					$current['mode_placeholder'] = false;
					$current['mode_label']       = true;
					$current['mode_text']        = false;
					break;

				case 'jgor-flcf7-mode-text':
					$current['mode_placeholder'] = false;
					$current['mode_label']       = false;
					$current['mode_text']        = true;
					break;

				case 'jgor-flcf7-marker-on':
					$current['required_marker'] = true;
					break;

				case 'jgor-flcf7-marker-off':
					$current['required_marker'] = false;
					break;

				default:
					if ( str_starts_with( $token, self::HTML_CLASS_STYLE_PREFIX ) ) {
						$style = substr( $token, strlen( self::HTML_CLASS_STYLE_PREFIX ) );
						if ( in_array( $style, JGOR_FLCF7_Settings::STYLES, true ) ) {
							$current['style'] = $style;
						}
					} elseif ( str_starts_with( $token, self::HTML_CLASS_EFFECT_PREFIX ) ) {
						$effect = substr( $token, strlen( self::HTML_CLASS_EFFECT_PREFIX ) );
						if ( in_array( $effect, JGOR_FLCF7_Settings::EFFECTS, true ) ) {
							$current['effect'] = $effect;
						}
					}
			}
		}

		return $current;
	}

	/**
	 * Merges the result of the filter jgor_flcf7_form_settings into the settings.
	 *
	 * Only known keys with valid values are taken over.
	 *
	 * @param array<string, mixed> $current  Settings so far.
	 * @param array<string, mixed> $filtered Array returned by the filter.
	 * @param string[]             $known    Base types the plugin may rebuild.
	 * @return array<string, mixed>
	 */
	private function merge_form_settings( array $current, array $filtered, array $known ): array {
		foreach ( array( 'enabled', 'mode_placeholder', 'mode_label', 'mode_text', 'required_marker' ) as $key ) {
			if ( ! array_key_exists( $key, $filtered ) ) {
				continue;
			}

			$value = $this->to_bool( $filtered[ $key ] );
			if ( null !== $value ) {
				$current[ $key ] = $value;
			}
		}

		if ( isset( $filtered['style'] ) && is_string( $filtered['style'] ) ) {
			$style = sanitize_key( $filtered['style'] );
			if ( in_array( $style, JGOR_FLCF7_Settings::STYLES, true ) ) {
				$current['style'] = $style;
			}
		}

		if ( isset( $filtered['effect'] ) && is_string( $filtered['effect'] ) ) {
			$effect = sanitize_key( $filtered['effect'] );
			if ( in_array( $effect, JGOR_FLCF7_Settings::EFFECTS, true ) ) {
				$current['effect'] = $effect;
			}
		}

		if ( isset( $filtered['types'] ) && is_array( $filtered['types'] ) ) {
			$wanted = array();
			foreach ( $filtered['types'] as $type ) {
				if ( is_string( $type ) ) {
					$wanted[] = sanitize_key( $type );
				}
			}
			$current['types'] = array_values( array_intersect( $known, $wanted ) );
		}

		if ( isset( $filtered['submit_error_text'] ) && is_string( $filtered['submit_error_text'] ) ) {
			$current['submit_error_text'] = sanitize_text_field( $filtered['submit_error_text'] );
		}

		return $current;
	}

	/**
	 * Reads a boolean from a setting value.
	 *
	 * Accepts bool, int and the strings on/off, true/false, yes/no, 1/0
	 * (case-insensitive). Anything else is null, which callers treat as
	 * "leave unchanged".
	 *
	 * @param mixed $value Raw value.
	 * @return bool|null
	 */
	private function to_bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return 0 !== $value;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		switch ( strtolower( trim( $value ) ) ) {
			case 'on':
			case 'true':
			case 'yes':
			case '1':
				return true;

			case 'off':
			case 'false':
			case 'no':
			case '0':
				return false;
		}

		return null;
	}

	/**
	 * Parses a list of base types from an additional setting.
	 *
	 * @param string   $setting Space or comma separated base types.
	 * @param string[] $known   Base types the plugin may rebuild.
	 * @return string[] Known types in the order of $known; empty when none match.
	 */
	private function parse_types( string $setting, array $known ): array {
		$parts = preg_split( '/[\s,]+/', strtolower( trim( $setting ) ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$wanted = array();
		foreach ( $parts as $part ) {
			$part = sanitize_key( $part );
			if ( '' !== $part ) {
				$wanted[] = $part;
			}
		}

		return array_values( array_intersect( $known, $wanted ) );
	}

	/**
	 * Names of the form-tags the module may rebuild, mapped to their base
	 * type and id option.
	 *
	 * Uses the form-tags of the template instead of guessing from attributes:
	 * only tags whose base type is allowed for this rendering and that do not
	 * carry the option "nofloat" qualify. A name used by two tags of
	 * different types is ambiguous and skipped. The id option (id:...) is
	 * kept because Contact Form 7 prints every id option only once per
	 * request: when the same form is rendered twice on a page, the second
	 * control has no id, while the <label for> of the template still names
	 * the option (see linked_label()).
	 *
	 * @param WPCF7_ContactForm $form  The contact form being rendered.
	 * @param string[]          $types Base types allowed for this rendering.
	 * @return array<string, array{type: string, id: string}> Field name => base type and id option ('' when none).
	 */
	private function fields( WPCF7_ContactForm $form, array $types ): array {
		if ( empty( $types ) ) {
			return array();
		}

		$fields    = array();
		$ambiguous = array();

		foreach ( $form->scan_form_tags() as $tag ) {
			$name     = (string) $tag->name;
			$basetype = (string) $tag->basetype;

			if ( '' === $name || $tag->has_option( 'nofloat' ) ) {
				$ambiguous[ $name ] = true;
				continue;
			}

			if ( ! in_array( $basetype, $types, true ) ) {
				continue;
			}

			if ( isset( $fields[ $name ] ) && $fields[ $name ]['type'] !== $basetype ) {
				$ambiguous[ $name ] = true;
				continue;
			}

			$id = $tag->get_option( 'id', 'id', true );

			$fields[ $name ] = array(
				'type' => $basetype,
				'id'   => is_string( $id ) ? $id : '',
			);
		}

		return array_diff_key( $fields, $ambiguous );
	}

	/**
	 * Parses the form HTML, rebuilds the qualifying fields and serialises it again.
	 *
	 * @param string                                         $html   Form HTML.
	 * @param WPCF7_ContactForm                              $form   The contact form being rendered.
	 * @param array<string, array{type: string, id: string}> $fields Field names mapped to base type and id option, see fields().
	 * @param array<string, mixed>                           $config Settings for this rendering, see form_settings().
	 * @return string|null Rebuilt HTML, or null when nothing changed or parsing failed.
	 *
	 * @phpstan-param array{placeholder: bool, label: bool, text: bool, types: string[], style: string, effect: string, marker: bool, submit_error: string} $config
	 */
	private function rebuild( string $html, WPCF7_ContactForm $form, array $fields, array $config ): ?string {
		$loaded = $this->load( $html );
		if ( null === $loaded ) {
			return null;
		}
		list( $dom, $root ) = $loaded;

		$xpath = new DOMXPath( $dom );
		$wraps = $xpath->query( './/span[contains(concat(" ", normalize-space(@class), " "), " wpcf7-form-control-wrap ")]', $root );
		if ( false === $wraps || 0 === $wraps->length ) {
			return null;
		}

		$this->used_ids = $this->collect_ids( $xpath, $root );

		$unit_tag = (string) $form->unit_tag();
		$changed  = 0;

		foreach ( iterator_to_array( $wraps ) as $wrap ) {
			if ( ! $wrap instanceof DOMElement ) {
				continue;
			}

			$name = $wrap->getAttribute( 'data-name' );
			if ( '' === $name || ! isset( $fields[ $name ] ) || $this->has_class( $wrap, self::WRAP_CLASS ) ) {
				continue;
			}

			$control = $this->control( $wrap );
			if ( null === $control ) {
				continue;
			}

			$text      = null;
			$label     = null;
			$enclosing = null;

			if ( $config['label'] ) {
				$enclosing = $this->enclosing_label( $wrap );
				if ( null !== $enclosing ) {
					$text = $this->label_text( $enclosing, $wrap );
					if ( null === $text ) {
						$enclosing = null;
					}
				}

				if ( null === $text ) {
					$label = $this->linked_label( $xpath, $root, $control, $fields[ $name ]['id'] );
					if ( null !== $label ) {
						$text = $this->normalize_text( $label->textContent );
						if ( '' === $text ) {
							$label = null;
							$text  = null;
						}
					}
				}
			}

			if ( null === $text && $config['placeholder'] ) {
				$text = $this->normalize_text( $control->getAttribute( 'placeholder' ) );
				if ( '' === $text ) {
					$text = null;
				}
			}

			$preceding = null;
			if ( null === $text && $config['text'] ) {
				$preceding = $this->preceding_text( $wrap );
				if ( null !== $preceding ) {
					$text = $preceding['text'];
				}
			}

			if ( null === $text ) {
				continue;
			}

			/**
			 * Filters the text of a floating label.
			 *
			 * The text is plain text (entities decoded); it is escaped when
			 * the markup is serialised. Return an empty string to leave the
			 * field untouched.
			 *
			 * @param string            $text         Label text.
			 * @param string            $name         Field name (name of the form-tag).
			 * @param WPCF7_ContactForm $contact_form The contact form being rendered.
			 */
			$filtered = apply_filters( 'jgor_flcf7_label_text', $text, $name, $form );
			if ( ! is_string( $filtered ) ) {
				continue;
			}
			$filtered = $this->normalize_text( $filtered );
			if ( '' === $filtered ) {
				continue;
			}

			$this->apply( $dom, $wrap, $control, $name, $unit_tag, $config['style'], $config['effect'], $config['marker'], $filtered, $text, $label, $enclosing );

			// Text mode: the loose text now lives in the label.
			if ( null !== $preceding ) {
				foreach ( $preceding['nodes'] as $node ) {
					if ( null !== $node->parentNode ) {
						$node->parentNode->removeChild( $node );
					}
				}
			}
			++$changed;
		}

		if ( 0 === $changed ) {
			return null;
		}

		$this->wrap_submit( $dom, $xpath, $root, $config['submit_error'] );

		return $this->save( $dom, $root );
	}

	/**
	 * Wraps the submit button(s) so the stylesheet can mark them on errors.
	 *
	 * The input itself stays untouched. It moves into a span.jgor-flcf7-submit;
	 * when a text is set, a span.jgor-flcf7-submit-error with that text follows
	 * it, and the stylesheet lays the text over the button while the form
	 * carries the class "invalid" that Contact Form 7 sets on validation
	 * errors (by its script and, without Ajax, on the server).
	 *
	 * @param DOMDocument $dom   Document.
	 * @param DOMXPath    $xpath XPath on the document.
	 * @param DOMElement  $root  Wrapper element of the fragment.
	 * @param string      $text  Error text; empty changes only the colour.
	 * @return void
	 */
	private function wrap_submit( DOMDocument $dom, DOMXPath $xpath, DOMElement $root, string $text ): void {
		$submits = $xpath->query( './/input[@type="submit"][contains(concat(" ", normalize-space(@class), " "), " wpcf7-submit ")]', $root );
		if ( false === $submits ) {
			return;
		}

		foreach ( iterator_to_array( $submits ) as $submit ) {
			if ( ! $submit instanceof DOMElement ) {
				continue;
			}

			$parent = $submit->parentNode;
			if ( ! $parent instanceof DOMElement || $this->has_class( $parent, self::SUBMIT_CLASS ) ) {
				continue;
			}

			$wrap = $dom->createElement( 'span' );
			$wrap->setAttribute( 'class', self::SUBMIT_CLASS );
			$parent->insertBefore( $wrap, $submit );
			$wrap->appendChild( $submit );

			if ( '' === $text ) {
				continue;
			}

			$error = $dom->createElement( 'span' );
			$error->setAttribute( 'class', self::SUBMIT_ERROR_CLASS );
			$error->setAttribute( 'aria-hidden', 'true' );
			$error->appendChild( $dom->createTextNode( $text ) );
			$wrap->appendChild( $error );
		}
	}

	/**
	 * Rebuilds one field in the DOM.
	 *
	 * @param DOMDocument     $dom           Document.
	 * @param DOMElement      $wrap          The span.wpcf7-form-control-wrap.
	 * @param DOMElement      $control       The input or textarea inside the wrap.
	 * @param string          $name          Field name.
	 * @param string          $unit_tag      Unit-tag of the form instance.
	 * @param string          $style         CSS variant for this rendering.
	 * @param string          $effect        Focus effect for this rendering ("none" for no class).
	 * @param bool            $marker        Whether required fields get the marker class.
	 * @param string          $text          Label text after the filter.
	 * @param string          $original_text Label text before the filter.
	 * @param DOMElement|null $label         Existing <label for> to move into the wrap, if any.
	 * @param DOMElement|null $enclosing     Enclosing <label> of the template to replace, if any.
	 * @return void
	 */
	private function apply( DOMDocument $dom, DOMElement $wrap, DOMElement $control, string $name, string $unit_tag, string $style, string $effect, bool $marker, string $text, string $original_text, ?DOMElement $label, ?DOMElement $enclosing ): void {
		$id = $control->getAttribute( 'id' );
		if ( '' === $id ) {
			$id = $this->unique_id( $unit_tag . '-fl-' . $name );
			$control->setAttribute( 'id', $id );
		}

		// A single space keeps :placeholder-shown working without showing text.
		$control->setAttribute( 'placeholder', ' ' );
		$this->add_class( $control, self::CONTROL_CLASS );
		$this->add_class( $wrap, self::WRAP_CLASS );
		$this->add_class( $wrap, 'jgor-flcf7-' . $style );
		if ( 'textarea' === strtolower( $control->tagName ) ) {
			$this->add_class( $wrap, self::TEXTAREA_CLASS );
		}
		if ( $marker ) {
			$this->add_class( $wrap, self::MARKER_CLASS );
		}
		if ( 'none' !== $effect && in_array( $effect, JGOR_FLCF7_Settings::EFFECTS, true ) ) {
			$this->add_class( $wrap, 'jgor-flcf7-' . $effect );
		}

		$former_parent = null;
		$trailing_br   = null;

		if ( null === $label ) {
			$label = $dom->createElement( 'label' );
			$label->appendChild( $dom->createTextNode( $text ) );
		} else {
			$former_parent = $label->parentNode;
			$trailing_br   = $this->trailing_br( $label );

			// The filter changed the text: inline markup of the label cannot
			// be kept, so the content is replaced with the new plain text.
			if ( $text !== $original_text ) {
				while ( null !== $label->firstChild ) {
					$label->removeChild( $label->firstChild );
				}
				$label->appendChild( $dom->createTextNode( $text ) );
			}
		}

		$label->setAttribute( 'for', $id );
		$this->add_class( $label, self::LABEL_CLASS );

		// Directly behind the control, so sibling selectors reach the label;
		// an existing validation tip moves behind the label.
		$wrap->insertBefore( $label, $control->nextSibling );

		// wpautop puts a <br> between a linked label and its field; without
		// the label it would only add an empty line above the field.
		if ( null !== $trailing_br && null !== $trailing_br->parentNode ) {
			$trailing_br->parentNode->removeChild( $trailing_br );
		}

		if ( $former_parent instanceof DOMElement ) {
			$this->remove_if_empty( $former_parent );
		}

		if ( null !== $enclosing ) {
			$parent = $enclosing->parentNode;
			if ( null === $parent ) {
				return;
			}

			// A <label> may not contain another <label>: the enclosing label
			// becomes a span; its text already lives in the new label.
			$field = $dom->createElement( 'span' );
			$field->setAttribute( 'class', trim( $enclosing->getAttribute( 'class' ) . ' ' . self::FIELD_CLASS ) );
			$parent->insertBefore( $field, $enclosing );
			$field->appendChild( $wrap );
			$parent->removeChild( $enclosing );
		}
	}

	/**
	 * Returns the single text-like control inside a wrap, or null.
	 *
	 * Besides the control the wrap may only hold whitespace and the
	 * validation tip Contact Form 7 renders for non-Ajax submissions.
	 *
	 * @param DOMElement $wrap The span.wpcf7-form-control-wrap.
	 * @return DOMElement|null
	 */
	private function control( DOMElement $wrap ): ?DOMElement {
		$control = null;

		foreach ( $wrap->childNodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$tag = strtolower( $node->tagName );

				if ( 'input' === $tag || 'textarea' === $tag ) {
					if ( null !== $control ) {
						return null;
					}
					$control = $node;
				} elseif ( 'span' === $tag && $this->has_class( $node, 'wpcf7-not-valid-tip' ) ) {
					continue;
				} else {
					return null;
				}
			} elseif ( $node instanceof DOMText ) {
				if ( '' !== trim( (string) $node->nodeValue ) ) {
					return null;
				}
			}
		}

		if ( null === $control ) {
			return null;
		}

		if ( 'input' === strtolower( $control->tagName ) ) {
			$type = strtolower( $control->getAttribute( 'type' ) );
			if ( in_array( $type, self::BLOCKED_INPUT_TYPES, true ) ) {
				return null;
			}
		}

		return $control;
	}

	/**
	 * Returns the <label> that directly encloses the wrap, or null.
	 *
	 * @param DOMElement $wrap The span.wpcf7-form-control-wrap.
	 * @return DOMElement|null
	 */
	private function enclosing_label( DOMElement $wrap ): ?DOMElement {
		$parent = $wrap->parentNode;

		if ( $parent instanceof DOMElement && 'label' === strtolower( $parent->tagName ) ) {
			return $parent;
		}

		return null;
	}

	/**
	 * Extracts the label text from an enclosing <label>.
	 *
	 * Allowed children: text, <br>, inline elements without controls, and
	 * exactly one wrap (the one being rebuilt). Anything else means the
	 * template does something the module does not understand; null then
	 * leaves the label alone and lets placeholder mode take over.
	 *
	 * @param DOMElement $label The enclosing <label>.
	 * @param DOMElement $wrap  The span.wpcf7-form-control-wrap.
	 * @return string|null Normalised text, or null.
	 */
	private function label_text( DOMElement $label, DOMElement $wrap ): ?string {
		$parts = array();
		$wraps = 0;

		foreach ( $label->childNodes as $node ) {
			if ( $node->isSameNode( $wrap ) ) {
				++$wraps;
				continue;
			}

			if ( $node instanceof DOMText ) {
				$parts[] = (string) $node->nodeValue;
				continue;
			}

			if ( $node instanceof DOMComment ) {
				continue;
			}

			if ( ! $node instanceof DOMElement ) {
				return null;
			}

			$tag = strtolower( $node->tagName );

			if ( 'br' === $tag ) {
				$parts[] = ' ';
				continue;
			}

			if ( in_array( $tag, self::INLINE_TAGS, true ) && ! $this->contains_control( $node ) ) {
				$parts[] = $node->textContent;
				continue;
			}

			return null;
		}

		if ( 1 !== $wraps ) {
			return null;
		}

		$text = $this->normalize_text( implode( '', $parts ) );

		return '' === $text ? null : $text;
	}

	/**
	 * Collects the loose text that belongs to a wrap for text mode.
	 *
	 * Contact Form 7 formats the template with its HTML formatter: a line
	 * that ends with <br /> starts a new paragraph, so the template of
	 * Contact Form 7 before 5.4 (<p>Text<br />[tag]</p>) renders as
	 * <p>Text</p><p>[wrap]</p>, while "Text\n[tag]" renders as
	 * <p>Text<br />[wrap]</p> and "Text [tag]" as <p>Text [wrap]</p>. Two
	 * searches cover both shapes, in this order: the loose text in front of
	 * the wrap inside its parent (text_before_wrap()), then the paragraph
	 * before a paragraph that holds nothing but the wrap
	 * (text_in_previous_paragraph()). The text becomes the label text, plain
	 * text only; the nodes it came from are removed once the field is
	 * rebuilt.
	 *
	 * @param DOMElement $wrap The span.wpcf7-form-control-wrap.
	 * @return array{text: string, nodes: DOMNode[]}|null Normalised text and the nodes to remove, or null when there is no text.
	 */
	private function preceding_text( DOMElement $wrap ): ?array {
		$parent = $wrap->parentNode;
		if ( ! $parent instanceof DOMElement || ! in_array( strtolower( $parent->tagName ), self::TEXT_PARENT_TAGS, true ) ) {
			return null;
		}

		$found = $this->text_before_wrap( $wrap );
		if ( null !== $found ) {
			return $found;
		}

		return $this->text_in_previous_paragraph( $wrap, $parent );
	}

	/**
	 * Text in front of the wrap inside the same parent.
	 *
	 * Walks back through the siblings of the wrap: first whitespace, then at
	 * most one <br>, then text nodes and text-source elements (see
	 * is_text_source()), up to the start of the parent, another <br>, another
	 * wrap or any other element. Comments are passed over and stay in place.
	 * The collected nodes, including the <br> directly in front of the wrap,
	 * are the nodes to remove; a <br> that marked the boundary to a previous
	 * field stays, so the fields keep their own lines.
	 *
	 * @param DOMElement $wrap The span.wpcf7-form-control-wrap.
	 * @return array{text: string, nodes: DOMNode[]}|null
	 */
	private function text_before_wrap( DOMElement $wrap ): ?array {
		$nodes = array();
		$node  = $wrap->previousSibling;

		while ( null !== $node && $this->is_blank_text( $node ) ) {
			array_unshift( $nodes, $node );
			$node = $node->previousSibling;
		}

		if ( $node instanceof DOMElement && 'br' === strtolower( $node->tagName ) ) {
			array_unshift( $nodes, $node );
			$node = $node->previousSibling;
		}

		$parts = array();
		while ( null !== $node ) {
			if ( $node instanceof DOMComment ) {
				$node = $node->previousSibling;
				continue;
			}

			if ( $node instanceof DOMText ) {
				array_unshift( $parts, (string) $node->nodeValue );
			} elseif ( $node instanceof DOMElement && $this->is_text_source( $node ) ) {
				array_unshift( $parts, $node->textContent );
			} else {
				break;
			}

			array_unshift( $nodes, $node );
			$node = $node->previousSibling;
		}

		$text = $this->normalize_text( implode( '', $parts ) );
		if ( '' === $text ) {
			return null;
		}

		return array(
			'text'  => $text,
			'nodes' => $nodes,
		);
	}

	/**
	 * Text in the paragraph before a paragraph that holds only the wrap.
	 *
	 * The previous element sibling of the wrap's <p> (whitespace and comments
	 * in between are skipped) must be a <p> made of text, <br>, comments and
	 * text-source elements only; that whole paragraph is the node to remove.
	 * A paragraph of explanation directly in front of a field without a
	 * label of its own is taken as well: text mode is a heuristic, which is
	 * why it is off by default.
	 *
	 * @param DOMElement $wrap      The span.wpcf7-form-control-wrap.
	 * @param DOMElement $paragraph Parent of the wrap.
	 * @return array{text: string, nodes: DOMNode[]}|null
	 */
	private function text_in_previous_paragraph( DOMElement $wrap, DOMElement $paragraph ): ?array {
		if ( 'p' !== strtolower( $paragraph->tagName ) ) {
			return null;
		}

		foreach ( $paragraph->childNodes as $node ) {
			if ( $node->isSameNode( $wrap ) || $node instanceof DOMComment || $this->is_blank_text( $node ) ) {
				continue;
			}

			return null;
		}

		$previous = $paragraph->previousSibling;
		while ( null !== $previous && ( $previous instanceof DOMComment || $this->is_blank_text( $previous ) ) ) {
			$previous = $previous->previousSibling;
		}

		if ( ! $previous instanceof DOMElement || 'p' !== strtolower( $previous->tagName ) ) {
			return null;
		}

		$text = $this->loose_text( $previous );
		if ( null === $text ) {
			return null;
		}

		return array(
			'text'  => $text,
			'nodes' => array( $previous ),
		);
	}

	/**
	 * Returns the text of an element made of text, <br>, comments and
	 * text-source elements only, or null when it holds anything else.
	 *
	 * @param DOMElement $element Element.
	 * @return string|null Normalised text, or null.
	 */
	private function loose_text( DOMElement $element ): ?string {
		$parts = array();

		foreach ( $element->childNodes as $node ) {
			if ( $node instanceof DOMText ) {
				$parts[] = (string) $node->nodeValue;
			} elseif ( $node instanceof DOMComment ) {
				continue;
			} elseif ( $node instanceof DOMElement && 'br' === strtolower( $node->tagName ) ) {
				$parts[] = ' ';
			} elseif ( $node instanceof DOMElement && $this->is_text_source( $node ) ) {
				$parts[] = $node->textContent;
			} else {
				return null;
			}
		}

		$text = $this->normalize_text( implode( '', $parts ) );

		return '' === $text ? null : $text;
	}

	/**
	 * Whether an element may contribute its text in text mode: an inline
	 * element from INLINE_TAGS, or a <label> without a "for" attribute (a
	 * label that is neither enclosing nor linked), in both cases without a
	 * control inside.
	 *
	 * @param DOMElement $element Element.
	 * @return bool
	 */
	private function is_text_source( DOMElement $element ): bool {
		$tag = strtolower( $element->tagName );

		if ( 'label' === $tag ) {
			return ! $element->hasAttribute( 'for' ) && ! $this->contains_control( $element );
		}

		return in_array( $tag, self::INLINE_TAGS, true ) && ! $this->contains_control( $element );
	}

	/**
	 * Whether a node is a text node with nothing but whitespace (no-break
	 * spaces included).
	 *
	 * @param DOMNode $node Node.
	 * @return bool
	 */
	private function is_blank_text( DOMNode $node ): bool {
		return $node instanceof DOMText && '' === $this->normalize_text( (string) $node->nodeValue );
	}

	/**
	 * Returns the single <label for> pointing at the control, or null.
	 *
	 * The label is looked up by the id of the control or, when the control
	 * has none, by the id option of its form-tag: Contact Form 7 prints every
	 * id option only once per request (WPCF7_FormTag::get_id_option()), so
	 * the second rendering of a form on one page has no id on the control
	 * while the <label for> of the template still names the option. apply()
	 * then gives the control a fresh id and points the label at it. The
	 * label must not contain a control itself (that is the enclosing case,
	 * handled before).
	 *
	 * @param DOMXPath   $xpath     XPath on the document.
	 * @param DOMElement $root      Wrapper element of the fragment.
	 * @param DOMElement $control   The input or textarea.
	 * @param string     $id_option Id option of the form-tag, '' when none.
	 * @return DOMElement|null
	 */
	private function linked_label( DOMXPath $xpath, DOMElement $root, DOMElement $control, string $id_option ): ?DOMElement {
		$id = $control->getAttribute( 'id' );
		if ( '' === $id ) {
			$id = $id_option;
		}

		if ( '' === $id || str_contains( $id, "'" ) ) {
			return null;
		}

		$labels = $xpath->query( ".//label[@for='" . $id . "']", $root );
		if ( false === $labels || 1 !== $labels->length ) {
			return null;
		}

		$label = $labels->item( 0 );
		if ( ! $label instanceof DOMElement || $this->contains_control( $label ) ) {
			return null;
		}

		return $label;
	}

	/**
	 * Whether an element contains a form control.
	 *
	 * @param DOMElement $element Element to inspect.
	 * @return bool
	 */
	private function contains_control( DOMElement $element ): bool {
		foreach ( array( 'input', 'textarea', 'select', 'button', 'label' ) as $tag ) {
			if ( $element->getElementsByTagName( $tag )->length > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the <br> that follows an element (whitespace in between allowed), or null.
	 *
	 * @param DOMElement $element Element.
	 * @return DOMElement|null
	 */
	private function trailing_br( DOMElement $element ): ?DOMElement {
		$node = $element->nextSibling;

		while ( $node instanceof DOMText && '' === trim( (string) $node->nodeValue ) ) {
			$node = $node->nextSibling;
		}

		if ( $node instanceof DOMElement && 'br' === strtolower( $node->tagName ) ) {
			return $node;
		}

		return null;
	}

	/**
	 * Removes a <p> that is left empty after its label moved away.
	 *
	 * @param DOMElement $element Former parent of the moved label.
	 * @return void
	 */
	private function remove_if_empty( DOMElement $element ): void {
		if ( 'p' !== strtolower( $element->tagName ) || null === $element->parentNode ) {
			return;
		}

		foreach ( $element->childNodes as $node ) {
			if ( ! $node instanceof DOMText || '' !== trim( (string) $node->nodeValue ) ) {
				return;
			}
		}

		$element->parentNode->removeChild( $element );
	}

	/**
	 * Collects every id used in the fragment.
	 *
	 * @param DOMXPath   $xpath XPath on the document.
	 * @param DOMElement $root  Wrapper element of the fragment.
	 * @return array<string, true>
	 */
	private function collect_ids( DOMXPath $xpath, DOMElement $root ): array {
		$ids   = array();
		$nodes = $xpath->query( './/*[@id]', $root );

		if ( false !== $nodes ) {
			foreach ( $nodes as $node ) {
				if ( $node instanceof DOMElement ) {
					$ids[ $node->getAttribute( 'id' ) ] = true;
				}
			}
		}

		return $ids;
	}

	/**
	 * Builds an id that is unique within the fragment.
	 *
	 * Sanitised like Contact Form 7 sanitises its validation error ids
	 * (letters, digits, underscore and hyphen). The unit-tag keeps ids apart
	 * when the same form is rendered twice on a page.
	 *
	 * @param string $base Proposed id.
	 * @return string
	 */
	private function unique_id( string $base ): string {
		$base = (string) preg_replace( '/[^0-9a-z_-]+/i', '', $base );
		if ( '' === $base ) {
			$base = 'jgor-flcf7-field';
		}

		$id = $base;
		$n  = 2;
		while ( isset( $this->used_ids[ $id ] ) ) {
			$id = $base . '-' . $n;
			++$n;
		}

		$this->used_ids[ $id ] = true;

		return $id;
	}

	/**
	 * Collapses whitespace (including no-break spaces) and trims.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function normalize_text( string $text ): string {
		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}

	/**
	 * Whether an element carries a class.
	 *
	 * @param DOMElement $element    Element.
	 * @param string     $class_name Class name.
	 * @return bool
	 */
	private function has_class( DOMElement $element, string $class_name ): bool {
		$classes = preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) );

		return is_array( $classes ) && in_array( $class_name, $classes, true );
	}

	/**
	 * Adds a class to an element unless it is present already.
	 *
	 * @param DOMElement $element    Element.
	 * @param string     $class_name Class name.
	 * @return void
	 */
	private function add_class( DOMElement $element, string $class_name ): void {
		if ( $this->has_class( $element, $class_name ) ) {
			return;
		}

		$current = trim( $element->getAttribute( 'class' ) );
		$element->setAttribute( 'class', '' === $current ? $class_name : $current . ' ' . $class_name );
	}

	/**
	 * Parses the HTML fragment into a DOMDocument.
	 *
	 * The fragment is wrapped in a div so it has one root element; the XML
	 * declaration makes libxml read the bytes as UTF-8. Parser warnings
	 * (unknown HTML5 attributes and the like) are collected and discarded.
	 *
	 * @param string $html Form HTML.
	 * @return array{0: DOMDocument, 1: DOMElement}|null Document and wrapper, or null on failure.
	 */
	private function load( string $html ): ?array {
		$previous = libxml_use_internal_errors( true );

		$dom               = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = false;

		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"><div id="' . self::ROOT_ID . '">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $loaded ) {
			return null;
		}

		$root = $dom->documentElement;
		if ( ! $root instanceof DOMElement || self::ROOT_ID !== $root->getAttribute( 'id' ) ) {
			return null;
		}

		return array( $dom, $root );
	}

	/**
	 * Serialises the fragment and strips the wrapper again.
	 *
	 * @param DOMDocument $dom  Document.
	 * @param DOMElement  $root Wrapper element.
	 * @return string|null HTML, or null when the wrapper is not where expected.
	 */
	private function save( DOMDocument $dom, DOMElement $root ): ?string {
		$out = $dom->saveHTML( $root );
		if ( ! is_string( $out ) ) {
			return null;
		}

		$open  = '<div id="' . self::ROOT_ID . '">';
		$close = '</div>';

		if ( ! str_starts_with( $out, $open ) || ! str_ends_with( $out, $close ) ) {
			return null;
		}

		return substr( $out, strlen( $open ), strlen( $out ) - strlen( $open ) - strlen( $close ) );
	}
}

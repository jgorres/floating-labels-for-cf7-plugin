<?php
/**
 * PHPStan stubs for Contact Form 7 (checked against 6.2.4).
 *
 * Only the classes, methods and functions this plugin uses. Signatures follow
 * the Contact Form 7 source; return types that Contact Form 7 does not
 * declare are documented in the DocBlocks. This file is scanned by PHPStan
 * (phpstan.neon.dist, scanDirectories) and never loaded by WordPress.
 *
 * @package Floating_Labels_For_CF7
 */

/**
 * A contact form, see includes/contact-form.php in Contact Form 7.
 */
class WPCF7_ContactForm {

	/**
	 * Returns the contact form currently being rendered or processed.
	 *
	 * @return WPCF7_ContactForm|null
	 */
	public static function get_current() {}

	/**
	 * Returns a contact form by post, post ID or instance and makes it current.
	 *
	 * @param int|string|WP_Post|WPCF7_ContactForm|null $post Post object, ID or contact form.
	 * @return WPCF7_ContactForm|null
	 */
	public static function get_instance( $post ) {}

	/**
	 * Returns one property of the contact form (form, mail, additional_settings, ...).
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function prop( $name ) {}

	/**
	 * Returns the post ID of the contact form.
	 *
	 * @return int
	 */
	public function id() {}

	/**
	 * Returns a new, unsaved contact form from the default template.
	 *
	 * @param string|array<string, mixed> $options Options: title, locale.
	 * @return WPCF7_ContactForm
	 */
	public static function get_template( $options = '' ) {}

	/**
	 * Sets properties (form, mail, mail_2, messages, additional_settings).
	 *
	 * @param array<string, mixed> $properties Properties to set.
	 * @return array<string, mixed> All properties after the change.
	 */
	public function set_properties( $properties ) {}

	/**
	 * Saves the contact form as a post.
	 *
	 * @return int Post id, 0 on failure.
	 */
	public function save() {}

	/**
	 * Deletes the contact form permanently.
	 *
	 * @return bool
	 */
	public function delete() {}

	/**
	 * Returns the hash of the contact form (used as id in shortcodes).
	 *
	 * @param int $length Length of the hash.
	 * @return string
	 */
	public function hash( $length = 7 ) {}

	/**
	 * Returns the unit-tag of the current rendering (wpcf7-f{id}-p{post}-o{n}).
	 *
	 * @return string
	 */
	public function unit_tag() {}

	/**
	 * Returns the name (slug) of the contact form.
	 *
	 * @return string
	 */
	public function name() {}

	/**
	 * Returns the title of the contact form.
	 *
	 * @return string
	 */
	public function title() {}

	/**
	 * Renders the form HTML, including the wpcf7 wrapper.
	 *
	 * @param string|array<string, mixed> $options Rendering options.
	 * @return string
	 */
	public function form_html( $options = '' ) {}

	/**
	 * Returns one attribute of the shortcode or block that renders the form
	 * (html_id, html_name, html_title, html_class, output), null when unset.
	 *
	 * @param string $name Attribute name.
	 * @return string|null
	 */
	public function shortcode_attr( $name ) {}

	/**
	 * Returns the form-tags of the form template, optionally filtered.
	 *
	 * @param array<string, mixed>|string|null $cond Filter conditions (type, basetype, name, feature).
	 * @return WPCF7_FormTag[]
	 */
	public function scan_form_tags( $cond = null ) {}

	/**
	 * Returns the first value of an additional setting, null when unset.
	 *
	 * @param string $name Setting name.
	 * @return string|null
	 */
	public function pref( $name ) {}

	/**
	 * Returns the values of an additional setting.
	 *
	 * @param string $name Setting name.
	 * @param int    $max  Maximum number of values, -1 for all.
	 * @return string[]
	 */
	public function additional_setting( $name, $max = 1 ) {}

	/**
	 * Whether an additional setting is set to a true-ish value ("on", "true", "1").
	 *
	 * @param string $name Setting name.
	 * @return bool
	 */
	public function is_true( $name ) {}
}

/**
 * A parsed form-tag, see includes/form-tag.php in Contact Form 7.
 */
class WPCF7_FormTag implements ArrayAccess {

	/**
	 * Type including the required marker, e.g. "text*".
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Type without the required marker, e.g. "text".
	 *
	 * @var string
	 */
	public $basetype;

	/**
	 * Name as written in the form template.
	 *
	 * @var string
	 */
	public $raw_name = '';

	/**
	 * Sanitized field name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Options as written, e.g. "id:your-id", "placeholder", "nofloat".
	 *
	 * @var string[]
	 */
	public $options = array();

	/**
	 * Values as written (quoted strings in the form-tag).
	 *
	 * @var string[]
	 */
	public $raw_values = array();

	/**
	 * Values without pipes.
	 *
	 * @var string[]
	 */
	public $values = array();

	/**
	 * Pipes of the values.
	 *
	 * @var WPCF7_Pipes|null
	 */
	public $pipes;

	/**
	 * Labels of the values.
	 *
	 * @var string[]
	 */
	public $labels = array();

	/**
	 * Raw attribute string of the form-tag.
	 *
	 * @var string
	 */
	public $attr = '';

	/**
	 * Enclosed content of the form-tag.
	 *
	 * @var string
	 */
	public $content = '';

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $tag Parsed form-tag.
	 */
	public function __construct( $tag = array() ) {}

	/**
	 * Whether the type carries the required marker.
	 *
	 * @return bool
	 */
	public function is_required() {}

	/**
	 * Whether the form-tag has the option, with or without a value.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	public function has_option( $option_name ) {}

	/**
	 * Returns the value(s) of an option.
	 *
	 * @param string $option_name Option name.
	 * @param string $pattern     Regex pattern or pattern name for the value.
	 * @param bool   $single      Return only the first value.
	 * @return string|string[]|false
	 */
	public function get_option( $option_name, $pattern = '', $single = false ) {}

	/**
	 * Returns the value of the "id:" option.
	 *
	 * @return string|false
	 */
	public function get_id_option() {}

	/**
	 * Returns the values of the "class:" option merged with the defaults.
	 *
	 * @param string|string[] $default_classes Default classes.
	 * @return string|false
	 */
	public function get_class_option( $default_classes = '' ) {}

	/**
	 * ArrayAccess: sets an offset.
	 *
	 * @param mixed $offset Offset.
	 * @param mixed $value  Value.
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {}

	/**
	 * ArrayAccess: returns an offset.
	 *
	 * @param mixed $offset Offset.
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {}

	/**
	 * ArrayAccess: whether an offset exists.
	 *
	 * @param mixed $offset Offset.
	 * @return bool
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ) {}

	/**
	 * ArrayAccess: unsets an offset.
	 *
	 * @param mixed $offset Offset.
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {}
}

/**
 * Enqueues the Contact Form 7 stylesheet and fires the action wpcf7_enqueue_styles.
 *
 * @return void
 */
function wpcf7_enqueue_styles() {}

/**
 * Whether Contact Form 7 loads its stylesheet (constant WPCF7_LOAD_CSS, filter wpcf7_load_css).
 *
 * @return bool
 */
function wpcf7_load_css() {}

/**
 * Returns a contact form by post, post ID or instance.
 *
 * @param int|string|WP_Post|WPCF7_ContactForm|null $post Post object, ID or contact form.
 * @return WPCF7_ContactForm|null
 */
function wpcf7_contact_form( $post ) {}

/**
 * Returns the contact form currently being rendered or processed.
 *
 * @return WPCF7_ContactForm|null
 */
function wpcf7_get_current_contact_form(): ?WPCF7_ContactForm {}

/**
 * Sanitizes a unit-tag: keeps a-z, 0-9, underscore and hyphen.
 *
 * @param string $tag Unit-tag.
 * @return string
 */
function wpcf7_sanitize_unit_tag( $tag ) {}

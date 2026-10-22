<?php
/**
 * Module 2: rebuilds the form markup rendered by Contact Form 7.
 *
 * Hooks into the filter wpcf7_form_elements and turns every text-like field
 * the plugin recognises into a floating-label field: the control gets an id
 * and the class jgor-flcf7-control, its placeholder becomes a single space,
 * and a <label for> with the label text follows the control inside the
 * span.wpcf7-form-control-wrap. The label text comes from the enclosing or
 * linked <label> of the form template (label mode) or from the placeholder
 * option of the form-tag (placeholder mode).
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

		$modes = $this->modes( $form );
		if ( null === $modes ) {
			return $html;
		}

		$fields = $this->fields( $form );
		if ( empty( $fields ) ) {
			return $html;
		}

		$rebuilt = $this->rebuild( $html, $form, $fields, $modes );

		return null === $rebuilt ? $html : $rebuilt;
	}

	/**
	 * Determines which modes apply to a form, or null when the form is skipped.
	 *
	 * Order: plugin settings, then the additional setting "floating_labels"
	 * of the form (off, placeholder, label), then the filter
	 * jgor_flcf7_enabled.
	 *
	 * @param WPCF7_ContactForm $form The contact form being rendered.
	 * @return array{placeholder: bool, label: bool}|null
	 */
	private function modes( WPCF7_ContactForm $form ): ?array {
		$placeholder = (bool) $this->settings->get( 'mode_placeholder' );
		$label       = (bool) $this->settings->get( 'mode_label' );
		$enabled     = true;

		$pref = $form->pref( 'floating_labels' );
		if ( is_string( $pref ) ) {
			switch ( strtolower( trim( $pref ) ) ) {
				case 'off':
				case 'false':
				case 'no':
				case '0':
					$enabled = false;
					break;

				case 'placeholder':
					$placeholder = true;
					$label       = false;
					break;

				case 'label':
					$placeholder = false;
					$label       = true;
					break;
			}
		}

		/**
		 * Filters whether a contact form gets floating labels.
		 *
		 * Runs after the additional setting "floating_labels" of the form has
		 * been read, so code can override it in both directions.
		 *
		 * @param bool              $enabled      Whether the form gets floating labels.
		 * @param WPCF7_ContactForm $contact_form The contact form being rendered.
		 */
		$enabled = (bool) apply_filters( 'jgor_flcf7_enabled', $enabled, $form );

		if ( ! $enabled || ( ! $placeholder && ! $label ) ) {
			return null;
		}

		return array(
			'placeholder' => $placeholder,
			'label'       => $label,
		);
	}

	/**
	 * Names of the form-tags the module may rebuild, mapped to their base type.
	 *
	 * Uses the form-tags of the template instead of guessing from attributes:
	 * only tags whose base type is enabled in the settings and that do not
	 * carry the option "nofloat" qualify. A name used by two tags of
	 * different types is ambiguous and skipped.
	 *
	 * @param WPCF7_ContactForm $form The contact form being rendered.
	 * @return array<string, string>
	 */
	private function fields( WPCF7_ContactForm $form ): array {
		$types = $this->settings->get( 'types' );
		if ( ! is_array( $types ) || empty( $types ) ) {
			return array();
		}
		$types = array_intersect( $this->settings->field_types(), $types );

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

			if ( isset( $fields[ $name ] ) && $fields[ $name ] !== $basetype ) {
				$ambiguous[ $name ] = true;
				continue;
			}

			$fields[ $name ] = $basetype;
		}

		return array_diff_key( $fields, $ambiguous );
	}

	/**
	 * Parses the form HTML, rebuilds the qualifying fields and serialises it again.
	 *
	 * @param string                                $html   Form HTML.
	 * @param WPCF7_ContactForm                     $form   The contact form being rendered.
	 * @param array<string, string>                 $fields Field names mapped to base types, see fields().
	 * @param array{placeholder: bool, label: bool} $modes  Active modes, see modes().
	 * @return string|null Rebuilt HTML, or null when nothing changed or parsing failed.
	 */
	private function rebuild( string $html, WPCF7_ContactForm $form, array $fields, array $modes ): ?string {
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

		$style    = (string) $this->settings->get( 'style' );
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

			if ( $modes['label'] ) {
				$enclosing = $this->enclosing_label( $wrap );
				if ( null !== $enclosing ) {
					$text = $this->label_text( $enclosing, $wrap );
					if ( null === $text ) {
						$enclosing = null;
					}
				}

				if ( null === $text ) {
					$label = $this->linked_label( $xpath, $root, $control );
					if ( null !== $label ) {
						$text = $this->normalize_text( $label->textContent );
						if ( '' === $text ) {
							$label = null;
							$text  = null;
						}
					}
				}
			}

			if ( null === $text && $modes['placeholder'] ) {
				$text = $this->normalize_text( $control->getAttribute( 'placeholder' ) );
				if ( '' === $text ) {
					$text = null;
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

			$this->apply( $dom, $wrap, $control, $name, $unit_tag, $style, $filtered, $text, $label, $enclosing );
			++$changed;
		}

		if ( 0 === $changed ) {
			return null;
		}

		return $this->save( $dom, $root );
	}

	/**
	 * Rebuilds one field in the DOM.
	 *
	 * @param DOMDocument     $dom           Document.
	 * @param DOMElement      $wrap          The span.wpcf7-form-control-wrap.
	 * @param DOMElement      $control       The input or textarea inside the wrap.
	 * @param string          $name          Field name.
	 * @param string          $unit_tag      Unit-tag of the form instance.
	 * @param string          $style         CSS variant from the settings.
	 * @param string          $text          Label text after the filter.
	 * @param string          $original_text Label text before the filter.
	 * @param DOMElement|null $label         Existing <label for> to move into the wrap, if any.
	 * @param DOMElement|null $enclosing     Enclosing <label> of the template to replace, if any.
	 * @return void
	 */
	private function apply( DOMDocument $dom, DOMElement $wrap, DOMElement $control, string $name, string $unit_tag, string $style, string $text, string $original_text, ?DOMElement $label, ?DOMElement $enclosing ): void {
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
	 * Returns the single <label for> pointing at the control, or null.
	 *
	 * The label must not contain a control itself (that is the enclosing
	 * case, handled before).
	 *
	 * @param DOMXPath   $xpath   XPath on the document.
	 * @param DOMElement $root    Wrapper element of the fragment.
	 * @param DOMElement $control The input or textarea.
	 * @return DOMElement|null
	 */
	private function linked_label( DOMXPath $xpath, DOMElement $root, DOMElement $control ): ?DOMElement {
		$id = $control->getAttribute( 'id' );
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

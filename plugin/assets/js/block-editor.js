/**
 * Floating Labels for CF7 – block editor script.
 *
 * Adds panels to the sidebar of the Contact Form 7 block
 * (contact-form-7/contact-form-selector) and of a Shortcode block
 * (core/shortcode) whose text holds a [contact-form-7 ...] shortcode:
 *
 * - "Floating labels": one select per group of placement classes of the
 *   plugin (on/off, label source, variant, required marker, focus effect).
 *   The chosen
 *   class is stored where Contact Form 7 reads it anyway: in the block
 *   attribute htmlClass of the Contact Form 7 block (shown as "Class" in its
 *   panel "Form attributes"), or in the html_class attribute of the
 *   shortcode text of a Shortcode block. Classes the panel does not know are
 *   kept untouched.
 * - "Floating labels: colours", "Floating labels: sizes", "Floating
 *   labels: animation" and "Floating labels: focus effect": design values
 *   for this placement only, one control per CSS custom property of the
 *   stylesheet; an empty control keeps the
 *   value of the settings screen. Stored in the block attribute
 *   jgorFlcf7Design (declared by the plugin on the server, the saved
 *   shortcode stays untouched) or in the jgor_flcf7_design attribute of the
 *   shortcode text ("label-color:#123456;font-size:1.1em"). The server
 *   prints them as custom properties on the wrapper of that form. The list
 *   of properties (key, group, type, label, placeholder) comes from the
 *   server in window.jgorFlcf7EditorData; without it only the first panel
 *   is shown.
 * - A reset button at the end of every panel: "Reset this placement" in
 *   the first panel removes the placement classes and the design values
 *   of this placement, the design panels reset their own group only. The
 *   buttons are disabled while there is nothing to reset.
 *
 * Every panel is a collapsible PanelBody; the controls sit directly inside
 * it and take the spacing of the block inspector (16px between controls,
 * as in the panels of the core blocks). A colour is one full-width button
 * with the indicator of the chosen colour and the name of the property;
 * it opens the colour palette of the editor (theme, default and custom
 * colours, plus a free colour) in a popover. Only hex colours are stored.
 * The styles of these rows live in assets/css/editor.css.
 *
 * No build step: the script uses the global "wp" packages and
 * wp.element.createElement instead of JSX. The helpers are exposed on
 * window.jgorFlcf7BlockEditor so tests/verify-editor-js.mjs can check them
 * under Node.
 *
 * @package Floating_Labels_For_CF7
 */

( function ( wp ) {
	'use strict';

	var __ = wp.i18n.__;
	var _x = wp.i18n._x;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;

	/**
	 * Name of the Contact Form 7 block.
	 *
	 * @type {string}
	 */
	var BLOCK_NAME = 'contact-form-7/contact-form-selector';

	/**
	 * Name of the core Shortcode block.
	 *
	 * @type {string}
	 */
	var SHORTCODE_BLOCK_NAME = 'core/shortcode';

	/**
	 * Namespace of the filter callback.
	 *
	 * @type {string}
	 */
	var FILTER_NAMESPACE = 'jgor-flcf7/block-editor';

	/**
	 * Block attribute of the Contact Form 7 block that holds the design
	 * values (object property => value).
	 *
	 * @type {string}
	 */
	var DESIGN_BLOCK_ATTRIBUTE = 'jgorFlcf7Design';

	/**
	 * Shortcode attribute that holds the design values ("key:value;...").
	 *
	 * @type {string}
	 */
	var DESIGN_SHORTCODE_ATTRIBUTE = 'jgor_flcf7_design';

	/**
	 * Matches the first [contact-form-7 ...] shortcode in a text.
	 *
	 * @type {RegExp}
	 */
	var SHORTCODE_PATTERN = /\[contact-form-7\b[^\]]*\]/;

	/**
	 * Builds the pattern that matches one attribute inside a shortcode:
	 * double quotes, single quotes or no quotes, as WordPress parses them.
	 *
	 * @param {string} name Attribute name.
	 * @return {RegExp} Pattern with the value in group 1, 2 or 3.
	 */
	function attributePattern( name ) {
		return new RegExp( '\\s+' + name + '=(?:"([^"]*)"|\'([^\']*)\'|([^\\s\\]]+))' );
	}

	/**
	 * Matches the html_class attribute inside a shortcode.
	 *
	 * @type {RegExp}
	 */
	var HTML_CLASS_ATTR_PATTERN = attributePattern( 'html_class' );

	/**
	 * Matches the jgor_flcf7_design attribute inside a shortcode.
	 *
	 * @type {RegExp}
	 */
	var DESIGN_ATTR_PATTERN = attributePattern( DESIGN_SHORTCODE_ATTRIBUTE );

	/**
	 * Whether an entry of the server data describes a design property.
	 *
	 * @param {*} property Entry to check.
	 * @return {boolean} True for {key, group, type, label} strings.
	 */
	function isDesignProperty( property ) {
		return !! property && typeof property.key === 'string' && property.key !== '' && typeof property.group === 'string' && typeof property.type === 'string' && typeof property.label === 'string';
	}

	/**
	 * Design properties handed over by the server (JGOR_FLCF7_Editor):
	 * {key, group, type, label, placeholder} in the order of the settings
	 * screen; the placeholder is the value in effect there. Empty without
	 * the data.
	 *
	 * @type {Array<{key: string, group: string, type: string, label: string, placeholder: string}>}
	 */
	var DESIGN = ( window.jgorFlcf7EditorData && Array.isArray( window.jgorFlcf7EditorData.design ) ) ? window.jgorFlcf7EditorData.design.filter( isDesignProperty ) : [];

	/**
	 * Panels of the design values, one per group of the settings screen.
	 * The colours panel carries the storage note of the block, the others
	 * the format of their values.
	 *
	 * @type {Array<{group: string, title: string, help: string, reset: string}>}
	 */
	var DESIGN_PANELS = [
		{
			group: 'colors',
			title: __( 'Floating labels: colours', 'floating-labels-for-cf7' ),
			help: '',
			reset: __( 'Reset colours', 'floating-labels-for-cf7' )
		},
		{
			group: 'sizes',
			title: __( 'Floating labels: sizes', 'floating-labels-for-cf7' ),
			help: __( 'A number with px, em, rem or %, for example 0.9em. The font size of the field also scales the label and the padding; 1em keeps the size of the surrounding text.', 'floating-labels-for-cf7' ),
			reset: __( 'Reset sizes', 'floating-labels-for-cf7' )
		},
		{
			group: 'motion',
			title: __( 'Floating labels: animation', 'floating-labels-for-cf7' ),
			help: __( 'Scale of the floated label between 0.5 and 1; duration of the transitions with s or ms.', 'floating-labels-for-cf7' ),
			reset: __( 'Reset animation', 'floating-labels-for-cf7' )
		},
		{
			group: 'effect',
			title: __( 'Floating labels: focus effect', 'floating-labels-for-cf7' ),
			help: __( 'Colour of the glow and of the running light; size of the glow and width of the running light with px, em, rem or %; duration of one pulse or one round with s or ms. The effect itself is chosen in the panel "Floating labels".', 'floating-labels-for-cf7' ),
			reset: __( 'Reset focus effect', 'floating-labels-for-cf7' )
		}
	];

	/**
	 * Groups of placement classes, one select each (concept, section 7.5).
	 *
	 * The empty value means "no class of this group": the settings of the
	 * site and of the form apply. Within one group the last class wins on
	 * the server, so currentValue() returns the last one as well.
	 *
	 * @type {Array<{key: string, label: string, options: Array<{value: string, label: string}>}>}
	 */
	var GROUPS = [
		{
			key: 'enabled',
			label: __( 'Floating labels', 'floating-labels-for-cf7' ),
			options: [
				{ value: '', label: __( 'Default', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-on', label: __( 'On', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-off', label: __( 'Off', 'floating-labels-for-cf7' ) }
			]
		},
		{
			key: 'mode',
			label: __( 'Label source', 'floating-labels-for-cf7' ),
			options: [
				{ value: '', label: __( 'Default', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-mode-label', label: __( 'Form template labels only', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-mode-placeholder', label: __( 'Placeholders only', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-mode-text', label: __( 'Text before the field only', 'floating-labels-for-cf7' ) }
			]
		},
		{
			key: 'style',
			label: __( 'Variant', 'floating-labels-for-cf7' ),
			options: [
				{ value: '', label: __( 'Default', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-style-outlined', label: __( 'Outlined', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-style-filled', label: __( 'Filled', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-style-minimal', label: __( 'Minimal', 'floating-labels-for-cf7' ) }
			]
		},
		{
			key: 'effect',
			label: __( 'Focus effect', 'floating-labels-for-cf7' ),
			options: [
				{ value: '', label: __( 'Default', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-effect-none', label: __( 'None', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-effect-glow', label: __( 'Glow', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-effect-pulse', label: __( 'Pulsing glow', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-effect-sweep', label: __( 'Running light', 'floating-labels-for-cf7' ) }
			]
		},
		{
			key: 'marker',
			label: __( 'Required fields', 'floating-labels-for-cf7' ),
			options: [
				{ value: '', label: __( 'Default', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-marker-on', label: __( 'Asterisk on', 'floating-labels-for-cf7' ) },
				{ value: 'jgor-flcf7-marker-off', label: __( 'Asterisk off', 'floating-labels-for-cf7' ) }
			]
		}
	];

	/**
	 * Splits a class attribute into its tokens.
	 *
	 * @param {*} value Attribute value, usually a string.
	 * @return {string[]} Non-empty tokens.
	 */
	function splitClasses( value ) {
		if ( typeof value !== 'string' ) {
			return [];
		}

		return value.split( /\s+/ ).filter( function ( token ) {
			return token !== '';
		} );
	}

	/**
	 * Returns the non-empty class names of a group.
	 *
	 * @param {Object} group Entry of GROUPS.
	 * @return {string[]} Class names.
	 */
	function groupValues( group ) {
		return group.options.map( function ( option ) {
			return option.value;
		} ).filter( function ( value ) {
			return value !== '';
		} );
	}

	/**
	 * Returns the class of a group present in the attribute, or ''.
	 *
	 * @param {*}      htmlClass Value of the class attribute.
	 * @param {Object} group     Entry of GROUPS.
	 * @return {string} The last matching class, or '' for none.
	 */
	function currentValue( htmlClass, group ) {
		var values = groupValues( group );
		var found = '';

		splitClasses( htmlClass ).forEach( function ( token ) {
			if ( values.indexOf( token ) !== -1 ) {
				found = token;
			}
		} );

		return found;
	}

	/**
	 * Returns the attribute with the class of a group set, replaced or removed.
	 *
	 * The first class of the group keeps its position and takes the new
	 * value, further classes of the group are dropped; when the group was not
	 * present the value is appended. An empty value removes the group. Other
	 * classes are kept as they are.
	 *
	 * @param {*}      htmlClass Value of the class attribute.
	 * @param {Object} group     Entry of GROUPS.
	 * @param {string} value     New class of the group, or '' for none.
	 * @return {string} New attribute value.
	 */
	function withGroupValue( htmlClass, group, value ) {
		var values = groupValues( group );
		var result = [];
		var placed = false;

		splitClasses( htmlClass ).forEach( function ( token ) {
			if ( values.indexOf( token ) === -1 ) {
				result.push( token );
			} else if ( value !== '' && ! placed ) {
				result.push( value );
				placed = true;
			}
		} );

		if ( value !== '' && ! placed ) {
			result.push( value );
		}

		return result.join( ' ' );
	}

	/**
	 * Returns the attribute without any class of the placement groups.
	 *
	 * @param {*} htmlClass Value of the class attribute.
	 * @return {string} The remaining classes, '' when none are left.
	 */
	function withoutGroups( htmlClass ) {
		var values = GROUPS.reduce( function ( all, group ) {
			return all.concat( groupValues( group ) );
		}, [] );

		return splitClasses( htmlClass ).filter( function ( token ) {
			return values.indexOf( token ) === -1;
		} ).join( ' ' );
	}

	/**
	 * Whether the attribute holds a class of any placement group.
	 *
	 * @param {*} htmlClass Value of the class attribute.
	 * @return {boolean} True when at least one group is set.
	 */
	function hasGroupValue( htmlClass ) {
		return GROUPS.some( function ( group ) {
			return currentValue( htmlClass, group ) !== '';
		} );
	}

	/**
	 * Keeps only known design properties with non-empty string values, in
	 * the order of the server list.
	 *
	 * Characters that would break a shortcode attribute (quotes, brackets,
	 * semicolons) are removed; the server validates the values themselves.
	 *
	 * @param {*} design Object property => value.
	 * @return {Object} Clean copy.
	 */
	function cleanDesign( design ) {
		var clean = {};

		if ( ! design || typeof design !== 'object' ) {
			return clean;
		}

		DESIGN.forEach( function ( property ) {
			var value = design[ property.key ];
			if ( typeof value !== 'string' ) {
				return;
			}

			value = value.replace( /["'[\];]/g, '' ).trim();
			if ( value !== '' ) {
				clean[ property.key ] = value;
			}
		} );

		return clean;
	}

	/**
	 * Parses the value of the shortcode attribute ("key:value;key:value").
	 *
	 * @param {*} text Attribute value.
	 * @return {Object} Clean design values.
	 */
	function parseDesign( text ) {
		var design = {};

		if ( typeof text !== 'string' ) {
			return design;
		}

		text.split( ';' ).forEach( function ( pair ) {
			var at = pair.indexOf( ':' );
			if ( at !== -1 ) {
				design[ pair.slice( 0, at ).trim() ] = pair.slice( at + 1 ).trim();
			}
		} );

		return cleanDesign( design );
	}

	/**
	 * Serialises design values for the shortcode attribute.
	 *
	 * @param {*} design Object property => value.
	 * @return {string} "key:value;key:value", or '' when nothing is set.
	 */
	function serializeDesign( design ) {
		var clean = cleanDesign( design );

		return Object.keys( clean ).map( function ( key ) {
			return key + ':' + clean[ key ];
		} ).join( ';' );
	}

	/**
	 * Returns the design values without the properties of one group.
	 *
	 * @param {*}      design Object property => value.
	 * @param {string} group  Group of the settings screen (colors, sizes, motion, effect).
	 * @return {Object} Clean copy without that group.
	 */
	function designWithoutGroup( design, group ) {
		var clean = cleanDesign( design );

		DESIGN.forEach( function ( property ) {
			if ( property.group === group ) {
				delete clean[ property.key ];
			}
		} );

		return clean;
	}

	/**
	 * Normalises a colour from the colour palette: hex colours only, in
	 * lower case, as the server accepts them; anything else means "unset".
	 *
	 * @param {*} value Colour string, or undefined when cleared.
	 * @return {string} "#rgb" / "#rrggbb" or ''.
	 */
	function colorValue( value ) {
		if ( typeof value !== 'string' ) {
			return '';
		}

		value = value.trim().toLowerCase();

		return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/.test( value ) ? value : '';
	}

	/**
	 * Builds the palette groups of the colour picker from the editor
	 * settings: the colours of the theme, the default colours of WordPress
	 * (unless the theme hides them) and the colours the user added, each as
	 * a named group as in the colour panels of the core blocks. Empty groups
	 * are left out; without any group the picker offers the free colour only.
	 *
	 * @param {*} theme        Theme palette (setting color.palette.theme).
	 * @param {*} defaults     Default palette (setting color.palette.default).
	 * @param {*} custom       User palette (setting color.palette.custom).
	 * @param {*} showDefaults Whether the default palette is offered (setting color.defaultPalette).
	 * @return {Array<{name: string, colors: Array}>} Palette groups.
	 */
	function paletteGroups( theme, defaults, custom, showDefaults ) {
		var groups = [];

		if ( Array.isArray( theme ) && theme.length > 0 ) {
			groups.push( { name: _x( 'Theme', 'colour palette group', 'floating-labels-for-cf7' ), colors: theme } );
		}

		if ( showDefaults !== false && Array.isArray( defaults ) && defaults.length > 0 ) {
			groups.push( { name: _x( 'Default', 'colour palette group', 'floating-labels-for-cf7' ), colors: defaults } );
		}

		if ( Array.isArray( custom ) && custom.length > 0 ) {
			groups.push( { name: _x( 'Custom', 'colour palette group', 'floating-labels-for-cf7' ), colors: custom } );
		}

		return groups;
	}

	/**
	 * Finds the first Contact Form 7 shortcode in a text.
	 *
	 * @param {*} text Text of a Shortcode block.
	 * @return {Object|null} Match (index, [0] = the shortcode) or null.
	 */
	function findShortcode( text ) {
		if ( typeof text !== 'string' ) {
			return null;
		}

		return text.match( SHORTCODE_PATTERN );
	}

	/**
	 * Returns one attribute of the first Contact Form 7 shortcode in a text,
	 * or ''.
	 *
	 * @param {*}      text    Text of a Shortcode block.
	 * @param {RegExp} pattern Pattern from attributePattern().
	 * @return {string} Attribute value, or '' when absent.
	 */
	function shortcodeAttribute( text, pattern ) {
		var shortcode = findShortcode( text );
		if ( ! shortcode ) {
			return '';
		}

		var attr = shortcode[ 0 ].match( pattern );
		if ( ! attr ) {
			return '';
		}

		if ( attr[ 1 ] !== undefined ) {
			return attr[ 1 ];
		}

		return attr[ 2 ] !== undefined ? attr[ 2 ] : attr[ 3 ];
	}

	/**
	 * Returns the text with one attribute of the first Contact Form 7
	 * shortcode set, replaced or removed.
	 *
	 * An existing attribute keeps its position and is rewritten with double
	 * quotes; a missing one is appended before the closing bracket; an empty
	 * value removes the attribute. Text without the shortcode comes back
	 * unchanged.
	 *
	 * @param {*}      text    Text of a Shortcode block.
	 * @param {string} name    Attribute name.
	 * @param {RegExp} pattern Pattern from attributePattern() for that name.
	 * @param {string} value   New attribute value, or '' for none.
	 * @return {*} New text.
	 */
	function withShortcodeAttribute( text, name, pattern, value ) {
		var shortcode = findShortcode( text );
		if ( ! shortcode ) {
			return text;
		}

		var tag = shortcode[ 0 ];
		var updated;

		if ( pattern.test( tag ) ) {
			updated = tag.replace( pattern, value === '' ? '' : ' ' + name + '="' + value + '"' );
		} else if ( value !== '' ) {
			updated = tag.replace( /\s*(\/?)\]$/, ' ' + name + '="' + value + '"$1]' );
		} else {
			updated = tag;
		}

		return text.slice( 0, shortcode.index ) + updated + text.slice( shortcode.index + tag.length );
	}

	/**
	 * Returns the html_class attribute of the first Contact Form 7 shortcode
	 * in a text, or ''.
	 *
	 * @param {*} text Text of a Shortcode block.
	 * @return {string} Attribute value, or '' when absent.
	 */
	function shortcodeHtmlClass( text ) {
		return shortcodeAttribute( text, HTML_CLASS_ATTR_PATTERN );
	}

	/**
	 * Returns the text with the html_class attribute of the first Contact
	 * Form 7 shortcode set, replaced or removed.
	 *
	 * @param {*}      text  Text of a Shortcode block.
	 * @param {string} value New attribute value, or '' for none.
	 * @return {*} New text.
	 */
	function withShortcodeHtmlClass( text, value ) {
		return withShortcodeAttribute( text, 'html_class', HTML_CLASS_ATTR_PATTERN, value );
	}

	/**
	 * Returns the design values of the first Contact Form 7 shortcode in a
	 * text (attribute jgor_flcf7_design).
	 *
	 * @param {*} text Text of a Shortcode block.
	 * @return {Object} Clean design values, empty when absent.
	 */
	function shortcodeDesign( text ) {
		return parseDesign( shortcodeAttribute( text, DESIGN_ATTR_PATTERN ) );
	}

	/**
	 * Returns the text with the design values of the first Contact Form 7
	 * shortcode set or removed (attribute jgor_flcf7_design).
	 *
	 * @param {*} text   Text of a Shortcode block.
	 * @param {*} design Object property => value; empty removes the attribute.
	 * @return {*} New text.
	 */
	function withShortcodeDesign( text, design ) {
		return withShortcodeAttribute( text, DESIGN_SHORTCODE_ATTRIBUTE, DESIGN_ATTR_PATTERN, serializeDesign( design ) );
	}

	/**
	 * Describes where the values of a block live, or null for other blocks.
	 *
	 * Contact Form 7 block: the attributes htmlClass and jgorFlcf7Design,
	 * once a form is selected (like the panels of the block itself).
	 * Shortcode block: the attributes html_class and jgor_flcf7_design of
	 * the first [contact-form-7] shortcode in its text.
	 *
	 * @param {Object} props Block edit props (name, attributes, setAttributes).
	 * @return {Object|null} {active, read, write, help, readDesign, writeDesign, designHelp, reset} or null.
	 */
	function storeFor( props ) {
		var attributes = props.attributes || {};

		if ( props.name === BLOCK_NAME ) {
			return {
				active: !! attributes.id,
				read: function () {
					return typeof attributes.htmlClass === 'string' ? attributes.htmlClass : '';
				},
				write: function ( value ) {
					props.setAttributes( { htmlClass: value } );
				},
				help: __( '"Default" keeps the settings of the site and of the form. The choices are stored as classes in the "Class" field of the panel "Form attributes".', 'floating-labels-for-cf7' ),
				readDesign: function () {
					return cleanDesign( attributes[ DESIGN_BLOCK_ATTRIBUTE ] );
				},
				writeDesign: function ( design ) {
					var clean = cleanDesign( design );
					var update = {};

					// Without values the attribute leaves the saved block.
					update[ DESIGN_BLOCK_ATTRIBUTE ] = Object.keys( clean ).length > 0 ? clean : undefined;
					props.setAttributes( update );
				},
				designHelp: __( 'Empty keeps the values of the settings screen. The values are stored with the block and apply to this placement only.', 'floating-labels-for-cf7' ),
				reset: function () {
					var update = { htmlClass: withoutGroups( attributes.htmlClass ) };

					update[ DESIGN_BLOCK_ATTRIBUTE ] = undefined;
					props.setAttributes( update );
				}
			};
		}

		if ( props.name === SHORTCODE_BLOCK_NAME ) {
			return {
				active: findShortcode( attributes.text ) !== null,
				read: function () {
					return shortcodeHtmlClass( attributes.text );
				},
				write: function ( value ) {
					props.setAttributes( { text: withShortcodeHtmlClass( attributes.text, value ) } );
				},
				help: __( '"Default" keeps the settings of the site and of the form. The choices are stored in the html_class attribute of the shortcode.', 'floating-labels-for-cf7' ),
				readDesign: function () {
					return shortcodeDesign( attributes.text );
				},
				writeDesign: function ( design ) {
					props.setAttributes( { text: withShortcodeDesign( attributes.text, design ) } );
				},
				designHelp: __( 'Empty keeps the values of the settings screen. The values are stored in the jgor_flcf7_design attribute of the shortcode and apply to this placement only.', 'floating-labels-for-cf7' ),
				reset: function () {
					var text = withShortcodeHtmlClass( attributes.text, withoutGroups( shortcodeHtmlClass( attributes.text ) ) );

					props.setAttributes( { text: withShortcodeDesign( text, {} ) } );
				}
			};
		}

		return null;
	}

	/**
	 * Builds the reset button of a panel.
	 *
	 * @param {string}   label   Button text.
	 * @param {boolean}  active  False disables the button: nothing to reset.
	 * @param {Function} onClick Callback.
	 * @return {Object} Element (Button).
	 */
	function resetButton( label, active, onClick ) {
		return el(
			wp.components.Button,
			{
				key: 'reset',
				className: 'jgor-flcf7-reset',
				variant: 'tertiary',
				disabled: ! active,
				onClick: onClick,
				__next40pxDefaultSize: true
			},
			label
		);
	}

	/**
	 * Builds the help paragraph of a panel.
	 *
	 * @param {string} text Help text.
	 * @return {Object|null} Element, or null for an empty text.
	 */
	function helpElement( text ) {
		if ( ! text ) {
			return null;
		}

		return el( 'p', { key: 'help', className: 'components-base-control__help' }, text );
	}

	/**
	 * One colour of the design panels: a full-width button with the colour
	 * indicator (hatched while unset) and the name of the property; a click
	 * opens the colour palette of the editor in a popover next to the
	 * sidebar, as the colour panels of the core blocks do.
	 *
	 * A component, because the palette comes from the block editor settings
	 * through the hook useSettings (WordPress 6.5 and newer).
	 *
	 * @param {Object}   props          Properties.
	 * @param {string}   props.label    Name of the property.
	 * @param {string}   props.value    Current colour, '' when unset.
	 * @param {Function} props.onChange Callback with the colour of the palette, or undefined when cleared.
	 * @return {Object} Element (Dropdown).
	 */
	function ColorControl( props ) {
		var settings = wp.blockEditor.useSettings( 'color.palette.theme', 'color.palette.default', 'color.palette.custom', 'color.defaultPalette' );
		var colors = paletteGroups( settings[ 0 ], settings[ 1 ], settings[ 2 ], settings[ 3 ] );
		var value = typeof props.value === 'string' ? props.value : '';

		return el( wp.components.Dropdown, {
			className: 'jgor-flcf7-color',
			contentClassName: 'jgor-flcf7-color__popover',
			popoverProps: { placement: 'left-start', offset: 36, shift: true },
			renderToggle: function ( toggle ) {
				return el(
					wp.components.Button,
					{
						className: 'jgor-flcf7-color__toggle',
						onClick: toggle.onToggle,
						'aria-expanded': toggle.isOpen,
						__next40pxDefaultSize: true
					},
					el( wp.components.ColorIndicator, {
						className: 'jgor-flcf7-color__indicator' + ( value === '' ? ' is-unset' : '' ),
						colorValue: value === '' ? undefined : value
					} ),
					el( 'span', { className: 'jgor-flcf7-color__name' }, props.label )
				);
			},
			renderContent: function () {
				return el(
					'div',
					{ className: 'jgor-flcf7-color__panel' },
					el( wp.components.ColorPalette, {
						colors: colors,
						value: value === '' ? undefined : value,
						onChange: props.onChange,
						clearable: true,
						enableAlpha: false,
						__experimentalIsRenderedInSidebar: true
					} )
				);
			}
		} );
	}

	/**
	 * Builds the control of one design property.
	 *
	 * Colours get the ColorControl row; the scale (type "number") a number
	 * input within the range the server accepts; every other value is typed
	 * with its unit into a text control that shows the value of the settings
	 * screen as placeholder.
	 *
	 * @param {Object}   property Entry of DESIGN.
	 * @param {string}   value    Current value, '' when unset.
	 * @param {Function} change   Callback (key, value).
	 * @return {Object} Element (ColorControl or TextControl).
	 */
	function designControl( property, value, change ) {
		if ( property.type === 'color' ) {
			return el( ColorControl, {
				key: property.key,
				label: property.label,
				value: value,
				onChange: function ( next ) {
					change( property.key, colorValue( next ) );
				}
			} );
		}

		var controlProps = {
			key: property.key,
			label: property.label,
			value: value,
			placeholder: typeof property.placeholder === 'string' ? property.placeholder : '',
			onChange: function ( next ) {
				change( property.key, next );
			},
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true
		};

		if ( property.type === 'number' ) {
			controlProps.type = 'number';
			controlProps.min = 0.5;
			controlProps.max = 1;
			controlProps.step = 0.01;
		}

		return el( wp.components.TextControl, controlProps );
	}

	/**
	 * Builds the design panels for one block instance; none without the
	 * property list from the server.
	 *
	 * @param {Object} store Result of storeFor().
	 * @return {Object[]} Elements (PanelBody).
	 */
	function designPanels( store ) {
		if ( DESIGN.length === 0 ) {
			return [];
		}

		var design = store.readDesign();

		var change = function ( key, value ) {
			var next = Object.assign( {}, design );

			if ( typeof value === 'string' && value.trim() !== '' ) {
				next[ key ] = value;
			} else {
				delete next[ key ];
			}

			store.writeDesign( next );
		};

		return DESIGN_PANELS.map( function ( spec ) {
			var properties = DESIGN.filter( function ( property ) {
				return property.group === spec.group;
			} );

			if ( properties.length === 0 ) {
				return null;
			}

			var controls = properties.map( function ( property ) {
				return designControl( property, design[ property.key ] || '', change );
			} );
			var help = helpElement( spec.group === 'colors' ? store.designHelp : spec.help );
			if ( help ) {
				controls.push( help );
			}

			var groupSet = properties.some( function ( property ) {
				return !! design[ property.key ];
			} );
			controls.push( resetButton( spec.reset, groupSet, function () {
				store.writeDesign( designWithoutGroup( design, spec.group ) );
			} ) );

			return el( wp.components.PanelBody, { key: spec.group, title: spec.title, initialOpen: false }, controls );
		} ).filter( Boolean );
	}

	/**
	 * Builds the panels for one block instance.
	 *
	 * @param {Object} store Result of storeFor().
	 * @return {Object} Element tree (InspectorControls).
	 */
	function panel( store ) {
		var htmlClass = store.read();

		var controls = GROUPS.map( function ( group ) {
			return el( wp.components.SelectControl, {
				key: group.key,
				label: group.label,
				value: currentValue( htmlClass, group ),
				options: group.options,
				onChange: function ( value ) {
					store.write( withGroupValue( htmlClass, group, value ) );
				},
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true
			} );
		} );

		controls.push( helpElement( store.help ) );

		var canReset = hasGroupValue( htmlClass ) || Object.keys( store.readDesign() ).length > 0;
		controls.push( resetButton( __( 'Reset this placement', 'floating-labels-for-cf7' ), canReset, store.reset ) );

		var panels = [
			el( wp.components.PanelBody, { key: 'main', title: __( 'Floating labels', 'floating-labels-for-cf7' ), initialOpen: true }, controls )
		].concat( designPanels( store ) );

		return el( wp.blockEditor.InspectorControls, null, panels );
	}

	/**
	 * Wraps the edit component of every block; only the Contact Form 7 block
	 * and a Shortcode block with a Contact Form 7 shortcode get the panels.
	 */
	var withFloatingLabelsPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var store = storeFor( props );

			if ( ! store ) {
				return el( BlockEdit, props );
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				store.active ? panel( store ) : null
			);
		};
	}, 'withJgorFlcf7FloatingLabelsPanel' );

	wp.hooks.addFilter( 'editor.BlockEdit', FILTER_NAMESPACE, withFloatingLabelsPanel );

	window.jgorFlcf7BlockEditor = {
		BLOCK_NAME: BLOCK_NAME,
		SHORTCODE_BLOCK_NAME: SHORTCODE_BLOCK_NAME,
		DESIGN_BLOCK_ATTRIBUTE: DESIGN_BLOCK_ATTRIBUTE,
		DESIGN_SHORTCODE_ATTRIBUTE: DESIGN_SHORTCODE_ATTRIBUTE,
		GROUPS: GROUPS,
		DESIGN: DESIGN,
		ColorControl: ColorControl,
		splitClasses: splitClasses,
		currentValue: currentValue,
		withGroupValue: withGroupValue,
		withoutGroups: withoutGroups,
		hasGroupValue: hasGroupValue,
		designWithoutGroup: designWithoutGroup,
		cleanDesign: cleanDesign,
		parseDesign: parseDesign,
		serializeDesign: serializeDesign,
		colorValue: colorValue,
		paletteGroups: paletteGroups,
		shortcodeHtmlClass: shortcodeHtmlClass,
		withShortcodeHtmlClass: withShortcodeHtmlClass,
		shortcodeDesign: shortcodeDesign,
		withShortcodeDesign: withShortcodeDesign,
		withFloatingLabelsPanel: withFloatingLabelsPanel
	};
} )( window.wp );

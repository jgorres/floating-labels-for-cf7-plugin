/**
 * Floating Labels for CF7 – block editor script.
 *
 * Adds the panel "Floating Labels" to the sidebar of the Contact Form 7 block
 * (contact-form-7/contact-form-selector). Each select of the panel stands for
 * one group of placement classes of the plugin (on/off, label source, variant,
 * required marker). The chosen class is written into the block attribute
 * htmlClass, which Contact Form 7 prints as html_class of the shortcode and as
 * class of the <form>; the block shows the same attribute as "Class" in its
 * panel "Form attributes", so both views stay in sync. Classes the panel does
 * not know are kept untouched.
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
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;

	/**
	 * Name of the Contact Form 7 block.
	 *
	 * @type {string}
	 */
	var BLOCK_NAME = 'contact-form-7/contact-form-selector';

	/**
	 * Namespace of the filter callback.
	 *
	 * @type {string}
	 */
	var FILTER_NAMESPACE = 'jgor-flcf7/block-editor';

	/**
	 * Groups of placement classes, one select each (concept, section 7.4).
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
				{ value: 'jgor-flcf7-mode-placeholder', label: __( 'Placeholders only', 'floating-labels-for-cf7' ) }
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
	 * @param {*}      htmlClass Value of the attribute htmlClass.
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
	 * @param {*}      htmlClass Value of the attribute htmlClass.
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
	 * Builds the panel for one block instance.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Setter of the block.
	 * @return {Object} Element tree (InspectorControls).
	 */
	function panel( attributes, setAttributes ) {
		var htmlClass = typeof attributes.htmlClass === 'string' ? attributes.htmlClass : '';

		var controls = GROUPS.map( function ( group ) {
			return el( wp.components.SelectControl, {
				key: group.key,
				label: group.label,
				value: currentValue( htmlClass, group ),
				options: group.options,
				onChange: function ( value ) {
					setAttributes( { htmlClass: withGroupValue( htmlClass, group, value ) } );
				},
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true
			} );
		} );

		controls.push(
			el(
				'p',
				{ key: 'help', className: 'components-base-control__help' },
				__( '"Default" keeps the settings of the site and of the form. The choices are stored as classes in the "Class" field of the panel "Form attributes".', 'floating-labels-for-cf7' )
			)
		);

		return el(
			wp.blockEditor.InspectorControls,
			null,
			el(
				wp.components.PanelBody,
				{ title: __( 'Floating Labels', 'floating-labels-for-cf7' ), initialOpen: true },
				el( wp.components.Flex, { direction: 'column', gap: 4, align: 'stretch' }, controls )
			)
		);
	}

	/**
	 * Wraps the edit component of every block; only the Contact Form 7 block
	 * gets the panel, and only once a form is selected (like the panels of
	 * the block itself).
	 */
	var withFloatingLabelsPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== BLOCK_NAME ) {
				return el( BlockEdit, props );
			}

			var hasForm = !! ( props.attributes && props.attributes.id );

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				hasForm ? panel( props.attributes, props.setAttributes ) : null
			);
		};
	}, 'withJgorFlcf7FloatingLabelsPanel' );

	wp.hooks.addFilter( 'editor.BlockEdit', FILTER_NAMESPACE, withFloatingLabelsPanel );

	window.jgorFlcf7BlockEditor = {
		BLOCK_NAME: BLOCK_NAME,
		GROUPS: GROUPS,
		splitClasses: splitClasses,
		currentValue: currentValue,
		withGroupValue: withGroupValue,
		withFloatingLabelsPanel: withFloatingLabelsPanel
	};
} )( window.wp );

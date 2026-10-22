/**
 * Verifies the block editor script of module 5 under Node, without a browser.
 * Prints one PASS/FAIL line per check and exits with code 1 when a check
 * fails.
 *
 * Usage:
 *
 *   node tests/verify-editor-js.mjs
 *
 * The WordPress packages are replaced by stubs: createElement returns plain
 * objects, so the element tree the panel builds can be inspected, and the
 * filter registration is recorded instead of applied. Covered: the class
 * helpers, the html_class helpers for the Shortcode block, and the panel on
 * the Contact Form 7 block and on a Shortcode block.
 *
 * @package Floating_Labels_For_CF7
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

let failed = 0;

/**
 * Records one check.
 *
 * @param {string}  label  Check description.
 * @param {boolean} ok     Result.
 * @param {string}  detail Optional detail shown on failure.
 */
function check( label, ok, detail = '' ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== '' ? ' -- ' + detail : '' ) );
	if ( ! ok ) {
		failed++;
	}
}

/**
 * Finds the first element of a type in an element tree built by the stub.
 *
 * @param {*}      node Element, array of elements or anything else.
 * @param {string} type Element type.
 * @return {Object|null} Element or null.
 */
function find( node, type ) {
	if ( Array.isArray( node ) ) {
		for ( const child of node ) {
			const hit = find( child, type );
			if ( hit ) {
				return hit;
			}
		}
		return null;
	}
	if ( ! node || typeof node !== 'object' ) {
		return null;
	}
	if ( node.type === type ) {
		return node;
	}
	return find( node.children, type );
}

/**
 * Collects every element of a type in an element tree.
 *
 * @param {*}      node Element, array of elements or anything else.
 * @param {string} type Element type.
 * @return {Object[]} Elements.
 */
function findAll( node, type ) {
	if ( Array.isArray( node ) ) {
		return node.flatMap( ( child ) => findAll( child, type ) );
	}
	if ( ! node || typeof node !== 'object' ) {
		return [];
	}
	return ( node.type === type ? [ node ] : [] ).concat( findAll( node.children, type ) );
}

// Stubs of the WordPress packages the script reads from window.wp.
const filters = [];
globalThis.window = globalThis;
globalThis.wp = {
	hooks: {
		addFilter: ( hook, namespace, callback ) => filters.push( { hook, namespace, callback } ),
	},
	compose: {
		createHigherOrderComponent: ( mapComponent ) => mapComponent,
	},
	element: {
		createElement: ( type, props, ...children ) => ( { type, props: props || {}, children } ),
		Fragment: 'Fragment',
	},
	components: { PanelBody: 'PanelBody', SelectControl: 'SelectControl', Flex: 'Flex' },
	blockEditor: { InspectorControls: 'InspectorControls' },
	i18n: { __: ( text ) => text },
};

const file = resolve( dirname( fileURLToPath( import.meta.url ) ), '../plugin/assets/js/block-editor.js' );
vm.runInThisContext( readFileSync( file, 'utf8' ), { filename: file } );

const api = globalThis.jgorFlcf7BlockEditor;
check( 'api: window.jgorFlcf7BlockEditor exposed', !! api && typeof api.withGroupValue === 'function' && typeof api.withShortcodeHtmlClass === 'function' );

// Filter registration.
check( 'filter: editor.BlockEdit registered once under jgor-flcf7/block-editor', filters.length === 1 && filters[ 0 ].hook === 'editor.BlockEdit' && filters[ 0 ].namespace === 'jgor-flcf7/block-editor' && typeof filters[ 0 ].callback === 'function' );

// Groups and classes (concept, section 7.4).
const groups = api.GROUPS;
const byKey = Object.fromEntries( groups.map( ( group ) => [ group.key, group ] ) );
check( 'groups: enabled, mode, style, marker', groups.map( ( group ) => group.key ).join( ',' ) === 'enabled,mode,style,marker' );
check( 'groups: first option of every group is the empty default', groups.every( ( group ) => group.options[ 0 ].value === '' && group.options[ 0 ].label === 'Default' ) );
const classes = groups.flatMap( ( group ) => group.options.map( ( option ) => option.value ) ).filter( ( value ) => value !== '' ).sort();
const expected = [
	'jgor-flcf7-marker-off', 'jgor-flcf7-marker-on', 'jgor-flcf7-mode-label', 'jgor-flcf7-mode-placeholder',
	'jgor-flcf7-off', 'jgor-flcf7-on', 'jgor-flcf7-style-filled', 'jgor-flcf7-style-minimal', 'jgor-flcf7-style-outlined',
];
check( 'groups: exactly the nine placement classes', classes.join( ' ' ) === expected.join( ' ' ), classes.join( ' ' ) );

// Helpers.
check( 'splitClasses: trims and splits on whitespace', api.splitClasses( '  a \t b\n' ).join( ',' ) === 'a,b' );
check( 'splitClasses: non-string -> []', api.splitClasses( undefined ).length === 0 && api.splitClasses( 7 ).length === 0 );
check( 'currentValue: class of the group found', api.currentValue( 'x jgor-flcf7-style-filled y', byKey.style ) === 'jgor-flcf7-style-filled' );
check( 'currentValue: none -> empty', api.currentValue( 'x jgor-flcf7-on', byKey.style ) === '' );
check( 'currentValue: last class of the group wins, as on the server', api.currentValue( 'jgor-flcf7-style-filled jgor-flcf7-style-minimal', byKey.style ) === 'jgor-flcf7-style-minimal' );
check( 'withGroupValue: appends when the group is absent', api.withGroupValue( 'custom', byKey.style, 'jgor-flcf7-style-filled' ) === 'custom jgor-flcf7-style-filled' );
check( 'withGroupValue: replaces in place', api.withGroupValue( 'a jgor-flcf7-style-filled b', byKey.style, 'jgor-flcf7-style-minimal' ) === 'a jgor-flcf7-style-minimal b' );
check( 'withGroupValue: drops duplicates of the group', api.withGroupValue( 'jgor-flcf7-style-filled a jgor-flcf7-style-minimal', byKey.style, 'jgor-flcf7-style-outlined' ) === 'jgor-flcf7-style-outlined a' );
check( 'withGroupValue: empty value removes the group, other classes stay', api.withGroupValue( 'a jgor-flcf7-off b jgor-flcf7-style-filled', byKey.enabled, '' ) === 'a b jgor-flcf7-style-filled' );
check( 'withGroupValue: empty attribute and empty value -> empty string', api.withGroupValue( undefined, byKey.marker, '' ) === '' );
check( 'withGroupValue: other groups untouched', api.withGroupValue( 'jgor-flcf7-mode-label', byKey.marker, 'jgor-flcf7-marker-on' ) === 'jgor-flcf7-mode-label jgor-flcf7-marker-on' );

// Shortcode helpers.
const sc = '[contact-form-7 id="123" title="Contact form 1"]';
check( 'shortcodeHtmlClass: no shortcode -> empty', api.shortcodeHtmlClass( 'plain text' ) === '' && api.shortcodeHtmlClass( undefined ) === '' );
check( 'shortcodeHtmlClass: shortcode without the attribute -> empty', api.shortcodeHtmlClass( sc ) === '' );
check( 'shortcodeHtmlClass: double quotes', api.shortcodeHtmlClass( '[contact-form-7 id="1" html_class="a b"]' ) === 'a b' );
check( 'shortcodeHtmlClass: single quotes', api.shortcodeHtmlClass( "[contact-form-7 id='1' html_class='a b']" ) === 'a b' );
check( 'shortcodeHtmlClass: no quotes', api.shortcodeHtmlClass( '[contact-form-7 id=1 html_class=a]' ) === 'a' );
check( 'shortcodeHtmlClass: first shortcode of two', api.shortcodeHtmlClass( '[contact-form-7 id="1" html_class="x"] [contact-form-7 id="2" html_class="y"]' ) === 'x' );
check( 'withShortcodeHtmlClass: appends the attribute before the closing bracket', api.withShortcodeHtmlClass( sc, 'a' ) === '[contact-form-7 id="123" title="Contact form 1" html_class="a"]' );
check( 'withShortcodeHtmlClass: keeps surrounding text', api.withShortcodeHtmlClass( 'before ' + sc + ' after', 'a' ) === 'before [contact-form-7 id="123" title="Contact form 1" html_class="a"] after' );
check( 'withShortcodeHtmlClass: replaces in place with double quotes', api.withShortcodeHtmlClass( "[contact-form-7 id='1' html_class='old' title='t']", 'new' ) === "[contact-form-7 id='1' html_class=\"new\" title='t']" );
check( 'withShortcodeHtmlClass: empty value removes the attribute', api.withShortcodeHtmlClass( '[contact-form-7 id="1" html_class="a" title="t"]', '' ) === '[contact-form-7 id="1" title="t"]' );
check( 'withShortcodeHtmlClass: empty value without attribute -> unchanged', api.withShortcodeHtmlClass( sc, '' ) === sc );
check( 'withShortcodeHtmlClass: self-closing shortcode', api.withShortcodeHtmlClass( '[contact-form-7 id="1" /]', 'a' ) === '[contact-form-7 id="1" html_class="a"/]' );
check( 'withShortcodeHtmlClass: text without shortcode unchanged', api.withShortcodeHtmlClass( '[gallery]', 'a' ) === '[gallery]' && api.withShortcodeHtmlClass( undefined, 'a' ) === undefined );

// The higher-order component.
const BlockEdit = 'BlockEdit';
const Wrapped = filters[ 0 ].callback( BlockEdit );
const noop = () => {};

const other = Wrapped( { name: 'core/paragraph', attributes: { content: 'x' }, setAttributes: noop } );
check( 'hoc: other blocks render the original edit component only', other.type === BlockEdit && other.props.name === 'core/paragraph' );

const noForm = Wrapped( { name: api.BLOCK_NAME, attributes: {}, setAttributes: noop } );
check( 'hoc: Contact Form 7 block without a selected form -> no panel', noForm.type === 'Fragment' && find( noForm, BlockEdit ) !== null && find( noForm, 'InspectorControls' ) === null );

let saved = null;
const tree = Wrapped( {
	name: api.BLOCK_NAME,
	attributes: { id: 123, htmlClass: 'custom jgor-flcf7-style-filled' },
	setAttributes: ( attributes ) => { saved = attributes; },
} );
check( 'hoc: original edit component kept', find( tree, BlockEdit ) !== null );
const inspector = find( tree, 'InspectorControls' );
const panelBody = find( inspector, 'PanelBody' );
check( 'panel: InspectorControls with PanelBody "Floating Labels", open by default', !! panelBody && panelBody.props.title === 'Floating Labels' && panelBody.props.initialOpen === true );
const selects = findAll( panelBody, 'SelectControl' );
check( 'panel: four selects in a vertical Flex', selects.length === 4 && find( panelBody, 'Flex' ) !== null && find( panelBody, 'Flex' ).props.direction === 'column' );
check( 'panel: labels', selects.map( ( select ) => select.props.label ).join( '|' ) === 'Floating labels|Label source|Variant|Required fields' );
check( 'panel: values read from htmlClass', selects.map( ( select ) => select.props.value ).join( '|' ) === '||jgor-flcf7-style-filled|' );
check( 'panel: keys set on the selects', selects.every( ( select, index ) => select.props.key === groups[ index ].key ) );
check( 'panel: help text names the "Class" field', !! find( panelBody, 'p' ) && String( find( panelBody, 'p' ).children[ 0 ] ).includes( '"Class"' ) );

selects[ 2 ].props.onChange( 'jgor-flcf7-style-minimal' );
check( 'change: variant -> setAttributes({ htmlClass }) with the class replaced', saved !== null && saved.htmlClass === 'custom jgor-flcf7-style-minimal' );
selects[ 0 ].props.onChange( 'jgor-flcf7-off' );
check( 'change: on/off -> class appended, others kept', saved.htmlClass === 'custom jgor-flcf7-style-filled jgor-flcf7-off' );
selects[ 2 ].props.onChange( '' );
check( 'change: default -> class removed', saved.htmlClass === 'custom' );

// Shortcode block.
const plainShortcode = Wrapped( { name: api.SHORTCODE_BLOCK_NAME, attributes: { text: '[gallery ids="1,2"]' }, setAttributes: noop } );
check( 'shortcode block: other shortcode -> no panel', plainShortcode.type === 'Fragment' && find( plainShortcode, 'InspectorControls' ) === null );
let savedText = null;
const shortcodeTree = Wrapped( {
	name: api.SHORTCODE_BLOCK_NAME,
	attributes: { text: 'Intro [contact-form-7 id="123" title="Contact form 1" html_class="custom jgor-flcf7-off"]' },
	setAttributes: ( attributes ) => { savedText = attributes; },
} );
const shortcodePanel = find( shortcodeTree, 'PanelBody' );
const shortcodeSelects = findAll( shortcodePanel, 'SelectControl' );
check( 'shortcode block: panel with four selects, values read from html_class', !! shortcodePanel && shortcodeSelects.length === 4 && shortcodeSelects.map( ( select ) => select.props.value ).join( '|' ) === 'jgor-flcf7-off|||' );
check( 'shortcode block: help text names html_class', String( find( shortcodePanel, 'p' ).children[ 0 ] ).includes( 'html_class attribute' ) );
shortcodeSelects[ 2 ].props.onChange( 'jgor-flcf7-style-filled' );
check( 'shortcode block: change -> setAttributes({ text }) with the attribute rewritten', savedText !== null && savedText.text === 'Intro [contact-form-7 id="123" title="Contact form 1" html_class="custom jgor-flcf7-off jgor-flcf7-style-filled"]' );
shortcodeSelects[ 0 ].props.onChange( '' );
check( 'shortcode block: default -> class removed, attribute kept while classes remain', savedText.text === 'Intro [contact-form-7 id="123" title="Contact form 1" html_class="custom"]' );
const onlyOurs = Wrapped( {
	name: api.SHORTCODE_BLOCK_NAME,
	attributes: { text: '[contact-form-7 id="5" html_class="jgor-flcf7-on"]' },
	setAttributes: ( attributes ) => { savedText = attributes; },
} );
findAll( find( onlyOurs, 'PanelBody' ), 'SelectControl' )[ 0 ].props.onChange( '' );
check( 'shortcode block: last class removed -> attribute removed', savedText.text === '[contact-form-7 id="5"]' );

if ( failed > 0 ) {
	console.error( failed + ' check(s) failed.' );
	process.exit( 1 );
}
console.log( 'Success: All checks passed.' );

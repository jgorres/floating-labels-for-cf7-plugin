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
 * objects, so the element tree the panels build can be inspected (function
 * components such as the colour rows are expanded by calling them), the
 * filter registration is recorded instead of applied, and useSettings hands
 * over a small colour palette. The design properties the server hands over
 * (window.jgorFlcf7EditorData) are stubbed with the same 22 properties and
 * groups as the settings screen. Covered: the class helpers, the shortcode
 * attribute helpers (html_class, jgor_flcf7_design), the design helpers,
 * the palette groups, the panels on the Contact Form 7 block and on a
 * Shortcode block (collapsible PanelBody each, colour rows with palette
 * popover, controls without extra wrappers), and the script without the
 * server data.
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
 * Returns the children of an element; a function component is called with
 * its props so its own element tree can be searched.
 *
 * @param {Object} node Element built by the stub.
 * @return {*} Children, or the rendered tree of a function component.
 */
function childrenOf( node ) {
	if ( typeof node.type === 'function' ) {
		return node.type( { ...node.props, children: node.children } );
	}
	return node.children;
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
	return find( childrenOf( node ), type );
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
	return ( node.type === type ? [ node ] : [] ).concat( findAll( childrenOf( node ), type ) );
}

/**
 * Builds the server data: the 22 design properties in the groups and the
 * order of the settings screen (JGOR_FLCF7_Admin::DESIGN_GROUPS).
 *
 * @return {Object} Data as JGOR_FLCF7_Editor::inline_data() prints it.
 */
function serverData() {
	const groups = {
		colors: [ 'label-color', 'label-color-active', 'label-color-error', 'border-color', 'border-color-active', 'border-color-error', 'background', 'background-filled', 'submit-error-background', 'submit-error-color' ],
		sizes: [ 'font-size', 'label-size', 'padding-x', 'padding-y', 'border-width', 'radius' ],
		motion: [ 'label-scale', 'duration' ],
		effect: [ 'effect-color', 'effect-size', 'effect-width', 'effect-duration' ],
	};
	const types = { 'label-scale': 'number', duration: 'time', 'effect-color': 'color', 'effect-duration': 'time' };
	const placeholders = { 'label-color': '#5f6368', 'font-size': '1em', 'label-scale': '0.75', duration: '0.15s', 'effect-color': '#1a73e8', 'effect-duration': '1.5s' };
	const design = [];
	for ( const [ group, keys ] of Object.entries( groups ) ) {
		for ( const key of keys ) {
			design.push( {
				key,
				group,
				type: types[ key ] || ( group === 'colors' ? 'color' : 'length' ),
				label: 'Label of ' + key,
				placeholder: placeholders[ key ] || '1px',
			} );
		}
	}
	return { design };
}

/**
 * Renders the toggle button of a colour row (Dropdown element).
 *
 * @param {Object} row Dropdown element of a colour row.
 * @return {Object} Button element.
 */
function toggleOf( row ) {
	return row.props.renderToggle( { isOpen: false, onToggle: () => {} } );
}

/**
 * Returns the ColorPalette element of a colour row (Dropdown element).
 *
 * @param {Object} row Dropdown element of a colour row.
 * @return {Object|null} ColorPalette element.
 */
function paletteOf( row ) {
	return find( row.props.renderContent(), 'ColorPalette' );
}

// Stubs of the WordPress packages the script reads from window.wp.
const filters = [];
const themePalette = [ { name: 'Base', slug: 'base', color: '#ffffff' }, { name: 'Contrast', slug: 'contrast', color: '#111111' } ];
const defaultPalette = [ { name: 'Black', slug: 'black', color: '#000000' } ];
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
	components: { PanelBody: 'PanelBody', SelectControl: 'SelectControl', TextControl: 'TextControl', Flex: 'Flex', Dropdown: 'Dropdown', Button: 'Button', ColorIndicator: 'ColorIndicator', ColorPalette: 'ColorPalette' },
	blockEditor: {
		InspectorControls: 'InspectorControls',
		PanelColorSettings: 'PanelColorSettings',
		useSettings: ( ...paths ) => paths.map( ( path ) => ( { 'color.palette.theme': themePalette, 'color.palette.default': defaultPalette, 'color.palette.custom': [], 'color.defaultPalette': true } )[ path ] ),
	},
	i18n: { __: ( text ) => text, _x: ( text ) => text },
};
globalThis.jgorFlcf7EditorData = serverData();

const file = resolve( dirname( fileURLToPath( import.meta.url ) ), '../plugin/assets/js/block-editor.js' );
const source = readFileSync( file, 'utf8' );
vm.runInThisContext( source, { filename: file } );

const api = globalThis.jgorFlcf7BlockEditor;
check( 'api: window.jgorFlcf7BlockEditor exposed', !! api && typeof api.withGroupValue === 'function' && typeof api.withShortcodeHtmlClass === 'function' && typeof api.withShortcodeDesign === 'function' && typeof api.ColorControl === 'function' && typeof api.withoutGroups === 'function' && typeof api.designWithoutGroup === 'function' );

// Filter registration.
check( 'filter: editor.BlockEdit registered once under jgor-flcf7/block-editor', filters.length === 1 && filters[ 0 ].hook === 'editor.BlockEdit' && filters[ 0 ].namespace === 'jgor-flcf7/block-editor' && typeof filters[ 0 ].callback === 'function' );

// Groups and classes (concept, section 7.5).
const groups = api.GROUPS;
const byKey = Object.fromEntries( groups.map( ( group ) => [ group.key, group ] ) );
check( 'groups: enabled, mode, style, effect, marker', groups.map( ( group ) => group.key ).join( ',' ) === 'enabled,mode,style,effect,marker' );
check( 'groups: first option of every group is the empty default', groups.every( ( group ) => group.options[ 0 ].value === '' && group.options[ 0 ].label === 'Default' ) );
check( 'groups: label source offers labels, placeholders and text before the field', byKey.mode.options.map( ( option ) => option.value ).join( ',' ) === ',jgor-flcf7-mode-label,jgor-flcf7-mode-placeholder,jgor-flcf7-mode-text' && byKey.mode.options[ 3 ].label === 'Text before the field only' );
const classes = groups.flatMap( ( group ) => group.options.map( ( option ) => option.value ) ).filter( ( value ) => value !== '' ).sort();
const expected = [
	'jgor-flcf7-effect-glow', 'jgor-flcf7-effect-none', 'jgor-flcf7-effect-pulse', 'jgor-flcf7-effect-sweep',
	'jgor-flcf7-marker-off', 'jgor-flcf7-marker-on', 'jgor-flcf7-mode-label', 'jgor-flcf7-mode-placeholder', 'jgor-flcf7-mode-text',
	'jgor-flcf7-off', 'jgor-flcf7-on', 'jgor-flcf7-style-filled', 'jgor-flcf7-style-minimal', 'jgor-flcf7-style-outlined',
];
check( 'groups: exactly the fourteen placement classes', classes.join( ' ' ) === expected.join( ' ' ), classes.join( ' ' ) );

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
check( 'withoutGroups: every placement class removed, other classes kept', api.withoutGroups( 'custom jgor-flcf7-style-filled jgor-flcf7-off x jgor-flcf7-mode-text' ) === 'custom x' && api.withoutGroups( 'jgor-flcf7-on' ) === '' && api.withoutGroups( undefined ) === '' );
check( 'hasGroupValue: true with a placement class only', api.hasGroupValue( 'custom' ) === false && api.hasGroupValue( 'custom jgor-flcf7-marker-on' ) === true && api.hasGroupValue( '' ) === false );
check( 'withGroupValue: effect classes form one group', api.withGroupValue( 'jgor-flcf7-effect-glow jgor-flcf7-style-filled', byKey.effect, 'jgor-flcf7-effect-sweep' ) === 'jgor-flcf7-effect-sweep jgor-flcf7-style-filled' && api.currentValue( 'jgor-flcf7-effect-none', byKey.effect ) === 'jgor-flcf7-effect-none' );

// Shortcode helpers (html_class).
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

// Design helpers.
check( 'design: 22 properties from the server data', api.DESIGN.length === 22 && api.DESIGN[ 0 ].key === 'label-color' && api.DESIGN[ 17 ].key === 'duration' && api.DESIGN[ 21 ].key === 'effect-duration' );
check( 'cleanDesign: known keys with non-empty strings only, trimmed, in server order', JSON.stringify( api.cleanDesign( { 'font-size': ' 1.2em ', 'label-color': '#123456', bogus: '1px', radius: '', duration: 7 } ) ) === '{"label-color":"#123456","font-size":"1.2em"}' );
check( 'cleanDesign: characters that would break a shortcode removed', api.cleanDesign( { 'font-size': '1.2em;" x]' } )[ 'font-size' ] === '1.2em x' );
check( 'cleanDesign: non-object -> {}', Object.keys( api.cleanDesign( undefined ) ).length === 0 && Object.keys( api.cleanDesign( 'x' ) ).length === 0 );
check( 'parseDesign: "key:value;key:value" with spaces, unknown and empty dropped', JSON.stringify( api.parseDesign( 'font-size: 1.2em ; label-color:#123456;bogus:1;radius:;junk' ) ) === '{"label-color":"#123456","font-size":"1.2em"}' );
check( 'parseDesign: non-string -> {}', Object.keys( api.parseDesign( undefined ) ).length === 0 );
check( 'designWithoutGroup: only the properties of the group removed', JSON.stringify( api.designWithoutGroup( { 'label-color': '#abc', 'font-size': '1em', 'effect-color': '#fff', bogus: 'x' }, 'colors' ) ) === '{"font-size":"1em","effect-color":"#fff"}' && Object.keys( api.designWithoutGroup( { 'font-size': '1em' }, 'sizes' ) ).length === 0 );
check( 'serializeDesign: server order, empty -> ""', api.serializeDesign( { 'font-size': '1.2em', 'label-color': '#123456' } ) === 'label-color:#123456;font-size:1.2em' && api.serializeDesign( {} ) === '' && api.serializeDesign( { bogus: 'x' } ) === '' );
check( 'colorValue: hex lower-cased, others unset', api.colorValue( '#ABC' ) === '#abc' && api.colorValue( ' #a1B2c3 ' ) === '#a1b2c3' && api.colorValue( 'red' ) === '' && api.colorValue( 'rgb(0,0,0)' ) === '' && api.colorValue( '#1234' ) === '' && api.colorValue( undefined ) === '' );
check( 'shortcodeDesign: attribute parsed, absent -> {}', api.shortcodeDesign( '[contact-form-7 id="1" jgor_flcf7_design="font-size:1.2em;label-color:#abc"]' )[ 'font-size' ] === '1.2em' && Object.keys( api.shortcodeDesign( sc ) ).length === 0 );
check( 'withShortcodeDesign: appends the attribute', api.withShortcodeDesign( sc, { 'font-size': '1.2em' } ) === '[contact-form-7 id="123" title="Contact form 1" jgor_flcf7_design="font-size:1.2em"]' );
check( 'withShortcodeDesign: replaces in place, html_class untouched', api.withShortcodeDesign( '[contact-form-7 id="1" jgor_flcf7_design="radius:9px" html_class="a"]', { 'label-color': '#abc' } ) === '[contact-form-7 id="1" jgor_flcf7_design="label-color:#abc" html_class="a"]' );
check( 'withShortcodeDesign: empty values remove the attribute', api.withShortcodeDesign( '[contact-form-7 id="1" jgor_flcf7_design="radius:9px" html_class="a"]', {} ) === '[contact-form-7 id="1" html_class="a"]' );

// Palette groups for the colour rows.
check( 'paletteGroups: theme, default and custom as named groups, in that order', JSON.stringify( api.paletteGroups( themePalette, defaultPalette, [ { color: '#abcdef' } ], true ).map( ( group ) => group.name + ':' + group.colors.length ) ) === '["Theme:2","Default:1","Custom:1"]' );
check( 'paletteGroups: empty groups left out, non-arrays ignored', api.paletteGroups( [], defaultPalette, undefined, true ).length === 1 && api.paletteGroups( undefined, undefined, undefined, true ).length === 0 );
check( 'paletteGroups: default palette hidden when the theme switches it off', api.paletteGroups( themePalette, defaultPalette, [], false ).map( ( group ) => group.name ).join( ',' ) === 'Theme' );

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
	attributes: { id: 123, htmlClass: 'custom jgor-flcf7-style-filled', jgorFlcf7Design: { 'label-color': '#abcdef', 'font-size': '1.2em', bogus: 'x' } },
	setAttributes: ( attributes ) => { saved = attributes; },
} );
check( 'hoc: original edit component kept', find( tree, BlockEdit ) !== null );
const inspector = find( tree, 'InspectorControls' );
const panelBodies = findAll( inspector, 'PanelBody' );
const panelBody = panelBodies[ 0 ];
check( 'panel: InspectorControls with PanelBody "Floating labels" first, open by default', !! panelBody && panelBody.props.title === 'Floating labels' && panelBody.props.initialOpen === true );
const selects = findAll( panelBody, 'SelectControl' );
check( 'panel: intro sentence first, five selects directly in the panel, no Flex wrapper (spacing of the inspector), help and reset button behind them', selects.length === 5 && find( panelBody, 'Flex' ) === null && panelBody.children[ 0 ].length === 8 && panelBody.children[ 0 ][ 0 ].type === 'p' && panelBody.children[ 0 ][ 0 ].props.className === 'jgor-flcf7-intro' && panelBody.children[ 0 ][ 0 ].children[ 0 ] === 'Modify the floating label settings in your Contact Form 7 forms.' );
check( 'panel: labels', selects.map( ( select ) => select.props.label ).join( '|' ) === 'Floating labels|Label source|Variant|Focus effect|Required fields' );
check( 'panel: values read from htmlClass', selects.map( ( select ) => select.props.value ).join( '|' ) === '||jgor-flcf7-style-filled||' );
check( 'panel: keys set on the selects', selects.every( ( select, index ) => select.props.key === groups[ index ].key ) );
check( 'panel: help text names the "Class" field', findAll( panelBody, 'p' ).length === 2 && String( findAll( panelBody, 'p' )[ 1 ].children[ 0 ] ).includes( '"Class"' ) );

selects[ 2 ].props.onChange( 'jgor-flcf7-style-minimal' );
check( 'change: variant -> setAttributes({ htmlClass }) with the class replaced', saved !== null && saved.htmlClass === 'custom jgor-flcf7-style-minimal' );
selects[ 0 ].props.onChange( 'jgor-flcf7-off' );
check( 'change: on/off -> class appended, others kept', saved.htmlClass === 'custom jgor-flcf7-style-filled jgor-flcf7-off' );
selects[ 3 ].props.onChange( 'jgor-flcf7-effect-glow' );
check( 'change: focus effect -> class appended', saved.htmlClass === 'custom jgor-flcf7-style-filled jgor-flcf7-effect-glow' );
selects[ 2 ].props.onChange( '' );
check( 'change: default -> class removed', saved.htmlClass === 'custom' );
selects[ 1 ].props.onChange( 'jgor-flcf7-mode-text' );
check( 'change: label source "Text before the field only" -> class appended', saved.htmlClass === 'custom jgor-flcf7-style-filled jgor-flcf7-mode-text' );
const resetMain = findAll( panelBody, 'Button' );
check( 'reset: one secondary button "Reset this placement" at the end of the panel, enabled while a class or a design value is set', resetMain.length === 1 && resetMain[ 0 ].props.className === 'jgor-flcf7-reset' && resetMain[ 0 ].props.variant === 'secondary' && resetMain[ 0 ].props.disabled === false && resetMain[ 0 ].children[ 0 ] === 'Reset this placement' && panelBody.children[ 0 ][ 7 ] === resetMain[ 0 ] );
resetMain[ 0 ].props.onClick();
check( 'reset: placement classes removed, other classes kept, design attribute dropped in one setAttributes()', saved.htmlClass === 'custom' && 'jgorFlcf7Design' in saved && saved.jgorFlcf7Design === undefined );
const untouched = Wrapped( { name: api.BLOCK_NAME, attributes: { id: 7, htmlClass: 'custom' }, setAttributes: noop } );
check( 'reset: disabled while nothing of the plugin is set', findAll( find( untouched, 'InspectorControls' ), 'Button' ).every( ( button ) => button.props.disabled === true ) );

// Design panels on the Contact Form 7 block: collapsible PanelBody each.
check( 'design: five PanelBody panels: "Floating labels", colours, sizes, animation, focus effect; the design panels closed', panelBodies.length === 5 && panelBodies.slice( 1 ).map( ( body ) => body.props.title ).join( '|' ) === 'Floating labels: colours|Floating labels: sizes|Floating labels: animation|Floating labels: focus effect' && panelBodies.slice( 1 ).every( ( body ) => body.props.initialOpen === false ) );
check( 'design: PanelColorSettings (ToolsPanel, not collapsible) no longer used', find( inspector, 'PanelColorSettings' ) === null );
const colorRows = findAll( panelBodies[ 1 ], 'Dropdown' );
check( 'design: ten colour rows (Dropdown) with popover next to the sidebar, no Flex wrapper', colorRows.length === 10 && find( panelBodies[ 1 ], 'Flex' ) === null && colorRows.every( ( row ) => row.props.popoverProps.placement === 'left-start' && row.props.className === 'jgor-flcf7-color' ) );
const firstToggle = toggleOf( colorRows[ 0 ] );
const secondToggle = toggleOf( colorRows[ 1 ] );
check( 'design: toggle is a full-width button with the colour indicator and the label', firstToggle.type === 'Button' && firstToggle.props.className === 'jgor-flcf7-color__toggle' && find( firstToggle, 'ColorIndicator' ).props.colorValue === '#abcdef' && String( find( firstToggle, 'span' ).children[ 0 ] ) === 'Label of label-color' );
check( 'design: unset colour -> hatched indicator without colour', find( secondToggle, 'ColorIndicator' ).props.colorValue === undefined && find( secondToggle, 'ColorIndicator' ).props.className.includes( 'is-unset' ) && ! find( firstToggle, 'ColorIndicator' ).props.className.includes( 'is-unset' ) );
const firstPalette = paletteOf( colorRows[ 0 ] );
check( 'design: popover holds the ColorPalette with the palette groups, clearable, hex only', !! firstPalette && firstPalette.props.value === '#abcdef' && firstPalette.props.clearable === true && firstPalette.props.enableAlpha === false && firstPalette.props.colors.map( ( group ) => group.name ).join( ',' ) === 'Theme,Default' && paletteOf( colorRows[ 1 ] ).props.value === undefined );
check( 'design: storage note under the colours', !! find( panelBodies[ 1 ], 'p' ) && String( find( panelBodies[ 1 ], 'p' ).children[ 0 ] ).includes( 'stored with the block' ) );
const resetColors = findAll( panelBodies[ 1 ], 'Button' );
const resetMotion = findAll( panelBodies[ 3 ], 'Button' );
check( 'design: reset button per panel, enabled only for a group with values', resetColors.length === 1 && resetColors[ 0 ].children[ 0 ] === 'Reset colours' && resetColors[ 0 ].props.disabled === false && resetMotion.length === 1 && resetMotion[ 0 ].children[ 0 ] === 'Reset animation' && resetMotion[ 0 ].props.disabled === true && findAll( panelBodies[ 2 ], 'Button' )[ 0 ].children[ 0 ] === 'Reset sizes' && findAll( panelBodies[ 4 ], 'Button' )[ 0 ].children[ 0 ] === 'Reset focus effect' );
resetColors[ 0 ].props.onClick();
check( 'design: reset colours removes that group only', JSON.stringify( saved.jgorFlcf7Design ) === '{"font-size":"1.2em"}' );
const sizeControls = findAll( panelBodies[ 2 ], 'TextControl' );
const motionControls = findAll( panelBodies[ 3 ], 'TextControl' );
check( 'design: six size controls with placeholder, value from jgorFlcf7Design, no Flex wrapper', sizeControls.length === 6 && find( panelBodies[ 2 ], 'Flex' ) === null && sizeControls[ 0 ].props.label === 'Label of font-size' && sizeControls[ 0 ].props.value === '1.2em' && sizeControls[ 0 ].props.placeholder === '1em' && sizeControls[ 1 ].props.value === '' && sizeControls[ 0 ].props.type === undefined );
check( 'design: two animation controls, the scale as number input 0.5 to 1', motionControls.length === 2 && motionControls[ 0 ].props.type === 'number' && motionControls[ 0 ].props.min === 0.5 && motionControls[ 0 ].props.max === 1 && motionControls[ 0 ].props.step === 0.01 && motionControls[ 0 ].props.placeholder === '0.75' && motionControls[ 1 ].props.type === undefined );
check( 'design: format notes under sizes and animation', String( find( panelBodies[ 2 ], 'p' ).children[ 0 ] ).includes( 'px, em, rem or %' ) && String( find( panelBodies[ 3 ], 'p' ).children[ 0 ] ).includes( '0.5 and 1' ) );
const effectRows = findAll( panelBodies[ 4 ], 'Dropdown' );
const effectControls = findAll( panelBodies[ 4 ], 'TextControl' );
check( 'design: focus effect panel with one colour row and three text controls, note names the panel "Floating labels"', effectRows.length === 1 && String( find( toggleOf( effectRows[ 0 ] ), 'span' ).children[ 0 ] ) === 'Label of effect-color' && effectControls.length === 3 && effectControls.map( ( control ) => control.props.key ).join( ',' ) === 'effect-size,effect-width,effect-duration' && effectControls[ 2 ].props.placeholder === '1.5s' && effectControls.every( ( control ) => control.props.type === undefined ) && String( find( panelBodies[ 4 ], 'p' ).children[ 0 ] ).includes( '"Floating labels"' ) );

paletteOf( colorRows[ 3 ] ).props.onChange( '#00FF00' );
check( 'design change: colour -> setAttributes({ jgorFlcf7Design }) with the hex colour added, unknown key dropped', JSON.stringify( saved.jgorFlcf7Design ) === '{"label-color":"#abcdef","border-color":"#00ff00","font-size":"1.2em"}' );
paletteOf( colorRows[ 0 ] ).props.onChange( undefined );
check( 'design change: colour cleared -> key removed', JSON.stringify( saved.jgorFlcf7Design ) === '{"font-size":"1.2em"}' );
paletteOf( colorRows[ 0 ] ).props.onChange( 'rgb(1,2,3)' );
check( 'design change: non-hex colour -> key removed', JSON.stringify( saved.jgorFlcf7Design ) === '{"font-size":"1.2em"}' );
sizeControls[ 2 ].props.onChange( '2em' );
check( 'design change: size typed -> added', JSON.stringify( saved.jgorFlcf7Design ) === '{"label-color":"#abcdef","font-size":"1.2em","padding-x":"2em"}' );
paletteOf( effectRows[ 0 ] ).props.onChange( '#ff8800' );
effectControls[ 2 ].props.onChange( '2s' );
check( 'design change: effect colour and duration -> added in server order', JSON.stringify( saved.jgorFlcf7Design ) === '{"label-color":"#abcdef","font-size":"1.2em","effect-duration":"2s"}' );
const onlyOne = Wrapped( {
	name: api.BLOCK_NAME,
	attributes: { id: 123, jgorFlcf7Design: { 'font-size': '1.2em' } },
	setAttributes: ( attributes ) => { saved = attributes; },
} );
findAll( findAll( find( onlyOne, 'InspectorControls' ), 'PanelBody' )[ 2 ], 'TextControl' )[ 0 ].props.onChange( '' );
check( 'design change: last value cleared -> attribute set to undefined (leaves the block)', 'jgorFlcf7Design' in saved && saved.jgorFlcf7Design === undefined );

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
check( 'shortcode block: panel with five selects, values read from html_class', !! shortcodePanel && shortcodeSelects.length === 5 && shortcodeSelects.map( ( select ) => select.props.value ).join( '|' ) === 'jgor-flcf7-off||||' );
check( 'shortcode block: intro sentence first, help text names html_class', findAll( shortcodePanel, 'p' ).length === 2 && String( findAll( shortcodePanel, 'p' )[ 0 ].children[ 0 ] ).includes( 'Modify the floating label settings' ) && String( findAll( shortcodePanel, 'p' )[ 1 ].children[ 0 ] ).includes( 'html_class attribute' ) );
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
findAll( shortcodePanel, 'Button' )[ 0 ].props.onClick();
check( 'shortcode block: reset -> placement classes removed from html_class in one setAttributes()', savedText.text === 'Intro [contact-form-7 id="123" title="Contact form 1" html_class="custom"]' );

// Design panels on the Shortcode block.
const shortcodeDesignTree = Wrapped( {
	name: api.SHORTCODE_BLOCK_NAME,
	attributes: { text: '[contact-form-7 id="5" jgor_flcf7_design="font-size:1.2em" html_class="a"]' },
	setAttributes: ( attributes ) => { savedText = attributes; },
} );
const shortcodeInspector = find( shortcodeDesignTree, 'InspectorControls' );
const shortcodeBodies = findAll( shortcodeInspector, 'PanelBody' );
const shortcodeColors = findAll( shortcodeBodies[ 1 ], 'Dropdown' );
const shortcodeSizes = findAll( shortcodeBodies[ 2 ], 'TextControl' );
check( 'shortcode block: design panels present, value read from jgor_flcf7_design', shortcodeBodies.length === 5 && shortcodeColors.length === 10 && shortcodeSizes.length === 6 && shortcodeSizes[ 0 ].props.value === '1.2em' );
check( 'shortcode block: storage note names jgor_flcf7_design', String( find( shortcodeBodies[ 1 ], 'p' ).children[ 0 ] ).includes( 'jgor_flcf7_design attribute' ) );
paletteOf( shortcodeColors[ 0 ] ).props.onChange( '#123456' );
check( 'shortcode block: colour -> attribute rewritten in place, html_class untouched', savedText.text === '[contact-form-7 id="5" jgor_flcf7_design="label-color:#123456;font-size:1.2em" html_class="a"]' );
shortcodeSizes[ 0 ].props.onChange( '' );
check( 'shortcode block: last design value cleared -> attribute removed', savedText.text === '[contact-form-7 id="5" html_class="a"]' );
findAll( shortcodeBodies[ 0 ], 'Button' )[ 0 ].props.onClick();
check( 'shortcode block: reset -> design attribute removed, foreign class kept', savedText.text === '[contact-form-7 id="5" html_class="a"]' );
findAll( shortcodeBodies[ 2 ], 'Button' )[ 0 ].props.onClick();
check( 'shortcode block: reset sizes -> attribute removed when nothing is left', savedText.text === '[contact-form-7 id="5" html_class="a"]' );

// Without the server data: only the first panel.
delete globalThis.jgorFlcf7EditorData;
vm.runInThisContext( source, { filename: file } );
const bare = globalThis.jgorFlcf7BlockEditor;
const bareTree = filters[ 1 ].callback( BlockEdit )( { name: bare.BLOCK_NAME, attributes: { id: 1, jgorFlcf7Design: { 'font-size': '1.2em' } }, setAttributes: noop } );
check( 'without data: no design properties, only the panel "Floating labels"', bare.DESIGN.length === 0 && findAll( find( bareTree, 'InspectorControls' ), 'PanelBody' ).length === 1 && find( bareTree, 'Dropdown' ) === null );

if ( failed > 0 ) {
	console.error( failed + ' check(s) failed.' );
	process.exit( 1 );
}
console.log( 'Success: All checks passed.' );

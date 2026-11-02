#!/usr/bin/env node
/**
 * Browser matrix of Floating Labels for CF7 (concept §13, step 17).
 *
 * Drives the sample page (Contact > Floating Labels > Sample page) in the
 * local browsers of bodhran and in the Windows browsers of the VM
 * "browser-dev" through lib/browser-matrix.js of the local-sites toolchain
 * (stage 10) and checks the states the stylesheet produces without
 * JavaScript:
 *
 *   per variant (outlined, filled, minimal, switched through the option
 *   jgor_flcf7_settings with wp-cli and restored at the end):
 *     ruhe          label at rest in the empty field
 *     fokus-klick   label floated and in the active colour after a click
 *     fokus-tab     label floated after Tab from the previous field
 *     eingabe       label stays floated in the rest colour after input and blur
 *     fehler        submit with empty required fields: control invalid, label
 *                   in the error colour, validation tip inside the wrap,
 *                   submit button in the error colour with the error text
 *     versand       complete form sent (skip_mail): labels sink back
 *     select-leer   blank option selected: label at rest (needs :has())
 *     select-fokus  focused select: label floated
 *     select-wahl   option chosen and blurred: label stays floated
 *     datum         date input: label floated at rest
 *     select-voll   select without blank option: label floated at rest
 *     select-fehler required select empty: tip at the inline end, clear of the arrow
 *     schmal-*      ruhe and fokus at 390 px width
 *   once per browser:
 *     effekt-glow / effekt-pulse / effekt-sweep (forms 4, 6 and 5 of the page)
 *     a11y: every control has an id and a <label for> pointing at it
 *
 * Every scenario leaves a cut-out screenshot and the computed styles of
 * control and label in tests/browser-results/<date>/<target>/, the run ends
 * with an index.html gallery (scenarios × browsers). Pixel differences are
 * printed for browsers of the same platform (Chromium/Edge locally, Edge/
 * Chrome in the VM); Linux against Windows is compared by values only.
 *
 * The script logs in with a temporary administrator it creates with wp-cli
 * (the sample page is private) and removes the account at the end. Nothing
 * else is saved on the site; the variant option is restored.
 *
 * Usage (from the repository root):
 *   ~/bin/local-sites/local-sites-browser.sh run tests/verify-browser.js --ziel beide
 *   ~/bin/local-sites/local-sites-browser.sh run tests/verify-browser.js --ziel lokal --browser chromium
 *   node tests/verify-browser.js --ziel lokal [--variante outlined] [--site-path …] [--url …]
 *
 * Requirements: wp-cli, local site (default below), lib/browser-matrix.js
 * (BROWSER_MATRIX_LIB or ~/bin/local-sites/lib/browser-matrix.js).
 *
 * Author: Jörn Gorres
 * Date: 02.11.2026
 */

'use strict';

const crypto = require( 'crypto' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const lib = require( process.env.BROWSER_MATRIX_LIB || path.join( os.homedir(), 'bin', 'local-sites', 'lib', 'browser-matrix.js' ) );

const SITE_PATH_DEFAULT = '/var/www/local-sites/floating-labels-for-cf7.gorres.local';
const SITE_URL_DEFAULT = 'https://floating-labels-for-cf7.gorres.local';
const OPTION = 'jgor_flcf7_settings';
const USER_LOGIN = 'jgor-browsertest';
const USER_EMAIL = 'browsertest@example.com';
const VARIANTS = [ 'outlined', 'filled', 'minimal' ];
const SETTLE = 400; // ms, longer than --jgor-flcf7-duration (0.15 s)
const NARROW = { width: 390, height: 844 };

// Forms of the sample page by their title part (class-jgor-flcf7-samples.php).
const FORMS = {
	default: 'FL Test Default template',
	filled: 'FL Test Filled and glow',
	minimal: 'FL Test Minimal and running light',
	mixed: 'FL Test Mixed',
	dateSelect: 'FL Test Date and select',
};

const PROPS = [ ...lib.DEFAULTS.styleProps, 'animationName', 'borderBottomColor' ];

/* ------------------------------------------------------------------------ */
/* wp-cli                                                                    */
/* ------------------------------------------------------------------------ */

let sitePath = SITE_PATH_DEFAULT;

function wp( args, options = {} ) {
	return execFileSync( 'wp', [ `--path=${ sitePath }`, ...args ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ], ...options } ).trim();
}

function readOption() {
	return JSON.parse( wp( [ 'option', 'get', OPTION, '--format=json' ] ) );
}

function setVariant( variant ) {
	wp( [ 'option', 'patch', 'update', OPTION, 'style', variant ] );
}

/**
 * Looks up the form IDs of the sample page by title.
 *
 * @return {Object} key => ID
 */
function formIds() {
	const rows = JSON.parse( wp( [ 'post', 'list', '--post_type=wpcf7_contact_form', '--posts_per_page=100', '--fields=ID,post_title', '--format=json' ] ) );
	const ids = {};
	for ( const [ key, title ] of Object.entries( FORMS ) ) {
		const row = rows.find( ( r ) => r.post_title.startsWith( title ) );
		if ( ! row ) {
			throw new Error( `Beispielformular „${ title }“ fehlt; Beispielseite unter Contact > Floating Labels anlegen.` );
		}
		ids[ key ] = String( row.ID );
	}
	return ids;
}

function samplePageUrl( base ) {
	const rows = JSON.parse( wp( [ 'post', 'list', '--post_type=page', '--name=testpage-floating-labels-for-cf7', '--post_status=private,publish', '--fields=ID', '--format=json' ] ) );
	if ( ! rows.length ) {
		throw new Error( 'Beispielseite testpage-floating-labels-for-cf7 fehlt.' );
	}
	return `${ base }/?page_id=${ rows[ 0 ].ID }`;
}

function createUser() {
	const password = crypto.randomBytes( 18 ).toString( 'base64url' );
	let id;
	try {
		id = wp( [ 'user', 'create', USER_LOGIN, USER_EMAIL, '--role=administrator', `--user_pass=${ password }`, '--porcelain' ] );
	} catch ( e ) {
		// Left over from an aborted run: reset the password and reuse the account.
		id = wp( [ 'user', 'get', USER_LOGIN, '--field=ID' ] );
		wp( [ 'user', 'update', id, `--user_pass=${ password }` ] );
	}
	return { id, password };
}

function deleteUser( id ) {
	try {
		wp( [ 'user', 'delete', id, '--yes' ] );
	} catch ( e ) {
		console.error( `Warnung: Konto ${ USER_LOGIN } konnte nicht entfernt werden: ${ e.message }` );
	}
}

/* ------------------------------------------------------------------------ */
/* Page helpers                                                              */
/* ------------------------------------------------------------------------ */

const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

/** The first placement of a form on the page. */
function formRoot( page, id ) {
	return page.locator( `div.wpcf7[data-wpcf7-id="${ id }"]` ).first();
}

function wrap( root, name ) {
	return root.locator( `.jgor-flcf7-wrap[data-name="${ name }"]` );
}

function control( root, name ) {
	return wrap( root, name ).locator( '.jgor-flcf7-control' );
}

function label( root, name ) {
	return wrap( root, name ).locator( '.jgor-flcf7-label' );
}

/** Computed values of control, label and tip of one wrap. */
async function stateOf( page, root, name ) {
	const handle = await wrap( root, name ).elementHandle();
	return handle.evaluate( ( el, props ) => {
		const pick = ( node ) => {
			if ( ! node ) {
				return null;
			}
			const cs = getComputedStyle( node );
			const r = node.getBoundingClientRect();
			const styles = {};
			for ( const p of props ) {
				styles[ p ] = cs[ p ];
			}
			return { styles, rect: { x: r.x, y: r.y, w: r.width, h: r.height, right: r.right }, classes: node.className, focused: document.activeElement === node, ariaInvalid: node.getAttribute( 'aria-invalid' ) };
		};
		const ctl = el.querySelector( '.jgor-flcf7-control' );
		const before = getComputedStyle( el, '::before' );
		return {
			wrap: pick( el ),
			control: pick( ctl ),
			label: pick( el.querySelector( '.jgor-flcf7-label' ) ),
			tip: pick( el.querySelector( '.wpcf7-not-valid-tip' ) ),
			before: { opacity: before.opacity, animationName: before.animationName, content: before.content },
		};
	}, PROPS );
}

/** Custom property colours of the form, converted to rgb() like getComputedStyle. */
async function expectedColours( page, root ) {
	const handle = await root.elementHandle();
	return handle.evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		const probe = document.createElement( 'span' );
		el.appendChild( probe );
		const toRgb = ( value ) => {
			probe.style.color = '';
			probe.style.color = value.trim();
			const rgb = getComputedStyle( probe ).color;
			return rgb;
		};
		const out = {
			rest: toRgb( cs.getPropertyValue( '--jgor-flcf7-label-color' ) ),
			active: toRgb( cs.getPropertyValue( '--jgor-flcf7-label-color-active' ) ),
			error: toRgb( cs.getPropertyValue( '--jgor-flcf7-label-color-error' ) ),
			borderActive: toRgb( cs.getPropertyValue( '--jgor-flcf7-border-color-active' ) ),
			borderError: toRgb( cs.getPropertyValue( '--jgor-flcf7-border-color-error' ) ),
			submitError: toRgb( cs.getPropertyValue( '--jgor-flcf7-submit-error-background' ) ),
			effect: toRgb( cs.getPropertyValue( '--jgor-flcf7-effect-color' ) ),
			scale: parseFloat( cs.getPropertyValue( '--jgor-flcf7-label-scale' ) ) || 0.75,
		};
		probe.remove();
		return out;
	} );
}

const floated = ( st, scale ) => !! st && st.styles.transform.startsWith( `matrix(${ scale }, 0, 0, ${ scale }` );
const atRest = ( st ) => !! st && st.styles.transform === 'none';

/** Viewport clip around a locator with a margin (box-shadows, rings). The
 * element is moved below the fixed admin bar so the margin above it stays. */
async function clipAround( page, locator, margin = 16 ) {
	await locator.scrollIntoViewIfNeeded();
	let box = await locator.boundingBox();
	const minY = 32 + margin;
	if ( box.y < minY ) {
		await page.evaluate( ( d ) => window.scrollBy( 0, -d ), minY - box.y );
		await sleep( 100 );
		box = await locator.boundingBox();
	}
	const vp = page.viewportSize();
	const x = Math.max( 0, box.x - margin );
	const y = Math.max( 0, box.y - margin );
	return { x, y, width: Math.min( vp.width - x, box.width + 2 * margin ), height: Math.min( vp.height - y, box.height + 2 * margin ) };
}

async function recordWrap( run, target, page, root, name, scenario, info = '' ) {
	const clip = await clipAround( page, wrap( root, name ) );
	return run.record( target, scenario, { page, clip, info } );
}

/**
 * Records a whole form with a margin: the floated label of the first field
 * sits above the form box, and the form may be taller than the viewport, so
 * the viewport grows for the shot and shrinks back afterwards.
 */
async function recordForm( run, target, page, root, scenario, info = '' ) {
	const margin = 16;
	const vp = page.viewportSize();
	const box = await root.boundingBox();
	const needed = Math.ceil( box.height + 2 * margin );
	if ( needed > vp.height ) {
		await page.setViewportSize( { width: vp.width, height: needed } );
	}
	const clip = await clipAround( page, root, margin );
	await run.record( target, scenario, { page, clip, info } );
	if ( needed > vp.height ) {
		await page.setViewportSize( vp );
	}
}

async function blur( page ) {
	await page.evaluate( () => document.activeElement && document.activeElement.blur() );
	await sleep( SETTLE );
}

/** Submits a form and waits for Contact Form 7 to mark it invalid or sent. */
async function submitAndWait( root, state ) {
	await root.locator( 'input.wpcf7-submit' ).click();
	await root.locator( `form.${ state }` ).waitFor( { timeout: 20000 } );
	await sleep( SETTLE );
}

/* ------------------------------------------------------------------------ */
/* Scenarios                                                                 */
/* ------------------------------------------------------------------------ */

async function variantScenarios( run, target, page, url, ids, variant ) {
	const v = variant;
	await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
	const root = formRoot( page, ids.default );
	const colours = await expectedColours( page, root );
	const scale = colours.scale;
	let st;

	// Variant class present on the wrap?
	st = await stateOf( page, root, 'your-name' );
	run.check( target, st.wrap.classes.includes( `jgor-flcf7-${ v }` ), `${ v }: Wrap trägt jgor-flcf7-${ v }` );

	// ruhe
	run.check( target, atRest( st.label ) && st.label.styles.color === colours.rest, `${ v }/ruhe: Label ruht im Feld, Farbe ${ st.label.styles.color }` );
	await recordWrap( run, target, page, root, 'your-name', `${ v }-ruhe` );

	// fokus-klick
	await control( root, 'your-name' ).click();
	await sleep( SETTLE );
	st = await stateOf( page, root, 'your-name' );
	run.check( target, st.control.focused && floated( st.label, scale ), `${ v }/fokus-klick: Label floatet (${ st.label.styles.transform })` );
	run.check( target, st.label.styles.color === colours.active, `${ v }/fokus-klick: Label in Aktivfarbe ${ st.label.styles.color }` );
	if ( v === 'outlined' ) {
		run.check( target, st.control.styles.borderTopColor === colours.borderActive, `${ v }/fokus-klick: Rahmen aktiv ${ st.control.styles.borderTopColor }` );
	} else if ( v === 'filled' ) {
		run.check( target, st.control.styles.borderBottomColor === colours.borderActive, `${ v }/fokus-klick: Unterlinie aktiv ${ st.control.styles.borderBottomColor }` );
	}
	await recordWrap( run, target, page, root, 'your-name', `${ v }-fokus-klick` );

	// fokus-tab: Tab moves from your-name to your-email
	await page.keyboard.press( 'Tab' );
	await sleep( SETTLE );
	st = await stateOf( page, root, 'your-email' );
	run.check( target, st.control.focused && floated( st.label, scale ), `${ v }/fokus-tab: Label von your-email floatet nach Tab` );
	await recordWrap( run, target, page, root, 'your-email', `${ v }-fokus-tab` );

	// eingabe: type into your-name, leave the field
	await control( root, 'your-name' ).fill( 'Jane Doe' );
	await blur( page );
	st = await stateOf( page, root, 'your-name' );
	run.check( target, floated( st.label, scale ) && st.label.styles.color === colours.rest, `${ v }/eingabe: Label bleibt oben in Ruhefarbe` );
	await recordWrap( run, target, page, root, 'your-name', `${ v }-eingabe` );

	// fehler: submit with the other required fields empty
	await submitAndWait( root, 'invalid' );
	st = await stateOf( page, root, 'your-email' );
	run.check( target, st.control.classes.includes( 'wpcf7-not-valid' ) && st.control.ariaInvalid === 'true', `${ v }/fehler: your-email ungültig markiert` );
	run.check( target, floated( st.label, scale ) && st.label.styles.color === colours.error, `${ v }/fehler: Label oben in Fehlerfarbe ${ st.label.styles.color }` );
	run.check( target, !! st.tip && st.tip.rect.x >= st.wrap.rect.x && st.tip.rect.right <= st.wrap.rect.right + 1 && st.tip.rect.y >= st.wrap.rect.y, `${ v }/fehler: Tip liegt im Wrap (${ st.tip ? Math.round( st.tip.rect.x - st.wrap.rect.x ) + ' px vom Rand' : 'kein Tip' })` );
	if ( v === 'outlined' ) {
		run.check( target, st.control.styles.borderTopColor === colours.borderError, `${ v }/fehler: Rahmen in Fehlerfarbe` );
	}
	const submit = await root.locator( '.jgor-flcf7-submit' ).elementHandle();
	const submitState = await submit.evaluate( ( el ) => {
		const btn = getComputedStyle( el.querySelector( '.wpcf7-submit' ) );
		const err = getComputedStyle( el.querySelector( '.jgor-flcf7-submit-error' ) );
		return { bg: btn.backgroundColor, display: err.display, text: el.querySelector( '.jgor-flcf7-submit-error' ).textContent };
	} );
	run.check( target, submitState.bg === colours.submitError && submitState.display === 'flex', `${ v }/fehler: Senden-Button rot mit Text „${ submitState.text }“` );
	await recordForm( run, target, page, root, `${ v }-fehler`, 'Formular nach leerem Absenden' );

	// versand: fill everything, send (skip_mail), labels sink back
	await control( root, 'your-email' ).fill( 'jane@example.com' );
	await control( root, 'your-subject' ).fill( 'Browser matrix' );
	await control( root, 'your-message' ).fill( 'Automated run.' );
	await blur( page );
	await submitAndWait( root, 'sent' );
	st = await stateOf( page, root, 'your-name' );
	run.check( target, atRest( st.label ) && ! st.control.classes.includes( 'wpcf7-not-valid' ), `${ v }/versand: Formular gesendet, Label wieder im Feld` );
	await recordForm( run, target, page, root, `${ v }-versand`, 'nach dem Versand (skip_mail)' );

	// Date and select form
	const ds = formRoot( page, ids.dateSelect );
	st = await stateOf( page, ds, 'your-topic' );
	run.check( target, atRest( st.label ), `${ v }/select-leer: Leeroption gewählt, Label ruht (${ st.label.styles.transform })` );
	await recordWrap( run, target, page, ds, 'your-topic', `${ v }-select-leer` );

	await control( ds, 'your-topic' ).focus();
	await sleep( SETTLE );
	st = await stateOf( page, ds, 'your-topic' );
	run.check( target, floated( st.label, scale ) && st.label.styles.color === colours.active, `${ v }/select-fokus: Label floatet bei Fokus` );
	await recordWrap( run, target, page, ds, 'your-topic', `${ v }-select-fokus` );

	await control( ds, 'your-topic' ).selectOption( 'Support' );
	await blur( page );
	st = await stateOf( page, ds, 'your-topic' );
	run.check( target, floated( st.label, scale ) && st.label.styles.color === colours.rest, `${ v }/select-wahl: Auswahl getroffen, Label bleibt oben` );
	await recordWrap( run, target, page, ds, 'your-topic', `${ v }-select-wahl` );

	st = await stateOf( page, ds, 'your-date' );
	run.check( target, floated( st.label, scale ), `${ v }/datum: Label des Datumsfelds steht oben` );
	await recordWrap( run, target, page, ds, 'your-date', `${ v }-datum` );

	st = await stateOf( page, ds, 'your-country' );
	run.check( target, floated( st.label, scale ), `${ v }/select-voll: Select ohne Leeroption, Label oben` );
	await recordWrap( run, target, page, ds, 'your-country', `${ v }-select-voll` );

	await submitAndWait( ds, 'invalid' );
	st = await stateOf( page, ds, 'your-reason' );
	run.check( target, !! st.tip && st.tip.rect.right <= st.control.rect.right - 16, `${ v }/select-fehler: Tip endet ${ st.tip ? Math.round( st.control.rect.right - st.tip.rect.right ) : '-' } px vor dem Pfeil` );
	run.check( target, floated( st.label, scale ) && st.label.styles.color === colours.error, `${ v }/select-fehler: Label in Fehlerfarbe` );
	await recordWrap( run, target, page, ds, 'your-reason', `${ v }-select-fehler` );

	// Narrow viewport
	await page.setViewportSize( NARROW );
	await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
	const nroot = formRoot( page, ids.default );
	st = await stateOf( page, nroot, 'your-name' );
	run.check( target, atRest( st.label ) && st.label.rect.right <= st.control.rect.right, `${ v }/schmal-ruhe: Label passt bei 390 px ins Feld` );
	await recordWrap( run, target, page, nroot, 'your-name', `${ v }-schmal-ruhe` );
	await control( nroot, 'your-name' ).click();
	await sleep( SETTLE );
	st = await stateOf( page, nroot, 'your-name' );
	run.check( target, floated( st.label, scale ), `${ v }/schmal-fokus: Label floatet bei 390 px` );
	await recordWrap( run, target, page, nroot, 'your-name', `${ v }-schmal-fokus` );
	await page.setViewportSize( lib.DEFAULTS.viewport );
}

async function effectScenarios( run, target, page, url, ids ) {
	await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
	const colours = await expectedColours( page, formRoot( page, ids.default ) );

	// glow: form "Filled and glow", first field
	let root = formRoot( page, ids.filled );
	await control( root, 'your-name' ).click();
	await sleep( SETTLE );
	let st = await stateOf( page, root, 'your-name' );
	run.check( target, st.wrap.classes.includes( 'jgor-flcf7-glow' ) && st.control.styles.boxShadow !== 'none' && st.control.styles.boxShadow.includes( colours.effect ), `effekt-glow: box-shadow ${ st.control.styles.boxShadow }` );
	await run.record( target, 'effekt-glow', { page, clip: await clipAround( page, wrap( root, 'your-name' ), 24 ), animations: 'allow' } );
	await blur( page );

	// pulse: form "Mixed", linked label field
	root = formRoot( page, ids.mixed );
	await control( root, 'your-subject' ).click();
	await sleep( SETTLE );
	st = await stateOf( page, root, 'your-subject' );
	run.check( target, st.wrap.classes.includes( 'jgor-flcf7-pulse' ) && st.control.styles.animationName === 'jgor-flcf7-pulse', `effekt-pulse: animation ${ st.control.styles.animationName }, box-shadow ${ st.control.styles.boxShadow }` );
	await run.record( target, 'effekt-pulse', { page, clip: await clipAround( page, wrap( root, 'your-subject' ), 24 ), animations: 'allow' } );
	await blur( page );

	// sweep: form "Minimal and running light", first field; ring is the ::before of the wrap
	root = formRoot( page, ids.minimal );
	await control( root, 'your-name' ).click();
	await sleep( SETTLE );
	st = await stateOf( page, root, 'your-name' );
	run.check( target, st.wrap.classes.includes( 'jgor-flcf7-sweep' ) && st.before.opacity === '1' && st.before.animationName === 'jgor-flcf7-sweep', `effekt-sweep: Ring sichtbar (opacity ${ st.before.opacity }, animation ${ st.before.animationName })` );
	await run.record( target, 'effekt-sweep', { page, clip: await clipAround( page, wrap( root, 'your-name' ), 24 ), animations: 'allow' } );
	await blur( page );

	// a11y: every control has an id and a label pointing at it
	const a11y = await page.evaluate( () => {
		const controls = Array.from( document.querySelectorAll( '.jgor-flcf7-wrap .jgor-flcf7-control' ) );
		const linked = controls.filter( ( c ) => c.id && c.labels && Array.from( c.labels ).some( ( l ) => l.classList.contains( 'jgor-flcf7-label' ) && l.htmlFor === c.id ) );
		return { total: controls.length, linked: linked.length };
	} );
	run.check( target, a11y.total > 0 && a11y.linked === a11y.total, `a11y: ${ a11y.linked } von ${ a11y.total } Feldern mit verknüpftem <label for>` );
}

/* ------------------------------------------------------------------------ */
/* Main                                                                      */
/* ------------------------------------------------------------------------ */

async function main() {
	const args = lib.parseArgs( process.argv.slice( 2 ) );
	let variants = VARIANTS;
	let siteUrl = SITE_URL_DEFAULT;
	for ( let i = 0; i < args.rest.length; i++ ) {
		if ( args.rest[ i ] === '--variante' ) {
			variants = [ args.rest[ ++i ] ];
		} else if ( args.rest[ i ] === '--site-path' ) {
			sitePath = args.rest[ ++i ];
		} else if ( args.rest[ i ] === '--site-url' ) {
			siteUrl = args.rest[ ++i ].replace( /\/$/, '' );
		}
	}
	for ( const v of variants ) {
		if ( ! VARIANTS.includes( v ) ) {
			throw new Error( `--variante: ${ VARIANTS.join( ', ' ) } erwartet.` );
		}
	}

	const pw = lib.loadPlaywright();
	const run = new lib.Run( { name: 'verify-browser (Floating Labels for CF7)', out: args.out } );
	const list = lib.targets( pw, args );
	const ids = formIds();
	const url = args.url || samplePageUrl( siteUrl );
	const option = readOption();
	const originalVariant = option.style;
	if ( option.design && Object.keys( option.design ).length ) {
		console.log( `Hinweis: Design-Werte in der Option gesetzt (${ Object.keys( option.design ).join( ', ' ) }); Farben werden aus den Custom Properties gelesen.` );
	}
	console.log( `Browser-Matrix: ${ url }` );
	console.log( `Ziele: ${ list.map( ( t ) => t.id ).join( ', ' ) }; Varianten: ${ variants.join( ', ' ) }; Ausgangswert style=${ originalVariant }` );

	const user = createUser();
	let exitCode = 1;
	try {
		await lib.forEachTarget( run, list, async ( target, browser ) => {
			const { context, page } = await lib.newPage( browser );
			try {
				await lib.wpLogin( page, { url: siteUrl, user: USER_LOGIN, pass: user.password } );
				for ( const variant of variants ) {
					setVariant( variant );
					await variantScenarios( run, target, page, url, ids, variant );
				}
				setVariant( originalVariant );
				await effectScenarios( run, target, page, url, ids );
			} finally {
				await context.close();
			}
		} );

		// Pixel differences within one platform (fonts differ across platforms).
		const pairs = [ [ 'lokal-chromium', 'lokal-edge' ], [ 'vm-edge', 'vm-chrome' ] ];
		const byId = Object.fromEntries( list.map( ( t ) => [ t.id, t ] ) );
		for ( const [ a, b ] of pairs ) {
			if ( ! byId[ a ] || ! byId[ b ] ) {
				continue;
			}
			console.log( '' );
			console.log( `Pixelvergleich ${ a } ↔ ${ b } (abweichende Pixel):` );
			for ( const s of run.scenarios ) {
				const n = run.compare( byId[ a ], byId[ b ], s );
				console.log( `  ${ s.padEnd( 24 ) } ${ n === null ? '-' : n }` );
			}
		}
		exitCode = run.finish();
	} finally {
		setVariant( originalVariant );
		deleteUser( user.id );
		console.log( `Option style zurück auf ${ originalVariant }, Konto ${ USER_LOGIN } entfernt.` );
	}
	process.exitCode = exitCode;
}

main().catch( ( e ) => {
	console.error( `Fehler: ${ e.message }` );
	process.exitCode = 1;
} );

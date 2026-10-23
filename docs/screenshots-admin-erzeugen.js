#!/usr/bin/env node
/**
 * Creates the WordPress.org screenshots 5 and 6 of Floating Labels for CF7
 * on the local test site.
 *
 * Screenshot 5: the settings screen (Contact > Floating Labels) with the
 * admin menu, from the page title down to the end of the colour fields,
 * with the colour picker of the first field open.
 * Screenshot 6: the block editor with a Contact Form 7 block selected and
 * the block sidebar scrolled to the panels "Floating Labels" and
 * "Floating labels: colours".
 *
 * The script creates a temporary administrator (locale en_US, display name
 * "Jane Doe") and a draft page, logs in with headless Chromium through
 * playwright-core, takes both screenshots and removes the account, the page
 * and a contact form it had to create. Nothing is saved on the site.
 *
 * Requirements: wp-cli, /usr/bin/chromium (or CHROMIUM=...), playwright-core
 * (installed globally or left in ~/.npm/_npx by @playwright/mcp).
 *
 * Author: Jörn Gorres
 * Date: 23.10.2026
 *
 * Usage:
 *   node docs/screenshots-admin-erzeugen.js [site-path] [site-url]
 */

'use strict';

const crypto = require( 'crypto' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const SITE_PATH = process.argv[ 2 ] || '/var/www/local-sites/floating-labels-for-cf7.gorres.local';
const SITE_URL = ( process.argv[ 3 ] || 'https://floating-labels-for-cf7.gorres.local' ).replace( /\/$/, '' );
const OUT_DIR = path.resolve( __dirname, '..', 'assets' );
const CHROMIUM = process.env.CHROMIUM || '/usr/bin/chromium';
const USER_LOGIN = 'jgor-shottest';
const USER_EMAIL = 'shottest@example.com';
const PAGE_SLUG = 'flcf7-shot-editor';
const FORM_TITLE = 'Contact form 1';
const WIDTH = 1280;
const HEIGHT_SETTINGS = 1000;
const HEIGHT_EDITOR = 1400;
const SETTINGS_PAGE = 'wpcf7-floating-labels';
const BLOCK_NAME = 'contact-form-7/contact-form-selector';
const HIDE_CSS = '#wp-admin-bar-query-monitor, #query-monitor-main { display: none !important; }';

/**
 * Prints a message and exits with an error code.
 *
 * @param {string} message Error message.
 */
function fail( message ) {
	console.error( 'Fehler: ' + message );
	process.exit( 1 );
}

/**
 * Runs wp-cli for the test site and returns its trimmed output.
 *
 * @param {string[]} args    Arguments after `wp --path=...`.
 * @param {Object}   options Extra options for execFileSync.
 * @return {string} Standard output.
 */
function wp( args, options = {} ) {
	return execFileSync( 'wp', [ '--path=' + SITE_PATH, ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'inherit' ],
		...options,
	} ).trim();
}

/**
 * Loads playwright-core: a global installation first, otherwise the newest
 * copy that @playwright/mcp left in the npx cache.
 *
 * @return {Object} The playwright-core module.
 */
function loadPlaywright() {
	try {
		return require( 'playwright-core' );
	} catch ( e ) {
		// Not installed next to this script; look in the npx cache.
	}
	const npxDir = path.join( os.homedir(), '.npm', '_npx' );
	const found = [];
	if ( fs.existsSync( npxDir ) ) {
		for ( const entry of fs.readdirSync( npxDir ) ) {
			const pkg = path.join( npxDir, entry, 'node_modules', 'playwright-core', 'package.json' );
			if ( fs.existsSync( pkg ) ) {
				found.push( { dir: path.dirname( pkg ), version: JSON.parse( fs.readFileSync( pkg, 'utf8' ) ).version } );
			}
		}
	}
	found.sort( ( a, b ) => b.version.localeCompare( a.version, undefined, { numeric: true } ) );
	if ( ! found.length ) {
		fail( 'playwright-core fehlt (npm install -g playwright-core oder einmal npx @playwright/mcp@latest starten).' );
	}
	return require( found[ 0 ].dir );
}

/**
 * Finds the contact form titled FORM_TITLE or creates it.
 *
 * @return {{id: number, hash: string, created: boolean}} Form data.
 */
function prepareForm() {
	const php = `
$jgor_posts = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'title' => '${ FORM_TITLE }', 'numberposts' => 1, 'post_status' => 'any' ) );
if ( $jgor_posts ) {
	$jgor_cf = WPCF7_ContactForm::get_instance( $jgor_posts[0]->ID );
	echo wp_json_encode( array( 'id' => $jgor_cf->id(), 'hash' => $jgor_cf->hash(), 'created' => false ) );
} else {
	$jgor_cf = WPCF7_ContactForm::get_template( array( 'title' => '${ FORM_TITLE }', 'locale' => 'en_US' ) );
	$jgor_cf->save();
	echo wp_json_encode( array( 'id' => $jgor_cf->id(), 'hash' => $jgor_cf->hash(), 'created' => true ) );
}`;
	return JSON.parse( wp( [ 'eval', php ] ) );
}

/**
 * Removes everything the script created on the site. Safe to call twice.
 *
 * @param {Object} state Created objects: pageId, userId, form.
 */
function cleanup( state ) {
	if ( state.pageId ) {
		wp( [ 'post', 'delete', String( state.pageId ), '--force' ] );
		state.pageId = 0;
	}
	if ( state.userId ) {
		const posts = wp( [ 'post', 'list', '--post_type=any', '--post_status=any', '--author=' + state.userId, '--format=ids' ] );
		if ( posts ) {
			wp( [ 'post', 'delete', ...posts.split( ' ' ), '--force' ] );
		}
		wp( [ 'user', 'delete', String( state.userId ), '--yes' ] );
		state.userId = 0;
	}
	if ( state.form && state.form.created ) {
		wp( [ 'post', 'delete', String( state.form.id ), '--force' ] );
		state.form = null;
	}
}

/**
 * Waits until the block editor exposes its stores and the post's blocks.
 *
 * @param {Object} page Playwright page.
 */
async function waitForEditor( page ) {
	for ( let i = 0; i < 80; i++ ) {
		const ready = await page.evaluate( () => !! ( window.wp && wp.data && wp.blocks && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() ) ).catch( () => false );
		if ( ready ) {
			return;
		}
		await page.waitForTimeout( 500 );
	}
	fail( 'Der Block-Editor wurde nicht fertig geladen.' );
}

/**
 * Takes screenshot 5: the settings screen with the first colour picker open.
 *
 * @param {Object} page Playwright page.
 */
async function shootSettings( page ) {
	await page.setViewportSize( { width: WIDTH, height: HEIGHT_SETTINGS } );
	await page.goto( SITE_URL + '/wp-admin/admin.php?page=' + SETTINGS_PAGE, { waitUntil: 'networkidle' } );
	await page.addStyleTag( { content: HIDE_CSS } );
	const picker = page.locator( '.wp-picker-container .wp-color-result' ).first();
	if ( ! ( await picker.count() ) ) {
		fail( 'Kein Farbwähler auf der Einstellungsseite gefunden.' );
	}
	await picker.evaluate( ( el ) => el.scrollIntoView( { behavior: 'instant', block: 'center' } ) );
	await picker.click();
	await page.waitForTimeout( 600 );
	// Fixed admin bar and menu must sit at the top of a full-page shot.
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 300 );
	const bottom = await page.evaluate( () => {
		const th = Array.from( document.querySelectorAll( '#wpbody-content th' ) ).find( ( el ) => el.textContent.trim() === 'Sizes' );
		return th ? Math.round( th.getBoundingClientRect().top + window.scrollY ) - 3 : document.documentElement.scrollHeight;
	} );
	const file = path.join( OUT_DIR, 'screenshot-5.png' );
	await page.screenshot( { path: file, fullPage: true, clip: { x: 0, y: 0, width: WIDTH, height: bottom }, timeout: 20000 } );
	console.log( '  ok  assets/screenshot-5.png (' + WIDTH + 'x' + bottom + ')' );
}

/**
 * Takes screenshot 6: the block editor with the plugin's panels in the
 * block sidebar.
 *
 * @param {Object} page   Playwright page.
 * @param {number} pageId ID of the draft page.
 * @param {Object} form   Contact form data (id, hash).
 */
async function shootEditor( page, pageId, form ) {
	await page.setViewportSize( { width: WIDTH, height: HEIGHT_EDITOR } );
	// The editor keeps the network busy (heartbeat); do not wait for idle.
	await page.goto( SITE_URL + '/wp-admin/post.php?post=' + pageId + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
	await waitForEditor( page );
	await page.evaluate( ( args ) => {
		const prefs = wp.data.dispatch( 'core/preferences' );
		prefs.set( 'core/edit-post', 'welcomeGuide', false );
		prefs.set( 'core', 'welcomeGuide', false );
		prefs.set( 'core/edit-post', 'fullscreenMode', true );
		prefs.set( 'core', 'fullscreenMode', true );
		prefs.set( 'core', 'enableChoosePatternModal', false );
		const block = wp.blocks.createBlock( args.blockName, { id: args.id, hash: args.hash, title: args.title } );
		wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ block ] );
		wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		wp.data.dispatch( 'core/interface' ).enableComplementaryArea( 'core', 'edit-post/block' );
	}, { blockName: BLOCK_NAME, id: form.id, hash: form.hash, title: FORM_TITLE } );
	// The block's combobox shows the form title once CF7's REST list arrived.
	for ( let i = 0; i < 20; i++ ) {
		const value = await page.evaluate( () => { const input = document.querySelector( '.wp-block-contact-form-7-contact-form-selector input, [data-type="contact-form-7/contact-form-selector"] input' ); return input ? input.value : ''; } ).catch( () => '' );
		if ( value ) {
			break;
		}
		await page.waitForTimeout( 500 );
	}
	const guide = page.locator( '.components-guide button[aria-label="Close"]' );
	if ( await guide.count() ) {
		await guide.first().click();
		await page.waitForTimeout( 400 );
	}
	// A new page may open the "Choose a pattern" modal (or another one);
	// its overlay would swallow every click in the sidebar.
	for ( let i = 0; i < 3 && ( await page.locator( '.components-modal__screen-overlay' ).count() ); i++ ) {
		const close = page.locator( '.components-modal__screen-overlay button[aria-label="Close"]' );
		if ( await close.count() ) {
			await close.first().click();
		} else {
			await page.keyboard.press( 'Escape' );
		}
		await page.waitForTimeout( 400 );
	}
	// Leave the combobox, otherwise its list stays open in the shot.
	await page.keyboard.press( 'Escape' );
	await page.evaluate( () => document.activeElement && document.activeElement.blur() );
	await page.waitForTimeout( 400 );
	// Collapse CF7's own panel (titled like the form) and scroll the sidebar
	// so that "Floating Labels" sits right below the sticky tab row.
	const cf7Panel = page.locator( '.components-panel__body-toggle', { hasText: FORM_TITLE } ).first();
	if ( ( await cf7Panel.count() ) && ( await cf7Panel.getAttribute( 'aria-expanded' ) ) === 'true' ) {
		await cf7Panel.click();
		await page.waitForTimeout( 300 );
	}
	// Open the colours panel (closed by default) so its rows are in the shot.
	const coloursPanel = page.locator( '.components-panel__body-toggle', { hasText: 'Floating labels: colours' } ).first();
	if ( ( await coloursPanel.count() ) && ( await coloursPanel.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
		await coloursPanel.click();
		await page.waitForTimeout( 300 );
	}
	await page.evaluate( () => {
		const sidebar = document.querySelector( '.interface-complementary-area' );
		let scroller = sidebar;
		for ( let el = sidebar; el; el = el.parentElement ) {
			const overflow = getComputedStyle( el ).overflowY;
			if ( ( overflow === 'auto' || overflow === 'scroll' ) && el.scrollHeight > el.clientHeight ) {
				scroller = el;
				break;
			}
		}
		const toggle = Array.from( sidebar.querySelectorAll( '.components-panel__body-toggle' ) ).find( ( b ) => b.textContent.trim() === 'Floating Labels' );
		const panel = toggle && toggle.closest( '.components-panel__body' );
		const tabs = scroller.querySelector( '[role="tablist"]' );
		const offset = tabs ? tabs.getBoundingClientRect().height : 0;
		if ( panel ) {
			scroller.scrollTo( { top: panel.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop - offset, behavior: 'instant' } );
		}
		if ( document.activeElement ) {
			document.activeElement.blur();
		}
	} );
	// Park the pointer over the canvas so no sidebar row shows a hover state.
	await page.mouse.move( 40, 600 );
	await page.waitForTimeout( 400 );
	await page.addStyleTag( { content: HIDE_CSS } );
	const file = path.join( OUT_DIR, 'screenshot-6.png' );
	await page.screenshot( { path: file, fullPage: false, timeout: 20000 } );
	console.log( '  ok  assets/screenshot-6.png (' + WIDTH + 'x' + HEIGHT_EDITOR + ')' );
}

( async () => {
	console.log( '====================================================' );
	console.log( '   FLOATING LABELS FOR CF7: SCREENSHOTS 5 UND 6' );
	console.log( '====================================================' );
	console.log( '' );

	if ( ! fs.existsSync( CHROMIUM ) ) {
		fail( 'Chromium fehlt unter ' + CHROMIUM + ' (CHROMIUM=... setzen).' );
	}
	if ( ! fs.existsSync( OUT_DIR ) ) {
		fail( 'Ausgabeordner fehlt: ' + OUT_DIR );
	}
	for ( const plugin of [ 'contact-form-7', 'floating-labels-for-cf7' ] ) {
		try {
			wp( [ 'plugin', 'is-active', plugin ] );
		} catch ( e ) {
			fail( 'Plugin ' + plugin + ' ist auf der Testsite nicht aktiv.' );
		}
	}
	const { chromium } = loadPlaywright();

	const state = { pageId: 0, userId: 0, form: null };
	const password = crypto.randomBytes( 12 ).toString( 'hex' );
	let browser = null;
	try {
		console.log( 'Formular, Seite und Testkonto anlegen ...' );
		state.form = prepareForm();
		state.pageId = Number( wp( [ 'post', 'create', '--post_type=page', '--post_status=draft', '--post_title=FL Editor Shot', '--post_name=' + PAGE_SLUG, '--porcelain' ] ) );
		let existing = '';
		try {
			existing = wp( [ 'user', 'get', USER_LOGIN, '--field=ID' ], { stdio: [ 'ignore', 'pipe', 'ignore' ] } );
		} catch ( e ) {
			// No leftover account.
		}
		if ( existing ) {
			wp( [ 'user', 'delete', existing, '--yes' ] );
		}
		state.userId = Number( wp( [ 'user', 'create', USER_LOGIN, USER_EMAIL, '--role=administrator', '--user_pass=' + password, '--display_name=Jane Doe', '--porcelain' ] ) );
		wp( [ 'user', 'meta', 'update', USER_LOGIN, 'locale', 'en_US' ] );
		console.log( '  ok  Formular ' + state.form.id + ( state.form.created ? ' (neu)' : '' ) + ', Seite ' + state.pageId + ', Benutzer ' + state.userId );

		console.log( '' );
		console.log( 'Screenshots aufnehmen ...' );
		browser = await chromium.launch( { executablePath: CHROMIUM, headless: true, args: [ '--hide-scrollbars' ] } );
		const context = await browser.newContext( { ignoreHTTPSErrors: true, viewport: { width: WIDTH, height: HEIGHT_SETTINGS }, deviceScaleFactor: 1, reducedMotion: 'reduce' } );
		const page = await context.newPage();
		page.on( 'dialog', ( dialog ) => dialog.accept() );

		await page.goto( SITE_URL + '/wp-login.php', { waitUntil: 'networkidle' } );
		await page.fill( '#user_login', USER_LOGIN );
		await page.fill( '#user_pass', password );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle' } ), page.press( '#user_pass', 'Enter' ) ] );
		if ( ! ( await page.locator( '#wpadminbar' ).count() ) ) {
			fail( 'Anmeldung fehlgeschlagen.' );
		}

		await shootSettings( page );
		await shootEditor( page, state.pageId, state.form );
	} finally {
		if ( browser ) {
			await browser.close();
		}
		console.log( '' );
		console.log( 'Seite, Testkonto und Formular entfernen ...' );
		cleanup( state );
		console.log( '  ok' );
	}

	console.log( '' );
	console.log( '====================================================' );
	console.log( '   Fertig!' );
	console.log( '====================================================' );
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );

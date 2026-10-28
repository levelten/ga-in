/**
 * Behavior tests for assets/js/optout.js, run with `npm test`.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM, VirtualConsole } from 'jsdom';

const source = readFileSync( new URL( '../../assets/js/optout.js', import.meta.url ), 'utf8' );

function load( settings, { existingOptout } = {} ) {
	const dom = new JSDOM(
		'<!doctype html><body><a href="#" class="gainwp_useroptout" data-done-text="Done">Opt <b>out</b></a><a id="other" href="#">Other</a></body>',
		{ url: 'https://example.com/', runScripts: 'outside-only', virtualConsole: new VirtualConsole() }
	);
	const win = dom.window;
	win.gainwpOptOutSettings = settings;
	if ( existingOptout ) {
		win.gaOptout = existingOptout;
	}
	win.eval( source );
	const click = ( selector ) => {
		const event = new win.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		win.document.querySelector( selector ).dispatchEvent( event );
		return event;
	};
	return { win, click };
}

const SETTINGS = { cookie: 'gainwp_optout', flag: 'gainwpOptOut', disableKey: 'ga-disable-G-TEST1234' };

test( 'clicking an opt-out link records the opt-out and stops tracking', () => {
	const { win, click } = load( SETTINGS );
	const event = click( '.gainwp_useroptout b' );

	assert.ok( event.defaultPrevented );
	assert.match( win.document.cookie, /gainwp_optout=1/ );
	assert.equal( win.gainwpOptOut, true );
	assert.equal( win[ 'ga-disable-G-TEST1234' ], true );
	assert.deepEqual( Array.from( win.dataLayer, ( e ) => ( { ...e } ) ), [ { event: 'gainwp_optout' } ] );
	assert.equal( win.document.querySelector( '.gainwp_useroptout' ).textContent, 'Done' );
} );

test( 'other links are ignored', () => {
	const { win, click } = load( SETTINGS );
	assert.ok( ! click( '#other' ).defaultPrevented );
	assert.equal( win.gainwpOptOut, undefined );
} );

test( 'gaOptout() is available for links added by hand, as in 5.x', () => {
	const { win } = load( { ...SETTINGS, disableKey: '' } );
	win.gaOptout();
	assert.equal( win.gainwpOptOut, true );
	assert.match( win.document.cookie, /gainwp_optout=1/ );
} );

test( 'an existing gaOptout() is kept', () => {
	let called = false;
	const { click } = load( SETTINGS, { existingOptout: () => { called = true; } } );
	click( '.gainwp_useroptout' );
	assert.ok( called );
} );

test( 'missing settings do nothing', () => {
	const { win, click } = load( undefined );
	assert.equal( typeof win.gaOptout, 'undefined' );
	assert.ok( ! click( '.gainwp_useroptout' ).defaultPrevented );
} );

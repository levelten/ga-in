/**
 * Behavior tests for assets/js/events.js, run with `npm test`.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM, VirtualConsole } from 'jsdom';

const source = readFileSync( new URL( '../../assets/js/events.js', import.meta.url ), 'utf8' );

const PAGE = `<!doctype html><body>
	<a id="mail" href="mailto:Jane.Doe@Example.com,b@other.org?subject=Hi">Email <b>jane.doe@example.com</b></a>
	<a id="tel" href="tel:+15551234">Call</a>
	<a id="aff" href="https://example.com/out/widget">Buy</a>
	<a id="anchor" href="#section">Jump</a>
	<a id="other-page-anchor" href="/other/#section">Other</a>
	<a id="plain" href="https://elsewhere.org/">Plain</a>
	<svg><a id="svg" href="mailto:svg@example.com"><text>x</text></a></svg>
</body>`;

function load( config ) {
	// jsdom can't navigate; ignore its complaints when links are followed.
	const dom = new JSDOM( PAGE, {
		url: 'https://example.com/post/',
		runScripts: 'outside-only',
		virtualConsole: new VirtualConsole(),
	} );
	const win = dom.window;
	const sent = [];
	// Copy params into this realm so deepEqual compares plain objects.
	win.gtag = ( command, name, params ) => sent.push( { name, params: { ...params } } );
	win.gainwpEvents = config;
	win.requestAnimationFrame = ( callback ) => callback();
	win.eval( source );

	const click = ( selector ) =>
		win.document
			.querySelector( selector )
			.dispatchEvent( new win.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	const scrollTo = ( y ) => {
		Object.defineProperty( win, 'scrollY', { value: y, configurable: true } );
		win.dispatchEvent( new win.Event( 'scroll' ) );
	};
	Object.defineProperty( win.document.documentElement, 'scrollHeight', { value: 2000, configurable: true } );
	Object.defineProperty( win, 'innerHeight', { value: 1000, configurable: true } );

	return { sent, click, scrollTo };
}

test( 'link clicks send the matching events', () => {
	const page = load( { email: true, phone: true, anchors: true, affiliates: [ '/out/', '' ] } );
	[ '#mail b', '#tel', '#aff', '#anchor', '#other-page-anchor', '#plain', '#svg' ].forEach( page.click );

	assert.deepEqual(
		page.sent.map( ( e ) => e.name ),
		[ 'email_link_click', 'phone_link_click', 'affiliate_link_click', 'anchor_link_click' ]
	);
	assert.deepEqual( page.sent[ 2 ].params, {
		transport_type: 'beacon',
		link_url: 'https://example.com/out/widget',
		link_text: 'Buy',
	} );
} );

test( 'email and phone clicks never send the address or number', () => {
	const page = load( { email: true, phone: true } );
	[ '#mail', '#tel' ].forEach( page.click );

	assert.deepEqual( page.sent[ 0 ].params, { transport_type: 'beacon', link_domain: 'example.com' } );
	assert.deepEqual( page.sent[ 1 ].params, { transport_type: 'beacon' } );
	assert.ok( ! JSON.stringify( page.sent ).includes( 'jane' ) );
	assert.ok( ! JSON.stringify( page.sent ).includes( '5551234' ) );
} );

test( 'disabled events and malformed config send nothing', () => {
	const page = load( { phone: true, affiliates: '/out/' } );
	[ '#mail', '#aff', '#anchor' ].forEach( page.click );

	assert.deepEqual( page.sent, [] );
} );

test( 'scroll depth fires each valid threshold once, in order', () => {
	const page = load( { scroll: [ 50, 25, 100, 75, 0, 150 ] } );
	const depths = () => page.sent.map( ( e ) => e.params.percent_scrolled );

	assert.deepEqual( depths(), [], 'nothing before scrolling' );
	page.scrollTo( 300 );
	assert.deepEqual( depths(), [ 25 ] );
	page.scrollTo( 800 );
	assert.deepEqual( depths(), [ 25, 50, 75 ] );
	page.scrollTo( 999.6 );
	page.scrollTo( 1000 );
	assert.deepEqual( depths(), [ 25, 50, 75, 100 ] );
} );

test( 'no config sends nothing and does not throw', () => {
	const page = load( undefined );
	page.click( '#mail' );
	page.scrollTo( 1000 );

	assert.deepEqual( page.sent, [] );
} );

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
	<a id="aff" href="https://example.com/out/widget?ref=me#top">Buy</a>
	<a id="sponsored" href="https://shop.example.org/item" rel="nofollow sponsored">Shop</a>
	<a id="anchor" href="?x=1#section">Jump</a>
	<a id="other-page-anchor" href="/other/#section">Other</a>
	<a id="plain" href="https://elsewhere.org/">Plain</a>
	<svg><a id="svg" href="mailto:svg@example.com"><text>x</text></a></svg>
	<form id="form"><button>Send</button></form>
</body>`;

const QUEUE = { prefix: 'gainwp_events_', path: '/', domain: '' };

/**
 * Loads events.js in a fresh page.
 *
 * @param {Object|undefined} config  window.gainwpEvents.
 * @param {Object}           options cookie: one array of queued events per cookie set before load; before( win ): runs before the script.
 */
function load( config, { cookie, before } = {} ) {
	// jsdom can't navigate; ignore its complaints when links are followed.
	const dom = new JSDOM( PAGE, {
		url: 'https://example.com/post/?utm_source=x',
		runScripts: 'outside-only',
		virtualConsole: new VirtualConsole(),
	} );
	const win = dom.window;
	const sent = [];
	// Copy params into this realm so deepEqual compares plain objects.
	win.gtag = ( command, name, params ) => sent.push( { name, params: { ...params } } );
	win.gainwpEvents = config;
	win.requestAnimationFrame = ( callback ) => callback();
	( cookie || [] ).forEach( ( events, index ) => {
		win.document.cookie = 'gainwp_events_a1b2c3d' + index + '=' + encodeURIComponent( JSON.stringify( events ) ) + '; path=/';
	} );
	if ( before ) {
		before( win );
	}
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
	const dataLayer = () => Array.from( win.dataLayer || [], ( entry ) => JSON.parse( JSON.stringify( entry ) ) );
	// What a form plugin's AJAX response does.
	const respond = ( events ) => {
		win.document.cookie = 'gainwp_events_0f0f0f0f=' + encodeURIComponent( JSON.stringify( events ) ) + '; path=/';
	};

	return { win, sent, click, scrollTo, dataLayer, respond };
}

/**
 * A stand-in for jQuery that records handlers bound on the document.
 *
 * @param {Object} handlers Event name -> handler, filled in by .on().
 */
function fakeJQuery( handlers ) {
	return () => ( {
		on: ( names, handler ) => names.split( ' ' ).forEach( ( name ) => ( handlers[ name ] = handler ) ),
	} );
}

test( 'link clicks send the matching events', () => {
	const page = load( { email: true, phone: true, anchors: true, affiliates: [ '/out/', '' ] } );
	[ '#mail b', '#tel', '#aff', '#sponsored', '#anchor', '#other-page-anchor', '#plain', '#svg' ].forEach( page.click );

	assert.deepEqual(
		page.sent.map( ( e ) => e.name ),
		[ 'email_link_click', 'phone_link_click', 'affiliate_link_click', 'affiliate_link_click', 'anchor_link_click' ]
	);
	assert.deepEqual( page.sent[ 2 ].params, { link_url: 'https://example.com/out/widget', link_text: 'Buy' }, 'query string and fragment are dropped' );
	assert.deepEqual( page.sent[ 3 ].params, { link_url: 'https://shop.example.org/item', link_text: 'Shop' }, 'rel="sponsored" counts' );
	assert.deepEqual( page.sent[ 4 ].params, { link_url: 'https://example.com/post/#section', link_text: 'Jump' }, 'anchors keep the fragment' );
} );

test( 'email and phone clicks never send the address or number', () => {
	const page = load( { email: true, phone: true } );
	[ '#mail', '#tel' ].forEach( page.click );

	assert.deepEqual( page.sent[ 0 ].params, { link_domain: 'example.com' } );
	assert.deepEqual( page.sent[ 1 ].params, {} );
	assert.ok( ! JSON.stringify( page.sent ).includes( 'jane' ) );
	assert.ok( ! JSON.stringify( page.sent ).includes( '5551234' ) );
} );

test( 'disabled events and malformed config send nothing', () => {
	const page = load( { phone: true, affiliates: '/out/' } );
	[ '#mail', '#aff', '#sponsored', '#anchor' ].forEach( page.click );

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

test( 'no config sends no automatic events and does not throw', () => {
	const page = load( undefined );
	page.click( '#mail' );
	page.scrollTo( 1000 );
	page.win.gainwp.track( 'generate_lead' );

	assert.deepEqual( page.sent, [ { name: 'generate_lead', params: {} } ] );
} );

test( 'gainwp.track() sends to GAinWP\'s tag with goal values', () => {
	const page = load( { mode: 'gtag', sendTo: 'G-TEST1234', goals: { generate_lead: 100, sign_up: null }, currency: 'EUR' } );
	const { track } = page.win.gainwp;

	track( 'generate_lead', { component_id: 'form:gravityforms:3' } );
	track( 'generate_lead', { value: 250 } );
	track( 'sign_up', { method: 'form' } );
	track( 'login', { method: 'wordpress' } );

	assert.deepEqual( page.sent, [
		{ name: 'generate_lead', params: { component_id: 'form:gravityforms:3', value: 100, currency: 'EUR', send_to: 'G-TEST1234' } },
		{ name: 'generate_lead', params: { value: 250, currency: 'EUR', send_to: 'G-TEST1234' } },
		{ name: 'sign_up', params: { method: 'form', send_to: 'G-TEST1234' } },
		{ name: 'login', params: { method: 'wordpress', send_to: 'G-TEST1234' } },
	] );
} );

test( 'parameters are cleaned of personal data and names GA4 rejects', () => {
	const page = load( {} );
	page.win.gainwp.track( 'custom_event', {
		link_text: 'Call (512) 555-1234 or mail Jane.Doe@example.com, order 2024',
		component_name: 'Contact jane@example.co.uk',
		link_url: 'https://example.com/thanks/?email=jane@example.com#done',
		user_id: '42',
		ga_session: 'x',
		'bad name': 'x',
		nested: { a: 1 },
		long: 'x'.repeat( 150 ),
		flag: true,
	} );
	page.win.gainwp.track( 'bad name' );

	assert.deepEqual( page.sent, [
		{
			name: 'custom_event',
			params: {
				link_text: 'Call [phone] or mail [email], order 2024',
				component_name: 'Contact [email]',
				link_url: 'https://example.com/thanks/',
				long: 'x'.repeat( 100 ),
				flag: true,
			},
		},
	] );
} );

test( 'Tag Manager mode pushes events to the data layer', () => {
	const page = load( { mode: 'tagmanager', goals: { generate_lead: 50 }, currency: 'USD' } );
	page.win.gainwp.track( 'generate_lead', { component_id: 'form:wpforms:9' } );
	page.win.gainwp.track( 'login', { method: 'wordpress' } );

	assert.deepEqual( page.sent, [], 'gtag() is not used' );
	assert.deepEqual( page.dataLayer(), [
		{ intelligence: null },
		{ event: 'generate_lead', intelligence: { component_id: 'form:wpforms:9', value: 50, currency: 'USD' } },
		{ intelligence: null },
		{ event: 'login', intelligence: { method: 'wordpress' } },
	] );
} );

test( 'visitors who opted out send nothing', () => {
	const page = load( { email: true, optOutFlag: 'gainwpOptOut' }, { before: ( win ) => ( win.gainwpOptOut = true ) } );
	page.click( '#mail' );
	page.win.gainwp.track( 'generate_lead' );

	assert.deepEqual( page.sent, [] );
} );

test( 'calls made before the script loaded are sent', () => {
	const page = load(
		{ sendTo: 'G-TEST1234' },
		{
			before: ( win ) => {
				win.eval( 'window.gainwp = window.gainwp || {}; window.gainwp.q = window.gainwp.q || []; window.gainwp.track = window.gainwp.track || function () { window.gainwp.q.push(arguments); };' );
				win.gainwp.track( 'quote_requested', { component_id: 'form:quote' } );
			},
		}
	);

	assert.deepEqual( page.sent, [ { name: 'quote_requested', params: { component_id: 'form:quote', send_to: 'G-TEST1234' } } ] );
	assert.equal( page.win.gainwp.q.length, 0 );
} );

test( 'events queued on the server are sent once on page load', () => {
	const page = load(
		{ queue: QUEUE, goals: { sign_up: 20, login: 5 }, currency: 'USD' },
		{
			cookie: [
				[ { name: 'login', params: { method: 'wordpress' } } ],
				[ { name: 'sign_up', params: { method: 'wordpress', value: 20, currency: 'USD' } }, { bogus: true } ],
			],
		}
	);

	assert.deepEqual( page.sent, [
		{ name: 'login', params: { method: 'wordpress' } },
		{ name: 'sign_up', params: { method: 'wordpress', value: 20, currency: 'USD' } },
	], 'goal values come from the server, not added again' );
	assert.ok( ! page.win.document.cookie.includes( 'gainwp_events_' ), 'the cookies are deleted' );

	page.win.dispatchEvent( new page.win.Event( 'pageshow' ) );
	assert.equal( page.sent.length, 2, 'not sent again' );
} );

test( 'queued events wait while gtag() is unavailable', () => {
	const page = load(
		{ queue: QUEUE },
		{
			cookie: [ [ { name: 'generate_lead', params: {} } ] ],
			before: ( win ) => {
				delete win.gtag;
			},
		}
	);

	assert.ok( page.win.document.cookie.includes( 'gainwp_events_' ), 'kept for a later page' );
} );

test( 'queued events are dropped for visitors who opted out', () => {
	const page = load(
		{ queue: QUEUE },
		{
			cookie: [ [ { name: 'generate_lead', params: {} } ] ],
			before: ( win ) => ( win.gainwpOptOut = true ),
		}
	);

	assert.deepEqual( page.sent, [] );
	assert.ok( ! page.win.document.cookie.includes( 'gainwp_events_' ) );
} );

test( 'events from an AJAX submission are sent when the form plugin reports success', () => {
	const page = load( { queue: { ...QUEUE, events: [ 'wpcf7mailsent' ] } } );
	const form = page.win.document.getElementById( 'form' );

	page.respond( [ { name: 'generate_lead', params: { component_id: 'form:contact_form_7:5' } } ] );
	form.dispatchEvent( new page.win.Event( 'submit', { bubbles: true, cancelable: true } ) );
	form.dispatchEvent( new page.win.CustomEvent( 'wpcf7invalid', { bubbles: true } ) );
	assert.deepEqual( page.sent, [], 'not before the success event' );

	form.dispatchEvent( new page.win.CustomEvent( 'wpcf7mailsent', { bubbles: true } ) );
	assert.deepEqual( page.sent, [ { name: 'generate_lead', params: { component_id: 'form:contact_form_7:5' } } ] );
} );

test( 'jQuery success events work, also when jQuery loads after the script', () => {
	const config = { queue: { ...QUEUE, events: [ 'gform_confirmation_loaded', 'wpformsAjaxSubmitSuccess' ] } };
	const early = {};
	const page = load( config, { before: ( win ) => ( win.jQuery = fakeJQuery( early ) ) } );
	assert.deepEqual( Object.keys( early ), [ 'gform_confirmation_loaded', 'wpformsAjaxSubmitSuccess' ] );

	page.respond( [ { name: 'generate_lead', params: { component_id: 'form:gravityforms:3' } } ] );
	early.gform_confirmation_loaded( {}, 3 );
	assert.deepEqual( page.sent, [ { name: 'generate_lead', params: { component_id: 'form:gravityforms:3' } } ] );

	const late = {};
	const later = load( config );
	later.win.jQuery = fakeJQuery( late );
	later.win.dispatchEvent( new later.win.Event( 'load' ) );
	later.respond( [ { name: 'sign_up', params: { method: 'form' } } ] );
	late.wpformsAjaxSubmitSuccess( {} );
	assert.deepEqual( later.sent, [ { name: 'sign_up', params: { method: 'form' } } ] );
} );

test( 'a malformed queue cookie is dropped', () => {
	const page = load( { queue: QUEUE }, { before: ( win ) => ( win.document.cookie = 'gainwp_events_abc=%7Bnot-json; path=/' ) } );

	assert.deepEqual( page.sent, [] );
	assert.ok( ! page.win.document.cookie.includes( 'gainwp_events_' ) );
} );


test( 'waiting events are capped so request headers stay small', () => {
	const page = load(
		{ queue: { ...QUEUE, max: 2 } },
		{
			cookie: [ [ { name: 'a' } ], [ { name: 'b' } ], [ { name: 'c' } ], [ { name: 'd' } ] ],
			before: ( win ) => {
				delete win.gtag;
			},
		}
	);

	assert.equal( ( page.win.document.cookie.match( /gainwp_events_/g ) || [] ).length, 2 );
} );

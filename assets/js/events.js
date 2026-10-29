/**
 * GAinWP events for Google Analytics 4.
 *
 * Provides gainwp.track( name, params ), which every GAinWP event goes
 * through, and sends:
 * - link clicks and scroll depth that GA4 Enhanced Measurement doesn't
 *   cover (outbound clicks, file downloads, form interactions and 90%
 *   scroll are left to it so they aren't counted twice);
 * - events recorded on the server (logins, sign-ups, comments, form
 *   submissions), which arrive in short-lived cookies. The cookies are read
 *   on page load and when a form plugin reports a successful AJAX
 *   submission.
 *
 * Configured by window.gainwpEvents:
 *   mode       {string}   'gtag' or 'tagmanager'
 *   sendTo     {string}   GA4 Measurement ID (gtag mode)
 *   optOutFlag {string}   global flag set for visitors who must not be tracked
 *   goals      {Object}   event name -> value, or null for goals without one
 *   currency   {string}   ISO 4217 code sent with values
 *   queue      {Object}   name prefix, path and domain of the server event cookies,
 *                         how many may wait (max), and the DOM or jQuery events
 *                         after which they are read again (events)
 *   email      {boolean}  mailto: link clicks  -> email_link_click
 *   phone      {boolean}  tel: link clicks     -> phone_link_click
 *   anchors    {boolean}  same-page #links     -> anchor_link_click
 *   affiliates {string[]} URL fragments; rel="sponsored" links count too -> affiliate_link_click
 *   scroll     {number[]} depth percentages    -> scroll_depth
 */
( function () {
	'use strict';

	var config = window.gainwpEvents || {};
	var goals = config.goals || {};
	var api = ( window.gainwp = window.gainwp || {} );
	var early = Array.isArray( api.q ) ? api.q : [];

	var NAME = /^[A-Za-z][A-Za-z0-9_]{0,39}$/;
	// Parameter names GA4 reserves.
	var RESERVED =
		/^(cid|customer_?id|dclid|gclid|session_?id|sfmc_id|sid|srsltid|uid|user_?id|firebase_.*|ga_.*|google_.*|gtag.*)$/i;
	var EMAIL = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi;
	var PHONE = /\+?\(?\d[\d\s().-]{6,}\d/g;
	var MAX_LENGTH = 100;

	function own( object, key ) {
		return Object.prototype.hasOwnProperty.call( object, key );
	}

	function redactPhones( text ) {
		return text.replace( PHONE, function ( match ) {
			var digits = match.replace( /\D/g, '' ).length;
			return digits >= 10 && digits <= 15 ? '[phone]' : match;
		} );
	}

	function stripUrl( url, keepHash ) {
		var hash = url.indexOf( '#' );
		var base = hash > -1 ? url.slice( 0, hash ) : url;
		var query = base.indexOf( '?' );
		return (
			( query > -1 ? base.slice( 0, query ) : base ) +
			( keepHash && hash > -1 ? url.slice( hash ) : '' )
		);
	}

	// Keeps personal data and parameters GA4 can't use out of events.
	function clean( name, params ) {
		var out = {};
		for ( var key in params ) {
			if ( ! own( params, key ) || ! NAME.test( key ) || RESERVED.test( key ) ) {
				continue;
			}
			var value = params[ key ];
			if ( typeof value === 'string' ) {
				if ( key === 'link_url' ) {
					value = stripUrl( value, name === 'anchor_link_click' );
				}
				value = value.replace( EMAIL, '[email]' );
				if ( key === 'link_text' ) {
					value = redactPhones( value );
				}
				out[ key ] = value.slice( 0, MAX_LENGTH );
			} else if ( typeof value === 'number' || typeof value === 'boolean' ) {
				out[ key ] = value;
			}
		}
		return out;
	}

	function optedOut() {
		return !! window[ config.optOutFlag || 'gainwpOptOut' ];
	}

	// Whether an event can be sent now: in GA4 mode, gtag() may be missing
	// while a consent tool holds back GAinWP's inline script.
	function canSend() {
		return config.mode === 'tagmanager' || typeof window.gtag === 'function';
	}

	function send( name, params, addGoalValue ) {
		if ( typeof name !== 'string' || ! NAME.test( name ) || optedOut() ) {
			return;
		}
		params = clean( name, params && typeof params === 'object' ? params : {} );

		if (
			addGoalValue &&
			params.value === undefined &&
			own( goals, name ) &&
			typeof goals[ name ] === 'number'
		) {
			params.value = goals[ name ];
		}
		if ( typeof params.value === 'number' && ! params.currency && config.currency ) {
			params.currency = config.currency;
		}

		if ( config.mode === 'tagmanager' ) {
			var dataLayer = ( window.dataLayer = window.dataLayer || [] );
			// The data layer merges pushes, so clear the previous event's parameters.
			dataLayer.push( { intelligence: null } );
			dataLayer.push( { event: name, intelligence: params } );
		} else if ( typeof window.gtag === 'function' ) {
			if ( config.sendTo ) {
				// Only to GAinWP's tag, not tags other plugins add.
				params.send_to = config.sendTo;
			}
			window.gtag( 'event', name, params );
		}
	}

	function track( name, params ) {
		send( name, params, true );
	}

	api.track = track;
	api.q = [];
	early.forEach( function ( args ) {
		track( args[ 0 ], args[ 1 ] );
	} );

	// Events recorded on the server, one cookie per request that recorded
	// any. The server already added goal values.
	var queue = config.queue || {};

	function remove( name ) {
		document.cookie =
			name +
			'=; max-age=0; path=' +
			( queue.path || '/' ) +
			( queue.domain ? '; domain=' + queue.domain : '' ) +
			'; SameSite=Lax' +
			( window.location.protocol === 'https:' ? '; Secure' : '' );
	}

	function drain() {
		if ( ! queue.prefix ) {
			return;
		}
		var pattern = new RegExp( '(?:^|; )(' + queue.prefix + '[0-9a-f]+)=([^;]*)', 'g' );
		var cookies = document.cookie;
		var found = [];
		var match;
		while ( ( match = pattern.exec( cookies ) ) ) {
			found.push( match );
		}

		if ( ! canSend() && ! optedOut() ) {
			// Keep them for a page where they can be sent, but not so many
			// that request headers grow too large.
			found.slice( queue.max || 4 ).forEach( function ( extra ) {
				remove( extra[ 1 ] );
			} );
			return;
		}

		found.forEach( function ( cookie ) {
			// Delete first, so each event is sent once.
			remove( cookie[ 1 ] );
			var events;
			try {
				events = JSON.parse( decodeURIComponent( cookie[ 2 ] ) );
			} catch ( e ) {
				return;
			}
			if ( Array.isArray( events ) ) {
				events.forEach( function ( event ) {
					if ( event && typeof event.name === 'string' ) {
						send( event.name, event.params, false );
					}
				} );
			}
		} );
	}

	drain();
	// Pages restored from the back/forward cache don't run scripts again.
	window.addEventListener( 'pageshow', drain );

	// Form plugins announce a finished AJAX submission with a DOM event
	// (Contact Form 7) or a jQuery one (Gravity Forms, WPForms), once the
	// response has set its cookie. Each is listened for both ways; when both
	// fire, the second drain finds the cookies gone.
	var finished = queue.events || [];
	var jQueryBound = false;

	function listenWithJQuery() {
		if ( jQueryBound || ! window.jQuery ) {
			return;
		}
		jQueryBound = true;
		window.jQuery( document ).on( finished.join( ' ' ), drain );
	}

	if ( finished.length ) {
		finished.forEach( function ( name ) {
			document.addEventListener( name, drain );
		} );
		listenWithJQuery();
		// jQuery may load after this script.
		document.addEventListener( 'DOMContentLoaded', listenWithJQuery );
		window.addEventListener( 'load', listenWithJQuery );
	}

	// Link clicks.
	function linkParams( name, link ) {
		var params = {};
		// Google Analytics forbids personal data, so email and phone clicks
		// never include the address or number (or link text, which often
		// repeats it). Email clicks keep only the domain.
		if ( name === 'email_link_click' ) {
			var address = link.pathname.split( ',' )[ 0 ];
			params.link_domain = ( address.split( '@' )[ 1 ] || '' ).toLowerCase();
		} else if ( name !== 'phone_link_click' ) {
			params.link_url = link.href;
			params.link_text = ( link.textContent || '' ).trim();
		}
		return params;
	}

	var affiliates = Array.isArray( config.affiliates )
		? config.affiliates.filter( function ( fragment ) {
				return typeof fragment === 'string' && fragment !== '';
		  } )
		: null;

	function isAffiliate( link ) {
		return (
			/(^|\s)sponsored(\s|$)/i.test( link.getAttribute( 'rel' ) || '' ) ||
			affiliates.some( function ( fragment ) {
				return link.href.indexOf( fragment ) > -1;
			} )
		);
	}

	function linkEvent( link ) {
		if ( link.protocol === 'mailto:' ) {
			return config.email ? 'email_link_click' : null;
		}
		if ( link.protocol === 'tel:' ) {
			return config.phone ? 'phone_link_click' : null;
		}
		if ( affiliates && isAffiliate( link ) ) {
			return 'affiliate_link_click';
		}
		if (
			config.anchors &&
			link.hash &&
			link.host === window.location.host &&
			link.pathname === window.location.pathname
		) {
			return 'anchor_link_click';
		}
		return null;
	}

	if ( config.email || config.phone || config.anchors || affiliates ) {
		// Capture phase, so handlers that stop propagation don't hide clicks.
		document.addEventListener(
			'click',
			function ( event ) {
				var link =
					event.target instanceof Element
						? event.target.closest( 'a[href]' )
						: null;
				// SVG links lack href strings and URL parts, so skip them.
				var name =
					link instanceof HTMLAnchorElement ? linkEvent( link ) : null;
				if ( name ) {
					track( name, linkParams( name, link ) );
				}
			},
			true
		);
	}

	// Scroll depth.
	if ( Array.isArray( config.scroll ) && config.scroll.length ) {
		var remaining = config.scroll
			.filter( function ( n ) {
				return n > 0 && n <= 100;
			} )
			.sort( function ( a, b ) {
				return a - b;
			} );
		var pending = false;

		var check = function () {
			pending = false;
			var scrollable =
				document.documentElement.scrollHeight - window.innerHeight;
			if ( scrollable <= 0 ) {
				return;
			}
			// Round up: fractional scroll positions can stop just short of 100%.
			var percent = Math.ceil( ( window.scrollY / scrollable ) * 100 );
			while ( remaining.length && percent >= remaining[ 0 ] ) {
				track( 'scroll_depth', { percent_scrolled: remaining.shift() } );
			}
			if ( ! remaining.length ) {
				window.removeEventListener( 'scroll', onScroll );
			}
		};

		var onScroll = function () {
			if ( ! pending ) {
				pending = true;
				window.requestAnimationFrame( check );
			}
		};

		// Only real scrolling counts, so short pages don't report 100% on load.
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}
} )();

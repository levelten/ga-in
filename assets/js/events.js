/**
 * GAinWP engagement events for Google Analytics 4.
 *
 * Sends events that GA4 Enhanced Measurement doesn't cover. Outbound
 * clicks, file downloads, form interactions and 90% scroll are left to
 * Enhanced Measurement so they aren't counted twice.
 *
 * Configured by window.gainwpEvents:
 *   email      {boolean}  mailto: link clicks  -> email_link_click
 *   phone      {boolean}  tel: link clicks     -> phone_link_click
 *   anchors    {boolean}  same-page #links     -> anchor_link_click
 *   affiliates {string[]} URL fragments        -> affiliate_link_click
 *   scroll     {number[]} depth percentages    -> scroll_depth
 */
( function () {
	'use strict';

	var config = window.gainwpEvents || {};

	function send( name, params ) {
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', name, params );
		}
	}

	function linkParams( name, link ) {
		var params = { transport_type: 'beacon' };
		// Google Analytics forbids personal data, so email and phone clicks
		// never include the address or number (or link text, which often
		// repeats it). Email clicks keep only the domain.
		if ( name === 'email_link_click' ) {
			var address = link.pathname.split( ',' )[ 0 ];
			params.link_domain = ( address.split( '@' )[ 1 ] || '' ).toLowerCase();
		} else if ( name !== 'phone_link_click' ) {
			params.link_url = link.href;
			params.link_text = ( link.textContent || '' ).trim().slice( 0, 100 );
		}
		return params;
	}

	var affiliates = Array.isArray( config.affiliates )
		? config.affiliates.filter( function ( fragment ) {
				return typeof fragment === 'string' && fragment !== '';
		  } )
		: [];

	function isAffiliate( href ) {
		return affiliates.some( function ( fragment ) {
			return href.indexOf( fragment ) > -1;
		} );
	}

	function linkEvent( link ) {
		if ( link.protocol === 'mailto:' ) {
			return config.email ? 'email_link_click' : null;
		}
		if ( link.protocol === 'tel:' ) {
			return config.phone ? 'phone_link_click' : null;
		}
		if ( isAffiliate( link.href ) ) {
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

	if ( config.email || config.phone || config.anchors || affiliates.length ) {
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
					send( name, linkParams( name, link ) );
				}
			},
			true
		);
	}

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
				send( 'scroll_depth', { percent_scrolled: remaining.shift() } );
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

/**
 * GAinWP visitor opt-out.
 *
 * Defines gaOptout() unless the head check already did (it does on tracked
 * pages, so the function exists while the page loads, as in 5.x), and
 * handles clicks on elements with the gainwp_useroptout class, such as the
 * [gainwp_useroptout] shortcode's link. Opting out stores a cookie that the
 * head check reads on later pages, stops the GA4 tag on this page and pushes
 * a gainwp_optout event so Tag Manager containers can react.
 *
 * Configured by window.gainwpOptOutSettings:
 *   cookie     {string} opt-out cookie name
 *   flag       {string} global flag that stops tags from loading
 *   disableKey {string} Google's ga-disable-<ID> key, or ''
 */
( function () {
	'use strict';

	var s = window.gainwpOptOutSettings;
	if ( ! s || ! s.cookie ) {
		return;
	}

	window.gaOptout =
		window.gaOptout ||
		function () {
			document.cookie =
				s.cookie + '=1; max-age=315360000; path=/; SameSite=Lax';
			window[ s.flag ] = true;
			if ( s.disableKey ) {
				window[ s.disableKey ] = true;
			}
			( window.dataLayer = window.dataLayer || [] ).push( {
				event: 'gainwp_optout',
			} );
		};

	document.addEventListener( 'click', function ( event ) {
		var el =
			event.target instanceof Element
				? event.target.closest( '.gainwp_useroptout' )
				: null;
		if ( ! el ) {
			return;
		}
		event.preventDefault();
		window.gaOptout();
		if ( el.getAttribute( 'data-done-text' ) ) {
			el.textContent = el.getAttribute( 'data-done-text' );
		}
	} );
} )();

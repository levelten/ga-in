<?php
/**
 * Inline script helpers.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

/**
 * Helpers for building JavaScript that is printed inline.
 */
final class Script {

	/**
	 * Defines the dataLayer and gtag() the way Google's snippet does.
	 */
	public const GTAG_STUB = 'window.dataLayer = window.dataLayer || [];' . "\n" . 'function gtag(){dataLayer.push(arguments);}';

	/**
	 * Queues gainwp.track() calls made before assets/js/events.js loads.
	 */
	public const API_STUB = 'window.gainwp = window.gainwp || {}; window.gainwp.q = window.gainwp.q || []; window.gainwp.track = window.gainwp.track || function () { window.gainwp.q.push(arguments); };';

	/**
	 * Encodes a value for use inside an inline <script>.
	 *
	 * Escapes "<" and ">" so values can't close the script element.
	 *
	 * @param mixed $value Value to encode.
	 */
	public static function json( $value ): string {
		return (string) wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Builds a gtag() call.
	 *
	 * @param mixed ...$args Arguments, JSON-encoded in order.
	 */
	public static function gtag( ...$args ): string {
		return 'gtag(' . implode( ', ', array_map( array( self::class, 'json' ), $args ) ) . ');';
	}

	/**
	 * Prints an HTML comment marking the plugin's output, which helps when
	 * checking a page's source for the tag.
	 *
	 * @param string $label What follows the comment.
	 */
	public static function print_marker( string $label ): void {
		printf( "\n<!-- GAinWP %s: %s -->\n", esc_html( GAINWP_VERSION ), esc_html( $label ) );
	}
}

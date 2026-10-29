<?php
/**
 * Delivery of server-side events to the browser.
 *
 * @package GAinWP
 */

namespace GAinWP\Events;

/**
 * Carries events recorded on the server (logins, form submissions, ...) to
 * the visitor's browser in short-lived cookies, where assets/js/events.js
 * sends them on the next page load, or right after an AJAX submission.
 *
 * Each request that records events sets its own cookie, so the server
 * never needs to read cookies back. Some hosts (Pantheon, for one) strip
 * unknown cookies from requests, which would make a shared cookie lose
 * events not sent yet.
 *
 * Never printed inline in the page: page caches would serve the events to
 * other visitors. The cookies hold no identifier, only the pending events.
 */
final class Queue {

	/**
	 * Cookie name prefix; a random suffix makes each request's cookie unique.
	 */
	public const PREFIX = 'gainwp_events_';

	/**
	 * Seconds an event waits to be sent: a day, so a login that lands in
	 * the admin, or a registration waiting for its email confirmation, is
	 * still sent on the next front-end visit. GA4 credits key events to the
	 * last non-direct source, so the later visit doesn't lose the source.
	 */
	public const LIFETIME = 86400;

	public const MAX_EVENTS = 10;

	/**
	 * Most cookies waiting at once, and the size of each, so pending events
	 * (say, while a consent tool holds back the tag) can't make request
	 * headers too large for the server.
	 */
	public const MAX_COOKIES = 4;

	private const MAX_BYTES = 1200;

	/**
	 * Events recorded during this request.
	 *
	 * @var array<int, array{name: string, params: array<string, mixed>}>
	 */
	private array $events = array();

	/**
	 * This request's cookie name, chosen on first use.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * Adds an event and updates this request's cookie. When the cookie is
	 * full, the new event is rejected, so events already queued are kept.
	 *
	 * @param string               $name   Event name.
	 * @param array<string, mixed> $params Event parameters.
	 * @return bool False when the event can't be delivered: output has
	 *              started, too many events are waiting, or it doesn't fit.
	 */
	public function add( string $name, array $params ): bool {
		if ( count( $this->events ) >= self::MAX_EVENTS || ( '' === $this->name && self::waiting() >= self::MAX_COOKIES ) ) {
			return false;
		}

		$events   = $this->events;
		$events[] = array(
			'name'   => $name,
			'params' => $params,
		);
		$value    = self::encode( $events );
		if ( strlen( $value ) > self::MAX_BYTES || headers_sent() ) {
			return false;
		}

		$this->events = $events;
		if ( '' === $this->name ) {
			$this->name = self::PREFIX . bin2hex( random_bytes( 4 ) );
		}
		return setrawcookie(
			$this->name,
			$value,
			array(
				'expires'  => time() + self::LIFETIME,
				'path'     => self::path(),
				'domain'   => self::domain(),
				'secure'   => is_ssl(),
				// The browser script reads and deletes it.
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * How many event cookies the browser sent with this request. Hosts that
	 * strip unknown cookies report none; the browser script caps them there.
	 */
	private static function waiting(): int {
		$cookies = preg_grep( '/^' . self::PREFIX . '[0-9a-f]+$/', array_keys( $_COOKIE ) );
		return is_array( $cookies ) ? count( $cookies ) : 0;
	}

	/**
	 * Events recorded during this request.
	 *
	 * @return array<int, array{name: string, params: array<string, mixed>}>
	 */
	public function events(): array {
		return $this->events;
	}

	/**
	 * Cookie details the browser script needs to find and delete the
	 * cookies, and how many may wait.
	 *
	 * @return array<string, int|string>
	 */
	public static function settings(): array {
		return array(
			'prefix' => self::PREFIX,
			'path'   => self::path(),
			'domain' => self::domain(),
			'max'    => self::MAX_COOKIES,
		);
	}

	/**
	 * Encodes events for a cookie, matching the browser's decodeURIComponent().
	 *
	 * @param array<int, array{name: string, params: array<string, mixed>}> $events Events.
	 */
	private static function encode( array $events ): string {
		return rawurlencode( (string) wp_json_encode( $events ) );
	}

	/**
	 * Cookie path: WordPress's, so it covers the whole site.
	 */
	private static function path(): string {
		return defined( 'COOKIEPATH' ) && '' !== COOKIEPATH ? (string) COOKIEPATH : '/';
	}

	/**
	 * Cookie domain, '' for the current host.
	 */
	private static function domain(): string {
		return defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '';
	}
}

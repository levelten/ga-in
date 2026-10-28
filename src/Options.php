<?php
/**
 * Plugin settings storage.
 *
 * @package GAinWP
 */

namespace GAinWP;

/**
 * Reads, normalizes and stores the plugin settings.
 *
 * Settings live in a single autoloaded option as an array. Every read and
 * write passes through sanitize(), so callers can rely on every key being
 * present with the expected type.
 */
final class Options {

	public const OPTION = 'gainwp_settings';

	public const TRACKING_TYPES = array( 'gtag', 'tagmanager', 'disabled' );

	public const PAGE_DATA_KEYS = array( 'author', 'category', 'tags', 'publish_year', 'publish_month', 'user_type' );

	public const AUTHOR_FORMATS = array( 'display_name', 'user_login' );

	public const EVENT_KEYS = array( 'email', 'phone', 'anchors', 'affiliates', 'scroll' );

	public const CONSENT_MODES = array( 'none', 'denied', 'denied_eea' );

	public const MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]{4,20}$/';

	public const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]{4,12}$/';

	/**
	 * A hostname with at least two labels; allows punycode TLDs like xn--p1ai.
	 */
	private const HOSTNAME_PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $values = null;

	/**
	 * Default settings. Boolean defaults must stay false: the settings form
	 * omits unchecked checkboxes, and sanitize() fills gaps from here.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'tracking_type'        => 'gtag',
			'measurement_id'       => '',
			'gtm_container_id'     => '',
			'gtm_amp_container_id' => '',
			'in_footer'            => false,
			'page_data'            => array(),
			'author_format'        => 'display_name',
			'events'               => array_fill_keys( self::EVENT_KEYS, false ),
			'affiliate_links'      => array( '/out/' ),
			'consent_default'      => 'none',
			'respect_dnt'          => false,
			'allow_optout'         => false,
			'exclude_roles'        => array(),
			'exclude_super_admins' => false,
			'cookie_domain'        => '',
			'cookie_expires'       => 0,
			'linker_domains'       => array(),
		);
	}

	/**
	 * Returns all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		return $this->values;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed Null for unknown keys.
	 */
	public function get( string $key ) {
		$values = $this->all();
		return $values[ $key ] ?? null;
	}

	/**
	 * The ID the selected tracking method needs: the Measurement ID for GA4
	 * or the container ID for Tag Manager. '' when tracking is disabled or
	 * the ID is missing, i.e. when nothing will be output.
	 */
	public function tag_id(): string {
		switch ( $this->get( 'tracking_type' ) ) {
			case 'gtag':
				return $this->get( 'measurement_id' );
			case 'tagmanager':
				return $this->get( 'gtm_container_id' );
			default:
				return '';
		}
	}

	/**
	 * Whether an event type is enabled.
	 *
	 * @param string $event One of EVENT_KEYS.
	 */
	public function event_enabled( string $event ): bool {
		$events = $this->get( 'events' );
		return ! empty( $events[ $event ] );
	}

	/**
	 * Sanitizes and stores settings.
	 *
	 * @param array<string, mixed> $values Settings to store.
	 */
	public function save( array $values ): void {
		$this->values = self::sanitize( $values );
		update_option( self::OPTION, $this->values );
	}

	/**
	 * Drops the per-request cache, e.g. after the Settings API saved the option.
	 */
	public function flush(): void {
		$this->values = null;
	}

	/**
	 * Normalizes raw settings: fills defaults, coerces types and drops
	 * anything invalid. Never reports errors; see Admin\SettingsPage for that.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$out      = array();

		$out['tracking_type'] = self::one_of( $input['tracking_type'] ?? null, self::TRACKING_TYPES, $defaults['tracking_type'] );

		$out['measurement_id']       = self::match_id( $input['measurement_id'] ?? '', self::MEASUREMENT_ID_PATTERN );
		$out['gtm_container_id']     = self::match_id( $input['gtm_container_id'] ?? '', self::CONTAINER_ID_PATTERN );
		$out['gtm_amp_container_id'] = self::match_id( $input['gtm_amp_container_id'] ?? '', self::CONTAINER_ID_PATTERN );

		$out['in_footer'] = ! empty( $input['in_footer'] );

		$out['page_data']     = array_values( array_intersect( self::PAGE_DATA_KEYS, (array) ( $input['page_data'] ?? array() ) ) );
		$out['author_format'] = self::one_of( $input['author_format'] ?? null, self::AUTHOR_FORMATS, $defaults['author_format'] );

		$events        = (array) ( $input['events'] ?? array() );
		$out['events'] = array();
		foreach ( self::EVENT_KEYS as $event ) {
			$out['events'][ $event ] = ! empty( $events[ $event ] );
		}

		$out['affiliate_links'] = self::url_fragments( $input['affiliate_links'] ?? array() );
		if ( ! $out['affiliate_links'] ) {
			$out['affiliate_links'] = $defaults['affiliate_links'];
		}

		$out['consent_default'] = self::one_of( $input['consent_default'] ?? null, self::CONSENT_MODES, $defaults['consent_default'] );
		$out['respect_dnt']     = ! empty( $input['respect_dnt'] );
		$out['allow_optout']    = ! empty( $input['allow_optout'] );

		$out['exclude_roles']        = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $input['exclude_roles'] ?? array() ) ) ) ) );
		$out['exclude_super_admins'] = ! empty( $input['exclude_super_admins'] );

		$cookie_domain        = ltrim( strtolower( trim( (string) ( $input['cookie_domain'] ?? '' ) ) ), '.' );
		$out['cookie_domain'] = ( in_array( $cookie_domain, array( 'auto', 'none' ), true ) || preg_match( self::HOSTNAME_PATTERN, $cookie_domain ) ) ? $cookie_domain : '';

		$out['cookie_expires'] = min( absint( $input['cookie_expires'] ?? 0 ), 63072000 );

		$out['linker_domains'] = self::domains( $input['linker_domains'] ?? array() );

		return $out;
	}

	/**
	 * Normalizes a Google tag ID, returning '' when it doesn't match.
	 *
	 * @param mixed  $value   Raw ID.
	 * @param string $pattern Regex the uppercased ID must match.
	 */
	public static function match_id( $value, string $pattern ): string {
		$id = strtoupper( trim( (string) $value ) );
		return preg_match( $pattern, $id ) ? $id : '';
	}

	/**
	 * Returns $value when it is in $allowed, otherwise $fallback.
	 *
	 * @param mixed         $value    Raw value.
	 * @param array<string> $allowed  Allowed values.
	 * @param string        $fallback Value to use when $value isn't allowed.
	 */
	private static function one_of( $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Parses a list of hostnames from an array or a comma/newline separated string.
	 *
	 * @param mixed $value Raw list.
	 * @return array<string>
	 */
	private static function domains( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}
		$domains = array();
		foreach ( (array) $value as $domain ) {
			$domain = strtolower( trim( (string) $domain ) );
			$domain = (string) preg_replace( '#^[a-z]+://#', '', $domain );
			$domain = ltrim( explode( '/', $domain )[0], '.' );
			if ( preg_match( self::HOSTNAME_PATTERN, $domain ) ) {
				$domains[] = $domain;
			}
		}
		return array_values( array_unique( $domains ) );
	}

	/**
	 * Parses URL fragments from an array or a newline separated string.
	 *
	 * @param mixed $value Raw list.
	 * @return array<string>
	 */
	private static function url_fragments( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n]+/', $value );
		}
		$fragments = array();
		foreach ( (array) $value as $fragment ) {
			// Matched as plain text in the browser, so only whitespace and
			// control characters are removed.
			$fragment = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $fragment ) );
			if ( '' !== $fragment ) {
				$fragments[] = mb_substr( $fragment, 0, 200 );
			}
		}
		return array_slice( array_values( array_unique( $fragments ) ), 0, 20 );
	}
}

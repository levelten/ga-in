<?php
/**
 * Upgrades stored data from earlier plugin versions.
 *
 * @package GAinWP
 */

namespace GAinWP;

/**
 * Migrates settings from earlier versions and sets up new installs.
 *
 * 5.x stored JSON in `gainwp_options` (and `gainwp_network_options` on
 * multisite), along with OAuth tokens and cached report data. 6.x keeps an
 * array in `gainwp_settings`. A credential-free copy of the old settings is
 * kept in `gainwp_legacy_options` in case a mapping needs revisiting.
 *
 * New features start off on existing sites, except form tracking: its
 * default event, form_complete, is one no other tool sends, so it can't
 * double-count leads a site already tracks. New installs get the
 * recommended settings.
 */
final class Upgrade {

	/**
	 * Settings format version. 5.x had none; 2 is the 6.0 format, 3 the 6.1 format.
	 */
	public const DB_VERSION = 3;

	public const VERSION_OPTION = 'gainwp_db_version';

	public const LEGACY_BACKUP_OPTION = 'gainwp_legacy_options';

	/**
	 * Goals on new installs and sites upgrading from 6.0 or 5.x.
	 */
	private const DEFAULT_GOALS = array(
		'generate_lead' => array( 'goal' => true ),
		'sign_up'       => array( 'goal' => true ),
	);

	/**
	 * Settings the migration couldn't carry over, shown on the settings
	 * screen until the settings are next saved.
	 */
	public const WARNINGS_OPTION = 'gainwp_upgrade_warnings';

	/**
	 * Set when an existing site updates to 6.1, for the "what's new" notice.
	 */
	public const WHATS_NEW_OPTION = 'gainwp_whats_new';

	/**
	 * 5.x site options, all removed after migration and on uninstall.
	 */
	public const LEGACY_OPTIONS = array( 'gainwp_options', 'gainwp_version', 'gainwp_got_updated', 'gainwp_redeemed_code' );

	/**
	 * Legacy keys that hold credentials and must not be kept around.
	 */
	private const CREDENTIAL_KEYS = array( 'token', 'client_id', 'client_secret', 'ga_profiles_list', 'network_tableid' );

	/**
	 * Runs pending upgrades for the current site.
	 */
	public static function maybe_run(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}
		self::run();
	}

	/**
	 * Converts earlier settings, or stores the recommended settings on new
	 * installs, and retires 5.x data.
	 */
	public static function run(): void {
		$version = (int) get_option( self::VERSION_OPTION, 0 );
		$legacy  = self::decode( get_option( 'gainwp_options' ) );

		if ( null !== $legacy ) {
			if ( false === get_option( Options::OPTION ) ) {
				$network = is_multisite() ? self::decode( get_site_option( 'gainwp_network_options' ) ) : null;
				update_option( Options::OPTION, Options::sanitize( self::convert( $legacy, $network ?? array() ) ) );
				$warnings = self::warnings( $legacy );
				if ( $warnings ) {
					update_option( self::WARNINGS_OPTION, $warnings, false );
				}
			}
			self::retire_legacy( $legacy );
		}

		$stored = get_option( Options::OPTION );
		if ( ! is_array( $stored ) ) {
			// New installs, and 6.0 sites that never saved their settings.
			update_option( Options::OPTION, Options::sanitize( self::recommended() ) );
		} elseif ( $version < 3 ) {
			update_option( Options::OPTION, Options::sanitize( self::from_6_0( $stored ) ) );
			// The version the site updated from: 6.0, or 5.x.
			update_option( self::WHATS_NEW_OPTION, 2 === $version ? '6.0' : '5', false );
		}

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Settings for new installs. Booleans left out stay off.
	 *
	 * @return array<string, mixed> Unsanitized settings.
	 */
	public static function recommended(): array {
		return array(
			// Tags can be long and are mostly noise in reports.
			'page_data' => array_values( array_diff( Options::PAGE_DATA_KEYS, array( 'content_tags' ) ) ),
			'events'    => array(
				'forms'    => true,
				'login'    => true,
				'sign_up'  => true,
				'comments' => true,
				'email'    => true,
				'phone'    => true,
				'scroll'   => true,
			),
			'goals'     => self::DEFAULT_GOALS,
		);
	}

	/**
	 * Maps 6.0 settings to the 6.1 format. Settings already in the 6.1
	 * format (such as converted 5.x settings) pass through unchanged.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed> Unsanitized settings.
	 */
	public static function from_6_0( array $settings ): array {
		$page_data = array();
		foreach ( (array) ( $settings['page_data'] ?? array() ) as $key ) {
			$page_data[] = Options::RENAMED_6_0[ $key ] ?? $key;
		}
		$settings['page_data'] = array_values( array_unique( $page_data ) );

		// Forms are tracked from the upgrade on. They send form_complete,
		// which no other tool sends, until the owner sets lead forms to Lead,
		// so existing lead tracking is never doubled. Other new events start
		// off; leads and sign-ups are goals once they're sent.
		$events = (array) ( $settings['events'] ?? array() );
		if ( ! isset( $events['forms'] ) ) {
			$settings['events'] = array( 'forms' => true ) + $events;
		}
		if ( ! isset( $settings['goals'] ) ) {
			$settings['goals'] = self::DEFAULT_GOALS;
		}

		// author_format is gone: authors are always sent as their display
		// name. Options::sanitize() drops the key.
		return $settings;
	}

	/**
	 * Maps 5.x settings to 6.1 settings.
	 *
	 * Universal Analytics IDs are not carried over: UA stopped processing
	 * data in July 2023 and GA4 needs a new Measurement ID.
	 *
	 * @param array<string, mixed> $legacy  Decoded `gainwp_options`.
	 * @param array<string, mixed> $network Decoded `gainwp_network_options`.
	 * @return array<string, mixed> Unsanitized 6.x settings.
	 */
	public static function convert( array $legacy, array $network = array() ): array {
		$flag = static function ( string $key ) use ( $legacy ): bool {
			return ! empty( $legacy[ $key ] );
		};

		$legacy_type   = $legacy['tracking_type'] ?? 'universal';
		$tracking_type = 'universal' === $legacy_type ? 'gtag' : $legacy_type;
		$tag_manager   = 'tagmanager' === $tracking_type;

		// 5.x kept separate custom dimension (gtag) and data layer (GTM)
		// switches for the same page data. Use whichever mode was active.
		// Authors are sent as display names even where 5.x sent usernames, and
		// the publication year is part of the month.
		$page_data_sources = $tag_manager
			? array(
				'content_author'        => array( 'tm_author_var', 'tm_author_login_var' ),
				'content_category'      => array( 'tm_category_var' ),
				'content_tags'          => array( 'tm_tag_var' ),
				'content_publish_month' => array( 'tm_pubyear_var', 'tm_pubyearmonth_var' ),
				'user_status'           => array( 'tm_user_var' ),
			)
			: array(
				'content_author'        => array( 'ga_author_dimindex', 'ga_author_login_dimindex' ),
				'content_category'      => array( 'ga_category_dimindex' ),
				'content_tags'          => array( 'ga_tag_dimindex' ),
				'content_publish_month' => array( 'ga_pubyear_dimindex', 'ga_pubyearmonth_dimindex' ),
				'user_status'           => array( 'ga_user_dimindex' ),
			);

		$page_data = array();
		foreach ( $page_data_sources as $key => $sources ) {
			foreach ( $sources as $source ) {
				if ( $flag( $source ) ) {
					$page_data[] = $key;
					break;
				}
			}
		}

		// In network mode 5.x let network settings override site settings.
		$super_admins = ( ! empty( $network['network_mode'] ) && isset( $network['superadmin_tracking'] ) )
			? ! empty( $network['superadmin_tracking'] )
			: $flag( 'superadmin_tracking' );

		$affiliate_links = self::affiliate_links( (string) ( $legacy['ga_event_affiliates'] ?? '' ) );

		$linker = '';
		if ( $flag( 'ga_crossdomain_tracking' ) ) {
			$linker = (string) ( $legacy['ga_crossdomain_list'] ?? '' );
		}

		return array(
			'tracking_type'        => $tracking_type,
			// Only a GA4 ID would survive validation; 5.x only accepted UA IDs.
			'measurement_id'       => $legacy['tracking_id'] ?? '',
			'gtm_container_id'     => $legacy['web_containerid'] ?? '',
			'gtm_amp_container_id' => $flag( 'amp_tracking_tagmanager' ) ? ( $legacy['amp_containerid'] ?? '' ) : '',
			'in_footer'            => $flag( 'trackingcode_infooter' ),
			'page_data'            => $page_data,
			'events'               => array(
				// As for 6.0 sites: forms send form_complete until set to Lead.
				'forms'      => true,
				// "Event tracking" covered downloads, outbound, email and phone
				// links. GA4 Enhanced Measurement handles the first two.
				'email'      => $flag( 'ga_event_tracking' ),
				'phone'      => $flag( 'ga_event_tracking' ),
				'anchors'    => $flag( 'ga_hash_tracking' ),
				'affiliates' => $flag( 'ga_aff_tracking' ) && null !== $affiliate_links,
				'scroll'     => $flag( 'ga_pagescrolldepth_tracking' ),
			),
			'affiliate_links'      => $affiliate_links ?? array(),
			'goals'                => self::DEFAULT_GOALS,
			'respect_dnt'          => $flag( 'ga_dnt_optout' ) || $flag( 'tm_dnt_optout' ),
			'allow_optout'         => $flag( 'ga_optout' ) || $flag( 'tm_optout' ),
			'exclude_roles'        => $legacy['track_exclude'] ?? array(),
			'exclude_super_admins' => $super_admins,
			'cookie_domain'        => $legacy['ga_cookiedomain'] ?? '',
			'cookie_expires'       => $legacy['ga_cookieexpires'] ?? 0,
			'linker_domains'       => $linker,
		);
	}

	/**
	 * Converts a 5.x affiliate regular expression to plain URL fragments.
	 *
	 * Handles what 5.x sites normally used: paths like /out/, alternatives
	 * like /go/|/recommends/, escaped characters and ^ or $ anchors. Returns
	 * null for anything else (groups, wildcards, classes), which can't be
	 * expressed as fragments.
	 *
	 * @param string $pattern 5.x `ga_event_affiliates` value.
	 * @return array<string>|null
	 */
	public static function affiliate_links( string $pattern ): ?array {
		$fragments = array();
		foreach ( (array) preg_split( '/(?<!\\\\)\|/', trim( $pattern ) ) as $alternative ) {
			$alternative = (string) preg_replace( '/^\^|(?<!\\\\)\$$/', '', (string) $alternative );
			if ( preg_match( '/(?<!\\\\)[()\[\]{}*+?]/', $alternative ) ) {
				return null;
			}
			$alternative = (string) preg_replace( '/\\\\(.)/', '$1', $alternative );
			if ( '' !== $alternative ) {
				$fragments[] = $alternative;
			}
		}
		return $fragments;
	}

	/**
	 * Lists 5.x settings that can't be carried over.
	 *
	 * @param array<string, mixed> $legacy Decoded `gainwp_options`.
	 * @return array<string, string> Problem key => original value.
	 */
	public static function warnings( array $legacy ): array {
		$warnings = array();
		$pattern  = (string) ( $legacy['ga_event_affiliates'] ?? '' );
		if ( ! empty( $legacy['ga_aff_tracking'] ) && null === self::affiliate_links( $pattern ) ) {
			$warnings['affiliate_pattern'] = $pattern;
		}
		return $warnings;
	}

	/**
	 * Removes 5.x options, keeping a credential-free backup.
	 *
	 * @param array<string, mixed> $legacy Decoded `gainwp_options`.
	 */
	private static function retire_legacy( array $legacy ): void {
		update_option( self::LEGACY_BACKUP_OPTION, self::strip_credentials( $legacy ), false );

		self::delete_legacy_options();

		// Other sites may still need network_mode and superadmin_tracking to
		// migrate, so only the credentials are removed here. Uninstall
		// deletes the rest.
		if ( is_multisite() ) {
			$network = self::decode( get_site_option( 'gainwp_network_options' ) );
			if ( null !== $network && array_intersect_key( $network, array_flip( self::CREDENTIAL_KEYS ) ) ) {
				update_site_option( 'gainwp_network_options', wp_json_encode( self::strip_credentials( $network ) ) );
			}
		}
	}

	/**
	 * Deletes 5.x options and cached Universal Analytics report data for
	 * the current site.
	 */
	public static function delete_legacy_options(): void {
		global $wpdb;

		foreach ( self::LEGACY_OPTIONS as $option ) {
			delete_option( $option );
		}

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of non-autoloaded rows.
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'gainwp_cache_' ) . '%' )
		);
	}

	/**
	 * Removes OAuth credentials and cached Google account data.
	 *
	 * @param array<string, mixed> $options Legacy options.
	 * @return array<string, mixed>
	 */
	private static function strip_credentials( array $options ): array {
		return array_diff_key( $options, array_flip( self::CREDENTIAL_KEYS ) );
	}

	/**
	 * Decodes a 5.x JSON option.
	 *
	 * @param mixed $raw Stored value.
	 * @return array<string, mixed>|null Null when missing or unreadable.
	 */
	private static function decode( $raw ): ?array {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}

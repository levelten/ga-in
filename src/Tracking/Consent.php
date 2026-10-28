<?php
/**
 * Google Consent Mode defaults.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

/**
 * Builds the Google Consent Mode v2 default command.
 *
 * The default is "none": consent banner plugins normally set their own
 * defaults, and two sets of defaults conflict. The "denied" modes are for
 * sites whose banner only sends consent updates.
 */
final class Consent {

	/**
	 * EEA member states plus the UK and Switzerland.
	 */
	private const EEA_REGIONS = array(
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
		'IS',
		'LI',
		'NO',
		'GB',
		'CH',
	);

	private const SIGNALS = array( 'ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage' );

	/**
	 * Returns the JavaScript for the configured mode, or '' for none.
	 * Expects gtag() to be defined (see Script::GTAG_STUB).
	 *
	 * @param string $mode One of Options::CONSENT_MODES.
	 */
	public static function script( string $mode ): string {
		if ( 'denied' === $mode ) {
			return Script::gtag( 'consent', 'default', self::state( 'denied' ) );
		}

		if ( 'denied_eea' === $mode ) {
			/**
			 * Filters the regions (ISO 3166-1 alpha-2) where consent defaults to denied.
			 *
			 * @since 6.0.0
			 *
			 * @param array<string> $regions Region codes.
			 */
			$regions = array_values( (array) apply_filters( 'gainwp_consent_regions', self::EEA_REGIONS ) );

			return Script::gtag( 'consent', 'default', self::state( 'granted' ) ) . "\n"
				. Script::gtag( 'consent', 'default', self::state( 'denied' ) + array( 'region' => $regions ) );
		}

		return '';
	}

	/**
	 * Builds a consent state with every signal set to $value.
	 *
	 * @param string $value 'granted' or 'denied'.
	 * @return array<string, mixed>
	 */
	private static function state( string $value ): array {
		$state = array_fill_keys( self::SIGNALS, $value );
		if ( 'denied' === $value ) {
			$state['wait_for_update'] = 500;
		}
		return $state;
	}
}

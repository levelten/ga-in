<?php
/**
 * Overlap with Site Kit by Google.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Integrations\Registry;
use GAinWP\Options;

/**
 * Detects when Site Kit by Google sends the same data as GAinWP, from its
 * stored settings, so owners can choose which plugin does what.
 */
final class SiteKit {

	/**
	 * Whether Site Kit places its own GA4 tag.
	 */
	public static function places_ga4_tag(): bool {
		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
			return false;
		}
		$modules  = (array) get_option( 'googlesitekit_active_modules', array() );
		$settings = (array) get_option( 'googlesitekit_analytics-4_settings', array() );
		return in_array( 'analytics-4', $modules, true ) && ! empty( $settings['measurementID'] ) && ! empty( $settings['useSnippet'] ?? true );
	}

	/**
	 * Whether Site Kit sends its own lead and purchase events. It only does
	 * when it places a GA4 or Google Ads tag itself.
	 */
	public static function sends_conversions(): bool {
		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
			return false;
		}
		$tracking = (array) get_option( 'googlesitekit_conversion_tracking', array() );
		$modules  = (array) get_option( 'googlesitekit_active_modules', array() );
		$ads      = (array) get_option( 'googlesitekit_ads_settings', array() );
		$ads_tag  = in_array( 'ads', $modules, true ) && ! empty( $ads['conversionID'] );
		return ! empty( $tracking['enabled'] ) && ( self::places_ga4_tag() || $ads_tag );
	}

	/**
	 * Whether GAinWP sends leads or sign-ups for forms. Forms on the default
	 * form_complete don't overlap with Site Kit's lead events.
	 *
	 * @param Options $options Plugin settings.
	 */
	private static function sends_leads( Options $options ): bool {
		if ( ! $options->event_enabled( 'forms' ) ) {
			return false;
		}
		foreach ( Registry::form_choices( $options ) as $choice ) {
			if ( 'generate_lead' === $choice['event'] || 'sign_up' === $choice['event'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Warnings about data counted twice, as HTML.
	 *
	 * @param Options $options Plugin settings.
	 * @return array<int, string>
	 */
	public static function warnings( Options $options ): array {
		// Tracking disabled, or no ID yet: GAinWP sends nothing to count twice.
		if ( '' === $options->tag_id() ) {
			return array();
		}

		$warnings = array();
		// In Tag Manager mode GAinWP adds no GA4 tag of its own.
		if ( 'gtag' === $options->get( 'tracking_type' ) && self::places_ga4_tag() ) {
			$warnings[] = __( '<strong>Site Kit by Google also adds a Google Analytics tag</strong>, so page views count twice. In Site Kit, go to Settings &rarr; Connected services &rarr; Analytics, edit it and turn off &ldquo;Place Google Analytics code&rdquo;. Site Kit keeps showing its reports.', 'ga-in' );
		}
		if ( self::sends_conversions() && self::sends_leads( $options ) ) {
			$warnings[] = __( '<strong>Site Kit by Google also tracks form submissions</strong> (as <code>contact</code> or <code>submit_lead_form</code>), so leads count twice. In Site Kit, go to Settings &rarr; Admin settings and turn off plugin conversion tracking, or turn off form tracking here.', 'ga-in' );
		}
		return $warnings;
	}
}

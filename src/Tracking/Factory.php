<?php
/**
 * Chooses the tracker for the current request.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use GAinWP\Options;

/**
 * Builds the configured tracker, or none when tracking is off for this visitor.
 */
final class Factory {

	/**
	 * Returns the tracker to use, or null if nothing should be output.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function create( Options $options ): ?Tracker {
		if ( '' === $options->tag_id() || self::is_excluded( $options ) ) {
			return null;
		}

		return 'tagmanager' === $options->get( 'tracking_type' ) ? new TagManager( $options ) : new Gtag( $options );
	}

	/**
	 * Whether the current request is excluded from tracking: previews, the
	 * Customizer, and logged-in users in excluded roles. Call once the main
	 * query is parsed.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function is_excluded( Options $options ): bool {
		$excluded = is_preview() || is_customize_preview();

		if ( ! $excluded && is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( array_intersect( (array) $user->roles, $options->get( 'exclude_roles' ) ) ) {
				$excluded = true;
			} elseif ( $options->get( 'exclude_super_admins' ) && is_multisite() && is_super_admin() ) {
				$excluded = true;
			}
		}

		/**
		 * Filters whether the current request is excluded from tracking.
		 * Runs on template_redirect, so conditional tags work.
		 *
		 * @since 6.0.0
		 *
		 * @param bool $excluded Whether tracking output is skipped.
		 */
		return (bool) apply_filters( 'gainwp_exclude_tracking', $excluded );
	}
}

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
	 * Customizer, and logged-in users in excluded roles. For page output,
	 * call once the main query is parsed.
	 *
	 * @param Options       $options Plugin settings.
	 * @param \WP_User|null $user    The user to check, when it isn't the current user yet (on login).
	 * @param string        $context 'page' for page output, 'event' for an event recorded on the server.
	 */
	public static function is_excluded( Options $options, ?\WP_User $user = null, string $context = 'page' ): bool {
		if ( null === $user && is_user_logged_in() ) {
			$user = wp_get_current_user();
		}
		$excluded = is_preview() || is_customize_preview() || ( null !== $user && self::user_excluded( $options, $user ) );

		/**
		 * Filters whether the current request is excluded from tracking.
		 *
		 * For page output ($context 'page') it runs on template_redirect, so
		 * conditional tags such as is_page() work. Since 6.1 it also runs
		 * when an event is recorded on the server ($context 'event'): a
		 * login, a comment, or a form submission through admin-ajax or the
		 * REST API, where conditional tags don't describe a page.
		 *
		 * @since 6.0.0
		 * @since 6.1.0 The $context argument.
		 *
		 * @param bool   $excluded Whether tracking is skipped.
		 * @param string $context  'page' or 'event'.
		 */
		return (bool) apply_filters( 'gainwp_exclude_tracking', $excluded, $context );
	}

	/**
	 * Whether a user's role (or super admin status, when set) excludes
	 * them from tracking. Also used for events recorded on the server.
	 *
	 * @param Options  $options Plugin settings.
	 * @param \WP_User $user    User; ID 0 for visitors who aren't logged in.
	 */
	public static function user_excluded( Options $options, \WP_User $user ): bool {
		if ( 0 === (int) $user->ID ) {
			return false;
		}
		if ( array_intersect( (array) $user->roles, $options->get( 'exclude_roles' ) ) ) {
			return true;
		}
		return $options->get( 'exclude_super_admins' ) && is_multisite() && is_super_admin( $user->ID );
	}
}

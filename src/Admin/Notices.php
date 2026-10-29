<?php
/**
 * Admin notices.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Options;
use GAinWP\Upgrade;

/**
 * Warns administrators when tracking is enabled but can't run, and tells
 * sites that updated to 6.1 what's new.
 *
 * Shown on the Dashboard and Plugins screens only. The warning resolves
 * itself once the missing ID is saved; the news stays until dismissed or
 * the settings form is submitted.
 */
final class Notices {

	private const SCREENS = array( 'dashboard', 'plugins' );

	private const DISMISS_ACTION = 'gainwp_dismiss_whats_new';

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss' ) );
	}

	/**
	 * Dismisses the "what's new" notice and returns to the previous screen.
	 */
	public function dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );
		if ( current_user_can( 'manage_options' ) ) {
			delete_option( Upgrade::WHATS_NEW_OPTION );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * The "what's new" notice for sites that updated to 6.1, or '' when
	 * there's nothing to say.
	 */
	public static function whats_new(): string {
		$from = get_option( Upgrade::WHATS_NEW_OPTION );
		if ( '6.0' !== $from && '5' !== $from ) {
			return '';
		}

		$message = sprintf(
			/* translators: %s: settings URL. */
			__( '<strong>GAinWP 6.1 can track results, not just traffic.</strong> Successful submissions in Gravity Forms, Contact Form 7 and WPForms now send a <code>form_complete</code> event. <a href="%s">Set the forms that bring you business to Lead</a> to count them as leads, with the value you give them. Logins, sign-ups and comments can be turned on there too.', 'ga-in' ),
			esc_url( SettingsPage::url() )
		);
		if ( '6.0' === $from ) {
			$message .= ' ' . sprintf(
				/* translators: %s: GA4 setup URL. */
				__( 'Page attributes now use GA4-style names such as <code>content_author</code>: if you registered 6.0&rsquo;s names as custom dimensions, <a href="%s">register the new ones</a>.', 'ga-in' ),
				esc_url( SettingsPage::url() . '&tab=setup' )
			);
		}
		return $message . ' ' . sprintf(
			'<a href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION ) ),
			esc_html__( 'Dismiss', 'ga-in' )
		);
	}

	/**
	 * Prints the notice when there is a problem.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, self::SCREENS, true ) ) {
			return;
		}

		$problem = self::problem( $this->options );
		if ( null !== $problem ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', wp_kses( $problem, SettingsPage::ALLOWED_HTML ) );
		}

		$news = self::whats_new();
		if ( '' !== $news ) {
			printf( '<div class="notice notice-info"><p>%s</p></div>', wp_kses( $news, SettingsPage::ALLOWED_HTML ) );
		}
	}

	/**
	 * Describes why tracking isn't running, or null when it is (or is off on purpose).
	 *
	 * @param Options $options Plugin settings.
	 * @return string|null HTML message.
	 */
	public static function problem( Options $options ): ?string {
		$type = $options->get( 'tracking_type' );

		if ( 'disabled' === $type || '' !== $options->tag_id() ) {
			return null;
		}

		if ( 'gtag' === $type ) {
			return sprintf(
				/* translators: %s: settings page URL. */
				__( '<strong>GAinWP is not sending data to Google Analytics.</strong> GAinWP now supports Google Analytics 4 only; Universal Analytics (UA-) properties stopped collecting data in July 2023. <a href="%s">Add your GA4 Measurement ID</a>.', 'ga-in' ),
				esc_url( SettingsPage::url() )
			);
		}

		return sprintf(
			/* translators: %s: settings page URL. */
			__( '<strong>GAinWP is not loading Google Tag Manager.</strong> <a href="%s">Add your container ID</a>.', 'ga-in' ),
			esc_url( SettingsPage::url() )
		);
	}
}

<?php
/**
 * Admin notices.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Options;

/**
 * Warns administrators when tracking is enabled but can't run.
 *
 * Shown on the Dashboard and Plugins screens only, and resolves itself once
 * the missing ID is saved.
 */
final class Notices {

	private const SCREENS = array( 'dashboard', 'plugins' );

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

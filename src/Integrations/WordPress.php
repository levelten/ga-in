<?php
/**
 * WordPress core events.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

use GAinWP\Events\Recorder;
use GAinWP\Options;
use WP_User;

/**
 * Records logins, registrations and comments as GA4's recommended `login`
 * and `sign_up` events and a custom `comment_submit` event.
 */
final class WordPress implements Integration {

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Records events; set in register().
	 *
	 * @var Recorder|null
	 */
	private ?Recorder $recorder = null;

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Logins, registrations and comments load a new page.
	 *
	 * @return array<int, string>
	 */
	public function ajax_events(): array {
		return array();
	}

	/**
	 * Hooks into the logins, registrations and comments that are turned on.
	 *
	 * @param Recorder $recorder Records events.
	 */
	public function register( Recorder $recorder ): void {
		$this->recorder = $recorder;
		if ( $this->options->event_enabled( 'login' ) ) {
			add_action( 'wp_login', array( $this, 'login' ), 10, 2 );
		}
		if ( $this->options->event_enabled( 'sign_up' ) ) {
			// Fires for registration through wp-login.php, not for users added in the admin.
			add_action( 'register_new_user', array( $this, 'sign_up' ) );
		}
		if ( $this->options->event_enabled( 'comments' ) ) {
			add_action( 'comment_post', array( $this, 'comment' ), 10, 2 );
		}
	}

	/**
	 * Records a login. Users in excluded roles, usually administrators,
	 * aren't recorded.
	 *
	 * @param string $user_login Username (unused: never sent).
	 * @param mixed  $user       The user who logged in.
	 */
	public function login( $user_login, $user ): void {
		if ( null !== $this->recorder && $user instanceof WP_User ) {
			$this->recorder->record( 'login', array( 'method' => 'wordpress' ), $user );
		}
	}

	/**
	 * Records a registration.
	 */
	public function sign_up(): void {
		if ( null !== $this->recorder ) {
			$this->recorder->record( 'sign_up', array( 'method' => 'wordpress' ) );
		}
	}

	/**
	 * Records a comment posted through the comment form, unless it was
	 * marked as spam. Replies written in the admin don't count.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   1, 0, 'spam' or 'trash'.
	 */
	public function comment( $comment_id, $approved ): void {
		if ( null === $this->recorder || ! did_action( 'pre_comment_on_post' ) || in_array( $approved, array( 'spam', 'trash' ), true ) ) {
			return;
		}
		$this->recorder->record( 'comment_submit' );
	}
}

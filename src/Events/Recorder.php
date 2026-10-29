<?php
/**
 * Records events that happen on the server.
 *
 * @package GAinWP
 */

namespace GAinWP\Events;

use GAinWP\Options;
use GAinWP\Tracking\Factory;
use WP_User;

/**
 * The server-side entry point for events: logins, registrations, comments
 * and form submissions. Recorded events are queued and sent from the
 * visitor's browser through gainwp.track(), so consent mode, opt-outs and
 * Tag Manager mode apply to them like any other event.
 *
 * Other plugins and themes can record their own events:
 *
 *     \GAinWP\Plugin::instance()->recorder()->record( 'quote_requested', array( 'component_id' => 'form:quote' ) );
 */
final class Recorder {

	/**
	 * GA4 event and parameter names: a letter, then letters, digits or
	 * underscores, up to 40 characters.
	 */
	public const NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';

	/**
	 * GA4 caps parameter values at 100 characters and events at 25 parameters.
	 */
	private const MAX_LENGTH = 100;

	private const MAX_PARAMS = 25;

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Delivery to the browser.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 * @param Queue   $queue   Delivery to the browser.
	 */
	public function __construct( Options $options, Queue $queue ) {
		$this->options = $options;
		$this->queue   = $queue;
	}

	/**
	 * Records an event. It is sent from the visitor's browser on their next
	 * page view, or, during an AJAX request, as soon as the page fires one
	 * of the integrations' ajax_events() (a form plugin's success event).
	 *
	 * Nothing is recorded when tracking is off or the user is in an
	 * excluded role.
	 *
	 * @param string                $name   GA4 event name.
	 * @param array<string, scalar> $params Event parameters. Never personal data.
	 * @param WP_User|null          $user   The user doing this, when it isn't the current user yet (on login).
	 * @return bool Whether the event was queued.
	 */
	public function record( string $name, array $params = array(), ?WP_User $user = null ): bool {
		if ( '' === $this->options->tag_id() || ! preg_match( self::NAME_PATTERN, $name ) ) {
			return false;
		}
		if ( Factory::is_excluded( $this->options, $user, 'event' ) ) {
			return false;
		}

		/**
		 * Filters an event recorded on the server before it is queued.
		 *
		 * @since 6.1.0
		 *
		 * @param array<string, mixed>|null $event 'name' and 'params'. Return null to drop the event.
		 *                                        Goal values are added afterwards, for the final name.
		 */
		$event = apply_filters(
			'gainwp_event',
			array(
				'name'   => $name,
				'params' => self::clean( $params ),
			)
		);
		if ( ! is_array( $event ) || ! isset( $event['name'] ) || ! is_string( $event['name'] ) || ! preg_match( self::NAME_PATTERN, $event['name'] ) ) {
			return false;
		}
		// Goal values follow the final event name.
		$params = $this->with_value( $event['name'], self::clean( is_array( $event['params'] ?? null ) ? $event['params'] : array() ) );
		$params = array_slice( $params, 0, self::MAX_PARAMS, true );

		if ( ! $this->queue->add( $event['name'], $params ) ) {
			return false;
		}

		/**
		 * Fires when an event is recorded on the server and queued for GA4,
		 * e.g. to react to a lead in other code.
		 *
		 * @since 6.1.0
		 *
		 * @param string                $name   Event name.
		 * @param array<string, scalar> $params Event parameters.
		 */
		do_action( 'gainwp_event_recorded', $event['name'], $params );
		return true;
	}

	/**
	 * Adds the value and currency set in the settings to goals: the form's
	 * own value for form events, else the goal's. A value the caller set
	 * is kept; non-goals get none from settings.
	 *
	 * @param string                $name   Event name.
	 * @param array<string, scalar> $params Event parameters.
	 * @return array<string, scalar>
	 */
	private function with_value( string $name, array $params ): array {
		$value = $params['value'] ?? null;
		if ( null === $value && $this->options->is_goal( $name ) ) {
			$form  = is_string( $params['component_id'] ?? null ) ? $this->options->form( $params['component_id'] ) : null;
			$value = $form['value'] ?? $this->options->goal_value( $name );
		}
		if ( null === $value ) {
			return $params;
		}
		// First, so the 25-parameter limit never drops them.
		return array(
			'value'    => $value,
			'currency' => $params['currency'] ?? $this->options->get( 'currency' ),
		) + $params;
	}

	/**
	 * Keeps parameters GA4 accepts: up to 25 with valid names and string
	 * (trimmed to 100 characters), number or boolean values.
	 *
	 * @param array<mixed> $params Raw parameters.
	 * @return array<string, scalar>
	 */
	public static function clean( array $params ): array {
		$out = array();
		foreach ( $params as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( self::NAME_PATTERN, $key ) ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$out[ $key ] = mb_substr( $value, 0, self::MAX_LENGTH );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				$out[ $key ] = $value;
			}
		}
		return array_slice( $out, 0, self::MAX_PARAMS, true );
	}
}

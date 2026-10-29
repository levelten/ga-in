<?php
/**
 * Shared tracker behavior.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use GAinWP\Events\Queue;
use GAinWP\Integrations\Registry;
use GAinWP\Options;

/**
 * Handles tag placement, consent and opt-out for both trackers.
 */
abstract class AbstractTracker implements Tracker {

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	protected Options $options;

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
	public function register(): void {
		if ( $this->options->get( 'in_footer' ) ) {
			// Consent defaults and opt-out must run before anything else on the
			// page, such as a consent banner's update, so they stay in the head.
			add_action( 'wp_head', array( $this, 'output_privacy' ), 1 );
			add_action( 'wp_footer', array( $this, 'output' ), 1 );
		} else {
			add_action( 'wp_head', array( $this, 'output' ), 1 );
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_events' ) );
	}

	/**
	 * Enqueues assets/js/events.js, which provides gainwp.track() and sends
	 * engagement events and events recorded on the server.
	 */
	public function enqueue_events(): void {
		wp_enqueue_script(
			'gainwp-events',
			plugins_url( 'assets/js/events.js', GAINWP_FILE ),
			array(),
			GAINWP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( 'gainwp-events', 'window.gainwpEvents = ' . Script::json( $this->events_config() ) . ';', 'before' );
	}

	/**
	 * Settings for assets/js/events.js.
	 *
	 * @return array<string, mixed>
	 */
	protected function events_config(): array {
		$queue       = Queue::settings();
		$ajax_events = Registry::ajax_events( $this->options );
		if ( $ajax_events ) {
			$queue['events'] = $ajax_events;
		}
		$config = array(
			'mode'       => $this instanceof TagManager ? 'tagmanager' : 'gtag',
			'optOutFlag' => OptOut::FLAG,
			'queue'      => $queue,
			'currency'   => $this->options->get( 'currency' ),
		);
		if ( $this instanceof Gtag ) {
			$config['sendTo'] = $this->options->get( 'measurement_id' );
		}

		$goals = $this->options->goal_values();
		if ( $goals ) {
			$config['goals'] = (object) $goals;
		}

		foreach ( array( 'email', 'phone', 'anchors' ) as $event ) {
			if ( $this->options->event_enabled( $event ) ) {
				$config[ $event ] = true;
			}
		}
		if ( $this->options->event_enabled( 'affiliates' ) ) {
			$config['affiliates'] = $this->options->get( 'affiliate_links' );
		}
		if ( $this->options->event_enabled( 'scroll' ) ) {
			/**
			 * Filters the scroll depth percentages that send a scroll_depth
			 * event. GA4's enhanced measurement sends its own scroll event at 90%.
			 *
			 * @since 6.0.0
			 *
			 * @param array<int> $thresholds Percentages.
			 */
			$config['scroll'] = array_values( array_map( 'absint', (array) apply_filters( 'gainwp_scroll_thresholds', array( 25, 50, 75 ) ) ) );
		}

		return $config;
	}

	/**
	 * Prints the tag.
	 */
	abstract public function output(): void;

	/**
	 * Prints consent defaults and opt-out on their own, for footer placement.
	 */
	public function output_privacy(): void {
		wp_print_inline_script_tag( $this->script_start() . "\n" . $this->privacy_script(), array( 'id' => 'gainwp-privacy' ) );
	}

	/**
	 * Defines the data layer, and gtag() when this page needs it: always
	 * for GA4, and for Tag Manager only to set consent defaults, so
	 * containers don't find a gtag() they didn't expect.
	 */
	protected function script_start(): string {
		$needs_gtag = $this instanceof Gtag || '' !== Consent::script( $this->options->get( 'consent_default' ) );
		return $needs_gtag ? Script::GTAG_STUB : 'window.dataLayer = window.dataLayer || [];';
	}

	/**
	 * Consent defaults and the opt-out check. Expects script_start() to
	 * have run.
	 */
	protected function privacy_script(): string {
		return implode(
			"\n",
			array_filter(
				array(
					Consent::script( $this->options->get( 'consent_default' ) ),
					OptOut::script( $this->options ),
					Script::API_STUB,
				)
			)
		);
	}

	/**
	 * Whether the main output includes privacy_script(), i.e. the tag is in
	 * the head. Otherwise output_privacy() has already printed it.
	 */
	protected function privacy_in_output(): bool {
		return ! $this->options->get( 'in_footer' );
	}

	/**
	 * Collects the page attributes enabled in settings, keyed by parameter name.
	 *
	 * @return array<string, string>
	 */
	protected function page_data(): array {
		return PageData::collect( $this->options->get( 'page_data' ), $this->options->get( 'content_group' ) );
	}
}

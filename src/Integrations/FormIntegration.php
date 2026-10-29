<?php
/**
 * Shared behavior of form plugin integrations.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

use GAinWP\Events\Recorder;
use GAinWP\Options;

/**
 * Turns a form plugin's successful submission into the event chosen for
 * that form in the settings: form_complete (the default), generate_lead or
 * sign_up, with component_id and component_name. Field values are
 * never read.
 */
abstract class FormIntegration implements FormSource {

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	protected Options $options;

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
	 * Hooks into the plugin's success hook when form tracking is on.
	 *
	 * @param Recorder $recorder Records events.
	 */
	public function register( Recorder $recorder ): void {
		if ( $this->options->event_enabled( 'forms' ) ) {
			$this->recorder = $recorder;
			$this->hook();
		}
	}

	/**
	 * The plugin's browser events for a successful AJAX submission, when
	 * form tracking is on.
	 *
	 * @return array<int, string>
	 */
	public function ajax_events(): array {
		return $this->options->event_enabled( 'forms' ) ? $this->success_events() : array();
	}

	/**
	 * Adds the plugin's success hook, which calls submitted().
	 */
	abstract protected function hook(): void;

	/**
	 * Browser events the plugin fires after a successful AJAX submission.
	 *
	 * @return array<int, string>
	 */
	abstract protected function success_events(): array;

	/**
	 * Records a successful submission.
	 *
	 * @param int|string $form_id The plugin's form ID.
	 * @param string     $title   The form's title.
	 */
	protected function submitted( $form_id, string $title ): void {
		$component_id = Options::form_id( $this->provider(), $form_id );
		if ( null === $this->recorder || '' === $component_id ) {
			return;
		}
		$form = $this->options->form( $component_id );
		if ( 'off' === $form['event'] ) {
			return;
		}

		$params = array(
			'component_id'   => $component_id,
			'component_name' => html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ),
		);
		if ( 'sign_up' === $form['event'] ) {
			$params = array( 'method' => 'form' ) + $params;
		}
		// The Recorder adds the form's own value, or the goal's.

		$this->recorder->record( $form['event'], $params );
	}
}

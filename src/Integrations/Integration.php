<?php
/**
 * Integration contract.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

use GAinWP\Events\Recorder;

/**
 * Reports what happens in WordPress or another plugin (a login, a form
 * submission) to the Recorder, which queues it as a GA4 event.
 *
 * Add or replace integrations with the `gainwp_integrations` filter.
 */
interface Integration {

	/**
	 * Whether the integration's target is present, e.g. its plugin is active.
	 */
	public function is_available(): bool;

	/**
	 * Hooks into the target. Called on `init` for every request except cron,
	 * including admin-ajax and REST requests, where AJAX forms submit.
	 *
	 * @param Recorder $recorder Records events.
	 */
	public function register( Recorder $recorder ): void;

	/**
	 * Browser events the target fires once one of its AJAX requests has
	 * finished, such as a form plugin's success event. Events recorded
	 * during that request are sent then; otherwise, on the next page load.
	 * DOM events and jQuery events both work. Names may use letters, digits,
	 * '_', '-', ':' and '/'.
	 *
	 * @return array<int, string> Event names.
	 */
	public function ajax_events(): array;
}

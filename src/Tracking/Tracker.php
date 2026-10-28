<?php
/**
 * Tracker contract.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

/**
 * Outputs a tracking tag on the front end.
 */
interface Tracker {

	/**
	 * Hooks the tracker into page output.
	 */
	public function register(): void;
}

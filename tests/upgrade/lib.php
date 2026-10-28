<?php
/**
 * Assertion helper for the upgrade test scripts (run with `wp eval-file`).
 *
 * @package GAinWP
 */

if ( ! function_exists( 'gainwp_check' ) ) {
	/**
	 * Prints PASS or FAIL for one expectation.
	 *
	 * @param string $label    What is checked.
	 * @param mixed  $actual   Actual value.
	 * @param mixed  $expected Expected value.
	 */
	function gainwp_check( $label, $actual, $expected ) {
		$ok = $actual === $expected;
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' (expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) . ')' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}
}

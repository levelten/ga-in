<?php
/**
 * Prints the opt-out head check (OptOut::script) for the settings passed as
 * JSON in the first argument, so JS tests can run the real generated code.
 *
 * @package GAinWP
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, Universal.Files.SeparateFunctionsFromOO, Squiz.Commenting.FunctionComment.Missing

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['gainwp_fixture_settings'] = json_decode( $argv[1] ?? '{}', true );

function get_option( $name, $fallback = false ) {
	return 'gainwp_settings' === $name ? $GLOBALS['gainwp_fixture_settings'] : $fallback;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

$gainwp_src = dirname( __DIR__, 3 ) . '/src';
require $gainwp_src . '/Options.php';
require $gainwp_src . '/Tracking/Script.php';
require $gainwp_src . '/Tracking/OptOut.php';

echo \GAinWP\Tracking\OptOut::script( new \GAinWP\Options() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- test fixture.

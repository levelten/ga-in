<?php
/**
 * Plugin Name:       GAinWP Google Analytics Integration for WP
 * Plugin URI:        https://wordpress.org/plugins/ga-in/
 * Description:       Google Analytics 4 or Google Tag Manager for WordPress, with form lead tracking, goal values, GA4's own event names and a setup checklist.
 * Version:           6.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            IntelligenceWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ga-in
 *
 * Originally based on Google Analytics Dashboard for WordPress (GADWP) by Alin Marcu.
 *
 * @package GAinWP
 */

// This file must stay parseable on every PHP version WordPress 6.0 runs on
// (5.6+) so the version check below can bail out instead of causing a fatal error.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// @phpstan-ignore-next-line if.alwaysFalse
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	/**
	 * Explains why tracking stopped.
	 *
	 * @return void
	 */
	function gainwp_php_version_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'GAinWP requires PHP 7.4 or newer, so Google Analytics tracking is paused. Ask your host to update PHP.', 'ga-in' )
		);
	}
	add_action( 'admin_notices', 'gainwp_php_version_notice' );
	return;
}

define( 'GAINWP_VERSION', '6.1.0' );
define( 'GAINWP_FILE', __FILE__ );

/**
 * Kept for third-party code that checked for the plugin.
 *
 * @deprecated 6.0.0 Use GAINWP_VERSION.
 */
define( 'GAINWP_CURRENT_VERSION', GAINWP_VERSION );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'GAinWP\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

\GAinWP\Plugin::instance()->register();

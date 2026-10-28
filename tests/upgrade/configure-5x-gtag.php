<?php
/**
 * Configures GAinWP 5.4.6 as a typical Universal Analytics (gtag.js) site,
 * using 5.4.6's own option handling so the stored format is authentic.
 *
 * @package GAinWP
 */

require __DIR__ . '/lib.php';

gainwp_check( '5.4.6 is installed', GAINWP_CURRENT_VERSION, '5.4.6' );

$gainwp_config = GAINWP()->config;
foreach (
	array(
		'tracking_type'               => 'universal',
		'tracking_id'                 => 'UA-12345-1',
		'ga_with_gtag'                => 1,
		'ga_event_tracking'           => 1,
		'ga_aff_tracking'             => 1,
		'ga_event_affiliates'         => '/go/|/recommends/',
		'ga_pagescrolldepth_tracking' => 1,
		'ga_author_dimindex'          => 1,
		'ga_category_dimindex'        => 2,
		'ga_dnt_optout'               => 1,
		'ga_optout'                   => 1,
		'track_exclude'               => array( 'administrator' ),
		// Stored Google sign-in, as a site that connected reporting would have.
		'token'                       => '{"access_token":"test-token","refresh_token":"test-refresh"}',
		'client_secret'               => 'test-secret',
	) as $gainwp_key => $gainwp_value
) {
	$gainwp_config->options[ $gainwp_key ] = $gainwp_value;
}
$gainwp_config->set_plugin_options();

// Cached Universal Analytics report data.
GAINWP_Tools::set_cache( 'qr_upgrade_test', array( 'rows' => 1 ), 3600 );
gainwp_check( '5.x cached report stored', (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE 'gainwp\\_cache\\_%'" ), 1 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

gainwp_check( '5.x settings stored', json_decode( get_option( 'gainwp_options' ), true )['tracking_id'], 'UA-12345-1' );

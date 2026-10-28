<?php
/**
 * Checks the stored data after a 5.x Universal Analytics site updated.
 *
 * @package GAinWP
 */

require __DIR__ . '/lib.php';

global $wpdb;

$gainwp_settings = (array) get_option( 'gainwp_settings' );
$gainwp_backup   = (array) get_option( 'gainwp_legacy_options' );

gainwp_check( 'settings format version', (int) get_option( 'gainwp_db_version' ), 2 );
gainwp_check( 'tracking type', $gainwp_settings['tracking_type'] ?? null, 'gtag' );
gainwp_check( 'UA ID not carried over', $gainwp_settings['measurement_id'] ?? null, '' );
gainwp_check(
	'events',
	$gainwp_settings['events'] ?? null,
	array(
		'email'      => true,
		'phone'      => true,
		'anchors'    => false,
		'affiliates' => true,
		'scroll'     => true,
	)
);
gainwp_check( 'affiliate regex converted', $gainwp_settings['affiliate_links'] ?? null, array( '/go/', '/recommends/' ) );
gainwp_check( 'custom dimensions became page attributes', $gainwp_settings['page_data'] ?? null, array( 'author', 'category' ) );
gainwp_check( 'Do Not Track kept', $gainwp_settings['respect_dnt'] ?? null, true );
gainwp_check( 'opt-out kept', $gainwp_settings['allow_optout'] ?? null, true );
gainwp_check( 'excluded roles kept', $gainwp_settings['exclude_roles'] ?? null, array( 'administrator' ) );
gainwp_check( '5.x option removed', get_option( 'gainwp_options' ), false );
gainwp_check( 'cached reports removed', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'gainwp\\_cache\\_%'" ), 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
gainwp_check( 'backup kept', $gainwp_backup['tracking_id'] ?? null, 'UA-12345-1' );
gainwp_check( 'backup has no credentials', array_intersect( array( 'token', 'client_id', 'client_secret' ), array_keys( $gainwp_backup ) ), array() );
gainwp_check( 'no migration warnings', get_option( 'gainwp_upgrade_warnings' ), false );
gainwp_check( 'admin is asked for a Measurement ID', null !== \GAinWP\Admin\Notices::problem( \GAinWP\Plugin::instance()->options() ), true );

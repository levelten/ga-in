<?php
/**
 * Checks that deleting the plugin removed all of its data.
 *
 * @package GAinWP
 */

require __DIR__ . '/lib.php';

global $wpdb;

gainwp_check( 'no gainwp options left', $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gainwp%'" ), array() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

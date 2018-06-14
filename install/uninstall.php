<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

class GAINWP_Uninstall {

	public static function uninstall() {
		global $wpdb;
		if ( is_multisite() ) { // Cleanup Network install
			foreach ( GAINWP_Tools::get_sites( array( 'number' => apply_filters( 'gainwp_sites_limit', 100 ) ) ) as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				$sqlquery = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'gainwp_cache_%%'" );
				delete_option( 'gainwp_options' );
				restore_current_blog();
			}
			delete_site_option( 'gainwp_network_options' );
		} else { // Cleanup Single install
			$sqlquery = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'gainwp_cache_%%'" );
			delete_option( 'gainwp_options' );
		}
		GAINWP_Tools::unset_cookie( 'default_metric' );
		GAINWP_Tools::unset_cookie( 'default_dimension' );
		GAINWP_Tools::unset_cookie( 'default_view' );
	}
}

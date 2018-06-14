<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Frontend_Item_Reports' ) ) {

	final class GAINWP_Frontend_Item_Reports {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();
			
			add_action( 'admin_bar_menu', array( $this, 'custom_adminbar_node' ), 999 );
		}

		function custom_adminbar_node( $wp_admin_bar ) {
			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_front'] ) && $this->gainwp->config->options['frontend_item_reports'] ) {
				/* @formatter:off */
				$args = array( 	'id' => 'gainwp-1',
								'title' => '<span class="ab-icon"></span><span class="">' . __( "Analytics", 'ga-in' ) . '</span>',
								'href' => '#1',
								);
				/* @formatter:on */
				$wp_admin_bar->add_node( $args );
			}
		}
	}
}

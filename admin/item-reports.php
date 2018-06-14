<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Backend_Item_Reports' ) ) {

	final class GAINWP_Backend_Item_Reports {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();

			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && 1 == $this->gainwp->config->options['backend_item_reports'] ) {
				// Add custom column in Posts List
				add_filter( 'manage_posts_columns', array( $this, 'add_columns' ) );

				// Populate custom column in Posts List
				add_action( 'manage_posts_custom_column', array( $this, 'add_icons' ), 10, 2 );

				// Add custom column in Pages List
				add_filter( 'manage_pages_columns', array( $this, 'add_columns' ) );

				// Populate custom column in Pages List
				add_action( 'manage_pages_custom_column', array( $this, 'add_icons' ), 10, 2 );
			}
		}

		public function add_icons( $column, $id ) {
			global $wp_version;

			if ( 'gainwp_stats' != $column ) {
				return;
			}

			if ( version_compare( $wp_version, '3.8.0', '>=' ) ) {
				echo '<a id="gainwp-' . $id . '" title="' . get_the_title( $id ) . '" href="#' . $id . '" class="gainwp-icon dashicons-before dashicons-chart-area">&nbsp;</a>';
			} else {
				echo '<a id="gainwp-' . $id . '" title="' . get_the_title( $id ) . '" href="#' . $id . '"><img class="gainwp-icon-oldwp" src="' . GAINWP_URL . 'admin/images/gainwp-icon.png"</a>';
			}
		}

		public function add_columns( $columns ) {
			return array_merge( $columns, array( 'gainwp_stats' => __( 'Analytics', 'ga-in' ) ) );
		}
	}
}

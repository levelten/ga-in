<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Backend_Widgets' ) ) {

	class GAINWP_Backend_Widgets {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();
			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && ( 1 == $this->gainwp->config->options['dashboard_widget'] ) ) {
				add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
			}
		}

		public function add_widget() {
			wp_add_dashboard_widget( 'gainwp-widget', __( "Google Analytics", 'ga-in' ), array( $this, 'dashboard_widget' ), $control_callback = null );
		}

		public function dashboard_widget() {
			$projectId = 0;
			
			if ( empty( $this->gainwp->config->options['token'] ) ) {
				echo '<p>' . __( "This plugin needs an authorization:", 'ga-in' ) . '</p><form action="' . menu_page_url( 'gainwp_settings', false ) . '" method="POST">' . get_submit_button( __( "Authorize Plugin", 'ga-in' ), 'secondary' ) . '</form>';
				return;
			}
			
			if ( current_user_can( 'manage_options' ) ) {
				if ( $this->gainwp->config->options['tableid_jail'] ) {
					$projectId = $this->gainwp->config->options['tableid_jail'];
				} else {
					echo '<p>' . __( "An admin should asign a default Google Analytics Profile.", 'ga-in' ) . '</p><form action="' . menu_page_url( 'gainwp_settings', false ) . '" method="POST">' . get_submit_button( __( "Select Domain", 'ga-in' ), 'secondary' ) . '</form>';
					return;
				}
			} else {
				if ( $this->gainwp->config->options['tableid_jail'] ) {
					$projectId = $this->gainwp->config->options['tableid_jail'];
				} else {
					echo '<p>' . __( "An admin should asign a default Google Analytics Profile.", 'ga-in' ) . '</p><form action="' . menu_page_url( 'gainwp_settings', false ) . '" method="POST">' . get_submit_button( __( "Select Domain", 'ga-in' ), 'secondary' ) . '</form>';
					return;
				}
			}
			
			if ( ! ( $projectId ) ) {
				echo '<p>' . __( "Something went wrong while retrieving property data. You need to create and properly configure a Google Analytics account:", 'ga-in' ) . '</p>';

				//echo '<p>' . __( "Something went wrong while retrieving property data. You need to create and properly configure a Google Analytics account:", 'ga-in' ) . '</p> <form action="https://intelligencewp.com/how-to-set-up-google-analytics-on-your-website/" method="POST">' . get_submit_button( __( "Find out more!", 'ga-in' ), 'secondary' ) . '</form>';
				return;
			}
			
			?>
<div id="gainwp-window-1"></div>
<?php
		}
	}
}

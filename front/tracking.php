<?php
/**
 * Copyright 2017 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Tracking' ) ) {

	class GAINWP_Tracking {

		private $gainwp;

		public $analytics;

		public $analytics_amp;

		public $tagmanager;

		public function __construct() {
			$this->gainwp = GAINWP();

			$this->init();
		}

		public function tracking_code() { // Removed since 5.0
			GAINWP_Tools::doing_it_wrong( __METHOD__, __( "This method is deprecated, read the documentation!", 'ga-in' ), '5.0' );
		}

		public static function gainwp_user_optout( $atts, $content = "" ) {
			if ( ! isset( $atts['html_tag'] ) ) {
				$atts['html_tag'] = 'a';
			}
			if ( 'a' == $atts['html_tag'] ) {
				return '<a href="#" class="gainwp_useroptout" onclick="gaOptout()">' . esc_html( $content ) . '</a>';
			} else if ( 'button' == $atts['html_tag'] ) {
				return '<button class="gainwp_useroptout" onclick="gaOptout()">' . esc_html( $content ) . '</button>';
			}
		}

		public function init() {
			// excluded roles
			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['track_exclude'], true ) || ( $this->gainwp->config->options['superadmin_tracking'] && current_user_can( 'manage_network' ) ) ) {
				return;
			}

			if ( 'universal' == $this->gainwp->config->options['tracking_type'] && ($this->gainwp->config->options['tableid_jail'] || $this->gainwp->config->options['tracking_id']) ) {

				// Analytics
				require_once 'tracking-analytics.php';

				if ( 1 == $this->gainwp->config->options['ga_with_gtag'] ) {
					$this->analytics = new GAINWP_Tracking_GlobalSiteTag();
				} else {
					$this->analytics = new GAINWP_Tracking_Analytics();
				}

				if ( $this->gainwp->config->options['amp_tracking_analytics'] ) {
					$this->analytics_amp = new GAINWP_Tracking_Analytics_AMP();
				}
			}

			if ( 'tagmanager' == $this->gainwp->config->options['tracking_type'] && $this->gainwp->config->options['web_containerid'] ) {

				// Tag Manager
				require_once 'tracking-tagmanager.php';
				$this->tagmanager = new GAINWP_Tracking_TagManager();
			}

			add_shortcode( 'gainwp_useroptout', array( $this, 'gainwp_user_optout' ) );
		}
	}
}

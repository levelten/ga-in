<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Frontend_Ajax' ) ) {

	final class GAINWP_Frontend_Ajax {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();

			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_front'] ) && $this->gainwp->config->options['frontend_item_reports'] ) {
				// Item Reports action
				add_action( 'wp_ajax_gainwp_frontend_item_reports', array( $this, 'ajax_item_reports' ) );
			}

			// Frontend Widget actions
			add_action( 'wp_ajax_ajax_frontwidget_report', array( $this, 'ajax_frontend_widget' ) );
			add_action( 'wp_ajax_nopriv_ajax_frontwidget_report', array( $this, 'ajax_frontend_widget' ) );
		}

		/**
		 * Ajax handler for Item Reports
		 *
		 * @return string|int
		 */
		public function ajax_item_reports() {
			if ( ! isset( $_POST['gainwp_security_frontend_item_reports'] ) || ! wp_verify_nonce( $_POST['gainwp_security_frontend_item_reports'], 'gainwp_frontend_item_reports' ) ) {
				wp_die( - 30 );
			}

			$from = $_POST['from'];
			$to = $_POST['to'];
			$query = $_POST['query'];
			$uri = $_POST['filter'];
			if ( isset( $_POST['metric'] ) ) {
				$metric = $_POST['metric'];
			} else {
				$metric = 'pageviews';
			}

			$query = $_POST['query'];
			if ( ob_get_length() ) {
				ob_clean();
			}

			if ( ! GAINWP_Tools::check_roles( $this->gainwp->config->options['access_front'] ) || 0 == $this->gainwp->config->options['frontend_item_reports'] ) {
				wp_die( - 31 );
			}

			if ( $this->gainwp->config->options['token'] && $this->gainwp->config->options['tableid_jail'] ) {
				if ( null === $this->gainwp->gapi_controller ) {
					$this->gainwp->gapi_controller = new GAINWP_GAPI_Controller();
				}
			} else {
				wp_die( - 24 );
			}

			if ( $this->gainwp->config->options['tableid_jail'] ) {
				$projectId = $this->gainwp->config->options['tableid_jail'];
			} else {
				wp_die( - 26 );
			}

			$profile_info = GAINWP_Tools::get_selected_profile( $this->gainwp->config->options['ga_profiles_list'], $projectId );

			if ( isset( $profile_info[4] ) ) {
				$this->gainwp->gapi_controller->timeshift = $profile_info[4];
			} else {
				$this->gainwp->gapi_controller->timeshift = (int) current_time( 'timestamp' ) - time();
			}

			$uri = '/' . ltrim( $uri, '/' );

			// allow URL correction before sending an API request
			$filter = apply_filters( 'gainwp_frontenditem_uri', $uri );

			$lastchar = substr( $filter, - 1 );

			if ( isset( $profile_info[6] ) && $profile_info[6] && '/' == $lastchar ) {
				$filter = $filter . $profile_info[6];
			}

			// Encode URL
			$filter = rawurlencode( rawurldecode( $filter ) );

			$queries = explode( ',', $query );

			$results = array();

			foreach ( $queries as $value ) {
				$results[] = $this->gainwp->gapi_controller->get( $projectId, $value, $from, $to, $filter, $metric );
			}

			wp_send_json( $results );
		}

		/**
		 * Ajax handler for getting analytics data for frontend Widget
		 *
		 * @return string|int
		 */
		public function ajax_frontend_widget() {
			if ( ! isset( $_POST['gainwp_number'] ) || ! isset( $_POST['gainwp_optionname'] ) || ! is_active_widget( false, false, 'gainwp-frontwidget-report' ) ) {
				wp_die( - 30 );
			}
			$widget_index = $_POST['gainwp_number'];
			$option_name = $_POST['gainwp_optionname'];
			$options = get_option( $option_name );
			if ( isset( $options[$widget_index] ) ) {
				$instance = $options[$widget_index];
			} else {
				wp_die( - 32 );
			}
			switch ( $instance['period'] ) { // make sure we have a valid request
				case '7daysAgo' :
					$period = '7daysAgo';
					break;
				case '14daysAgo' :
					$period = '14daysAgo';
					break;
				default :
					$period = '30daysAgo';
					break;
			}
			if ( ob_get_length() ) {
				ob_clean();
			}
			if ( $this->gainwp->config->options['token'] && $this->gainwp->config->options['tableid_jail'] ) {
				if ( null === $this->gainwp->gapi_controller ) {
					$this->gainwp->gapi_controller = new GAINWP_GAPI_Controller();
				}
			} else {
				wp_die( - 24 );
			}
			$projectId = $this->gainwp->config->options['tableid_jail'];
			$profile_info = GAINWP_Tools::get_selected_profile( $this->gainwp->config->options['ga_profiles_list'], $projectId );
			if ( isset( $profile_info[4] ) ) {
				$this->gainwp->gapi_controller->timeshift = $profile_info[4];
			} else {
				$this->gainwp->gapi_controller->timeshift = (int) current_time( 'timestamp' ) - time();
			}
			wp_send_json( $this->gainwp->gapi_controller->frontend_widget_stats( $projectId, $period, (int) $instance['anonim'] ) );
		}
	}
}

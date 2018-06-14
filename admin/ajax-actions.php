<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Backend_Ajax' ) ) {

	final class GAINWP_Backend_Ajax {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();

			if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && ( ( 1 == $this->gainwp->config->options['backend_item_reports'] ) || ( 1 == $this->gainwp->config->options['dashboard_widget'] ) ) ) {
				// Items action
				add_action( 'wp_ajax_gainwp_backend_item_reports', array( $this, 'ajax_item_reports' ) );
			}
			if ( current_user_can( 'manage_options' ) ) {
				// Admin Widget action
				add_action( 'wp_ajax_gainwp_dismiss_notices', array( $this, 'ajax_dismiss_notices' ) );
			}
		}

		/**
		 * Ajax handler for Item Reports
		 *
		 * @return json|int
		 */
		public function ajax_item_reports() {
			if ( ! isset( $_POST['gainwp_security_backend_item_reports'] ) || ! wp_verify_nonce( $_POST['gainwp_security_backend_item_reports'], 'gainwp_backend_item_reports' ) ) {
				wp_die( - 30 );
			}
			if ( isset( $_POST['projectId'] ) && $this->gainwp->config->options['switch_profile'] && 'false' !== $_POST['projectId'] ) {
				$projectId = $_POST['projectId'];
			} else {
				$projectId = false;
			}
			$from = $_POST['from'];
			$to = $_POST['to'];
			$query = $_POST['query'];
			if ( isset( $_POST['filter'] ) ) {
				$filter_id = $_POST['filter'];
			} else {
				$filter_id = false;
			}
			if ( isset( $_POST['metric'] ) ) {
				$metric = $_POST['metric'];
			} else {
				$metric = 'sessions';
			}

			if ( $filter_id && $metric == 'sessions' ) { // Sessions metric is not available for item reports
				$metric = 'pageviews';
			}

			if ( ob_get_length() ) {
				ob_clean();
			}

			if ( ! ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && ( ( 1 == $this->gainwp->config->options['backend_item_reports'] ) || ( 1 == $this->gainwp->config->options['dashboard_widget'] ) ) ) ) {
				wp_die( - 31 );
			}
			if ( $this->gainwp->config->options['token'] && $this->gainwp->config->options['tableid_jail'] && $from && $to ) {
				if ( null === $this->gainwp->gapi_controller ) {
					$this->gainwp->gapi_controller = new GAINWP_GAPI_Controller();
				}
			} else {
				wp_die( - 24 );
			}
			if ( false == $projectId ) {
				$projectId = $this->gainwp->config->options['tableid_jail'];
			}
			$profile_info = GAINWP_Tools::get_selected_profile( $this->gainwp->config->options['ga_profiles_list'], $projectId );
			if ( isset( $profile_info[4] ) ) {
				$this->gainwp->gapi_controller->timeshift = $profile_info[4];
			} else {
				$this->gainwp->gapi_controller->timeshift = (int) current_time( 'timestamp' ) - time();
			}

			if ( $filter_id ) {
				$uri_parts = explode( '/', get_permalink( $filter_id ), 4 );

				if ( isset( $uri_parts[3] ) ) {
					$uri = '/' . $uri_parts[3];
				} else {
					wp_die( - 25 );
				}

				// allow URL correction before sending an API request
				$filter = apply_filters( 'gainwp_backenditem_uri', $uri, $filter_id );

				$lastchar = substr( $filter, - 1 );

				if ( isset( $profile_info[6] ) && $profile_info[6] && '/' == $lastchar ) {
					$filter = $filter . $profile_info[6];
				}

				// Encode URL
				$filter = rawurlencode( rawurldecode( $filter ) );
			} else {
				$filter = false;
			}

			$queries = explode( ',', $query );

			$results = array();

			foreach ( $queries as $value ) {
				$results[] = $this->gainwp->gapi_controller->get( $projectId, $value, $from, $to, $filter, $metric );
			}

			wp_send_json( $results );
		}

		/**
		 * Ajax handler for dismissing Admin notices
		 *
		 * @return json|int
		 */
		public function ajax_dismiss_notices() {
			if ( ! isset( $_POST['gainwp_security_dismiss_notices'] ) || ! wp_verify_nonce( $_POST['gainwp_security_dismiss_notices'], 'gainwp_dismiss_notices' ) ) {
				wp_die( - 30 );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( - 31 );
			}

			delete_option( 'gainwp_got_updated' );

			wp_die();
		}
	}
}

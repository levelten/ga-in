<?php
/**
 * Plugin Name: GAinWP Google Analytics Integration for WordPress
 * Plugin URI: https://intelligencewp.com/google-analytics-in-wordpress
 * Description: Automatically adds Google Analytics tracking to your site and displays Google Analytics reports and real-time statistics in your dashboard.
 * Author: IntelligenceWP
 * Version: 5.4.5-dev
 * Author URI: https://intelligencewp.com
 * Text Domain: ga-in
 * Domain Path: /languages
 *
 * This plugin was originally created as the Google Analytics Dashboard for WordPress (GADWP) by Alin Marcu (https://deconf.com).
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

// Plugin Version
if ( ! defined( 'GAINWP_CURRENT_VERSION' ) ) {
	define( 'GAINWP_CURRENT_VERSION', '5.4.5-dev' );
}

if ( ! defined( 'GAINWP_ENDPOINT_URL' ) ) {
	define( 'GAINWP_ENDPOINT_URL', '' );
}


if ( ! class_exists( 'GAINWP_Manager' ) ) {

	final class GAINWP_Manager {

		private static $instance = null;

		public $config = null;

		public $frontend_actions = null;

		public $common_actions = null;

		public $backend_actions = null;

		public $tracking = null;

		public $frontend_item_reports = null;

		public $backend_setup = null;

		public $frontend_setup = null;

		public $backend_widgets = null;

		public $backend_item_reports = null;

		public $gapi_controller = null;

		/**
		 * Construct forbidden
		 */
		private function __construct() {
			if ( null !== self::$instance ) {
				_doing_it_wrong( __FUNCTION__, __( "This is not allowed, read the documentation!", 'ga-in' ), '4.6' );
			}
		}

		/**
		 * Clone warning
		 */
		private function __clone() {
			_doing_it_wrong( __FUNCTION__, __( "This is not allowed, read the documentation!", 'ga-in' ), '4.6' );
		}

		/**
		 * Wakeup warning
		 */
		private function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( "This is not allowed, read the documentation!", 'ga-in' ), '4.6' );
		}

		/**
		 * Creates a single instance for GAINWP and makes sure only one instance is present in memory.
		 *
		 * @return GAINWP_Manager
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
				self::$instance->setup();
				self::$instance->config = new GAINWP_Config();
			}
			return self::$instance;
		}

		/**
		 * Defines constants and loads required resources
		 */
		private function setup() {

			// Plugin Path
			if ( ! defined( 'GAINWP_DIR' ) ) {
				define( 'GAINWP_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin URL
			if ( ! defined( 'GAINWP_URL' ) ) {
				define( 'GAINWP_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin main File
			if ( ! defined( 'GAINWP_FILE' ) ) {
				define( 'GAINWP_FILE', __FILE__ );
			}

			/*
			 * Load Tools class
			 */
			include_once ( GAINWP_DIR . 'tools/tools.php' );

			/*
			 * Load Config class
			 */
			include_once ( GAINWP_DIR . 'config.php' );

			/*
			 * Load GAPI Controller class
			 */
			include_once ( GAINWP_DIR . 'tools/gapi.php' );

			/*
			 * Plugin i18n
			 */
			add_action( 'init', array( self::$instance, 'load_i18n' ) );

			/*
			 * Plugin Init
			 */
			add_action( 'init', array( self::$instance, 'load' ) );

			/*
			 * Include Install
			 */
			include_once ( GAINWP_DIR . 'install/install.php' );
			register_activation_hook( GAINWP_FILE, array( 'GAINWP_Install', 'install' ) );

			/*
			 * Include Uninstall
			 */
			include_once ( GAINWP_DIR . 'install/uninstall.php' );
			register_uninstall_hook( GAINWP_FILE, array( 'GAINWP_Uninstall', 'uninstall' ) );

			/*
			 * Load Frontend Widgets
			 * (needed during ajax)
			 */
			include_once ( GAINWP_DIR . 'front/widgets.php' );

			/*
			 * Add Frontend Widgets
			 * (needed during ajax)
			 */
			add_action( 'widgets_init', array( self::$instance, 'add_frontend_widget' ) );
		}

		/**
		 * Load i18n
		 */
		public function load_i18n() {
			load_plugin_textdomain( 'ga-in', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Register Frontend Widgets
		 */
		public function add_frontend_widget() {
			register_widget( 'GAINWP_Frontend_Widget' );
		}

		/**
		 * Conditional load
		 */
		public function load() {
			if ( is_admin() ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					if ( GAINWP_Tools::check_roles( self::$instance->config->options['access_back'] ) ) {
						/*
						 * Load Backend ajax actions
						 */
						include_once ( GAINWP_DIR . 'admin/ajax-actions.php' );
						self::$instance->backend_actions = new GAINWP_Backend_Ajax();
					}

					/*
					 * Load Frontend ajax actions
					 */
					include_once ( GAINWP_DIR . 'front/ajax-actions.php' );
					self::$instance->frontend_actions = new GAINWP_Frontend_Ajax();

					/*
					 * Load Common ajax actions
					 */
					include_once ( GAINWP_DIR . 'common/ajax-actions.php' );
					self::$instance->common_actions = new GAINWP_Common_Ajax();

					if ( self::$instance->config->options['backend_item_reports'] ) {
						/*
						 * Load Backend Item Reports for Quick Edit
						 */
						include_once ( GAINWP_DIR . 'admin/item-reports.php' );
						self::$instance->backend_item_reports = new GAINWP_Backend_Item_Reports();
					}
				} else if ( GAINWP_Tools::check_roles( self::$instance->config->options['access_back'] ) ) {

					/*
					 * Load Backend Setup
					 */
					include_once ( GAINWP_DIR . 'admin/setup.php' );
					self::$instance->backend_setup = new GAINWP_Backend_Setup();

					if ( self::$instance->config->options['dashboard_widget'] ) {
						/*
						 * Load Backend Widget
						 */
						include_once ( GAINWP_DIR . 'admin/widgets.php' );
						self::$instance->backend_widgets = new GAINWP_Backend_Widgets();
					}

					if ( self::$instance->config->options['backend_item_reports'] ) {
						/*
						 * Load Backend Item Reports
						 */
						include_once ( GAINWP_DIR . 'admin/item-reports.php' );
						self::$instance->backend_item_reports = new GAINWP_Backend_Item_Reports();
					}
				}
			} else {
				if ( GAINWP_Tools::check_roles( self::$instance->config->options['access_front'] ) ) {
					/*
					 * Load Frontend Setup
					 */
					include_once ( GAINWP_DIR . 'front/setup.php' );
					self::$instance->frontend_setup = new GAINWP_Frontend_Setup();

					if ( self::$instance->config->options['frontend_item_reports'] ) {
						/*
						 * Load Frontend Item Reports
						 */
						include_once ( GAINWP_DIR . 'front/item-reports.php' );
						self::$instance->frontend_item_reports = new GAINWP_Frontend_Item_Reports();
					}
				}

				if ( ! GAINWP_Tools::check_roles( self::$instance->config->options['track_exclude'], true ) && 'disabled' != self::$instance->config->options['tracking_type'] ) {
					/*
					 * Load tracking class
					 */
					include_once ( GAINWP_DIR . 'front/tracking.php' );
					self::$instance->tracking = new GAINWP_Tracking();
				}
			}
		}
	}
}

/**
 * Returns a unique instance of GAINWP
 */
function GAINWP() {
	return GAINWP_Manager::instance();
}

/*
 * Start GAINWP
 */
GAINWP();

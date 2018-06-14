<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Backend_Setup' ) ) {

	final class GAINWP_Backend_Setup {

		private $gainwp;

		public function __construct() {
			$this->gainwp = GAINWP();

			// Styles & Scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'load_styles_scripts' ) );
			// Site Menu
			add_action( 'admin_menu', array( $this, 'site_menu' ) );
			// Network Menu
			add_action( 'network_admin_menu', array( $this, 'network_menu' ) );
			// Settings link
			add_filter( "plugin_action_links_" . plugin_basename( GAINWP_DIR . 'gainwp.php' ), array( $this, 'settings_link' ) );
			// Updated admin notice
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		}

		/**
		 * Add Site Menu
		 */
		public function site_menu() {
			global $wp_version;
			if ( current_user_can( 'manage_options' ) ) {
				include ( GAINWP_DIR . 'admin/settings.php' );
				add_menu_page( __( "Google Analytics", 'ga-in' ), __( "Google Analytics", 'ga-in' ), 'manage_options', 'gainwp_settings', array( 'GAINWP_Settings', 'general_settings' ), version_compare( $wp_version, '3.8.0', '>=' ) ? 'dashicons-chart-area' : GAINWP_URL . 'admin/images/gainwp-icon.png' );
				add_submenu_page( 'gainwp_settings', __( "General Settings", 'ga-in' ), __( "General Settings", 'ga-in' ), 'manage_options', 'gainwp_settings', array( 'GAINWP_Settings', 'general_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Tracking Settings", 'ga-in' ), __( "Tracking Settings", 'ga-in' ), 'manage_options', 'gainwp_tracking_settings', array( 'GAINWP_Settings', 'tracking_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Reporting Settings", 'ga-in' ), __( "Reporting Settings", 'ga-in' ), 'manage_options', 'gainwp_report_settings', array( 'GAINWP_Settings', 'reporting_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Errors & Debug", 'ga-in' ), __( "Errors & Debug", 'ga-in' ), 'manage_options', 'gainwp_errors_debugging', array( 'GAINWP_Settings', 'errors_debugging' ) );
				/*
				add_submenu_page( 'gainwp_settings', __( "General Settings", 'ga-in' ), __( "General Settings", 'ga-in' ), 'manage_options', 'gainwp_settings', array( 'GAINWP_Settings', 'general_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Backend Settings", 'ga-in' ), __( "Backend Settings", 'ga-in' ), 'manage_options', 'gainwp_backend_settings', array( 'GAINWP_Settings', 'backend_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Frontend Settings", 'ga-in' ), __( "Frontend Settings", 'ga-in' ), 'manage_options', 'gainwp_frontend_settings', array( 'GAINWP_Settings', 'frontend_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Tracking Settings", 'ga-in' ), __( "Tracking Code", 'ga-in' ), 'manage_options', 'gainwp_tracking_settings', array( 'GAINWP_Settings', 'tracking_settings' ) );
				add_submenu_page( 'gainwp_settings', __( "Errors & Debug", 'ga-in' ), __( "Errors & Debug", 'ga-in' ), 'manage_options', 'gainwp_errors_debugging', array( 'GAINWP_Settings', 'errors_debugging' ) );
				*/
			}
		}

		/**
		 * Add Network Menu
		 */
		public function network_menu() {
			global $wp_version;
			if ( current_user_can( 'manage_network' ) ) {
				include ( GAINWP_DIR . 'admin/settings.php' );
				add_menu_page( __( "Google Analytics", 'ga-in' ), "Google Analytics", 'manage_network', 'gainwp_settings', array( 'GAINWP_Settings', 'general_settings_network' ), version_compare( $wp_version, '3.8.0', '>=' ) ? 'dashicons-chart-area' : GAINWP_URL . 'admin/images/gainwp-icon.png' );
				add_submenu_page( 'gainwp_settings', __( "General Settings", 'ga-in' ), __( "General Settings", 'ga-in' ), 'manage_network', 'gainwp_settings', array( 'GAINWP_Settings', 'general_settings_network' ) );
				add_submenu_page( 'gainwp_settings', __( "Errors & Debug", 'ga-in' ), __( "Errors & Debug", 'ga-in' ), 'manage_network', 'gainwp_errors_debugging', array( 'GAINWP_Settings', 'errors_debugging' ) );
			}
		}

		/**
		 * Styles & Scripts conditional loading (based on current URI)
		 *
		 * @param
		 *            $hook
		 */
		public function load_styles_scripts( $hook ) {
			$new_hook = explode( '_page_', $hook );

			if ( isset( $new_hook[1] ) ) {
				$new_hook = '_page_' . $new_hook[1];
			} else {
				$new_hook = $hook;
			}

			/*
			 * GAINWP main stylesheet
			 */
			wp_enqueue_style( 'gainwp', GAINWP_URL . 'admin/css/gainwp.css', null, GAINWP_CURRENT_VERSION );

			/*
			 * GAINWP UI
			 */

			if ( GAINWP_Tools::get_cache( 'gapi_errors' ) ) {
				$ed_bubble = '!';
			} else {
				$ed_bubble = '';
			}

			wp_enqueue_script( 'gainwp-backend-ui', plugins_url( 'js/ui.js', __FILE__ ), array( 'jquery' ), GAINWP_CURRENT_VERSION, true );

			/* @formatter:off */
			wp_localize_script( 'gainwp-backend-ui', 'gainwp_ui_data', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'gainwp_dismiss_notices' ),
				'ed_bubble' => $ed_bubble,
			)
			);
			/* @formatter:on */

			if ( $this->gainwp->config->options['switch_profile'] && count( $this->gainwp->config->options['ga_profiles_list'] ) > 1 ) {
				$views = array();
				foreach ( $this->gainwp->config->options['ga_profiles_list'] as $items ) {
					if ( $items[3] ) {
						$views[$items[1]] = esc_js( GAINWP_Tools::strip_protocol( $items[3] ) ); // . ' &#8658; ' . $items[0] );
					}
				}
			} else {
				$views = false;
			}

			/*
			 * Main Dashboard Widgets Styles & Scripts
			 */
			$widgets_hooks = array( 'index.php' );

			if ( in_array( $new_hook, $widgets_hooks ) ) {
				if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && $this->gainwp->config->options['dashboard_widget'] ) {

					if ( $this->gainwp->config->options['ga_target_geomap'] ) {
						$country_codes = GAINWP_Tools::get_countrycodes();
						if ( isset( $country_codes[$this->gainwp->config->options['ga_target_geomap']] ) ) {
							$region = $this->gainwp->config->options['ga_target_geomap'];
						} else {
							$region = false;
						}
					} else {
						$region = false;
					}

					wp_enqueue_style( 'gainwp-nprogress', GAINWP_URL . 'common/nprogress/nprogress.css', null, GAINWP_CURRENT_VERSION );

					wp_enqueue_style( 'gainwp-backend-item-reports', GAINWP_URL . 'admin/css/admin-widgets.css', null, GAINWP_CURRENT_VERSION );

					wp_register_style( 'jquery-ui-tooltip-html', GAINWP_URL . 'common/realtime/jquery.ui.tooltip.html.css' );

					wp_enqueue_style( 'jquery-ui-tooltip-html' );

					wp_register_script( 'jquery-ui-tooltip-html', GAINWP_URL . 'common/realtime/jquery.ui.tooltip.html.js' );

					wp_register_script( 'googlecharts', 'https://www.gstatic.com/charts/loader.js', array(), null );

					wp_enqueue_script( 'gainwp-nprogress', GAINWP_URL . 'common/nprogress/nprogress.js', array( 'jquery' ), GAINWP_CURRENT_VERSION );

					wp_enqueue_script( 'gainwp-backend-dashboard-reports', GAINWP_URL . 'common/js/reports5.js', array( 'jquery', 'googlecharts', 'gainwp-nprogress', 'jquery-ui-tooltip', 'jquery-ui-core', 'jquery-ui-position', 'jquery-ui-tooltip-html' ), GAINWP_CURRENT_VERSION, true );

					/* @formatter:off */

					$datelist = array(
						'realtime' => __( "Real-Time", 'ga-in' ),
						'today' => __( "Today", 'ga-in' ),
						'yesterday' => __( "Yesterday", 'ga-in' ),
						'7daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 7 ),
						'14daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 14 ),
						'30daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 30 ),
						'90daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 90 ),
						'365daysAgo' =>  sprintf( _n( "%s Year", "%s Years", 1, 'ga-in' ), __('One', 'ga-in') ),
						'1095daysAgo' =>  sprintf( _n( "%s Year", "%s Years", 3, 'ga-in' ), __('Three', 'ga-in') ),
					);


					if ( $this->gainwp->config->options['user_api'] && ! $this->gainwp->config->options['backend_realtime_report'] ) {
						array_shift( $datelist );
					}

					wp_localize_script( 'gainwp-backend-dashboard-reports', 'gainwpItemData', array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'security' => wp_create_nonce( 'gainwp_backend_item_reports' ),
						'dateList' => $datelist,
						'reportList' => array(
							'sessions' => __( "Sessions", 'ga-in' ),
							'users' => __( "Users", 'ga-in' ),
							'organicSearches' => __( "Organic", 'ga-in' ),
							'pageviews' => __( "Page Views", 'ga-in' ),
							'visitBounceRate' => __( "Bounce Rate", 'ga-in' ),
							'locations' => __( "Location", 'ga-in' ),
							'contentpages' =>  __( "Pages", 'ga-in' ),
							'referrers' => __( "Referrers", 'ga-in' ),
							'searches' => __( "Searches", 'ga-in' ),
							'trafficdetails' => __( "Traffic", 'ga-in' ),
							'technologydetails' => __( "Technology", 'ga-in' ),
							'404errors' => __( "404 Errors", 'ga-in' ),
						),
						'i18n' => array(
							__( "A JavaScript Error is blocking plugin resources!", 'ga-in' ), //0
							__( "Traffic Mediums", 'ga-in' ),
							__( "Visitor Type", 'ga-in' ),
							__( "Search Engines", 'ga-in' ),
							__( "Social Networks", 'ga-in' ),
							__( "Sessions", 'ga-in' ),
							__( "Users", 'ga-in' ),
							__( "Page Views", 'ga-in' ),
							__( "Bounce Rate", 'ga-in' ),
							__( "Organic Search", 'ga-in' ),
							__( "Pages/Session", 'ga-in' ),
							__( "Invalid response", 'ga-in' ),
							__( "No Data", 'ga-in' ),
							__( "This report is unavailable", 'ga-in' ),
							__( "report generated by", 'ga-in' ), //14
							__( "This plugin needs an authorization:", 'ga-in' ) . ' <a href="' . menu_page_url( 'gainwp_settings', false ) . '">' . __( "authorize the plugin", 'ga-in' ) . '</a>.',
							__( "Browser", 'ga-in' ), //16
							__( "Operating System", 'ga-in' ),
							__( "Screen Resolution", 'ga-in' ),
							__( "Mobile Brand", 'ga-in' ),
							__( "REFERRALS", 'ga-in' ), //20
							__( "KEYWORDS", 'ga-in' ),
							__( "SOCIAL", 'ga-in' ),
							__( "CAMPAIGN", 'ga-in' ),
							__( "DIRECT", 'ga-in' ),
							__( "NEW", 'ga-in' ), //25
							__( "Time on Page", 'ga-in' ),
							__( "Page Load Time", 'ga-in' ),
							__( "Session Duration", 'ga-in' ),
						),
						'rtLimitPages' => $this->gainwp->config->options['ga_realtime_pages'],
						'colorVariations' => GAINWP_Tools::variations( $this->gainwp->config->options['theme_color'] ),
						'region' => $region,
						'mapsApiKey' => apply_filters( 'gainwp_maps_api_key', $this->gainwp->config->options['maps_api_key'] ),
						'language' => get_bloginfo( 'language' ),
						'viewList' => $views,
						'scope' => 'admin-widgets',
					)

					);
					/* @formatter:on */
				}
			}

			/*
			 * Posts/Pages List Styles & Scripts
			 */
			$contentstats_hooks = array( 'edit.php' );
			if ( in_array( $hook, $contentstats_hooks ) ) {
				if ( GAINWP_Tools::check_roles( $this->gainwp->config->options['access_back'] ) && $this->gainwp->config->options['backend_item_reports'] ) {

					if ( $this->gainwp->config->options['ga_target_geomap'] ) {
						$country_codes = GAINWP_Tools::get_countrycodes();
						if ( isset( $country_codes[$this->gainwp->config->options['ga_target_geomap']] ) ) {
							$region = $this->gainwp->config->options['ga_target_geomap'];
						} else {
							$region = false;
						}
					} else {
						$region = false;
					}

					wp_enqueue_style( 'gainwp-nprogress', GAINWP_URL . 'common/nprogress/nprogress.css', null, GAINWP_CURRENT_VERSION );

					wp_enqueue_style( 'gainwp-backend-item-reports', GAINWP_URL . 'admin/css/item-reports.css', null, GAINWP_CURRENT_VERSION );

					wp_enqueue_style( "wp-jquery-ui-dialog" );

					wp_register_script( 'googlecharts', 'https://www.gstatic.com/charts/loader.js', array(), null );

					wp_enqueue_script( 'gainwp-nprogress', GAINWP_URL . 'common/nprogress/nprogress.js', array( 'jquery' ), GAINWP_CURRENT_VERSION );

					wp_enqueue_script( 'gainwp-backend-item-reports', GAINWP_URL . 'common/js/reports5.js', array( 'gainwp-nprogress', 'googlecharts', 'jquery', 'jquery-ui-dialog' ), GAINWP_CURRENT_VERSION, true );

					/* @formatter:off */
					wp_localize_script( 'gainwp-backend-item-reports', 'gainwpItemData', array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'security' => wp_create_nonce( 'gainwp_backend_item_reports' ),
						'dateList' => array(
							'today' => __( "Today", 'ga-in' ),
							'yesterday' => __( "Yesterday", 'ga-in' ),
							'7daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 7 ),
							'14daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 14 ),
							'30daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 30 ),
							'90daysAgo' => sprintf( __( "Last %d Days", 'ga-in' ), 90 ),
							'365daysAgo' =>  sprintf( _n( "%s Year", "%s Years", 1, 'ga-in' ), __('One', 'ga-in') ),
							'1095daysAgo' =>  sprintf( _n( "%s Year", "%s Years", 3, 'ga-in' ), __('Three', 'ga-in') ),
						),
						'reportList' => array(
							'uniquePageviews' => __( "Unique Views", 'ga-in' ),
							'users' => __( "Users", 'ga-in' ),
							'organicSearches' => __( "Organic", 'ga-in' ),
							'pageviews' => __( "Page Views", 'ga-in' ),
							'visitBounceRate' => __( "Bounce Rate", 'ga-in' ),
							'locations' => __( "Location", 'ga-in' ),
							'referrers' => __( "Referrers", 'ga-in' ),
							'searches' => __( "Searches", 'ga-in' ),
							'trafficdetails' => __( "Traffic", 'ga-in' ),
							'technologydetails' => __( "Technology", 'ga-in' ),
						),
						'i18n' => array(
							__( "A JavaScript Error is blocking plugin resources!", 'ga-in' ), //0
							__( "Traffic Mediums", 'ga-in' ),
							__( "Visitor Type", 'ga-in' ),
							__( "Social Networks", 'ga-in' ),
							__( "Search Engines", 'ga-in' ),
							__( "Unique Views", 'ga-in' ),
							__( "Users", 'ga-in' ),
							__( "Page Views", 'ga-in' ),
							__( "Bounce Rate", 'ga-in' ),
							__( "Organic Search", 'ga-in' ),
							__( "Pages/Session", 'ga-in' ),
							__( "Invalid response", 'ga-in' ),
							__( "No Data", 'ga-in' ),
							__( "This report is unavailable", 'ga-in' ),
							__( "report generated by", 'ga-in' ), //14
							__( "This plugin needs an authorization:", 'ga-in' ) . ' <a href="' . menu_page_url( 'gainwp_settings', false ) . '">' . __( "authorize the plugin", 'ga-in' ) . '</a>.',
							__( "Browser", 'ga-in' ), //16
							__( "Operating System", 'ga-in' ),
							__( "Screen Resolution", 'ga-in' ),
							__( "Mobile Brand", 'ga-in' ), //19
							__( "Future Use", 'ga-in' ),
							__( "Future Use", 'ga-in' ),
							__( "Future Use", 'ga-in' ),
							__( "Future Use", 'ga-in' ),
							__( "Future Use", 'ga-in' ),
							__( "Future Use", 'ga-in' ), //25
							__( "Time on Page", 'ga-in' ),
							__( "Page Load Time", 'ga-in' ),
							__( "Exit Rate", 'ga-in' ),
						),
						'colorVariations' => GAINWP_Tools::variations( $this->gainwp->config->options['theme_color'] ),
						'region' => $region,
						'mapsApiKey' => apply_filters( 'gainwp_maps_api_key', $this->gainwp->config->options['maps_api_key'] ),
						'language' => get_bloginfo( 'language' ),
						'viewList' => false,
						'scope' => 'admin-item',
						)
					);
					/* @formatter:on */
				}
			}

			/*
			 * Settings Styles & Scripts
			 */
			$settings_hooks = array( '_page_gainwp_settings', '_page_gainwp_backend_settings', '_page_gainwp_frontend_settings', '_page_gainwp_tracking_settings', '_page_gainwp_errors_debugging' );

			if ( in_array( $new_hook, $settings_hooks ) ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker-script-handle', plugins_url( 'js/wp-color-picker-script.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
				wp_enqueue_script( 'gainwp-settings', plugins_url( 'js/settings.js', __FILE__ ), array( 'jquery' ), GAINWP_CURRENT_VERSION, true );
			}
		}

		/**
		 * Add "Settings" link in Plugins List
		 *
		 * @param
		 *            $links
		 * @return array
		 */
		public function settings_link( $links ) {
			$settings_link = '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=gainwp_settings' ) ) . '">' . __( "Settings", 'ga-in' ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 *  Add an admin notice after a manual or atuomatic update
		 */
		function admin_notice() {
			$currentScreen = get_current_screen();

			if ( ! current_user_can( 'manage_options' ) || strpos( $currentScreen->base, '_gainwp_' ) === false ) {
				return;
			}

			if ( get_option( 'gainwp_got_updated' ) ) :
				?>
<div id="gainwp-notice" class="notice is-dismissible">
	<p><?php echo sprintf( __('Google Analytics for WP has been updated to version %s.', 'ga-in' ), GAINWP_CURRENT_VERSION).' '.sprintf( __('For details, check out %1$s.', 'ga-in' ), sprintf(' <a href="https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_notice&utm_medium=link&utm_content=release_notice&utm_campaign=gainwp">%s</a>', __('the plugin documentation', 'ga-in') ) ); ?></p>
</div>

			<?php
			endif;
		}
	}
}

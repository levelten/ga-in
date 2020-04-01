<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Config' ) ) {

	final class GAINWP_Config {

		public $options;

		public function __construct() {
			// Rename old option keys
			$this->option_keys_rename(); // v5.2
			                             // Get plugin options
			$this->get_plugin_options();
			// Automatic updates
			add_filter( 'auto_update_plugin', array( $this, 'automatic_update' ), 10, 2 );
			// Provide language packs for all available Network languages
			if ( is_multisite() ) {
				add_filter( 'plugins_update_check_locales', array( $this, 'translation_updates' ), 10, 1 );
			}
		}

		public function get_major_version( $version ) {
			$exploded_version = explode( '.', $version );
			if ( isset( $exploded_version[2] ) ) {
				return $exploded_version[0] . '.' . $exploded_version[1] . '.' . $exploded_version[2];
			} else {
				return $exploded_version[0] . '.' . $exploded_version[1] . '.0';
			}
		}

		public function automatic_update( $update, $item ) {
			$item = (array) $item;
			if ( is_multisite() && ! is_main_site() ) {
				return;
			}
			if ( ! isset( $item['new_version'] ) || ! isset( $item['plugin'] ) || ! $this->options['automatic_updates_minorversion'] ) {
				return $update;
			}
			if ( isset( $item['slug'] ) && 'ga-in' == $item['slug'] ) {
				// Only when a minor update is available
				if ( $this->get_major_version( GAINWP_CURRENT_VERSION ) == $this->get_major_version( $item['new_version'] ) ) {
					return ( $this->get_major_version( GAINWP_CURRENT_VERSION ) == $this->get_major_version( $item['new_version'] ) );
				}
			}
			return $update;
		}

		public function translation_updates( $locales ) {
			$languages = get_available_languages();
			return array_values( $languages );
		}

		// Validates data before storing
		private function validate_data( $options ) {
			/* @formatter:off */
			$numerics = array(
			  'ga_realtime_pages',
        'ga_enhanced_links',
        'ga_crossdomain_tracking',
        'ga_author_dimindex',
        'ga_author_login_dimindex',
        'ga_category_dimindex',
        'ga_tag_dimindex',
        'ga_user_dimindex',
        'ga_pubyear_dimindex',
        'ga_pubyearmonth_dimindex',
        'tm_author_var',
        'tm_author_login_var',
        'tm_category_var',
        'tm_tag_var',
        'tm_user_var',
        'tm_pubyear_var',
        'tm_pubyearmonth_var',
        'ga_aff_tracking',
        'amp_tracking_analytics',
        'amp_tracking_clientidapi',
        'amp_tracking_tagmanager',
        'optimize_tracking',
        'optimize_pagehiding',
        'trackingcode_infooter',
        'trackingevents_infooter',
        'ga_formsubmit_tracking',
        'superadmin_tracking',
        'ga_pagescrolldepth_tracking',
        'tm_pagescrolldepth_tracking',
        'ga_speed_samplerate',
        'ga_user_samplerate',
        'ga_event_precision',
        'with_endpoint',
        'backend_realtime_report',
        'ga_optout',
        'ga_dnt_optout',
        'tm_optout',
        'tm_dnt_optout',
        'ga_with_gtag',
			);
			foreach ( $numerics as $key ) {
				if ( isset( $options[$key] ) ) {
					$options[$key] = (int) $options[$key];
				}
			}

			$texts = array( 'ga_crossdomain_list',
							'client_id',
							'client_secret',
							'theme_color',
							'ga_target_geomap',
							'ga_cookiedomain',
							'ga_cookiename',
							'pagetitle_404',
							'maps_api_key',
							'web_containerid',
							'amp_containerid',
							'optimize_containerid',
							'ga_event_downloads',
							'ga_event_affiliates',
							'ecommerce_mode',
							'tracking_type',
			);
			foreach ( $texts as $key ) {
				if ( isset( $options[$key] ) ) {
					$options[$key] = trim (sanitize_text_field( $options[$key] ));
				}
			}
			/* @formatter:on */

			if ( isset( $options['ga_event_downloads'] ) && empty( $options['ga_event_downloads'] ) ) {
				$options['ga_event_downloads'] = 'zip|mp3*|mpe*g|pdf|docx*|pptx*|xlsx*|rar*';
			}

			if ( isset( $options['pagetitle_404'] ) && empty( $options['pagetitle_404'] ) ) {
				$options['pagetitle_404'] = 'Page Not Found';
			}

			if ( isset( $options['ga_event_affiliates'] ) && empty( $options['ga_event_affiliates'] ) ) {
				$options['ga_event_affiliates'] = '/out/';
			}

			if ( isset( $options['ga_speed_samplerate'] ) && ( $options['ga_speed_samplerate'] < 1 || $options['ga_speed_samplerate'] > 100 ) ) {
				$options['ga_speed_samplerate'] = 1;
			}

			if ( isset( $options['ga_user_samplerate'] ) && ( $options['ga_user_samplerate'] < 1 || $options['ga_user_samplerate'] > 100 ) ) {
				$options['ga_user_samplerate'] = 100;
			}

			if ( isset( $options['ga_cookieexpires'] ) && $options['ga_cookieexpires'] ) { // v4.9
				$options['ga_cookieexpires'] = (int) $options['ga_cookieexpires'];
			}

			return $options;
		}

		public function set_plugin_options( $network_settings = false ) {
			// Handle Network Mode
			$options = $this->options;
			$get_network_options = get_site_option( 'gainwp_network_options' );
			$old_network_options = (array) json_decode( $get_network_options );

			if ( is_multisite() ) {
				if ( $network_settings ) { // Retrieve network options, clear blog options, store both to db
					$network_options['token'] = $this->options['token'];
					$options['token'] = '';
					if ( is_network_admin() ) {
						$network_options['ga_profiles_list'] = $this->options['ga_profiles_list'];
						$options['ga_profiles_list'] = array();
						$network_options['client_id'] = $this->options['client_id'];
						$options['client_id'] = '';
						$network_options['client_secret'] = $this->options['client_secret'];
						$options['client_secret'] = '';
						$network_options['user_api'] = $this->options['user_api'];
						$options['user_api'] = 0;
						$network_options['network_mode'] = $this->options['network_mode'];
						$network_options['superadmin_tracking'] = $this->options['superadmin_tracking'];
						$network_options['automatic_updates_minorversion'] = $this->options['automatic_updates_minorversion'];
						unset( $options['network_mode'] );
						if ( isset( $this->options['network_tableid'] ) ) {
							$network_options['network_tableid'] = $this->options['network_tableid'];
							unset( $options['network_tableid'] );
						}
					}
					$merged_options = array_merge( $old_network_options, $network_options );
					update_site_option( 'gainwp_network_options', json_encode( $this->validate_data( $merged_options ) ) );
				}
			}
			update_option( 'gainwp_options', json_encode( $this->validate_data( $options ) ) );
		}

		private function get_plugin_options() {
			/*
			 * Get plugin options
			 */
			global $blog_id;

			if ( ! get_option( 'gainwp_options' ) ) {
				GAINWP_Install::install();
			}
			$this->options = (array) json_decode( get_option( 'gainwp_options' ) );
			// Maintain Compatibility
			$this->maintain_compatibility();
			// Handle Network Mode
			if ( is_multisite() ) {
				$get_network_options = get_site_option( 'gainwp_network_options' );
				$network_options = (array) json_decode( $get_network_options );
				if ( isset( $network_options['network_mode'] ) && ( $network_options['network_mode'] ) ) {
					if ( ! is_network_admin() && ! empty( $network_options['ga_profiles_list'] ) && isset( $network_options['network_tableid']->$blog_id ) ) {
						$network_options['ga_profiles_list'] = array( 0 => GAINWP_Tools::get_selected_profile( $network_options['ga_profiles_list'], $network_options['network_tableid']->$blog_id ) );
						$network_options['tableid_jail'] = $network_options['ga_profiles_list'][0][1];
					}
					$this->options = array_merge( $this->options, $network_options );
				} else {
					$this->options['network_mode'] = 0;
				}
			}
		}

		private function maintain_compatibility() {
			$flag = false;

			$prevver = get_option( 'gainwp_version' );
			if ( $prevver && GAINWP_CURRENT_VERSION != $prevver ) {
				$flag = true;
				update_option( 'gainwp_version', GAINWP_CURRENT_VERSION );
				update_option( 'gainwp_got_updated', true );
				GAINWP_Tools::clear_cache();
				GAINWP_Tools::delete_cache( 'last_error' );
				if ( is_multisite() ) { // Cleanup errors and cookies on the entire network
					foreach ( GAINWP_Tools::get_sites( array( 'number' => apply_filters( 'gainwp_sites_limit', 100 ) ) ) as $blog ) {
						switch_to_blog( $blog['blog_id'] );
						GAINWP_Tools::delete_cache( 'gapi_errors' );
						restore_current_blog();
					}
				} else {
					GAINWP_Tools::delete_cache( 'gapi_errors' );
				}

				// Enable GAINWP EndPoint for those updating from a version lower than 5.2, introduced in GAINWP v5.3
				if (version_compare( $prevver, '5.2', '<' ) ) {
					$this->options['with_endpoint'] = 2;
				}
			}

			if ( isset( $this->options['item_reports'] ) ) { // v4.8
				$this->options['backend_item_reports'] = $this->options['item_reports'];
			}
			if ( isset( $this->options['ga_dash_frontend_stats'] ) ) { // v4.8
				$this->options['frontend_item_reports'] = $this->options['ga_dash_frontend_stats'];
			}

			/* @formatter:off */
			$zeros = array( 	'ga_enhanced_links',
        'network_mode',
        'ga_enhanced_excludesa',
        'ga_remarketing',
        'ga_event_bouncerate',
        'ga_author_dimindex',
        'ga_author_login_dimindex',
        'ga_tag_dimindex',
        'ga_category_dimindex',
        'ga_user_dimindex',
        'ga_pubyear_dimindex',
        'ga_pubyearmonth_dimindex',
        'tm_author_var', // v5.0
        'tm_author_login_var', // v5.4.5
        'tm_category_var', // v5.0
        'tm_tag_var', // v5.0
        'tm_user_var', // v5.0
        'tm_pubyear_var', // v5.0
        'tm_pubyearmonth_var', // v5.0
        'ga_crossdomain_tracking',
        'api_backoff',  // v4.8.1.3
        'ga_aff_tracking',
        'ga_hash_tracking',
        'switch_profile', // V4.7
        'amp_tracking_analytics', //v5.0
        'amp_tracking_clientidapi', //v5.1.2
        'optimize_tracking', //v5.0
        'optimize_pagehiding', //v5.0
        'amp_tracking_tagmanager', //v5.0
        'trackingcode_infooter', //v5.0
        'trackingevents_infooter', //v5.0
        'ga_formsubmit_tracking', //v5.0
        'superadmin_tracking', //v5.0
        'ga_pagescrolldepth_tracking', //v5.0
        'tm_pagescrolldepth_tracking', //v5.0
        'ga_event_precision', //v5.1.1.1
        'ga_force_ssl', //v5.1.2
        'with_endpoint', //v5.2
        'backend_realtime_report', //v5.2
        'ga_optout', //v5.2.3
        'ga_dnt_optout', //v5.2.3
        'ga_with_gtag', //v5.3
        'frontend_item_reports',
        'tm_optout', //v5.3.1.2
        'tm_dnt_optout', //v5.3.1.2
			);
			foreach ( $zeros as $key ) {
				if ( ! isset( $this->options[$key] ) ) {
					$this->options[$key] = 0;
					$flag = true;
				}
			}

			if ( isset($this->options['ga_dash_tracking']) && 0 == $this->options['ga_dash_tracking'] ) { // v5.0.1
				$this->options['tracking_type'] = 'disabled';
				$flag = true;
			}

			$unsets = array( 	'ga_dash_jailadmins', // v4.7
								'ga_tracking_code',
								'ga_dash_tableid', // v4.9
								'ga_dash_frontend_keywords', // v4.8
								'ga_dash_apikey', // v4.9.1.3
								'ga_dash_default_metric', // v4.8.1
								'ga_dash_default_dimension', // v4.8.1
								'ga_dash_adsense', // v5.0
								'ga_dash_frontend_stats', // v4.8
								'item_reports', // v4.8
								'ga_dash_tracking', // v5.0
								'ga_dash_cachetime', // v5.2
								'ga_dash_default_ua', // v5.2
								'ga_dash_hidden', // v5.2
			);
			foreach ( $unsets as $key ) {
				if ( isset( $this->options[$key] ) ) {
					unset( $this->options[$key] );
					$flag = true;
				}
			}

			$empties = array( 	'ga_crossdomain_list',
								'ga_cookiedomain',  // v4.9.4
								'ga_cookiename',  // v4.9.4
								'ga_cookieexpires',  // v4.9.4
								'maps_api_key',  // v4.9.4
								'web_containerid', // v5.0
								'amp_containerid', // v5.0
								'optimize_containerid', // v5.0
			);
			foreach ( $empties as $key ) {
				if ( ! isset( $this->options[$key] ) ) {
					$this->options[$key] = '';
					$flag = true;
				}
			}

			$ones = array( 	'ga_speed_samplerate',
							'automatic_updates_minorversion',
							'backend_item_reports', // v4.8
							'dashboard_widget', // v4.7
			);
			foreach ( $ones as $key ) {
				if ( ! isset( $this->options[$key] ) ) {
					$this->options[$key] = 1;
					$flag = true;
				}
			}

			$arrays = array( 	'access_front',
								'access_back',
								'ga_profiles_list',
								'track_exclude',
			);
			foreach ( $arrays as $key ) {
				if ( ! is_array( $this->options[$key] ) ) {
					$this->options[$key] = array();
					$flag = true;
				}
			}
			if ( empty( $this->options['access_front'] ) ) {
				$this->options['access_front'][] = 'administrator';
			}
			if ( empty( $this->options['access_back'] ) ) {
				$this->options['access_back'][] = 'administrator';
			}
			/* @formatter:on */

			if ( ! isset( $this->options['ga_event_affiliates'] ) ) {
				$this->options['ga_event_affiliates'] = '/out/';
				$flag = true;
			}

			if ( ! isset( $this->options['ga_user_samplerate'] ) ) {
				$this->options['ga_user_samplerate'] = 100;
			}

			if ( ! isset( $this->options['ga_event_downloads'] ) ) {
				$this->options['ga_event_downloads'] = 'zip|mp3*|mpe*g|pdf|docx*|pptx*|xlsx*|rar*';
				$flag = true;
			}

			if ( ! isset( $this->options['pagetitle_404'] ) ) { // v4.9.4
				$this->options['pagetitle_404'] = 'Page Not Found';
				$flag = true;
			}

			if ( ! isset( $this->options['ecommerce_mode'] ) ) { // v5.0
				$this->options['ecommerce_mode'] = 'disabled';
				$flag = true;
			}

			if ( isset( $this->options['ga_dash_tracking'] ) && 'classic' == $this->options['ga_dash_tracking'] ) { // v5.0
				$this->options['tracking_type'] = 'universal';
				$flag = true;
			}

			if ( $flag ) {
				$this->set_plugin_options( false );
			}
		}

		private function option_keys_rename() {

			/* @formatter:off */
			$batch = array( 	'ga_dash_token' => 'token',
								'ga_dash_clientid' => 'client_id',
								'ga_dash_clientsecret' => 'client_secret',
								'ga_dash_access_front' => 'access_front',
								'ga_dash_access_back' => 'access_back',
								'ga_dash_tableid_jail' => 'tableid_jail',
								'ga_dash_tracking_type' => 'tracking_type',
								'ga_dash_userapi' => 'user_api',
								'ga_dash_network' => 'network_mode',
								'ga_dash_tableid_network' => 'network_tableid',
								'ga_dash_anonim' => 'ga_anonymize_ip',
								'ga_dash_profile_list' => 'ga_profiles_list',
								'ga_dash_remarketing' => 'ga_remarketing',
								'ga_dash_excludesa' => 'superadmin_tracking',
								'ga_track_exclude' => 'track_exclude',
								'ga_dash_style' => 'theme_color',
			);
			/* @formatter:on */

			if ( is_multisite() ) {
				$options = get_site_option( 'gadash_network_options' );
				if ( $options ) {
					$options = (array) json_decode( $options );
					$options = GAINWP_Tools::array_keys_rename( $options, $batch );
					update_site_option( 'gainwp_network_options', json_encode( $this->validate_data( $options ) ) );
					delete_site_option( 'gadash_network_options' );
				}
			}

			$options = get_option( 'gadash_options' );
			if ( $options ) {
				$options = (array) json_decode( $options );
				$options = GAINWP_Tools::array_keys_rename( $options, $batch );
				update_option( 'gainwp_options', json_encode( $this->validate_data( $options ) ) );
				delete_option( 'gadash_options' );
			}
		}
	}
}


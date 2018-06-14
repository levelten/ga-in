<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

class GAINWP_Install {

	public static function install() {
		if ( ! get_option( 'ga_dash_token' ) ) {
			$options = array();
			$options['client_id'] = '';
			$options['client_secret'] = '';
			$options['access_front'][] = 'administrator';
			$options['access_back'][] = 'administrator';
			$options['tableid_jail'] = '';
			$options['tracking_id'] = '';
			$options['theme_color'] = '#1e73be';
			$options['switch_profile'] = 0;
			$options['tracking_type'] = 'universal';
			$options['ga_anonymize_ip'] = 0;
			$options['user_api'] = 0;
			$options['ga_event_tracking'] = 0;
			$options['ga_event_downloads'] = 'zip|mp3*|mpe*g|pdf|docx*|pptx*|xlsx*|rar*';
			$options['track_exclude'] = array();
			$options['ga_target_geomap'] = '';
			$options['ga_realtime_pages'] = 10;
			$options['token'] = '';
			$options['ga_profiles_list'] = array();
			$options['ga_tracking_code'] = '';
			$options['ga_enhanced_links'] = 0;
			$options['ga_remarketing'] = 0;
			$options['network_mode'] = 0;
			$options['ga_speed_samplerate'] = 1;
			$options['ga_user_samplerate'] = 100;
			$options['ga_event_bouncerate'] = 0;
			$options['ga_crossdomain_tracking'] = 0;
			$options['ga_crossdomain_list'] = '';
			$options['ga_author_dimindex'] = 0;
			$options['ga_category_dimindex'] = 0;
			$options['ga_tag_dimindex'] = 0;
			$options['ga_user_dimindex'] = 0;
			$options['ga_pubyear_dimindex'] = 0;
			$options['ga_pubyearmonth_dimindex'] = 0;
			$options['ga_aff_tracking'] = 0;
			$options['ga_event_affiliates'] = '/out/';
			$options['automatic_updates_minorversion'] = 1;
			$options['backend_item_reports'] = 1;
			$options['backend_realtime_report'] = 0;
			$options['frontend_item_reports'] = 0;
			$options['dashboard_widget'] = 1;
			$options['api_backoff'] = 0;
			$options['ga_cookiedomain'] = '';
			$options['ga_cookiename'] = '';
			$options['ga_cookieexpires'] = '';
			$options['pagetitle_404'] = 'Page Not Found';
			$options['maps_api_key'] = '';
			$options['tm_author_var'] = 0;
			$options['tm_category_var'] = 0;
			$options['tm_tag_var'] = 0;
			$options['tm_user_var'] = 0;
			$options['tm_pubyear_var'] = 0;
			$options['tm_pubyearmonth_var'] = 0;
			$options['web_containerid'] = '';
			$options['amp_containerid'] = '';
			$options['amp_tracking_tagmanager'] = 0;
			$options['amp_tracking_analytics'] = 0;
			$options['amp_tracking_clientidapi'] = 0;
			$options['trackingcode_infooter'] = 0;
			$options['trackingevents_infooter'] = 0;
			$options['ecommerce_mode'] = 'disabled';
			$options['ga_formsubmit_tracking'] = 0;
			$options['optimize_tracking'] = 0;
			$options['optimize_containerid'] = '';
			$options['optimize_pagehiding'] = '';
			$options['superadmin_tracking'] = 0;
			$options['ga_pagescrolldepth_tracking'] = 0;
			$options['tm_pagescrolldepth_tracking'] = 0;
			$options['ga_event_precision'] = 0;
			$options['ga_force_ssl'] = 0;
			$options['with_endpoint'] = 1;
			$options['ga_optout'] = 0;
			$options['ga_dnt_optout'] = 0;
			$options['tm_optout'] = 0;
			$options['tm_dnt_optout'] = 0;
			$options['ga_with_gtag'] = 0;
		} else {
			$options = array();
			$options['client_id'] = get_option( 'ga_dash_clientid' );
			$options['client_secret'] = get_option( 'ga_dash_clientsecret' );
			$options['access_front'][] = 'administrator';
			$options['access_back'][] = 'administrator';
			$options['tableid_jail'] = get_option( 'ga_dash_tableid_jail' );
			$options['frontend_item_reports'] = get_option( 'ga_dash_frontend' );
			$options['theme_color'] = '#1e73be';
			$options['switch_profile'] = get_option( 'ga_dash_jailadmins' );
			$options['tracking_type'] = get_option( 'ga_dash_tracking_type' );
			$options['ga_anonymize_ip'] = get_option( 'ga_dash_anonim' );
			$options['user_api'] = get_option( 'ga_dash_userapi' );
			$options['ga_event_tracking'] = get_option( 'ga_event_tracking' );
			$options['ga_event_downloads'] = get_option( 'ga_event_downloads' );
			$options['track_exclude'] = array();
			$options['ga_target_geomap'] = get_option( 'ga_target_geomap' );
			$options['ga_realtime_pages'] = get_option( 'ga_realtime_pages' );
			$options['token'] = get_option( 'ga_dash_token' );
			$options['ga_profiles_list'] = get_option( 'ga_dash_profile_list' );
			$options['ga_enhanced_links'] = 0;
			$options['ga_remarketing'] = 0;
			$options['network_mode'] = 0;
			$options['ga_event_bouncerate'] = 0;
			$options['ga_crossdomain_tracking'] = 0;
			$options['ga_crossdomain_list'] = '';
			$options['ga_author_dimindex'] = 0;
			$options['ga_category_dimindex'] = 0;
			$options['ga_tag_dimindex'] = 0;
			$options['ga_user_dimindex'] = 0;
			$options['ga_pubyear_dimindex'] = 0;
			$options['ga_pubyearmonth_dimindex'] = 0;
			$options['ga_event_affiliates'] = '/out/';
			$options['ga_aff_tracking'] = 0;
			$options['automatic_updates_minorversion'] = 1;
			$options['backend_item_reports'] = 1;
			$options['backend_realtime_report'] = 0;
			$options['dashboard_widget'] = 1;
			$options['api_backoff'] = 0;
			$options['ga_cookiedomain'] = '';
			$options['ga_cookiename'] = '';
			$options['ga_cookieexpires'] = '';
			$options['pagetitle_404'] = 'Page Not Found';
			$options['maps_api_key'] = '';
			$options['tm_author_var'] = 0;
			$options['tm_category_var'] = 0;
			$options['tm_tag_var'] = 0;
			$options['tm_user_var'] = 0;
			$options['tm_pubyear_var'] = 0;
			$options['tm_pubyearmonth_var'] = 0;
			$options['web_containerid'] = '';
			$options['amp_containerid'] = '';
			$options['amp_tracking_tagmanager'] = 0;
			$options['amp_tracking_analytics'] = 0;
			$options['amp_tracking_clientidapi'] = 0;
			$options['trackingcode_infooter'] = 0;
			$options['trackingevents_infooter'] = 0;
			$options['ecommerce_mode'] = 'disabled';
			$options['ga_formsubmit_tracking'] = 0;
			$options['optimize_tracking'] = 0;
			$options['optimize_containerid'] = '';
			$options['optimize_pagehiding'] = '';
			$options['superadmin_tracking'] = 0;
			$options['ga_pagescrolldepth_tracking'] = 0;
			$options['tm_pagescrolldepth_tracking'] = 0;
			$options['ga_speed_samplerate'] = 1;
			$options['ga_user_samplerate'] = 100;
			$options['ga_event_precision'] = 0;
			$options['ga_force_ssl'] = 0;
			$options['with_endpoint'] = 1;
			$options['ga_optout'] = 0;
			$options['ga_dnt_optout'] = 0;
			$options['tm_optout'] = 0;
			$options['tm_dnt_optout'] = 0;
			$options['ga_with_gtag'] = 0;

			delete_option( 'ga_dash_clientid' );
			delete_option( 'ga_dash_clientsecret' );
			delete_option( 'ga_dash_access' );
			delete_option( 'ga_dash_access_front' );
			delete_option( 'ga_dash_access_back' );
			delete_option( 'ga_dash_tableid_jail' );
			delete_option( 'ga_dash_frontend' );
			delete_option( 'ga_dash_style' );
			delete_option( 'ga_dash_jailadmins' );

			delete_option( 'ga_dash_tracking' );
			delete_option( 'ga_dash_tracking_type' );
			delete_option( 'ga_dash_anonim' );
			delete_option( 'ga_dash_userapi' );
			delete_option( 'ga_event_tracking' );
			delete_option( 'ga_event_downloads' );
			delete_option( 'track_exclude' );
			delete_option( 'ga_target_geomap' );
			delete_option( 'ga_realtime_pages' );
			delete_option( 'ga_dash_token' );
			delete_option( 'ga_dash_refresh_token' );
			delete_option( 'ga_dash_profile_list' );
		}
		add_option( 'gainwp_options', json_encode( $options ) );
	}
}

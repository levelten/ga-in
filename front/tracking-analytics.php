<?php
/**
 * Copyright 2017 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Tracking_Analytics_Base' ) ) {

	class GAINWP_Tracking_Analytics_Base {

		protected $gainwp;

		protected $uaid;

		public function __construct() {
			$this->gainwp = GAINWP();

			$profile = GAINWP_Tools::get_selected_profile( $this->gainwp->config->options['ga_profiles_list'], $this->gainwp->config->options['tableid_jail'] );

			$this->uaid = '';

			if (!empty($profile[2])) {
				$this->uaid = esc_html( $profile[2] );
			}
			else if (!empty($this->gainwp->config->options['tracking_id'])) {
				$this->uaid = esc_html( $this->gainwp->config->options['tracking_id'] );
			}


		}

		protected function build_custom_dimensions() {
			$custom_dimensions = array();

			if ( $this->gainwp->config->options['ga_author_dimindex'] && ( is_single() || is_page() ) ) {
				global $post;
				$author_id = $post->post_author;
				$author_name = get_the_author_meta( 'display_name', $author_id );
				$index = (int) $this->gainwp->config->options['ga_author_dimindex'];
				$custom_dimensions[$index] = esc_attr( $author_name );
			}

      if ( $this->gainwp->config->options['ga_author_login_dimindex'] && ( is_single() || is_page() ) ) {
        global $post;
        $author_id = $post->post_author;
        $author_name = get_the_author_meta( 'user_login', $author_id );
        $index = (int) $this->gainwp->config->options['ga_author_login_dimindex'];
        $custom_dimensions[$index] = esc_attr( $author_name );
      }

			if ( $this->gainwp->config->options['ga_pubyear_dimindex'] && is_single() ) {
				global $post;
				$date = get_the_date( 'Y', $post->ID );
				$index = (int) $this->gainwp->config->options['ga_pubyear_dimindex'];
				$custom_dimensions[$index] = (int) $date;
			}

			if ( $this->gainwp->config->options['ga_pubyearmonth_dimindex'] && is_single() ) {
				global $post;
				$date = get_the_date( 'Y-m', $post->ID );
				$index = (int) $this->gainwp->config->options['ga_pubyearmonth_dimindex'];
				$custom_dimensions[$index] = esc_attr( $date );
			}

			if ( $this->gainwp->config->options['ga_category_dimindex'] && is_category() ) {
				$fields = array();
				$index = (int) $this->gainwp->config->options['ga_category_dimindex'];
				$custom_dimensions[$index] = esc_attr( single_tag_title( '', false ) );
			}

			if ( $this->gainwp->config->options['ga_category_dimindex'] && is_single() ) {
				global $post;
				$categories = get_the_category( $post->ID );
				foreach ( $categories as $category ) {
					$index = (int) $this->gainwp->config->options['ga_category_dimindex'];
					$custom_dimensions[$index] = esc_attr( $category->name );
					break;
				}
			}

			if ( $this->gainwp->config->options['ga_tag_dimindex'] && is_single() ) {
				global $post;
				$fields = array();
				$post_tags_list = '';
				$post_tags_array = get_the_tags( $post->ID );
				if ( $post_tags_array ) {
					foreach ( $post_tags_array as $tag ) {
						$post_tags_list .= $tag->name . ', ';
					}
				}
				$post_tags_list = rtrim( $post_tags_list, ', ' );
				if ( $post_tags_list ) {
					$index = (int) $this->gainwp->config->options['ga_tag_dimindex'];
					$custom_dimensions[$index] = esc_attr( $post_tags_list );
				}
			}

			if ( $this->gainwp->config->options['ga_user_dimindex'] ) {
				$fields = array();
				$index = (int) $this->gainwp->config->options['ga_user_dimindex'];
				$custom_dimensions[$index] = is_user_logged_in() ? 'registered' : 'guest';
			}

			return $custom_dimensions;
		}

		protected function is_event_tracking( $opt, $with_pagescrolldepth = true ) {
			if ( $this->gainwp->config->options['ga_event_tracking'] || $this->gainwp->config->options['ga_aff_tracking'] || $this->gainwp->config->options['ga_hash_tracking'] || $this->gainwp->config->options['ga_formsubmit_tracking'] ) {
				return true;
			}

			if ( $this->gainwp->config->options['ga_pagescrolldepth_tracking'] && $with_pagescrolldepth ) {
				return true;
			}
			return false;
		}
	}
}

if ( ! class_exists( 'GAINWP_Tracking_Analytics_Common' ) ) {

	class GAINWP_Tracking_Analytics_Common extends GAINWP_Tracking_Analytics_Base {

		protected $commands;

		public function __construct() {
			parent::__construct();

			$this->load_scripts();

			if ( $this->gainwp->config->options['optimize_tracking'] && $this->gainwp->config->options['optimize_pagehiding'] && $this->gainwp->config->options['optimize_containerid'] ) {
				add_action( 'wp_head', array( $this, 'optimize_output' ), 99 );
			}
		}

		/**
		 * Styles & Scripts load
		 */
		private function load_scripts() {
			if ( $this->is_event_tracking( true ) ) {

				$root_domain = GAINWP_Tools::get_root_domain();

				if (GAINWP_DEBUG_JS) {
          wp_enqueue_script( 'gainwp-tracking-analytics-events', GAINWP_URL . 'front/js/tracking-analytics-events.js', array( 'jquery' ), GAINWP_CURRENT_VERSION, $this->gainwp->config->options['trackingevents_infooter'] );
        }
				else {
          wp_enqueue_script( 'gainwp-tracking-analytics-events', GAINWP_URL . 'front/js/tracking-analytics-events.min.js', array( 'jquery' ), GAINWP_CURRENT_VERSION, $this->gainwp->config->options['trackingevents_infooter'] );
        }

				if ( $this->gainwp->config->options['ga_pagescrolldepth_tracking'] ) {
				  if (GAINWP_DEBUG_JS) {
            wp_enqueue_script( 'gainwp-pagescrolldepth-tracking', GAINWP_URL . 'front/js/tracking-scrolldepth.js', array( 'jquery' ), GAINWP_CURRENT_VERSION, $this->gainwp->config->options['trackingevents_infooter'] );
          }
				  else {
            wp_enqueue_script( 'gainwp-pagescrolldepth-tracking', GAINWP_URL . 'front/js/tracking-scrolldepth.min.js', array( 'jquery' ), GAINWP_CURRENT_VERSION, $this->gainwp->config->options['trackingevents_infooter'] );
          }

				}

				/* @formatter:off */
				wp_localize_script( 'gainwp-tracking-analytics-events', 'gainwpUAEventsData', array(
					'options' => array(
						'event_tracking' => $this->gainwp->config->options['ga_event_tracking'],
						'event_downloads' => esc_js($this->gainwp->config->options['ga_event_downloads']),
						'event_bouncerate' => $this->gainwp->config->options['ga_event_bouncerate'],
						'aff_tracking' => $this->gainwp->config->options['ga_aff_tracking'],
						'event_affiliates' =>  esc_js($this->gainwp->config->options['ga_event_affiliates']),
						'hash_tracking' =>  $this->gainwp->config->options ['ga_hash_tracking'],
						'root_domain' => $root_domain,
						'event_timeout' => apply_filters( 'gainwp_analyticsevents_timeout', 100 ),
						'event_precision' => $this->gainwp->config->options['ga_event_precision'],
						'event_formsubmit' =>  $this->gainwp->config->options ['ga_formsubmit_tracking'],
						'ga_pagescrolldepth_tracking' => $this->gainwp->config->options['ga_pagescrolldepth_tracking'],
						'ga_with_gtag' => $this->gainwp->config->options ['ga_with_gtag'],
					),
				)
				);
				/* @formatter:on */
			}
		}

		/**
		 * Outputs the Google Optimize tracking code
		 */
		public function optimize_output() {
			GAINWP_Tools::load_view( 'front/views/optimize-code.php', array( 'containerid' => $this->gainwp->config->options['optimize_containerid'] ) );
		}

		/**
		 * Sanitizes the output of commands in the tracking code
		 * @param string $value
		 * @return string
		 */
		protected function filter( $value, $is_dim = false ) {
			if ( 'true' == $value || 'false' == $value || ( is_numeric( $value ) && ! $is_dim ) ) {
				return $value;
			}

			if ( substr( $value, 0, 1 ) == '[' && substr( $value, - 1 ) == ']' || substr( $value, 0, 1 ) == '{' && substr( $value, - 1 ) == '}' ) {
				return $value;
			}

			return "'" . $value . "'";
		}

		/**
		 * Retrieves the commands
		 */
		public function get() {
			return $this->commands;
		}

		/**
		 * Stores the commands
		 * @param array $commands
		 */
		public function set( $commands ) {
			$this->commands = $commands;
		}

		/**
		 * Formats the command before being added to the commands
		 * @param string $command
		 * @param array $fields
		 * @param string $fieldsobject
		 * @return array
		 */
		public function prepare( $command, $fields, $fieldsobject = null ) {
			return array( 'command' => $command, 'fields' => $fields, 'fieldsobject' => $fieldsobject );
		}

		/**
		 * Adds a formatted command to commands
		 * @param string $command
		 * @param array $fields
		 * @param string $fieldsobject
		 */
		protected function add( $command, $fields, $fieldsobject = null ) {
			$this->commands[] = $this->prepare( $command, $fields, $fieldsobject );
		}
	}
}

if ( ! class_exists( 'GAINWP_Tracking_Analytics' ) ) {

	class GAINWP_Tracking_Analytics extends GAINWP_Tracking_Analytics_Common {

		public function __construct() {
			parent::__construct();

			if ( $this->gainwp->config->options['trackingcode_infooter'] ) {
				add_action( 'wp_footer', array( $this, 'output' ), 99 );
			} else {
				add_action( 'wp_head', array( $this, 'output' ), 99 );
			}
		}

		/**
		 * Builds the commands based on user's options
		 */
		private function build_commands() {
			$fields = array();
			$fieldsobject = array();
			$fields['trackingId'] = $this->uaid;
			if ( 1 != $this->gainwp->config->options['ga_speed_samplerate'] ) {
				$fieldsobject['siteSpeedSampleRate'] = (int) $this->gainwp->config->options['ga_speed_samplerate'];
			}
			if ( 100 != $this->gainwp->config->options['ga_user_samplerate'] ) {
				$fieldsobject['sampleRate'] = (int) $this->gainwp->config->options['ga_user_samplerate'];
			}
			if ( $this->gainwp->config->options['ga_crossdomain_tracking'] && '' != $this->gainwp->config->options['ga_crossdomain_list'] ) {
				$fieldsobject['allowLinker'] = 'true';
			}
			if ( ! empty( $this->gainwp->config->options['ga_cookiedomain'] ) ) {
				$fieldsobject['cookieDomain'] = $this->gainwp->config->options['ga_cookiedomain'];
			} else {
				$fields['cookieDomain'] = 'auto';
			}
			if ( ! empty( $this->gainwp->config->options['ga_cookiename'] ) ) {
				$fieldsobject['cookieName'] = $this->gainwp->config->options['ga_cookiename'];
			}
			if ( ! empty( $this->gainwp->config->options['ga_cookieexpires'] ) ) {
				$fieldsobject['cookieExpires'] = (int) $this->gainwp->config->options['ga_cookieexpires'];
			}
			if ( $this->gainwp->config->options['amp_tracking_clientidapi'] ) {
				$fieldsobject['useAmpClientId'] = 'true';
			}
			$this->add( 'create', $fields, $fieldsobject );

			if ( $this->gainwp->config->options['ga_crossdomain_tracking'] && '' != $this->gainwp->config->options['ga_crossdomain_list'] ) {
				$fields = array();
				$fields['plugin'] = 'linker';
				$this->add( 'require', $fields );

				$fields = array();
				$domains = '';
				$domains = explode( ',', $this->gainwp->config->options['ga_crossdomain_list'] );
				$domains = array_map( 'trim', $domains );
				$domains = strip_tags( implode( "','", $domains ) );
				$domains = "['" . $domains . "']";
				$fields['domains'] = $domains;
				$this->add( 'linker:autoLink', $fields );
			}

			if ( $this->gainwp->config->options['ga_remarketing'] ) {
				$fields = array();
				$fields['plugin'] = 'displayfeatures';
				$this->add( 'require', $fields );
			}

			if ( $this->gainwp->config->options['ga_enhanced_links'] ) {
				$fields = array();
				$fields['plugin'] = 'linkid';
				$this->add( 'require', $fields );
			}

			if ( $this->gainwp->config->options['ga_force_ssl'] ) {
				$fields = array();
				$fields['option'] = 'forceSSL';
				$fields['value'] = 'true';
				$this->add( 'set', $fields );
			}

			$custom_dimensions = $this->build_custom_dimensions();
			if ( ! empty( $custom_dimensions ) ) {
				foreach ( $custom_dimensions as $index => $value ) {
					$fields = array();
					$fields['gainwp_dimension'] = 'dimension' . $index;
					$fields['gainwp_dim_value'] = $value;
					$this->add( 'set', $fields );
				}
			}

			if ( $this->gainwp->config->options['ga_anonymize_ip'] ) {
				$fields = array();
				$fields['option'] = 'anonymizeIp';
				$fields['value'] = 'true';
				$this->add( 'set', $fields );
			}

			if ( 'enhanced' == $this->gainwp->config->options['ecommerce_mode'] ) {
				$fields = array();
				$fields['plugin'] = 'ec';
				$this->add( 'require', $fields );
			} else if ( 'standard' == $this->gainwp->config->options['ecommerce_mode'] ) {
				$fields = array();
				$fields['plugin'] = 'ecommerce';
				$this->add( 'require', $fields );
			}

			if ( $this->gainwp->config->options['optimize_tracking'] && $this->gainwp->config->options['optimize_containerid'] ) {
				$fields = array();
				$fields['plugin'] = esc_attr( $this->gainwp->config->options['optimize_containerid'] );
				$this->add( 'require', $fields );
			}

			$fields = array();
			$fields['hitType'] = 'pageview';
			$this->add( 'send', $fields );

			do_action( 'gainwp_analytics_commands', $this );
		}

		/**
		 * Outputs the Google Analytics tracking code
		 */
		public function output() {
			$this->commands = array();

			$this->build_commands();

			$trackingcode = '';

			foreach ( $this->commands as $set ) {
				$command = $set['command'];

				$fields = '';
				foreach ( $set['fields'] as $fieldkey => $fieldvalue ) {
					if ( false === strpos( $fieldkey, 'gainwp_dim_value' ) ) {
						$fieldvalue = $this->filter( $fieldvalue );
					} else {
						$fieldvalue = $this->filter( $fieldvalue, true );
					}
					$fields .= ", " . $fieldvalue;
				}

				if ( $set['fieldsobject'] ) {
					$fieldsobject = ", {";
					foreach ( $set['fieldsobject'] as $fieldkey => $fieldvalue ) {
						$fieldvalue = $this->filter( $fieldvalue );
						$fieldkey = $this->filter( $fieldkey );
						$fieldsobject .= $fieldkey . ": " . $fieldvalue . ", ";
					}
					$fieldsobject = rtrim( $fieldsobject, ", " );
					$fieldsobject .= "}";
					$trackingcode .= "  ga('" . $command . "'" . $fields . $fieldsobject . ");\n";
				} else {
					$trackingcode .= "  ga('" . $command . "'" . $fields . ");\n";
				}
			}

			$tracking_script_path = apply_filters( 'gainwp_analytics_script_path', 'https://www.google-analytics.com/analytics.js' );

			if ( $this->gainwp->config->options['ga_optout'] || $this->gainwp->config->options['ga_dnt_optout'] ) {
				GAINWP_Tools::load_view( 'front/views/analytics-optout-code.php', array( 'uaid' => $this->uaid, 'gaDntOptout' => $this->gainwp->config->options['ga_dnt_optout'], 'gaOptout' => $this->gainwp->config->options['ga_optout'] ) );
			}

			GAINWP_Tools::load_view( 'front/views/analytics-code.php', array( 'trackingcode' => $trackingcode, 'tracking_script_path' => $tracking_script_path, 'ga_with_gtag' => $this->gainwp->config->options['ga_with_gtag'] , 'uaid' => $this->uaid ) );
		}
	}
}


if ( ! class_exists( 'GAINWP_Tracking_GlobalSiteTag' ) ) {

	class GAINWP_Tracking_GlobalSiteTag extends GAINWP_Tracking_Analytics_Common {

		public function __construct() {
			parent::__construct();

			if ( $this->gainwp->config->options['trackingcode_infooter'] ) {
				add_action( 'wp_footer', array( $this, 'output' ), 99 );
			} else {
				add_action( 'wp_head', array( $this, 'output' ), 99 );
			}
		}

		/**
		 * Builds the commands based on user's options
		 */
		private function build_commands() {
			$fields = array();
			$fieldsobject = array();
			$fields['trackingId'] = $this->uaid;
			$custom_dimensions = $this->build_custom_dimensions();
			/*
			 * if ( 1 != $this->gainwp->config->options['ga_speed_samplerate'] ) {
			 * $fieldsobject['siteSpeedSampleRate'] = (int) $this->gainwp->config->options['ga_speed_samplerate'];
			 * }
			 */
			if ( ! empty( $this->gainwp->config->options['ga_cookiedomain'] ) ) {
				$fieldsobject['cookie_domain'] = $this->gainwp->config->options['ga_cookiedomain'];
			}
			if ( ! empty( $this->gainwp->config->options['ga_cookiename'] ) ) {
				$fieldsobject['cookie_name'] = $this->gainwp->config->options['ga_cookiename'];
			}
			if ( ! empty( $this->gainwp->config->options['ga_cookieexpires'] ) ) {
				$fieldsobject['cookie_expires'] = (int) $this->gainwp->config->options['ga_cookieexpires'];
			}
			/*
			 * if ( $this->gainwp->config->options['amp_tracking_clientidapi'] ) {
			 * $fieldsobject['useAmpClientId'] = 'true';
			 * }
			 */
			if ( $this->gainwp->config->options['ga_crossdomain_tracking'] && '' != $this->gainwp->config->options['ga_crossdomain_list'] ) {
				$domains = '';
				$domains = explode( ',', $this->gainwp->config->options['ga_crossdomain_list'] );
				$domains = array_map( 'trim', $domains );
				$domains = strip_tags( implode( "','", $domains ) );
				$domains = "['" . $domains . "']";
				$fieldsobject['linker'] = "{ 'domains' : " . $domains . " }";
			}
			if ( ! $this->gainwp->config->options['ga_remarketing'] ) {
				$fieldsobject['allow_display_features'] = 'false';
			}
			if ( $this->gainwp->config->options['ga_enhanced_links'] ) {
				$fieldsobject['link_attribution'] = 'true';
			}
			if ( $this->gainwp->config->options['ga_anonymize_ip'] ) {
				$fieldsobject['anonymize_ip'] = 'true';
			}
			if ( $this->gainwp->config->options['optimize_tracking'] && $this->gainwp->config->options['optimize_containerid'] ) {
				$fieldsobject['optimize_id'] = esc_attr( $this->gainwp->config->options['optimize_containerid'] );
			}
			if ( 100 != $this->gainwp->config->options['ga_user_samplerate'] ) {
				$fieldsobject['sample_rate'] = (int) $this->gainwp->config->options['ga_user_samplerate'];
			}
			if ( ! empty( $custom_dimensions ) ) {
				$fieldsobject['custom_map'] = "{\n\t\t";
				foreach ( $custom_dimensions as $index => $value ) {
					$fieldsobject['custom_map'] .= "'dimension" . $index . "': '" . "gainwp_dim_" . $index . "', \n\t\t";
				}
				$fieldsobject['custom_map'] = rtrim( $fieldsobject['custom_map'], ", \n\t\t" );
				$fieldsobject['custom_map'] .= "\n\t}";
			}
			$this->add( 'config', $fields, $fieldsobject );

			if ( ! empty( $custom_dimensions ) ) {
				$fields = array();
				$fieldsobject = array();
				$fields['event_name'] = 'gainwp_dimensions';
				foreach ( $custom_dimensions as $index => $value ) {
					$fieldsobject['gainwp_dim_' . $index] = $value;
				}
				$this->add( 'event', $fields, $fieldsobject );
			}

			do_action( 'gainwp_gtag_commands', $this );
		}

		/**
		 * Outputs the Google Analytics tracking code
		 */
		public function output() {
			$this->commands = array();

			$this->build_commands();

			$trackingcode = '';

			foreach ( $this->commands as $set ) {
				$command = $set['command'];

				$fields = '';
				foreach ( $set['fields'] as $fieldkey => $fieldvalue ) {
					$fieldvalue = $this->filter( $fieldvalue );
					$fields .= ", " . $fieldvalue;
				}

				if ( $set['fieldsobject'] ) {
					$fieldsobject = ", {\n\t";
					foreach ( $set['fieldsobject'] as $fieldkey => $fieldvalue ) {
						if ( false === strpos( $fieldkey, 'gainwp_' ) ) {
							$fieldvalue = $this->filter( $fieldvalue );
						} else {
							$fieldvalue = $this->filter( $fieldvalue, true );
						}
						$fieldkey = $this->filter( $fieldkey );
						$fieldsobject .= $fieldkey . ": " . $fieldvalue . ", \n\t";
					}
					$fieldsobject = rtrim( $fieldsobject, ", \n\t" );
					$fieldsobject .= "\n  }";
					$trackingcode .= "  gtag('" . $command . "'" . $fields . $fieldsobject . ");\n";
				} else {
					$trackingcode .= "  gtag('" . $command . "'" . $fields . ");\n";
				}
			}

			$tracking_script_path = apply_filters( 'gainwp_gtag_script_path', 'https://www.googletagmanager.com/gtag/js' );

			if ( $this->gainwp->config->options['ga_optout'] || $this->gainwp->config->options['ga_dnt_optout'] ) {
				GAINWP_Tools::load_view( 'front/views/analytics-optout-code.php', array( 'uaid' => $this->uaid, 'gaDntOptout' => $this->gainwp->config->options['ga_dnt_optout'], 'gaOptout' => $this->gainwp->config->options['ga_optout'] ) );
			}

			GAINWP_Tools::load_view( 'front/views/analytics-code.php', array( 'trackingcode' => $trackingcode, 'tracking_script_path' => $tracking_script_path, 'ga_with_gtag' => $this->gainwp->config->options['ga_with_gtag'] , 'uaid' => $this->uaid ) );
		}
	}
}

if ( ! class_exists( 'GAINWP_Tracking_Analytics_AMP' ) ) {

	class GAINWP_Tracking_Analytics_AMP extends GAINWP_Tracking_Analytics_Base {

		private $config;

		public function __construct() {
			parent::__construct();

			add_filter( 'amp_post_template_data', array( $this, 'load_scripts' ) );
			add_action( 'amp_post_template_footer', array( $this, 'output' ) );
			add_filter( 'the_content', array( $this, 'add_data_attributes' ), 999, 1 );
			if ( $this->gainwp->config->options['amp_tracking_clientidapi'] ) {
				add_action( 'amp_post_template_head', array( $this, 'add_amp_client_id' ) );
			}
		}

		private function get_link_event_data( $link ) {
			if ( empty( $link ) ) {
				return false;
			}
			if ( $this->gainwp->config->options['ga_event_tracking'] ) {
				// on changes adjust the substr() length parameter
				if ( substr( $link, 0, 7 ) === "mailto:" ) {
					return array( 'email', 'send', $link );
				}

				// on changes adjust the substr() length parameter
				if ( substr( $link, 0, 4 ) === "tel:" ) {
					return array( 'telephone', 'call', $link );
				}

				// Add download data-vars
				if ( $this->gainwp->config->options['ga_event_downloads'] && preg_match( '/.*\.(' . $this->gainwp->config->options['ga_event_downloads'] . ')(\?.*)?$/i', $link, $matches ) ) {
					return array( 'download', 'click', $link );
				}
			}
			if ( $this->gainwp->config->options['ga_hash_tracking'] ) {
				// Add hashmark data-vars
				$root_domain = GAINWP_Tools::get_root_domain();
				if ( $root_domain && ( strpos( $link, $root_domain ) > - 1 || strpos( $link, '://' ) === false ) && strpos( $link, '#' ) > - 1 ) {
					return array( 'hashmark', 'click', $link );
				}
			}
			if ( $this->gainwp->config->options['ga_aff_tracking'] ) {
				// Add affiliate data-vars
				if ( strpos( $link, $this->gainwp->config->options['ga_event_affiliates'] ) > - 1 ) {
					return array( 'affiliates', 'click', $link );
				}
			}
			if ( $this->gainwp->config->options['ga_event_tracking'] ) {
				// Add outbound data-vars
				$root_domain = GAINWP_Tools::get_root_domain();
				if ( $root_domain && strpos( $link, $root_domain ) === false && strpos( $link, '://' ) > - 1 ) {
					return array( 'outbound', 'click', $link );
				}
			}
			return false;
		}

		public function add_data_attributes( $content ) {
			if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() && $this->is_event_tracking( false ) ) {

				$dom = GAINWP_Tools::get_dom_from_content( $content );

				if ( $dom ) {

					$links = $dom->getElementsByTagName( 'a' );

					foreach ( $links as $item ) {

						$data_attributes = $this->get_link_event_data( $item->getAttribute( 'href' ) );

						if ( $data_attributes ) {
							if ( ! $item->hasAttribute( 'data-vars-ga-category' ) ) {
								$item->setAttribute( 'data-vars-ga-category', $data_attributes[0] );
							}
							if ( ! $item->hasAttribute( 'data-vars-ga-action' ) ) {
								$item->setAttribute( 'data-vars-ga-action', $data_attributes[1] );
							}
							if ( ! $item->hasAttribute( 'data-vars-ga-label' ) ) {
								$item->setAttribute( 'data-vars-ga-label', $data_attributes[2] );
							}
						}
					}

					if ( $this->gainwp->config->options['ga_formsubmit_tracking'] ) {
						$form_submits = $dom->getElementsByTagName( 'input' );
						foreach ( $form_submits as $item ) {
							if ( $item->getAttribute( 'type' ) == 'submit' ) {
								if ( ! $item->hasAttribute( 'data-vars-ga-category' ) ) {
									$item->setAttribute( 'data-vars-ga-category', 'form' );
								}
								if ( ! $item->hasAttribute( 'data-vars-ga-action' ) ) {
									$item->setAttribute( 'data-vars-ga-action', 'submit' );
								}
								if ( ! $item->hasAttribute( 'data-vars-ga-label' ) ) {
									if ( $item->getAttribute( 'value' ) ) {
										$label = $item->getAttribute( 'value' );
									}
									if ( $item->getAttribute( 'name' ) ) {
										$label = $item->getAttribute( 'name' );
									}
									$item->setAttribute( 'data-vars-ga-label', $label );
								}
							}
						}
					}
					return GAINWP_Tools::get_content_from_dom( $dom );
				}
			}

			return $content;
		}

		/**
		 * Inserts the Analytics AMP script in the head section
		 */
		public function load_scripts( $data ) {
			if ( ! isset( $data['amp_component_scripts'] ) ) {
				$data['amp_component_scripts'] = array();
			}

			$data['amp_component_scripts']['amp-analytics'] = 'https://cdn.ampproject.org/v0/amp-analytics-0.1.js';

			return $data;
		}

		/**
		 * Retrieves the AMP config array
		 */
		public function get() {
			return $this->config;
		}

		/**
		 * Stores the AMP config array
		 * @param array $config
		 */
		public function set( $config ) {
			$this->config = $config;
		}

		private function build_json() {
			$this->config = array();

			// Set the Tracking ID
			/* @formatter:off */
			$this->config['vars'] = array(
				'account' => $this->uaid,
				'documentLocation' => '${canonicalUrl}',
			);
			/* @formatter:on */

			// Set Custom Dimensions as extraUrlParams
			$custom_dimensions = $this->build_custom_dimensions();

			if ( ! empty( $custom_dimensions ) ) {
				foreach ( $custom_dimensions as $index => $value ) {
					$dimension = 'cd' . $index;
					$this->config['extraUrlParams'][$dimension] = $value;
				}
			}

			// Set Triggers
			/* @formatter:off */
			$this->config['triggers']['gainwpTrackPageview'] = array(
				'on' => 'visible',
				'request' => 'pageview',
			);
			/* @formatter:on */

			// Set Sampling Rate only if lower than 100%
			if ( 100 != $this->gainwp->config->options['ga_user_samplerate'] ) {
				/* @formatter:off */
				$this->config['triggers']['gainwpTrackPageview']['sampleSpec'] = array(
					'sampleOn' => '${clientId}',
					'threshold' => (int) $this->gainwp->config->options['ga_user_samplerate'],
				);
				/* @formatter:on */
			}

			// Set Scroll events
			if ( $this->gainwp->config->options['ga_pagescrolldepth_tracking'] ) {
				/* @formatter:off */
				$this->config['triggers']['gainwpScrollPings'] = array (
					'on' => 'scroll',
					'scrollSpec' => array(
						'verticalBoundaries' => '&#91;25, 50, 75, 100&#93;',
					),
					'request' => 'event',
					'vars' => array(
						'eventCategory' => 'Scroll Depth',
						'eventAction' => 'Percentage',
						'eventLabel' => '${verticalScrollBoundary}%',
					),
				);
				/* @formatter:on */
				$this->config['triggers']['gainwpScrollPings']['extraUrlParams'] = array( 'ni' => true );
			}

			if ( $this->is_event_tracking( false ) ) {
				// Set downloads, outbound links, affiliate links, hashmarks, e-mails, telephones, form submits events
				/* @formatter:off */
				$this->config['triggers']['gainwpEventTracking'] = array (
					'on' => 'click',
					'selector' => '[data-vars-ga-category][data-vars-ga-action][data-vars-ga-label]',
					'request' => 'event',
					'vars' => array(
						'eventCategory' => '${gaCategory}',
						'eventAction' => '${gaAction}',
						'eventLabel' => '${gaLabel}',
					),
				);
				/* @formatter:on */
				if ( $this->gainwp->config->options['ga_event_bouncerate'] ) {
					$this->config['triggers']['gainwpEventTracking']['extraUrlParams'] = array( 'ni' => (bool) $this->gainwp->config->options['ga_event_bouncerate'] );
				}
			}
			do_action( 'gainwp_analytics_amp_config', $this );
		}

		public function add_amp_client_id() {
			GAINWP_Tools::load_view( 'front/views/analytics-amp-clientidapi.php' );
		}

		/**
		 * Outputs the Google Analytics tracking code for AMP
		 */
		public function output() {
			$this->build_json();

			if ( version_compare( phpversion(), '5.4.0', '<' ) ) {
				$json = json_encode( $this->config );
			} else {
				$json = json_encode( $this->config, JSON_PRETTY_PRINT );
			}

			$json = str_replace( array( '"&#91;', '&#93;"' ), array( '[', ']' ), $json ); // make verticalBoundaries a JavaScript array

			$data = array( 'json' => $json );

			GAINWP_Tools::load_view( 'front/views/analytics-amp-code.php', $data );
		}
	}
}
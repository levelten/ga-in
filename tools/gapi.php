<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_GAPI_Controller' ) ) {

	final class GAINWP_GAPI_Controller {

		public $client;

		public $service;

		public $timeshift;

		public $managequota;

		private $gainwp;

		private $access = array( '639045947306-divu0nao1j0db2hcpg4mqp9gv1ibresl.apps.googleusercontent.com', 'tMciX2hz3-wqAZbsDB9mApgrvY' );

		public function __construct() {
			$this->gainwp = GAINWP();
			include_once ( GAINWP_DIR . 'tools/src/Deconfin/autoload.php' );
			$config = new Deconfin_Config();
			$config->setCacheClass( 'Deconfin_Cache_Null' );
			if ( function_exists( 'curl_version' ) ) {
				$curlversion = curl_version();
				$curl_options = array();
				if ( isset( $curlversion['version'] ) ) {
					$rightversion = ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) && version_compare( $curlversion['version'], '7.10.8' ) >= 0;
				} else {
					$rightversion = false;
				}
				if ( $rightversion && defined( 'GAINWP_IP_VERSION' ) && GAINWP_IP_VERSION ) {
					$curl_options[CURLOPT_IPRESOLVE] = GAINWP_IP_VERSION; // Force CURL_IPRESOLVE_V4 or CURL_IPRESOLVE_V6
				}
				// add Proxy server settings to curl, if defined
				if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
					$curl_options[CURLOPT_PROXY] = WP_PROXY_HOST;
					$curl_options[CURLOPT_PROXYPORT] = WP_PROXY_PORT;
				}
				if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
					$curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
					$curl_options[CURLOPT_PROXYUSERPWD] = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD;
				}
				$curl_options = apply_filters( 'gainwp_curl_options', $curl_options );
				if ( ! empty( $curl_options ) ) {
					$config->setClassConfig( 'Deconfin_IO_Curl', 'options', $curl_options );
				}
			}
			$this->client = new Deconfin_Client( $config );
			$this->client->setScopes( array( 'https://www.googleapis.com/auth/analytics.readonly' ) );
			$this->client->setAccessType( 'offline' );
			$this->client->setApplicationName( 'GAINWP ' . GAINWP_CURRENT_VERSION );
			$this->client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
			$this->managequota = 'u' . get_current_user_id() . 's' . get_current_blog_id();

			$this->client = apply_filters('gainwp_gapi_client_alter', $this->client);

			$auth_class = $this->client->getAuthClass();
			$auth_config = $this->client->getClassConfig($auth_class);

			$access = array(
				'client_id' => '',
				'client_secret'=> '',
				'token' => '',
			);

			list($access['client_id'], $access['client_secret']) = array_map( array( $this, 'map' ), $this->access );

			if (!empty($auth_config['client_id'])) {
				$access['client_id'] = $auth_config['client_id'];
			}
			if (!empty($auth_config['client_secret'])) {
				$access['client_secret'] = $auth_config['client_secret'];
			}

			if ( $this->gainwp->config->options['user_api'] ) {
				$access['client_id'] = $this->gainwp->config->options['client_id'];
				$access['client_secret'] = $this->gainwp->config->options['client_secret'];
			}

			$this->client->setClientId( $access['client_id'] );
			$this->client->setClientSecret( $access['client_secret'] );

			/**
			 * GAINWP Endpoint support
			 */
			add_action( 'gainwp_endpoint_support', array( $this, 'add_endpoint_support' ) );

			$this->service = new Deconfin_Service_Analytics( $this->client );

			if ( $this->gainwp->config->options['token'] ) {
				$token = $this->gainwp->config->options['token'];
				if ( $token ) {
					try {
						$this->client->setAccessToken( $token );
						if ( $this->client->isAccessTokenExpired() ) {
							// returns refresh token string
							$refreshtoken = $this->client->getRefreshToken();
							// refreshes access_token on client
							$this->client->refreshToken( $refreshtoken );
						}
						$this->gainwp->config->options['token'] = $this->client->getAccessToken();
					} catch ( Deconfin_IO_Exception $e ) {
						$timeout = $this->get_timeouts( 'midnight' );
						GAINWP_Tools::set_error( $e, $timeout );
					} catch ( Deconfin_Service_Exception $e ) {
						$timeout = $this->get_timeouts( 'midnight' );
						GAINWP_Tools::set_error( $e, $timeout );
						$this->reset_token();
					} catch ( Exception $e ) {
						$timeout = $this->get_timeouts( 'midnight' );
						GAINWP_Tools::set_error( $e, $timeout );
						$this->reset_token();
					}
					if ( is_multisite() && $this->gainwp->config->options['network_mode'] ) {
						$this->gainwp->config->set_plugin_options( true );
					} else {
						$this->gainwp->config->set_plugin_options();
					}
				}
			}
		}

		public function add_endpoint_support( $request ) {
			if ( $this->gainwp->config->options['with_endpoint'] && ! $this->gainwp->config->options['user_api'] ) {

				$url = $request->getUrl();

				if ( in_array( $url, array( 'https://accounts.google.com/o/oauth2/token', 'https://accounts.google.com/o/oauth2/revoke' ) ) ) {
					if ( get_class( $this->client->getIo() ) != 'Deconfin_IO_Stream' ) {
						$curl_old_options = $this->client->getClassConfig( 'Deconfin_IO_Curl' );
						$curl_options = $curl_old_options['options'];
						$curl_options[CURLOPT_SSL_VERIFYPEER] = 0;
						$this->client->setClassConfig( 'Deconfin_IO_Curl', 'options', $curl_options );
					} else {
						add_filter( 'gainwp_endpoint_stream_options', array( $this, 'add_endpoint_stream_ssl' ), 10 );
					}
				} else {
					if ( get_class( $this->client->getIo() ) != 'Deconfin_IO_Stream' ) {
						$curl_old_options = $this->client->getClassConfig( 'Deconfin_IO_Curl' );
						$curl_options = $curl_old_options['options'];
						if ( isset( $curl_options[CURLOPT_SSL_VERIFYPEER] ) ) {
							unset( $curl_options[CURLOPT_SSL_VERIFYPEER] );
							if ( empty( $curl_options ) ) {
								$this->client->setClassConfig( 'Deconfin_IO_Curl', 'options', '' );
							} else {
								$this->client->setClassConfig( 'Deconfin_IO_Curl', 'options', $curl_options );
							}
						}
					}
				}

				$url = str_replace( 'https://accounts.google.com/o/oauth2/token', GAINWP_ENDPOINT_URL . 'gainwp-token.php', $url );

				$url = str_replace( 'https://accounts.google.com/o/oauth2/revoke', GAINWP_ENDPOINT_URL . 'gainwp-revoke.php', $url );

				$request->setUrl( $url );

				if ( ! $request->getUserAgent() ) {
					$request->setUserAgent( $this->client->getApplicationName() );
				}
			}
		}

		public function add_endpoint_stream_ssl( $requestSslContext ) {
			return array( "verify_peer" => false );
		}

		/**
		 * Handles errors returned by GAPI Library
		 *
		 * @return boolean
		 */
		public function gapi_errors_handler() {
			$errors = GAINWP_Tools::get_cache( 'gapi_errors' );

			if ( false === $errors || ! isset( $errors[0] ) ) { // invalid error
				return false;
			}

			if ( isset( $errors[1][0]['reason'] ) && ( 'invalidParameter' == $errors[1][0]['reason'] || 'badRequest' == $errors[1][0]['reason'] || 'invalidCredentials' == $errors[1][0]['reason'] || 'insufficientPermissions' == $errors[1][0]['reason'] || 'required' == $errors[1][0]['reason'] ) ) {
				$this->reset_token();
				return true;
			}

			/** Back-off system for subsequent requests - an Auth error generated after a Service request
			 *  The native back-off system for Service requests is covered by the GAPI PHP Client
			 */
			if ( isset( $errors[1][0]['reason'] ) && ( 'authError' == $errors[1][0]['reason'] ) ) {
				if ( $this->gainwp->config->options['api_backoff'] <= 5 ) {
					usleep( $this->gainwp->config->options['api_backoff'] * 1000000 + rand( 100000, 1000000 ) );
					$this->gainwp->config->options['api_backoff'] = $this->gainwp->config->options['api_backoff'] + 1;
					$this->gainwp->config->set_plugin_options();
					return false;
				} else {
					return true;
				}
			}

			if ( 500 == $errors[0] || 503 == $errors[0] || 400 == $errors[0] || 401 == $errors[0] || 403 == $errors[0] || $errors[0] < - 50 ) {
				return true;
			}

			return false;
		}

		/**
		 * Calculates proper timeouts for each GAPI query
		 *
		 * @param
		 *            $interval
		 * @return number
		 */
		public function get_timeouts( $interval = '' ) {
			$local_time = time() + $this->timeshift;
			if ( 'daily' == $interval ) {
				$nextday = explode( '-', date( 'n-j-Y', strtotime( ' +1 day', $local_time ) ) );
				$midnight = mktime( 0, 0, 0, $nextday[0], $nextday[1], $nextday[2] );
				return $midnight - $local_time;
			} else if ( 'midnight' == $interval ) {
				$midnight = strtotime( "tomorrow 00:00:00" ); // UTC midnight
				$midnight = $midnight + 8 * 3600; // UTC 8 AM
				return $midnight - time();
			} else if ( 'hourly' == $interval ) {
				$nexthour = explode( '-', date( 'H-n-j-Y', strtotime( ' +1 hour', $local_time ) ) );
				$newhour = mktime( $nexthour[0], 0, 0, $nexthour[1], $nexthour[2], $nexthour[3] );
				return $newhour - $local_time;
			} else {
				$newtime = strtotime( ' +5 minutes', $local_time );
				return $newtime - $local_time;
			}
		}

		/**
		 * Generates and retrieves the Access Code
		 */
		public function token_request() {
			$data['authUrl'] = $this->client->createAuthUrl();
			GAINWP_Tools::load_view( 'admin/views/access-code.php', $data );
		}

		/**
		 * Retrieves all Google Analytics Views with details
		 *
		 * @return array
		 */
		public function refresh_profiles() {
			try {

				$ga_profiles_list = array();
				$startindex = 1;
				$totalresults = 65535; // use something big

				while ( $startindex < $totalresults ) {

					$profiles = $this->service->management_profiles->listManagementProfiles( '~all', '~all', array( 'start-index' => $startindex ) );

					$items = $profiles->getItems();

					$totalresults = $profiles->getTotalResults();

					if ( $totalresults > 0 ) {

						foreach ( $items as $profile ) {
							$timetz = new DateTimeZone( $profile->getTimezone() );
							$localtime = new DateTime( 'now', $timetz );
							$timeshift = strtotime( $localtime->format( 'Y-m-d H:i:s' ) ) - time();
							$ga_profiles_list[] = array( $profile->getName(), $profile->getId(), $profile->getwebPropertyId(), $profile->getwebsiteUrl(), $timeshift, $profile->getTimezone(), $profile->getDefaultPage() );
							$startindex++;
						}
					}
				}

				if ( empty( $ga_profiles_list ) ) {
					$timeout = $this->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( 'No properties were found in this account!', $timeout );
				} else {
					GAINWP_Tools::delete_cache( 'last_error' );
				}
				return $ga_profiles_list;
			} catch ( Deconfin_IO_Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
				return $ga_profiles_list;
			} catch ( Deconfin_Service_Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
			} catch ( Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
			}
		}

		/**
		 * Handles the token reset process
		 *
		 * @param
		 *            $all
		 */
		public function reset_token( $all = false ) {
			$this->gainwp->config->options['token'] = "";
			if ( $all ) {
				$this->gainwp->config->options['tableid_jail'] = "";
				$this->gainwp->config->options['ga_profiles_list'] = array();
				try {
					$this->client->revokeToken();
				} catch ( Exception $e ) {
					if ( is_multisite() && $this->gainwp->config->options['network_mode'] ) {
						$this->gainwp->config->set_plugin_options( true );
					} else {
						$this->gainwp->config->set_plugin_options();
					}
				}
			}
			if ( is_multisite() && $this->gainwp->config->options['network_mode'] ) {
				$this->gainwp->config->set_plugin_options( true );
			} else {
				$this->gainwp->config->set_plugin_options();
			}
		}

		/**
		 * Get and cache Core Reports
		 *
		 * @param
		 *            $projecId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $metrics
		 * @param
		 *            $options
		 * @param
		 *            $serial
		 * @return int|Deconfin_Service_Analytics_GaData
		 */
		private function handle_corereports( $projectId, $from, $to, $metrics, $options, $serial ) {
			try {
				if ( 'today' == $from ) {
					$interval = 'hourly';
				} else {
					$interval = 'daily';
				}
				$transient = GAINWP_Tools::get_cache( $serial );
				if ( false === $transient ) {
					if ( $this->gapi_errors_handler() ) {
						return - 23;
					}
					$options['samplingLevel'] = 'HIGHER_PRECISION';
					$data = $this->service->data_ga->get( 'ga:' . $projectId, $from, $to, $metrics, $options );
					if ( method_exists( $data, 'getContainsSampledData' ) && $data->getContainsSampledData() ) {
						$sampling['date'] = date( 'Y-m-d H:i:s' );
						$sampling['percent'] = number_format( ( $data->getSampleSize() / $data->getSampleSpace() ) * 100, 2 ) . '%';
						$sampling['sessions'] = $data->getSampleSize() . ' / ' . $data->getSampleSpace();
						GAINWP_Tools::set_cache( 'sampleddata', $sampling, 30 * 24 * 3600 );
						GAINWP_Tools::set_cache( $serial, $data, $this->get_timeouts( 'hourly' ) ); // refresh every hour if data is sampled
					} else {
						GAINWP_Tools::set_cache( $serial, $data, $this->get_timeouts( $interval ) );
					}
				} else {
					$data = $transient;
				}
			} catch ( Deconfin_Service_Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
				return $e->getCode();
			} catch ( Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
				return $e->getCode();
			}

			$this->gainwp->config->options['api_backoff'] = 0;
			$this->gainwp->config->set_plugin_options();

			if ( $data->getRows() > 0 ) {
				return $data;
			} else {
				$data->rows = array();
				return $data;
			}
		}

		/**
		 * Generates serials for transients
		 *
		 * @param
		 *            $serial
		 * @return string
		 */
		public function get_serial( $serial ) {
			return sprintf( "%u", crc32( $serial ) );
		}

		/**
		 * Analytics data for Area Charts (Admin Dashboard Widget report)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $query
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_areachart_data( $projectId, $from, $to, $query, $filter = '' ) {
			switch ( $query ) {
				case 'users' :
					$title = __( "Users", 'ga-in' );
					break;
				case 'pageviews' :
					$title = __( "Page Views", 'ga-in' );
					break;
				case 'visitBounceRate' :
					$title = __( "Bounce Rate", 'ga-in' );
					break;
				case 'organicSearches' :
					$title = __( "Organic Searches", 'ga-in' );
					break;
				case 'uniquePageviews' :
					$title = __( "Unique Page Views", 'ga-in' );
					break;
				default :
					$title = __( "Sessions", 'ga-in' );
			}
			$metrics = 'ga:' . $query;
			if ( 'today' == $from || 'yesterday' == $from ) {
				$dimensions = 'ga:hour';
				$dayorhour = __( "Hour", 'ga-in' );
			} else if ( '365daysAgo' == $from || '1095daysAgo' == $from ) {
				$dimensions = 'ga:yearMonth, ga:month';
				$dayorhour = __( "Date", 'ga-in' );
			} else {
				$dimensions = 'ga:date,ga:dayOfWeekName';
				$dayorhour = __( "Date", 'ga-in' );
			}
			$options = array( 'dimensions' => $dimensions, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:pagePath==' . $filter;
			}
			$serial = 'qr2_' . $this->get_serial( $projectId . $from . $metrics . $filter );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			if ( empty( $data->rows ) ) {
				// unable to render it as an Area Chart, returns a numeric value to be handled by reportsx.js
				return - 21;
			}
			$gainwp_data = array( array( $dayorhour, $title ) );
			if ( 'today' == $from || 'yesterday' == $from ) {
				foreach ( $data->getRows() as $row ) {
					$gainwp_data[] = array( (int) $row[0] . ':00', round( $row[1], 2 ) );
				}
			} else if ( '365daysAgo' == $from || '1095daysAgo' == $from ) {
				foreach ( $data->getRows() as $row ) {
					/*
					 * translators:
					 * Example: 'F, Y' will become 'November, 2015'
					 * For details see: http://php.net/manual/en/function.date.php#refsect1-function.date-parameters
					 */
					$gainwp_data[] = array( date_i18n( __( 'F, Y', 'ga-in' ), strtotime( $row[0] . '01' ) ), round( $row[2], 2 ) );
				}
			} else {
				foreach ( $data->getRows() as $row ) {
					/*
					 * translators:
					 * Example: 'l, F j, Y' will become 'Thusday, November 17, 2015'
					 * For details see: http://php.net/manual/en/function.date.php#refsect1-function.date-parameters
					 */
					$gainwp_data[] = array( date_i18n( __( 'l, F j, Y', 'ga-in' ), strtotime( $row[0] ) ), round( $row[2], 2 ) );
				}
			}

			return $gainwp_data;
		}

		/**
		 * Analytics data for Bottom Stats (bottom stats on main report)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_bottomstats( $projectId, $from, $to, $filter = '' ) {
			$options = array( 'dimensions' => null, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:pagePath==' . $filter;
				$metrics = 'ga:uniquePageviews,ga:users,ga:pageviews,ga:BounceRate,ga:organicSearches,ga:pageviewsPerSession,ga:avgTimeOnPage,ga:avgPageLoadTime,ga:exitRate';
			} else {
				$metrics = 'ga:sessions,ga:users,ga:pageviews,ga:BounceRate,ga:organicSearches,ga:pageviewsPerSession,ga:avgTimeOnPage,ga:avgPageLoadTime,ga:avgSessionDuration';
			}
			$serial = 'qr3_' . $this->get_serial( $projectId . $from . $filter );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array();
			foreach ( $data->getRows() as $row ) {
				$gainwp_data = array_map( 'floatval', $row );
			}

			// i18n support
			$gainwp_data[0] = isset( $gainwp_data[0] ) ? number_format_i18n( $gainwp_data[0] ) : 0;
			$gainwp_data[1] = isset( $gainwp_data[1] ) ? number_format_i18n( $gainwp_data[1] ) : 0;
			$gainwp_data[2] = isset( $gainwp_data[2] ) ? number_format_i18n( $gainwp_data[2] ) : 0;
			$gainwp_data[3] = isset( $gainwp_data[3] ) ? number_format_i18n( $gainwp_data[3], 2 ) . '%' : '0%';
			$gainwp_data[4] = isset( $gainwp_data[4] ) ? number_format_i18n( $gainwp_data[4] ) : 0;
			$gainwp_data[5] = isset( $gainwp_data[5] ) ? number_format_i18n( $gainwp_data[5], 2 ) : 0;
			$gainwp_data[6] = isset( $gainwp_data[6] ) ? gmdate( "H:i:s", $gainwp_data[6] ) : '00:00:00';
			$gainwp_data[7] = isset( $gainwp_data[7] ) ? number_format_i18n( $gainwp_data[7], 2 ) : 0;
			if ( $filter ) {
				$gainwp_data[8] = isset( $gainwp_data[8] ) ? number_format_i18n( $gainwp_data[8], 2 ) . '%' : '0%';
			} else {
				$gainwp_data[8] = isset( $gainwp_data[8] ) ? gmdate( "H:i:s", $gainwp_data[8] ) : '00:00:00';
			}

			return $gainwp_data;
		}

		/**
		 * Analytics data for Table Charts (content pages)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_contentpages( $projectId, $from, $to, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:pageTitle';
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:pagePath==' . $filter;
			}
			$serial = 'qr4_' . $this->get_serial( $projectId . $from . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array( array( __( "Pages", 'ga-in' ), __( ucfirst( $metric ), 'ga-in' ) ) );
			foreach ( $data->getRows() as $row ) {
				$gainwp_data[] = array( esc_html( $row[0] ), (int) $row[1] );
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for 404 Errors
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @return array|int
		 */
		private function get_404errors( $projectId, $from, $to, $filter = "Page Not Found", $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:pagePath,ga:fullReferrer';
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			$options['filters'] = 'ga:pageTitle=@' . $filter;
			$serial = 'qr4_' . $this->get_serial( $projectId . $from . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array( array( __( "404 Errors", 'ga-in' ), __( ucfirst( $metric ), 'ga-in' ) ) );
			foreach ( $data->getRows() as $row ) {
				$path = esc_html( $row[0] );
				$source = esc_html( $row[1] );
				$gainwp_data[] = array( "<strong>" . __( "URI:", 'ga-in' ) . "</strong> " . $path . "<br><strong>" . __( "Source:", 'ga-in' ) . "</strong> " . $source, (int) $row[2] );
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for Table Charts (referrers)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_referrers( $projectId, $from, $to, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:source';
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:medium==referral;ga:pagePath==' . $filter;
			} else {
				$options['filters'] = 'ga:medium==referral';
			}
			$serial = 'qr5_' . $this->get_serial( $projectId . $from . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array( array( __( "Referrers", 'ga-in' ), __( ucfirst( $metric ), 'ga-in' ) ) );
			foreach ( $data->getRows() as $row ) {
				$gainwp_data[] = array( esc_html( $row[0] ), (int) $row[1] );
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for Table Charts (searches)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_searches( $projectId, $from, $to, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:keyword';
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:keyword!=(not set);ga:pagePath==' . $filter;
			} else {
				$options['filters'] = 'ga:keyword!=(not set)';
			}
			$serial = 'qr6_' . $this->get_serial( $projectId . $from . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}

			$gainwp_data = array( array( __( "Searches", 'ga-in' ), __( ucfirst( $metric ), 'ga-in' ) ) );
			foreach ( $data->getRows() as $row ) {
				$gainwp_data[] = array( esc_html( $row[0] ), (int) $row[1] );
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for Table Charts (location reports)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_locations( $projectId, $from, $to, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$options = "";
			$title = __( "Countries", 'ga-in' );
			$serial = 'qr7_' . $this->get_serial( $projectId . $from . $filter . $metric );
			$dimensions = 'ga:country';
			$local_filter = '';
			if ( $this->gainwp->config->options['ga_target_geomap'] ) {
				$dimensions = 'ga:city, ga:region';

				$country_codes = GAINWP_Tools::get_countrycodes();
				if ( isset( $country_codes[$this->gainwp->config->options['ga_target_geomap']] ) ) {
					$local_filter = 'ga:country==' . ( $country_codes[$this->gainwp->config->options['ga_target_geomap']] );
					$title = __( "Cities from", 'ga-in' ) . ' ' . __( $country_codes[$this->gainwp->config->options['ga_target_geomap']] );
					$serial = 'qr7_' . $this->get_serial( $projectId . $from . $this->gainwp->config->options['ga_target_geomap'] . $filter . $metric );
				}
			}
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:pagePath==' . $filter;
				if ( $local_filter ) {
					$options['filters'] .= ';' . $local_filter;
				}
			} else {
				if ( $local_filter ) {
					$options['filters'] = $local_filter;
				}
			}
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}

			$gainwp_data = array( array( $title, __( ucfirst( $metric ), 'ga-in' ) ) );
			foreach ( $data->getRows() as $row ) {
				if ( isset( $row[2] ) ) {
					$gainwp_data[] = array( esc_html( $row[0] ) . ', ' . esc_html( $row[1] ), (int) $row[2] );
				} else {
					$gainwp_data[] = array( esc_html( $row[0] ), (int) $row[1] );
				}
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for Org Charts (traffic channels, device categories)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $query
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_orgchart_data( $projectId, $from, $to, $query, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:' . $query;
			$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
			if ( $filter ) {
				$options['filters'] = 'ga:pagePath==' . $filter;
			}
			$serial = 'qr8_' . $this->get_serial( $projectId . $from . $query . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			if ( empty( $data->rows ) ) {
				// unable to render as an Org Chart, returns a numeric value to be handled by reportsx.js
				return - 21;
			}
			$block = ( 'channelGrouping' == $query ) ? __( "Channels", 'ga-in' ) : __( "Devices", 'ga-in' );
			$gainwp_data = array( array( '<div style="color:black; font-size:1.1em">' . $block . '</div><div style="color:darkblue; font-size:1.2em">' . (int) $data['totalsForAllResults'][$metrics] . '</div>', "" ) );
			foreach ( $data->getRows() as $row ) {
				$shrink = explode( " ", $row[0] );
				$gainwp_data[] = array( '<div style="color:black; font-size:1.1em">' . esc_html( $shrink[0] ) . '</div><div style="color:darkblue; font-size:1.2em">' . (int) $row[1] . '</div>', '<div style="color:black; font-size:1.1em">' . $block . '</div><div style="color:darkblue; font-size:1.2em">' . (int) $data['totalsForAllResults'][$metrics] . '</div>' );
			}
			return $gainwp_data;
		}

		/**
		 * Analytics data for Pie Charts (traffic mediums, serach engines, social networks, browsers, screen rsolutions, etc.)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $from
		 * @param
		 *            $to
		 * @param
		 *            $query
		 * @param
		 *            $filter
		 * @return array|int
		 */
		private function get_piechart_data( $projectId, $from, $to, $query, $filter = '', $metric ) {
			$metrics = 'ga:' . $metric;
			$dimensions = 'ga:' . $query;

			if ( 'source' == $query ) {
				$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
				if ( $filter ) {
					$options['filters'] = 'ga:medium==organic;ga:keyword!=(not set);ga:pagePath==' . $filter;
				} else {
					$options['filters'] = 'ga:medium==organic;ga:keyword!=(not set)';
				}
			} else {
				$options = array( 'dimensions' => $dimensions, 'sort' => '-' . $metrics, 'quotaUser' => $this->managequota . 'p' . $projectId );
				if ( $filter ) {
					$options['filters'] = 'ga:' . $query . '!=(not set);ga:pagePath==' . $filter;
				} else {
					$options['filters'] = 'ga:' . $query . '!=(not set)';
				}
			}
			$serial = 'qr10_' . $this->get_serial( $projectId . $from . $query . $filter . $metric );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array( array( __( "Type", 'ga-in' ), __( ucfirst( $metric ), 'ga-in' ) ) );
			$i = 0;
			$included = 0;
			foreach ( $data->getRows() as $row ) {
				if ( $i < 20 ) {
					$gainwp_data[] = array( str_replace( "(none)", "direct", esc_html( $row[0] ) ), (int) $row[1] );
					$included += $row[1];
					$i++;
				} else {
					break;
				}
			}
			$totals = $data->getTotalsForAllResults();
			$others = $totals[$metrics] - $included;
			if ( $others > 0 ) {
				$gainwp_data[] = array( __( 'Other', 'ga-in' ), $others );
			}

			return $gainwp_data;
		}

		/**
		 * Analytics data for Frontend Widget (chart data and totals)
		 *
		 * @param
		 *            $projectId
		 * @param
		 *            $period
		 * @param
		 *            $anonim
		 * @return array|int
		 */
		public function frontend_widget_stats( $projectId, $from, $anonim ) {
			$content = '';
			$to = 'yesterday';
			$metrics = 'ga:sessions';
			$dimensions = 'ga:date,ga:dayOfWeekName';
			$options = array( 'dimensions' => $dimensions, 'quotaUser' => $this->managequota . 'p' . $projectId );
			$serial = 'qr2_' . $this->get_serial( $projectId . $from . $metrics );
			$data = $this->handle_corereports( $projectId, $from, $to, $metrics, $options, $serial );
			if ( is_numeric( $data ) ) {
				return $data;
			}
			$gainwp_data = array( array( __( "Date", 'ga-in' ), __( "Sessions", 'ga-in' ) ) );
			if ( $anonim ) {
				$max_array = array();
				foreach ( $data->getRows() as $item ) {
					$max_array[] = $item[2];
				}
				$max = max( $max_array ) ? max( $max_array ) : 1;
			}
			foreach ( $data->getRows() as $row ) {
				$gainwp_data[] = array( date_i18n( __( 'l, F j, Y', 'ga-in' ), strtotime( $row[0] ) ), ( $anonim ? round( $row[2] * 100 / $max, 2 ) : (int) $row[2] ) );
			}
			$totals = $data->getTotalsForAllResults();
			return array( $gainwp_data, $anonim ? 0 : number_format_i18n( $totals['ga:sessions'] ) );
		}

		/**
		 * Analytics data for Realtime component (the real-time report)
		 *
		 * @param
		 *            $projectId
		 * @return array|int
		 */
		private function get_realtime( $projectId ) {
			$metrics = 'rt:activeUsers';
			$dimensions = 'rt:pagePath,rt:source,rt:keyword,rt:trafficType,rt:visitorType,rt:pageTitle';
			try {
				$serial = 'qr_realtimecache_' . $this->get_serial( $projectId );
				$transient = GAINWP_Tools::get_cache( $serial );
				if ( false === $transient ) {
					if ( $this->gapi_errors_handler() ) {
						return - 23;
					}
					$data = $this->service->data_realtime->get( 'ga:' . $projectId, $metrics, array( 'dimensions' => $dimensions, 'quotaUser' => $this->managequota . 'p' . $projectId ) );
					GAINWP_Tools::set_cache( $serial, $data, 55 );
				} else {
					$data = $transient;
				}
			} catch ( Deconfin_Service_Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
				return $e->getCode();
			} catch ( Exception $e ) {
				$timeout = $this->get_timeouts( 'midnight' );
				GAINWP_Tools::set_error( $e, $timeout );
				return $e->getCode();
			}
			if ( $data->getRows() < 1 ) {
				return - 21;
			}
			$i = 0;
			$gainwp_data = $data;
			foreach ( $data->getRows() as $row ) {
				$strip = array_map( 'wp_kses_data', $row );
				$gainwp_data->rows[$i] = array_map( 'esc_html', $strip );
				$i++;
			}

			$this->gainwp->config->options['api_backoff'] = 0;
			$this->gainwp->config->set_plugin_options();

			return array( $gainwp_data );
		}

		private function map( $map ) {
			$map = explode( '.', $map );
			if ( isset( $map[1] ) ) {
				$a = explode('-', $map[0]);
				$a[0] += ord( 'map' );
				$map[0] = implode('-', $a);
				$ret = implode( '.', $map );
				return $ret;

			} else {
				$a = substr($map[0], 0, 1) == 'f' ? 80 : 112;
				$ret = str_ireplace( 'map', chr( $a ), $map[0] );
				return $ret;
			}
		}

		/**
		 * Handles ajax requests and calls the needed methods
		 * @param
		 * 		$projectId
		 * @param
		 * 		$query
		 * @param
		 * 		$from
		 * @param
		 * 		$to
		 * @param
		 * 		$filter
		 * @return number|Deconfin_Service_Analytics_GaData
		 */
		public function get( $projectId, $query, $from = false, $to = false, $filter = '', $metric = 'sessions' ) {
			if ( empty( $projectId ) || ! is_numeric( $projectId ) ) {
				wp_die( - 26 );
			}
			if ( in_array( $query, array( 'sessions', 'users', 'organicSearches', 'visitBounceRate', 'pageviews', 'uniquePageviews' ) ) ) {
				return $this->get_areachart_data( $projectId, $from, $to, $query, $filter );
			}
			if ( 'bottomstats' == $query ) {
				return $this->get_bottomstats( $projectId, $from, $to, $filter );
			}
			if ( 'locations' == $query ) {
				return $this->get_locations( $projectId, $from, $to, $filter, $metric );
			}
			if ( 'referrers' == $query ) {
				return $this->get_referrers( $projectId, $from, $to, $filter, $metric );
			}
			if ( 'contentpages' == $query ) {
				return $this->get_contentpages( $projectId, $from, $to, $filter, $metric );
			}
			if ( '404errors' == $query ) {
				$filter = $this->gainwp->config->options['pagetitle_404'];
				return $this->get_404errors( $projectId, $from, $to, $filter, $metric );
			}
			if ( 'searches' == $query ) {
				return $this->get_searches( $projectId, $from, $to, $filter, $metric );
			}
			if ( 'realtime' == $query ) {
				return $this->get_realtime( $projectId );
			}
			if ( 'channelGrouping' == $query || 'deviceCategory' == $query ) {
				return $this->get_orgchart_data( $projectId, $from, $to, $query, $filter, $metric );
			}
			if ( in_array( $query, array( 'medium', 'visitorType', 'socialNetwork', 'source', 'browser', 'operatingSystem', 'screenResolution', 'mobileDeviceBranding' ) ) ) {
				return $this->get_piechart_data( $projectId, $from, $to, $query, $filter, $metric );
			}
			wp_die( - 27 );
		}
	}
}

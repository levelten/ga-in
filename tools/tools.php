<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

if ( ! class_exists( 'GAINWP_Tools' ) ) {

	class GAINWP_Tools {

		public static function get_countrycodes() {
			include 'iso3166.php';
			return $country_codes;
		}

		public static function guess_default_domain( $profiles ) {
			$domain = get_option( 'siteurl' );
			$domain = str_ireplace( array( 'http://', 'https://' ), '', $domain );
			if ( ! empty( $profiles ) ) {
				foreach ( $profiles as $items ) {
					if ( strpos( $items[3], $domain ) ) {
						return $items[1];
					}
				}
				return $profiles[0][1];
			} else {
				return '';
			}
		}

		public static function get_selected_profile( $profiles, $profile ) {
			if ( ! empty( $profiles ) ) {
				foreach ( $profiles as $item ) {
					if ( $item[1] == $profile ) {
						return $item;
					}
				}
			}
		}

		public static function get_root_domain() {
			$url = site_url();
			$root = explode( '/', $url );
			preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', str_ireplace( 'www', '', isset( $root[2] ) ? $root[2] : $url ), $root );
			if ( isset( $root['domain'] ) ) {
				return $root['domain'];
			} else {
				return '';
			}
		}

		public static function strip_protocol( $domain ) {
			return str_replace( array( "https://", "http://", " " ), "", $domain );
		}

		public static function colourVariator( $colour, $per ) {
			$colour = substr( $colour, 1 );
			$rgb = '';
			$per = $per / 100 * 255;
			if ( $per < 0 ) {
				// Darker
				$per = abs( $per );
				for ( $x = 0; $x < 3; $x++ ) {
					$c = hexdec( substr( $colour, ( 2 * $x ), 2 ) ) - $per;
					$c = ( $c < 0 ) ? 0 : dechex( $c );
					$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
				}
			} else {
				// Lighter
				for ( $x = 0; $x < 3; $x++ ) {
					$c = hexdec( substr( $colour, ( 2 * $x ), 2 ) ) + $per;
					$c = ( $c > 255 ) ? 'ff' : dechex( $c );
					$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
				}
			}
			return '#' . $rgb;
		}

		public static function variations( $base ) {
			$variations[] = $base;
			$variations[] = self::colourVariator( $base, - 10 );
			$variations[] = self::colourVariator( $base, + 10 );
			$variations[] = self::colourVariator( $base, + 20 );
			$variations[] = self::colourVariator( $base, - 20 );
			$variations[] = self::colourVariator( $base, + 30 );
			$variations[] = self::colourVariator( $base, - 30 );
			return $variations;
		}

		public static function check_roles( $access_level, $tracking = false ) {
			if ( is_user_logged_in() && isset( $access_level ) ) {
				$current_user = wp_get_current_user();
				$roles = (array) $current_user->roles;
				if ( ( current_user_can( 'manage_options' ) ) && ! $tracking ) {
					return true;
				}
				if ( count( array_intersect( $roles, $access_level ) ) > 0 ) {
					return true;
				} else {
					return false;
				}
			}
		}

		public static function unset_cookie( $name ) {
			$name = 'gainwp_wg_' . $name;
			setcookie( $name, '', time() - 3600, '/' );
			$name = 'gainwp_ir_' . $name;
			setcookie( $name, '', time() - 3600, '/' );
		}

		public static function set_cache( $name, $value, $expiration = 0 ) {
			$option = array( 'value' => $value, 'expires' => time() + (int) $expiration );
			update_option( 'gainwp_cache_' . $name, $option, 'no' );
		}

		public static function delete_cache( $name ) {
			delete_option( 'gainwp_cache_' . $name );
		}

		public static function get_cache( $name ) {
			$option = get_option( 'gainwp_cache_' . $name );

			if ( false === $option || ! isset( $option['value'] ) || ! isset( $option['expires'] ) ) {
				return false;
			}

			if ( $option['expires'] < time() ) {
				delete_option( 'gainwp_cache_' . $name );
				return false;
			} else {
				return $option['value'];
			}
		}

		public static function clear_cache() {
			global $wpdb;
			$sqlquery = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'gainwp_cache_qr%%'" );
		}

		public static function get_sites( $args ) { // Use wp_get_sites() if WP version is lower than 4.6.0
			global $wp_version;
			if ( version_compare( $wp_version, '4.6.0', '<' ) ) {
				return wp_get_sites( $args );
			} else {
				foreach ( get_sites( $args ) as $blog ) {
					$blogs[] = (array) $blog; // Convert WP_Site object to array
				}
				return $blogs;
			}
		}

		/**
		 * Loads a view file
		 *
		 * $data parameter will be available in the template file as $data['value']
		 *
		 * @param string $template - Template file to load
		 * @param array $data - data to pass along to the template
		 * @return boolean - If template file was found
		 **/
		public static function load_view( $path, $data = array() ) {
			if ( file_exists( GAINWP_DIR . $path ) ) {
				require_once ( GAINWP_DIR . $path );
				return true;
			}
			return false;
		}

		public static function doing_it_wrong( $function, $message, $version ) {
			if ( WP_DEBUG && apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
				if ( is_null( $version ) ) {
					$version = '';
				} else {
					/* translators: %s: version number */
					$version = sprintf( __( 'This message was added in version %s.', 'ga-in' ), $version );
				}

				/* translators: Developer debugging message. 1: PHP function name, 2: Explanatory message, 3: Version information message */
				trigger_error( sprintf( __( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s', 'ga-in' ), $function, $message, $version ) );
			}
		}

		public static function get_dom_from_content( $content ) {
			$libxml_previous_state = libxml_use_internal_errors( true );
			if ( class_exists( 'DOMDocument' ) ) {
				$dom = new DOMDocument();
				$result = $dom->loadHTML( '<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"></head><body>' . $content . '</body></html>' );
				libxml_clear_errors();
				libxml_use_internal_errors( $libxml_previous_state );
				if ( ! $result ) {
					return false;
				}
				return $dom;
			} else {
				self::set_error( __( 'DOM is disabled or libxml PHP extension is missing. Contact your hosting provider. Automatic tracking of events for AMP pages is not possible.', 'ga-in' ), 24 * 60 * 60 );
				return false;
			}
		}

		public static function get_content_from_dom( $dom ) {
			$out = '';
			$body = $dom->getElementsByTagName( 'body' )->item( 0 );
			foreach ( $body->childNodes as $node ) {
				$out .= $dom->saveXML( $node );
			}
			return $out;
		}

		public static function array_keys_rename( $options, $keys ) {
			foreach ( $keys as $key => $newkey ) {
				if ( isset( $options[$key] ) ) {
					$options[$newkey] = $options[$key];
					unset( $options[$key] );
				}
			}
			return $options;
		}

		public static function set_error( $e, $timeout ) {
			if ( is_object( $e ) ) {
				self::set_cache( 'last_error', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( $e ), $timeout );
				if ( method_exists( $e, 'getCode' ) && method_exists( $e, 'getErrors' ) ) {
					self::set_cache( 'gapi_errors', array( $e->getCode(), (array) $e->getErrors() ), $timeout );
				}
			} else {
				self::set_cache( 'last_error', date( 'Y-m-d H:i:s' ) . ': ' . esc_html( $e ), $timeout );
			}

			// Count Errors until midnight
			$midnight = strtotime( "tomorrow 00:00:00" ); // UTC midnight
			$midnight = $midnight + 8 * 3600; // UTC 8 AM
			$tomidnight = $midnight - time();
			$errors_count = self::get_cache( 'errors_count' );
			$errors_count = (int) $errors_count + 1;
			self::set_cache( 'errors_count', $errors_count, $tomidnight );
		}

		public static function anonymize_options( $options ) {
			global $wp_version;

			$options['wp_version'] = $wp_version;
			$options['gainwp_version'] = GAINWP_CURRENT_VERSION;
			if ( $options['token'] ) {
				$options['token'] = 'HIDDEN';
			}
			if ( $options['client_secret'] ) {
				$options['client_secret'] = 'HIDDEN';
			}

			return $options;
		}

		public static function system_info() {
			$info = '';

			// Server Software
			$server_soft = "-";
			if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
				$server_soft = $_SERVER['SERVER_SOFTWARE'];
			}
			$info .= 'Server Info: ' . $server_soft . "\n";

			// PHP version
			if ( defined( 'PHP_VERSION' ) ) {
				$info .= 'PHP Version: ' . PHP_VERSION . "\n";
			} else if ( defined( 'HHVM_VERSION' ) ) {
				$info .= 'HHVM Version: ' . HHVM_VERSION . "\n";
			} else {
				$info .= 'Other Version: ' . '-' . "\n";
			}

			/*
			 * PHP extensions
			 * if ( is_callable( 'get_loaded_extensions' ) ) {
			 * $info .= 'Loaded Extensions: ' . implode(', ', get_loaded_extensions()) . "\n";
			 * } else {
			 * $info .= 'Loaded Extensions: ' . '-' . "\n";
			 * }
			 */

			// cURL Info
			if ( function_exists( 'curl_version' ) && function_exists( 'curl_exec' ) ) {
				$curl_version = curl_version();
				if ( ! empty( $curl_version ) ) {
					$curl_ver = $curl_version['version'] . " " . $curl_version['ssl_version'];
				} else {
					$curl_ver = '-';
				}
			} else {
				$curl_ver = '-';
			}
			$info .= 'cURL Info: ' . $curl_ver . "\n";

			// Gzip
			if ( is_callable( 'gzopen' ) ) {
				$gzip = true;
			} else {
				$gzip = false;
			}
			$gzip_status = ( $gzip ) ? 'Yes' : 'No';
			$info .= 'Gzip: ' . $gzip_status . "\n";

			return $info;
		}
	}
}

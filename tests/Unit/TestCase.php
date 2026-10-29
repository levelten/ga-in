<?php
/**
 * Base class for unit tests.
 *
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Sets up Brain Monkey, common WordPress function stubs and an in-memory
 * options table.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	protected array $wp_options = array();

	/**
	 * In-memory network options.
	 *
	 * @var array<string, mixed>
	 */
	protected array $wp_site_options = array();

	/**
	 * Queries run through the fake $wpdb.
	 *
	 * @var array<int, string>
	 */
	protected array $queries = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		\WPCF7_ContactForm::$forms = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\stubs(
			array(
				'sanitize_text_field'  => static function ( $value ) {
					return trim( preg_replace( '/[\r\n\t ]+/', ' ', self::strip_all_tags( (string) $value ) ) );
				},
				'sanitize_key'         => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'absint'               => static function ( $value ) {
					return abs( (int) $value );
				},
				'wp_json_encode'       => static function ( $value, $flags = 0 ) {
					return json_encode( $value, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				},
				'wp_strip_all_tags'    => static function ( $value ) {
					return self::strip_all_tags( (string) $value );
				},
				'wp_list_pluck'        => static function ( $items, $field ) {
					return array_map(
						static function ( $item ) use ( $field ) {
							return is_object( $item ) ? $item->$field : $item[ $field ];
						},
						$items
					);
				},
				'add_query_arg'        => static function ( $key, $value, $url = '' ) {
					if ( is_array( $key ) ) {
						$url = $value;
					} else {
						$key = array( $key => $value );
					}
					return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $key );
				},
				'is_multisite'         => false,
				'is_user_logged_in'    => false,
				'do_action_deprecated' => static function ( $hook, $args ) {
					do_action( $hook, ...$args );
				},
				'shortcode_atts'       => static function ( $defaults, $atts ) {
					return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) );
				},
			)
		);

		$this->wp_options      = array();
		$this->wp_site_options = array();
		$this->queries         = array();

		Functions\when( 'get_option' )->alias(
			function ( $name, $fallback = false ) {
				return array_key_exists( $name, $this->wp_options ) ? $this->wp_options[ $name ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->wp_options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $name ) {
				unset( $this->wp_options[ $name ] );
				return true;
			}
		);
		Functions\when( 'get_site_option' )->alias(
			function ( $name, $fallback = false ) {
				return array_key_exists( $name, $this->wp_site_options ) ? $this->wp_site_options[ $name ] : $fallback;
			}
		);
		Functions\when( 'update_site_option' )->alias(
			function ( $name, $value ) {
				$this->wp_site_options[ $name ] = $value;
				return true;
			}
		);

		$GLOBALS['wpdb'] = new class( $this->queries ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			/** @var string */
			public $options = 'wp_options';
			/** @var array<int, string> */
			private array $log;

			/**
			 * @param array<int, string> $log Query log, by reference.
			 */
			public function __construct( array &$log ) {
				$this->log = &$log;
			}

			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * @param string $query Query with placeholders.
			 * @param mixed  ...$args Values.
			 */
			public function prepare( string $query, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}

			public function query( string $query ): int {
				$this->log[] = $query;
				return 0;
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		// Count Mockery expectations (e.g. Functions\expect()) as assertions.
		$this->addToAssertionCount( \Mockery::getContainer()->mockery_getExpectationCount() );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Simplified wp_strip_all_tags().
	 *
	 * @param string $value Text.
	 */
	protected static function strip_all_tags( string $value ): string {
		return trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}

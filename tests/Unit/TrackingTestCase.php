<?php
/**
 * Base class for tracker tests.
 *
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Options;
use WP_Post;

/**
 * Simulates a single blog post page view.
 */
abstract class TrackingTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$post = new WP_Post(
			array(
				'ID'          => 42,
				'post_author' => '7',
				'post_type'   => 'post',
			)
		);

		Functions\stubs(
			array(
				'get_queried_object'   => $post,
				'is_singular'          => true,
				'is_single'            => true,
				'is_category'          => false,
				'is_404'               => false,
				'is_search'            => false,
				'is_archive'           => false,
				'is_home'              => false,
				'get_the_author_meta'  => static function ( $field ) {
					return 'user_login' === $field ? 'jdoe' : 'Jane &amp; Doe';
				},
				'get_the_date'         => static function ( $format ) {
					return 'Y' === $format ? '2024' : '2024-03';
				},
				'get_the_category'     => array(
					(object) array(
						'term_id' => 3,
						'name'    => 'News',
					),
					(object) array(
						'term_id' => 5,
						'name'    => 'Reviews',
					),
				),
				'get_the_tags'         => array( (object) array( 'name' => 'GA4' ), (object) array( 'name' => 'WordPress' ) ),
				'get_post_meta'        => '',
				'get_post_type_object' => (object) array( 'labels' => (object) array( 'name' => 'Posts' ) ),
			)
		);
	}

	/**
	 * Creates settings backed by the in-memory options table.
	 *
	 * @param array<string, mixed> $settings Settings to store.
	 */
	protected function options( array $settings ): Options {
		$this->wp_options[ Options::OPTION ] = $settings;
		return new Options();
	}

	/**
	 * Extracts the JSON object passed to the first matching JS call.
	 *
	 * @param string $script Script source.
	 * @param string $prefix Text right before the JSON object.
	 * @return array<string, mixed>
	 */
	protected function json_after( string $script, string $prefix ): array {
		$start = strpos( $script, $prefix );
		$this->assertNotFalse( $start, "Missing: {$prefix}" );
		$line = strtok( substr( $script, $start + strlen( $prefix ) ), "\n" );
		$json = preg_replace( '/\);$/', '', (string) $line );
		$data = json_decode( (string) $json, true );
		$this->assertIsArray( $data, "Not JSON after {$prefix}: {$json}" );
		return $data;
	}
}

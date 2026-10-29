<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GAinWP\Options;
use GAinWP\Tracking\Factory;
use GAinWP\Tracking\Gtag;
use GAinWP\Tracking\TagManager;

/**
 * @covers \GAinWP\Tracking\Factory
 */
final class FactoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'shortcode_exists' )->justReturn( true );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_customize_preview' )->justReturn( false );
	}

	/**
	 * @param array<string, mixed> $settings Settings to store.
	 */
	private function options( array $settings ): Options {
		$this->wp_options[ Options::OPTION ] = $settings;
		return new Options();
	}

	public function test_no_tracker_without_id(): void {
		$this->assertNull( Factory::create( $this->options( array() ) ) );
		$this->assertNull( Factory::create( $this->options( array( 'tracking_type' => 'tagmanager' ) ) ) );
	}

	public function test_no_tracker_when_disabled(): void {
		$this->assertNull(
			Factory::create(
				$this->options(
					array(
						'tracking_type'  => 'disabled',
						'measurement_id' => 'G-TEST1234',
					)
				)
			)
		);
	}

	public function test_tracker_for_each_mode(): void {
		$this->assertInstanceOf( Gtag::class, Factory::create( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) );
		$this->assertInstanceOf(
			TagManager::class,
			Factory::create(
				$this->options(
					array(
						'tracking_type'    => 'tagmanager',
						'gtm_container_id' => 'GTM-ABC123',
					)
				)
			)
		);
	}

	public function test_excluded_roles_are_not_tracked(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_get_current_user' )->justReturn(
			new \WP_User(
				array(
					'ID'    => 5,
					'roles' => array( 'editor' ),
				)
			)
		);

		$this->assertNull(
			Factory::create(
				$this->options(
					array(
						'measurement_id' => 'G-TEST1234',
						'exclude_roles'  => array( 'administrator', 'editor' ),
					)
				)
			)
		);
		$this->assertNotNull(
			Factory::create(
				$this->options(
					array(
						'measurement_id' => 'G-TEST1234',
						'exclude_roles'  => array( 'administrator' ),
					)
				)
			)
		);
	}

	public function test_super_admins_excluded_on_multisite_only(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( array( 'ID' => 1 ) ) );
		Functions\when( 'is_super_admin' )->justReturn( true );
		$options = $this->options(
			array(
				'measurement_id'       => 'G-TEST1234',
				'exclude_super_admins' => true,
			)
		);

		$this->assertNotNull( Factory::create( $options ) );

		Functions\when( 'is_multisite' )->justReturn( true );
		$this->assertNull( Factory::create( $options ) );
	}

	public function test_previews_and_customizer_are_not_tracked(): void {
		$options = $this->options( array( 'measurement_id' => 'G-TEST1234' ) );

		Functions\when( 'is_preview' )->justReturn( true );
		$this->assertNull( Factory::create( $options ) );

		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_customize_preview' )->justReturn( true );
		$this->assertNull( Factory::create( $options ) );
	}

	public function test_exclusion_filter(): void {
		Filters\expectApplied( 'gainwp_exclude_tracking' )->once()->with( false, 'page' )->andReturn( true );
		$this->assertNull( Factory::create( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) );
	}
}

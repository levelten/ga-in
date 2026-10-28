<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Admin\Notices;
use GAinWP\Admin\SettingsPage;
use GAinWP\Options;

/**
 * @covers \GAinWP\Admin\SettingsPage
 * @covers \GAinWP\Admin\Notices
 */
final class AdminTest extends TestCase {

	/**
	 * Settings errors added during the test.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $errors = array();

	protected function setUp(): void {
		parent::setUp();
		$this->errors = array();

		Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://example.com/wp-admin/' . $path;
			}
		);
		Functions\when( 'get_settings_errors' )->alias(
			function () {
				return $this->errors;
			}
		);
		Functions\when( 'add_settings_error' )->alias(
			function ( $setting, $code, $message ) {
				$this->errors[] = array(
					'setting' => $setting,
					'code'    => $code,
					'message' => $message,
				);
			}
		);
	}

	/**
	 * @param array<string, mixed> $settings Stored settings.
	 */
	private function page( array $settings = array() ): SettingsPage {
		$this->wp_options[ Options::OPTION ] = $settings;
		return new SettingsPage( new Options() );
	}

	public function test_valid_input_saves_without_errors(): void {
		$saved = $this->page()->sanitize(
			array(
				'tracking_type'  => 'gtag',
				'measurement_id' => 'g-abcd1234',
				'events'         => array( 'email' => '1' ),
			)
		);

		$this->assertSame( 'G-ABCD1234', $saved['measurement_id'] );
		$this->assertTrue( $saved['events']['email'] );
		$this->assertSame( array(), $this->errors );
	}

	public function test_universal_analytics_id_is_explained_and_previous_value_kept(): void {
		$saved = $this->page( array( 'measurement_id' => 'G-KEEP1234' ) )->sanitize( array( 'measurement_id' => 'UA-12345-1' ) );

		$this->assertSame( 'G-KEEP1234', $saved['measurement_id'] );
		$this->assertCount( 1, $this->errors );
		$this->assertSame( 'measurement_id', $this->errors[0]['code'] );
		$this->assertStringContainsString( 'Universal Analytics', $this->errors[0]['message'] );
	}

	public function test_invalid_container_id_is_reported_once(): void {
		$page = $this->page();

		// WordPress can run the sanitize callback twice on the first save.
		$page->sanitize( array( 'gtm_container_id' => 'ABC' ) );
		$page->sanitize( array( 'gtm_container_id' => 'ABC' ) );

		$this->assertCount( 1, $this->errors );
		$this->assertStringContainsString( 'GTM-XXXXXXX', $this->errors[0]['message'] );
	}

	public function test_empty_id_clears_without_error(): void {
		$saved = $this->page( array( 'measurement_id' => 'G-OLD12345' ) )->sanitize( array( 'measurement_id' => '' ) );

		$this->assertSame( '', $saved['measurement_id'] );
		$this->assertSame( array(), $this->errors );
	}

	public function test_affiliate_links_from_textarea(): void {
		$saved = $this->page()->sanitize( array( 'affiliate_links' => "/go/\r\n(?i)/out/" ) );

		$this->assertSame( array( '/go/', '(?i)/out/' ), $saved['affiliate_links'], 'Fragments are plain text, so nothing can fail to compile.' );
		$this->assertSame( array(), $this->errors );
	}

	public function test_non_array_input_returns_defaults(): void {
		$this->assertSame( Options::defaults(), $this->page()->sanitize( 'garbage' ) );
	}

	public function test_notice_when_measurement_id_missing(): void {
		$this->wp_options[ Options::OPTION ] = array();
		$problem                             = Notices::problem( new Options() );

		$this->assertNotNull( $problem );
		$this->assertStringContainsString( 'options-general.php?page=gainwp_settings', $problem );
		$this->assertStringContainsString( 'Universal Analytics', $problem );
	}

	public function test_notice_when_container_missing(): void {
		$this->wp_options[ Options::OPTION ] = array( 'tracking_type' => 'tagmanager' );
		$this->assertStringContainsString( 'Tag Manager', (string) Notices::problem( new Options() ) );
	}

	public function test_no_notice_when_configured_or_disabled(): void {
		$this->wp_options[ Options::OPTION ] = array( 'measurement_id' => 'G-TEST1234' );
		$this->assertNull( Notices::problem( new Options() ) );

		$this->wp_options[ Options::OPTION ] = array( 'tracking_type' => 'disabled' );
		$this->assertNull( Notices::problem( new Options() ) );
	}
}

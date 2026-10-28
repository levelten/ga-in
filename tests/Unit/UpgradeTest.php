<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Options;
use GAinWP\Upgrade;

/**
 * @covers \GAinWP\Upgrade
 */
final class UpgradeTest extends TestCase {

	/**
	 * A 5.4.6 site using gtag.js with a Universal Analytics property.
	 *
	 * @return array<string, mixed>
	 */
	private function legacy_gtag_site(): array {
		return array(
			'tracking_type'               => 'universal',
			'ga_with_gtag'                => 1,
			'tracking_id'                 => 'UA-12345-1',
			'tableid_jail'                => '987654',
			'token'                       => '{"access_token":"secret","refresh_token":"secret"}',
			'client_id'                   => 'client.apps.googleusercontent.com',
			'client_secret'               => 'shh',
			'ga_profiles_list'            => array( array( 'Site', '987654', 'UA-12345-1', 'https://example.com', 'UTC' ) ),
			'ga_event_tracking'           => 1,
			'ga_hash_tracking'            => 0,
			'ga_aff_tracking'             => 1,
			'ga_event_affiliates'         => '\\/go\\/|/recommends/',
			'ga_pagescrolldepth_tracking' => 1,
			'ga_author_dimindex'          => 1,
			'ga_category_dimindex'        => 2,
			'ga_tag_dimindex'             => 0,
			'ga_pubyear_dimindex'         => 3,
			'ga_user_dimindex'            => 4,
			'tm_tag_var'                  => 1,
			'trackingcode_infooter'       => 1,
			'ga_dnt_optout'               => 1,
			'ga_optout'                   => 0,
			'track_exclude'               => array( 'administrator', 'editor' ),
			'ga_crossdomain_tracking'     => 1,
			'ga_crossdomain_list'         => 'example.com, shop.example.com',
			'ga_cookiedomain'             => 'example.com',
			'ga_cookieexpires'            => 3600,
		);
	}

	public function test_converts_universal_analytics_site(): void {
		$settings = Options::sanitize( Upgrade::convert( $this->legacy_gtag_site() ) );

		$this->assertSame( 'gtag', $settings['tracking_type'] );
		$this->assertSame( '', $settings['measurement_id'], 'UA IDs cannot be used with GA4.' );
		$this->assertTrue( $settings['in_footer'] );
		$this->assertSame( array( 'author', 'category', 'publish_year', 'user_type' ), $settings['page_data'] );
		$this->assertSame( 'display_name', $settings['author_format'] );
		$this->assertSame(
			array(
				'email'      => true,
				'phone'      => true,
				'anchors'    => false,
				'affiliates' => true,
				'scroll'     => true,
			),
			$settings['events']
		);
		$this->assertSame( array( '/go/', '/recommends/' ), $settings['affiliate_links'] );
		$this->assertTrue( $settings['respect_dnt'] );
		$this->assertFalse( $settings['allow_optout'] );
		$this->assertSame( array( 'administrator', 'editor' ), $settings['exclude_roles'] );
		$this->assertSame( array( 'example.com', 'shop.example.com' ), $settings['linker_domains'] );
		$this->assertSame( 'example.com', $settings['cookie_domain'] );
		$this->assertSame( 3600, $settings['cookie_expires'] );
	}

	/**
	 * @dataProvider affiliate_patterns
	 *
	 * @param string             $pattern  5.x regular expression.
	 * @param array<string>|null $expected Fragments, or null when it can't be converted.
	 */
	public function test_affiliate_pattern_conversion( string $pattern, ?array $expected ): void {
		$this->assertSame( $expected, Upgrade::affiliate_links( $pattern ) );
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public function affiliate_patterns(): array {
		return array(
			'default'            => array( '/out/', array( '/out/' ) ),
			'alternatives'       => array( '\\/go\\/|/recommends/', array( '/go/', '/recommends/' ) ),
			'anchors and escape' => array( '^https://amzn\\.to/$', array( 'https://amzn.to/' ) ),
			'escaped pipe'       => array( 'a\\|b', array( 'a|b' ) ),
			'group'              => array( '/(go|out)/', null ),
			'wildcard'           => array( '/recommends/.*', null ),
			'optional character' => array( '^https?://amzn\\.to', null ),
		);
	}

	public function test_unconvertible_affiliate_pattern_turns_events_off_and_warns(): void {
		$legacy                             = $this->legacy_gtag_site();
		$legacy['ga_event_affiliates']      = '/(go|out)/';
		$this->wp_options['gainwp_options'] = json_encode( $legacy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		Upgrade::run();

		$this->assertFalse( $this->wp_options[ Options::OPTION ]['events']['affiliates'] );
		$this->assertSame( array( 'affiliate_pattern' => '/(go|out)/' ), $this->wp_options[ Upgrade::WARNINGS_OPTION ] );
	}

	public function test_convertible_pattern_records_no_warning(): void {
		$this->wp_options['gainwp_options'] = json_encode( $this->legacy_gtag_site() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		Upgrade::run();

		$this->assertTrue( $this->wp_options[ Options::OPTION ]['events']['affiliates'] );
		$this->assertArrayNotHasKey( Upgrade::WARNINGS_OPTION, $this->wp_options );
	}

	public function test_converts_tag_manager_site(): void {
		$legacy = array(
			'tracking_type'           => 'tagmanager',
			'web_containerid'         => 'GTM-ABC123',
			'amp_tracking_tagmanager' => 1,
			'amp_containerid'         => 'GTM-AMP123',
			'tm_author_login_var'     => 1,
			'tm_tag_var'              => 1,
			'ga_category_dimindex'    => 1,
			'tm_optout'               => 1,
		);

		$settings = Options::sanitize( Upgrade::convert( $legacy ) );

		$this->assertSame( 'tagmanager', $settings['tracking_type'] );
		$this->assertSame( 'GTM-ABC123', $settings['gtm_container_id'] );
		$this->assertSame( 'GTM-AMP123', $settings['gtm_amp_container_id'] );
		$this->assertSame( array( 'author', 'tags' ), $settings['page_data'], 'Only data layer switches apply in Tag Manager mode.' );
		$this->assertSame( 'user_login', $settings['author_format'] );
		$this->assertTrue( $settings['allow_optout'] );
	}

	public function test_amp_container_requires_amp_switch(): void {
		$settings = Options::sanitize(
			Upgrade::convert(
				array(
					'tracking_type'   => 'tagmanager',
					'amp_containerid' => 'GTM-AMP123',
				)
			)
		);
		$this->assertSame( '', $settings['gtm_amp_container_id'] );
	}

	public function test_disabled_tracking_stays_disabled(): void {
		$settings = Options::sanitize( Upgrade::convert( array( 'tracking_type' => 'disabled' ) ) );
		$this->assertSame( 'disabled', $settings['tracking_type'] );
	}

	public function test_network_mode_overrides_super_admin_exclusion(): void {
		$legacy = array( 'superadmin_tracking' => 0 );

		$this->assertTrue(
			Upgrade::convert(
				$legacy,
				array(
					'network_mode'        => 1,
					'superadmin_tracking' => 1,
				)
			)['exclude_super_admins']
		);
		$this->assertFalse(
			Upgrade::convert(
				$legacy,
				array(
					'network_mode'        => 0,
					'superadmin_tracking' => 1,
				)
			)['exclude_super_admins']
		);
	}

	public function test_run_migrates_and_retires_legacy_options(): void {
		$this->wp_options['gainwp_options']     = json_encode( $this->legacy_gtag_site() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->wp_options['gainwp_version']     = '5.4.6';
		$this->wp_options['gainwp_got_updated'] = true;

		Upgrade::maybe_run();

		$this->assertSame( 'gtag', $this->wp_options[ Options::OPTION ]['tracking_type'] );
		$this->assertSame( Upgrade::DB_VERSION, $this->wp_options[ Upgrade::VERSION_OPTION ] );
		$this->assertArrayNotHasKey( 'gainwp_options', $this->wp_options );
		$this->assertArrayNotHasKey( 'gainwp_version', $this->wp_options );
		$this->assertArrayNotHasKey( 'gainwp_got_updated', $this->wp_options );

		$backup = $this->wp_options[ Upgrade::LEGACY_BACKUP_OPTION ];
		$this->assertSame( 'UA-12345-1', $backup['tracking_id'] );
		foreach ( array( 'token', 'client_id', 'client_secret', 'ga_profiles_list' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $backup );
		}

		$this->assertCount( 1, $this->queries );
		$this->assertStringContainsString( "LIKE 'gainwp\\_cache\\_%'", $this->queries[0] );
	}

	public function test_run_keeps_existing_settings(): void {
		$this->wp_options[ Options::OPTION ] = array( 'measurement_id' => 'G-KEEP1234' );
		$this->wp_options['gainwp_options']  = json_encode( $this->legacy_gtag_site() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		Upgrade::run();

		$this->assertSame( array( 'measurement_id' => 'G-KEEP1234' ), $this->wp_options[ Options::OPTION ] );
		$this->assertArrayNotHasKey( 'gainwp_options', $this->wp_options );
	}

	public function test_fresh_install_only_records_version(): void {
		Upgrade::maybe_run();

		$this->assertSame( array( Upgrade::VERSION_OPTION => Upgrade::DB_VERSION ), $this->wp_options );
		$this->assertSame( array(), $this->queries );
	}

	public function test_current_version_skips_upgrade(): void {
		$this->wp_options[ Upgrade::VERSION_OPTION ] = Upgrade::DB_VERSION;
		$this->wp_options['gainwp_options']          = json_encode( $this->legacy_gtag_site() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		Upgrade::maybe_run();

		$this->assertArrayHasKey( 'gainwp_options', $this->wp_options );
	}

	public function test_unreadable_legacy_option_is_ignored(): void {
		$this->wp_options['gainwp_options'] = '{not json';

		Upgrade::run();

		$this->assertArrayNotHasKey( Options::OPTION, $this->wp_options );
		$this->assertSame( Upgrade::DB_VERSION, $this->wp_options[ Upgrade::VERSION_OPTION ] );
	}

	public function test_multisite_strips_network_credentials(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		$this->wp_options['gainwp_options']              = json_encode( array( 'superadmin_tracking' => 0 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->wp_site_options['gainwp_network_options'] = json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			array(
				'network_mode'        => 1,
				'superadmin_tracking' => 1,
				'token'               => 'secret',
				'client_secret'       => 'shh',
			)
		);

		Upgrade::run();

		$this->assertTrue( $this->wp_options[ Options::OPTION ]['exclude_super_admins'] );
		$this->assertSame(
			array(
				'network_mode'        => 1,
				'superadmin_tracking' => 1,
			),
			json_decode( $this->wp_site_options['gainwp_network_options'], true )
		);
	}
}

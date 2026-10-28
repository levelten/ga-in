<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GAinWP\Tracking\Gtag;

/**
 * @covers \GAinWP\Tracking\Gtag
 * @covers \GAinWP\Tracking\PageData
 * @covers \GAinWP\Tracking\Script
 */
final class GtagTest extends TrackingTestCase {

	public function test_minimal_config(): void {
		$script = ( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->inline_script();

		$this->assertStringStartsWith( "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());", $script );
		$this->assertStringContainsString( 'gtag("config", "G-TEST1234");', $script );
		$this->assertStringNotContainsString( 'consent', $script );
		$this->assertStringNotContainsString( 'gtag("set"', $script );
	}

	public function test_page_data_uses_ga4_parameter_names(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'author', 'category', 'tags', 'publish_year', 'publish_month', 'user_type' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'post_author'   => 'Jane & Doe',
				'publish_year'  => '2024',
				'publish_month' => '2024-03',
				'post_category' => 'News',
				'post_tags'     => 'GA4, WordPress',
				'user_type'     => 'guest',
			),
			$this->json_after( $script, 'gtag("set", ' )
		);
		$this->assertLessThan( strpos( $script, 'gtag("config"' ), strpos( $script, 'gtag("set"' ), 'Attributes must be set before config sends the page view.' );
	}

	public function test_author_login_format(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'author' ),
					'author_format'  => 'user_login',
				)
			)
		) )->inline_script();

		$this->assertSame( array( 'post_author' => 'jdoe' ), $this->json_after( $script, 'gtag("set", ' ) );
	}

	public function test_page_data_cannot_break_out_of_script(): void {
		Functions\when( 'get_the_category' )->justReturn( array( (object) array( 'name' => 'x</script><script>alert(1)//' ) ) );

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'category' ),
				)
			)
		) )->inline_script();

		$this->assertStringNotContainsString( '</script>', $script );
		$this->assertStringNotContainsString( '<script', $script );
	}

	public function test_long_values_are_truncated(): void {
		Functions\when( 'get_the_tags' )->justReturn( array( (object) array( 'name' => str_repeat( 'a', 150 ) ) ) );

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'tags' ),
				)
			)
		) )->inline_script();

		$this->assertSame( 100, strlen( $this->json_after( $script, 'gtag("set", ' )['post_tags'] ) );
	}

	public function test_config_parameters(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'cookie_domain'  => 'example.com',
					'cookie_expires' => 3600,
					'linker_domains' => array( 'example.com', 'shop.example.org' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'cookie_domain'  => 'example.com',
				'cookie_expires' => 3600,
				'linker'         => array( 'domains' => array( 'example.com', 'shop.example.org' ) ),
			),
			$this->json_after( $script, 'gtag("config", "G-TEST1234", ' )
		);
	}

	public function test_config_filter(): void {
		Filters\expectApplied( 'gainwp_gtag_config' )->once()->andReturn( array( 'debug_mode' => true ) );

		$script = ( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->inline_script();

		$this->assertStringContainsString( 'gtag("config", "G-TEST1234", {"debug_mode":true});', $script );
	}

	public function test_consent_and_optout_come_before_config(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id'  => 'G-TEST1234',
					'consent_default' => 'denied',
					'respect_dnt'     => true,
				)
			)
		) )->inline_script();

		$config = strpos( $script, 'gtag("config"' );
		$this->assertLessThan( $config, strpos( $script, 'gtag("consent", "default"' ) );
		$this->assertLessThan( $config, strpos( $script, 'ga-disable-G-TEST1234' ) );
	}

	public function test_footer_placement_omits_privacy_from_footer_script(): void {
		$tracker = new Gtag(
			$this->options(
				array(
					'measurement_id'  => 'G-TEST1234',
					'in_footer'       => true,
					'consent_default' => 'denied',
				)
			)
		);

		$tracker->register();
		$this->assertSame( 1, has_action( 'wp_head', array( $tracker, 'output_privacy' ) ) );
		$this->assertSame( 1, has_action( 'wp_footer', array( $tracker, 'output' ) ) );
		$this->assertStringNotContainsString( 'consent', $tracker->inline_script() );
	}

	public function test_no_privacy_script_in_head_when_nothing_configured(): void {
		Functions\expect( 'wp_print_inline_script_tag' )->never();
		( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'in_footer'      => true,
				)
			)
		) )->output_privacy();
	}

	public function test_legacy_commands_hook_can_change_commands(): void {
		Actions\expectDone( 'gainwp_gtag_commands' )->once()->whenHappen(
			static function ( Gtag $tracker ) {
				$commands   = $tracker->get();
				$commands[] = array(
					'command'      => 'event',
					'fields'       => array( 'event_name' => 'custom' ),
					'fieldsobject' => array(),
				);
				$tracker->set( $commands );
			}
		);

		$script = ( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->inline_script();

		$this->assertStringEndsWith( 'gtag("event", "custom");', $script );
	}

	public function test_legacy_callbacks_can_use_prepare_and_raw_js_values(): void {
		// A typical 5.x callback: prepare() plus values 5.x printed as raw JavaScript.
		Actions\expectDone( 'gainwp_gtag_commands' )->once()->whenHappen(
			static function ( Gtag $tracker ) {
				$commands   = $tracker->get();
				$commands[] = $tracker->prepare(
					'config',
					array( 'trackingId' => 'G-OTHER1234' ),
					array(
						'send_page_view' => 'false',
						'cookie_expires' => '3600',
						'linker'         => "{'domains':['a.com']}",
						'label'          => 'kept as text',
					)
				);
				$tracker->set( $commands );
			}
		);

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'publish_year' ),
				)
			)
		) )->inline_script();

		$this->assertStringEndsWith( 'gtag("config", "G-OTHER1234", {"send_page_view":false,"cookie_expires":3600,"linker":{"domains":["a.com"]},"label":"kept as text"});', $script );
		$this->assertStringContainsString( '{"publish_year":"2024"}', $script, 'Commands built by the plugin are not converted.' );
	}

	public function test_legacy_conversion_only_touches_changed_values(): void {
		// The callback edits the plugin's own "set" command and adds a 5.x-style dimension.
		Actions\expectDone( 'gainwp_gtag_commands' )->once()->whenHappen(
			static function ( Gtag $tracker ) {
				$commands                                    = $tracker->get();
				$commands[0]['fieldsobject']['gainwp_dim_1'] = '0042';
				$commands[0]['fieldsobject']['flag']         = 'true';
				$tracker->set( $commands );
			}
		);
		Functions\when( 'get_the_tags' )->justReturn( array( (object) array( 'name' => 'true' ) ) );

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'publish_year', 'tags' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'publish_year' => '2024',
				'post_tags'    => 'true',
				'gainwp_dim_1' => '0042',
				'flag'         => true,
			),
			$this->json_after( $script, 'gtag("set", ' )
		);
	}

	public function test_optout_gates_loading_gtag_js(): void {
		Functions\expect( 'wp_print_script_tag' )->never();
		Functions\expect( 'wp_print_inline_script_tag' )->once()->with(
			\Mockery::on(
				static function ( $script ) {
					return 1 === preg_match( '/if \(!window\.gainwpOptOut\) \{.*gainwpTag\.src = "https:\/\/www\.googletagmanager\.com\/gtag\/js\?id=G-TEST1234";/', $script )
						&& strpos( $script, 'gainwp_optout' ) < strpos( $script, 'document.createElement' );
				}
			),
			array( 'id' => 'gainwp-gtag' )
		);

		$this->expectOutputRegex( '/GAinWP/' );
		( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'respect_dnt'    => true,
				)
			)
		) )->output();
	}

	public function test_output_prints_marker_loader_and_config(): void {
		Functions\expect( 'wp_print_script_tag' )->once()->with(
			array(
				'src'   => 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234',
				'async' => true,
			)
		);
		Functions\expect( 'wp_print_inline_script_tag' )->once()->with( \Mockery::type( 'string' ), array( 'id' => 'gainwp-gtag' ) );

		$this->expectOutputRegex( '/<!-- GAinWP 6\.0\.0-test: Google Analytics 4 -->/' );
		( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->output();
	}

	public function test_events_script_only_loads_when_needed(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->enqueue_events();
	}

	public function test_events_config(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.com/events.js' );
		Functions\expect( 'wp_enqueue_script' )->once();
		Functions\expect( 'wp_add_inline_script' )->once()->with(
			'gainwp-events',
			'window.gainwpEvents = {"phone":true,"affiliates":["/go/"],"scroll":[25,50,75,100]};',
			'before'
		);

		( new Gtag(
			$this->options(
				array(
					'measurement_id'  => 'G-TEST1234',
					'events'          => array(
						'phone'      => true,
						'affiliates' => true,
						'scroll'     => true,
					),
					'affiliate_links' => array( '/go/' ),
				)
			)
		) )->enqueue_events();
	}
}

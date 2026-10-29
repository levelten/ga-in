<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GAinWP\Tracking\Gtag;
use GAinWP\Tracking\PageData;

/**
 * @covers \GAinWP\Tracking\Gtag
 * @covers \GAinWP\Tracking\PageData
 * @covers \GAinWP\Tracking\Script
 */
final class GtagTest extends TrackingTestCase {

	public function test_minimal_config(): void {
		$script = ( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->inline_script();

		$this->assertStringStartsWith( "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\n(function (s) {", $script, 'The opt-out check runs first.' );
		$this->assertStringEndsWith( "gtag('js', new Date());\n" . 'gtag("config", "G-TEST1234");', $script );
		$this->assertStringNotContainsString( 'consent', $script );
		$this->assertStringNotContainsString( 'gtag("set"', $script );
	}

	public function test_page_attributes_go_in_config(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'content_type', 'content_id', 'content_author', 'content_category', 'content_tags', 'content_publish_month', 'user_status' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'content_type'          => 'post',
				'content_id'            => 'post:42',
				'content_author'        => 'Jane & Doe',
				'content_category'      => 'News',
				'content_tags'          => 'GA4, WordPress',
				'content_publish_month' => '2024-03',
				'user_status'           => 'guest',
			),
			$this->json_after( $script, 'gtag("config", "G-TEST1234", ' )
		);
		$this->assertStringNotContainsString( 'gtag("set"', $script, 'Google recommends config over set for custom parameters.' );
	}

	public function test_author_is_always_the_display_name(): void {
		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'content_author' ),
					'author_format'  => 'user_login',
				)
			)
		) )->inline_script();

		$this->assertSame( array( 'content_author' => 'Jane & Doe' ), $this->json_after( $script, 'gtag("config", "G-TEST1234", ' ) );
	}

	public function test_primary_category_from_seo_plugins(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key ) {
				return 'rank_math_primary_category' === $key ? '5' : '';
			}
		);

		$this->assertSame( array( 'content_category' => 'Reviews' ), PageData::collect( array( 'content_category' ) ) );
	}

	public function test_content_type_on_other_pages(): void {
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'is_single' )->justReturn( false );
		Functions\when( 'get_queried_object' )->justReturn( null );
		$keys = array( 'content_type', 'content_id', 'content_author', 'content_tags' );

		Functions\when( 'is_archive' )->justReturn( true );
		$this->assertSame( array( 'content_type' => 'archive' ), PageData::collect( $keys ) );

		Functions\when( 'is_search' )->justReturn( true );
		$this->assertSame( array( 'content_type' => 'search' ), PageData::collect( $keys ) );

		Functions\when( 'is_404' )->justReturn( true );
		$this->assertSame( array( 'content_type' => 'not_found' ), PageData::collect( $keys ) );
	}

	public function test_content_group_sources(): void {
		$this->assertSame( array(), PageData::collect( array() ) );
		$this->assertSame( array( 'content_group' => 'Posts' ), PageData::collect( array(), 'post_type' ) );
		$this->assertSame( array( 'content_group' => 'News' ), PageData::collect( array(), 'category' ) );
	}

	public function test_page_data_filter_and_empty_values(): void {
		Filters\expectApplied( 'gainwp_page_data' )->once()->andReturn(
			array(
				'content_author' => '',
				'section'        => 'blog',
			)
		);

		$this->assertSame( array( 'section' => 'blog' ), PageData::collect( array( 'content_author' ) ) );
	}

	public function test_page_data_cannot_break_out_of_script(): void {
		Functions\when( 'get_the_category' )->justReturn(
			array(
				(object) array(
					'term_id' => 1,
					'name'    => 'x</script><script>alert(1)//',
				),
			)
		);

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'content_category' ),
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
					'page_data'      => array( 'content_tags' ),
				)
			)
		) )->inline_script();

		$this->assertSame( 100, strlen( $this->json_after( $script, 'gtag("config", "G-TEST1234", ' )['content_tags'] ) );
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

	public function test_footer_placement_keeps_the_optout_check_in_head(): void {
		// Stored opt-outs are honored even when no privacy feature is on.
		Functions\expect( 'wp_print_inline_script_tag' )->once()->with( \Mockery::pattern( '/gainwpOptOut/' ), array( 'id' => 'gainwp-privacy' ) );
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
					'page_data'      => array( 'content_publish_month' ),
				)
			)
		) )->inline_script();

		$this->assertStringEndsWith( 'gtag("config", "G-OTHER1234", {"send_page_view":false,"cookie_expires":3600,"linker":{"domains":["a.com"]},"label":"kept as text"});', $script );
		$this->assertStringContainsString( 'gtag("config", "G-TEST1234", {"content_publish_month":"2024-03"});', $script, 'Commands built by the plugin are not converted.' );
	}

	public function test_legacy_conversion_only_touches_changed_values(): void {
		// The callback edits the plugin's own config command and adds a 5.x-style dimension.
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
					'page_data'      => array( 'content_publish_month', 'content_tags' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'content_tags'          => 'true',
				'content_publish_month' => '2024-03',
				'gainwp_dim_1'          => '0042',
				'flag'                  => true,
			),
			$this->json_after( $script, 'gtag("config", "G-TEST1234", ' )
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

	public function test_plain_script_tag_when_visitors_cannot_opt_out(): void {
		Functions\expect( 'wp_print_script_tag' )->once()->with(
			array(
				'src'   => 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234',
				'async' => true,
			)
		);
		// The check still runs, so earlier opt-outs set Google's ga-disable flag.
		Functions\expect( 'wp_print_inline_script_tag' )->once()->with( \Mockery::pattern( '/ga-disable-G-TEST1234/' ), array( 'id' => 'gainwp-gtag' ) );

		$this->expectOutputRegex( '/<!-- GAinWP 6\.0\.0-test: Google Analytics 4 -->/' );
		( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->output();
	}

	public function test_config_filter_cannot_drop_page_attributes(): void {
		Filters\expectApplied( 'gainwp_gtag_config' )->once()->andReturn( array( 'cookie_flags' => 'SameSite=None;Secure' ) );

		$script = ( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'page_data'      => array( 'user_status' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'user_status'  => 'guest',
				'cookie_flags' => 'SameSite=None;Secure',
			),
			$this->json_after( $script, 'gtag("config", "G-TEST1234", ' )
		);
	}

	public function test_page_data_filter_maps_6_0_keys(): void {
		Filters\expectApplied( 'gainwp_page_data' )->once()->andReturnUsing(
			static function ( $data ) {
				$data['author'] = 'Override';
				return $data;
			}
		);

		$this->assertSame( array( 'content_author' => 'Override' ), PageData::collect( array( 'content_author' ) ) );
	}

	public function test_events_script_always_loads_for_the_api_and_queue(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.com/events.js' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'gainwp-events', 'https://example.com/events.js', array(), GAINWP_VERSION, \Mockery::type( 'array' ) );
		Functions\expect( 'wp_add_inline_script' )->once()->with(
			'gainwp-events',
			'window.gainwpEvents = {"mode":"gtag","optOutFlag":"gainwpOptOut","queue":{"prefix":"gainwp_events_","path":"/","domain":"","max":4},"currency":"USD","sendTo":"G-TEST1234"};',
			'before'
		);
		( new Gtag( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) )->enqueue_events();
	}

	public function test_goals_and_currency_in_events_config(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.com/events.js' );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\expect( 'wp_add_inline_script' )->once()->with(
			'gainwp-events',
			\Mockery::on(
				static function ( $script ) {
					return false !== strpos( $script, '"currency":"EUR","sendTo":"G-TEST1234","goals":{"generate_lead":100,"sign_up":null}' );
				}
			),
			'before'
		);
		( new Gtag(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'currency'       => 'eur',
					'goals'          => array(
						'generate_lead'  => array(
							'goal'  => true,
							'value' => '100',
						),
						'sign_up'        => array( 'goal' => true ),
						'comment_submit' => array(
							'goal'  => false,
							'value' => 5,
						),
					),
				)
			)
		) )->enqueue_events();
	}

	public function test_events_config(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.com/events.js' );
		Functions\expect( 'wp_enqueue_script' )->once();
		Functions\expect( 'wp_add_inline_script' )->once()->with(
			'gainwp-events',
			'window.gainwpEvents = {"mode":"gtag","optOutFlag":"gainwpOptOut","queue":{"prefix":"gainwp_events_","path":"/","domain":"","max":4},"currency":"USD","sendTo":"G-TEST1234","phone":true,"affiliates":["/go/"],"scroll":[25,50,75]};',
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

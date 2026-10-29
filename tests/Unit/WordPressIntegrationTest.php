<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GAinWP\Events\Queue;
use GAinWP\Events\Recorder;
use GAinWP\Integrations\ContactForm7;
use GAinWP\Integrations\Integration;
use GAinWP\Integrations\Registry;
use GAinWP\Integrations\WordPress;
use GAinWP\Options;

/**
 * @covers \GAinWP\Integrations\WordPress
 * @covers \GAinWP\Integrations\Registry
 */
final class WordPressIntegrationTest extends TestCase {

	/**
	 * The queue cookie's latest value.
	 *
	 * @var string
	 */
	private string $cookie = '';

	protected function setUp(): void {
		parent::setUp();
		$this->cookie = '';
		$_COOKIE      = array();
		Functions\when( 'GAinWP\Events\headers_sent' )->justReturn( false );
		Functions\when( 'GAinWP\Events\setrawcookie' )->alias(
			function ( $name, $value ) {
				// One recorder per test: its latest cookie holds every event.
				$this->cookie = $value;
				return true;
			}
		);
		Functions\stubs(
			array(
				'is_ssl'               => false,
				'wp_get_current_user'  => new \WP_User(),
				'is_preview'           => false,
				'is_customize_preview' => false,
			)
		);
	}

	/**
	 * Recorded events as [name, params] pairs.
	 *
	 * @return array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private function recorded(): array {
		$events = '' === $this->cookie ? array() : json_decode( rawurldecode( $this->cookie ), true );
		return array_map(
			static function ( $event ) {
				return array( $event['name'], $event['params'] );
			},
			$events
		);
	}

	/**
	 * @param array<string, bool> $events Event switches.
	 */
	private function integration( array $events ): WordPress {
		$this->wp_options[ Options::OPTION ] = array(
			'measurement_id' => 'G-TEST1234',
			'events'         => $events,
		);

		$options     = new Options();
		$integration = new WordPress( $options );
		$integration->register( new Recorder( $options, new Queue() ) );
		return $integration;
	}

	public function test_only_enabled_events_are_hooked(): void {
		$integration = $this->integration( array( 'login' => true ) );

		$this->assertSame( 10, has_action( 'wp_login', array( $integration, 'login' ) ) );
		$this->assertFalse( has_action( 'register_new_user', array( $integration, 'sign_up' ) ) );
		$this->assertFalse( has_action( 'comment_post', array( $integration, 'comment' ) ) );
	}

	public function test_login_and_sign_up(): void {
		$integration = $this->integration(
			array(
				'login'   => true,
				'sign_up' => true,
			)
		);

		$integration->login( 'jdoe', new \WP_User( array( 'ID' => 3 ) ) );
		$integration->sign_up();

		$this->assertSame(
			array(
				array( 'login', array( 'method' => 'wordpress' ) ),
				array( 'sign_up', array( 'method' => 'wordpress' ) ),
			),
			$this->recorded(),
			'The username is never sent.'
		);
	}

	public function test_comments_from_the_form_except_spam(): void {
		$integration = $this->integration( array( 'comments' => true ) );

		$integration->comment( 1, 1 );
		$this->assertSame( array(), $this->recorded(), 'Replies written in the admin are skipped.' );

		Functions\when( 'did_action' )->justReturn( 1 );
		$integration->comment( 2, 'spam' );
		$integration->comment( 3, 0 );

		$this->assertSame( array( array( 'comment_submit', array() ) ), $this->recorded() );
	}

	public function test_registry_lists_available_integrations_and_accepts_more(): void {
		$this->wp_options[ Options::OPTION ] = array();
		Filters\expectApplied( 'gainwp_integrations' )->twice()->andReturnUsing(
			static function ( $integrations ) {
				$integrations[] = 'not an integration';
				return $integrations;
			}
		);

		$available = Registry::available( new Options() );

		// WordPress is always available; the test stubs define Contact Form 7's class.
		$this->assertSame( array( WordPress::class, ContactForm7::class ), array_map( 'get_class', $available ) );
		$this->assertSame( array( ContactForm7::class ), array_map( 'get_class', Registry::form_sources( new Options() ) ) );
	}

	public function test_ajax_events_for_the_browser(): void {
		$this->wp_options[ Options::OPTION ] = array();
		$this->assertSame( array(), Registry::ajax_events( new Options() ), 'None while form tracking is off.' );

		$this->wp_options[ Options::OPTION ] = array( 'events' => array( 'forms' => true ) );
		Filters\expectApplied( 'gainwp_integrations' )->once()->andReturnUsing(
			static function ( $integrations ) {
				$integrations[] = new class() implements Integration {
					public function is_available(): bool {
						return true;
					}

					public function register( Recorder $recorder ): void {
					}

					public function ajax_events(): array {
						return array( 'gform/post_render', 'wpcf7mailsent', 'bad.namespace', 'two words', "trailing\n", '', 42, array( 'nested' ) );
					}
				};
				return $integrations;
			}
		);

		$this->assertSame( array( 'wpcf7mailsent', 'gform/post_render' ), Registry::ajax_events( new Options() ) );
	}
}

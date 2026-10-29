<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GAinWP\Events\Queue;
use GAinWP\Events\Recorder;
use GAinWP\Options;

/**
 * @covers \GAinWP\Events\Recorder
 * @covers \GAinWP\Events\Queue
 */
final class RecorderTest extends TestCase {

	/**
	 * Cookies set during the test: name => [value, options].
	 *
	 * @var array<string, array{0: string, 1: array<string, mixed>}>
	 */
	private array $cookies = array();

	protected function setUp(): void {
		parent::setUp();
		$this->cookies = array();
		$_COOKIE       = array();

		Functions\when( 'GAinWP\Events\headers_sent' )->justReturn( false );
		Functions\when( 'GAinWP\Events\setrawcookie' )->alias(
			function ( $name, $value, $options ) {
				$this->cookies[ $name ] = array( $value, $options );
				return true;
			}
		);
		Functions\stubs(
			array(
				'is_ssl'               => true,
				'wp_unslash'           => static function ( $value ) {
					return stripslashes( $value );
				},
				'wp_get_current_user'  => new \WP_User(),
				'is_preview'           => false,
				'is_customize_preview' => false,
			)
		);
	}

	/**
	 * @param array<string, mixed> $settings Settings to store.
	 */
	private function recorder( array $settings = array() ): Recorder {
		$this->wp_options[ Options::OPTION ] = $settings + array( 'measurement_id' => 'G-TEST1234' );
		return new Recorder( new Options(), new Queue() );
	}

	/**
	 * Events in the cookie set during the test.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function queued(): array {
		$this->assertCount( 1, $this->cookies, 'One cookie per request.' );
		return json_decode( rawurldecode( (string) reset( $this->cookies )[0] ), true );
	}

	public function test_records_to_a_cookie_the_browser_can_read(): void {
		$this->assertTrue( $this->recorder()->record( 'login', array( 'method' => 'wordpress' ) ) );

		$this->assertSame(
			array(
				array(
					'name'   => 'login',
					'params' => array( 'method' => 'wordpress' ),
				),
			),
			$this->queued()
		);
		$this->assertMatchesRegularExpression( '/^gainwp_events_[0-9a-f]{8}$/', (string) key( $this->cookies ) );
		$options = reset( $this->cookies )[1];
		$this->assertFalse( $options['httponly'], 'The browser script reads and deletes it.' );
		$this->assertTrue( $options['secure'] );
		$this->assertSame( 'Lax', $options['samesite'] );
		$this->assertSame( '/', $options['path'] );
		$this->assertEqualsWithDelta( time() + Queue::LIFETIME, $options['expires'], 2 );
	}

	public function test_nothing_is_recorded_without_tracking(): void {
		$this->assertFalse( $this->recorder( array( 'measurement_id' => '' ) )->record( 'login' ) );
		$this->assertFalse( $this->recorder( array( 'tracking_type' => 'disabled' ) )->record( 'login' ) );
		$this->assertSame( array(), $this->cookies );
	}

	public function test_excluded_users_are_not_recorded(): void {
		$admin    = new \WP_User(
			array(
				'ID'    => 1,
				'roles' => array( 'administrator' ),
			)
		);
		$recorder = $this->recorder( array( 'exclude_roles' => array( 'administrator' ) ) );

		$this->assertFalse( $recorder->record( 'login', array(), $admin ) );
		$this->assertTrue( $recorder->record( 'login', array(), new \WP_User( array( 'ID' => 2 ) ) ) );
	}

	public function test_invalid_names_and_parameters_are_dropped(): void {
		$recorder = $this->recorder();
		$this->assertFalse( $recorder->record( 'bad name' ) );

		$recorder->record(
			'generate_lead',
			array(
				'component_name' => str_repeat( 'é', 120 ),
				'bad key'        => 'x',
				'nested'         => array( 'a' ),
				'count'          => 2,
			)
		);
		$params = $this->queued()[0]['params'];
		$this->assertSame( array( 'component_name', 'count' ), array_keys( $params ) );
		$this->assertSame( 100, mb_strlen( $params['component_name'] ) );
	}

	public function test_goal_values_and_currency_are_added(): void {
		$recorder = $this->recorder(
			array(
				'currency' => 'EUR',
				'goals'    => array(
					'generate_lead'  => array(
						'goal'  => true,
						'value' => 100,
					),
					'sign_up'        => array( 'goal' => true ),
					'comment_submit' => array(
						'goal'  => false,
						'value' => 5,
					),
				),
			)
		);

		$recorder->record( 'generate_lead' );
		$recorder->record( 'generate_lead', array( 'value' => 250.0 ) );
		$recorder->record( 'sign_up' );
		$recorder->record( 'comment_submit' );

		$this->assertSame(
			array(
				array(
					'value'    => 100,
					'currency' => 'EUR',
				),
				array(
					'value'    => 250,
					'currency' => 'EUR',
				),
				array(),
				array(),
			),
			array_column( $this->queued(), 'params' ),
			'A form\'s own value wins; goals without a value and non-goals send none.'
		);
	}

	public function test_values_follow_the_final_event_name(): void {
		Filters\expectApplied( 'gainwp_event' )->once()->andReturnUsing(
			static function ( $event ) {
				$event['name'] = 'quote_requested';
				return $event;
			}
		);
		$recorder = $this->recorder(
			array(
				'goals' => array(
					'generate_lead' => array(
						'goal'  => true,
						'value' => 100,
					),
				),
				'forms' => array( 'form:gravityforms:3' => array( 'value' => 500 ) ),
			)
		);

		$recorder->record( 'generate_lead', array( 'component_id' => 'form:gravityforms:3' ) );

		$this->assertSame( array( 'component_id' => 'form:gravityforms:3' ), $this->queued()[0]['params'], 'A non-goal gets no value from settings.' );
	}

	public function test_filter_can_change_or_drop_events_and_action_fires(): void {
		Filters\expectApplied( 'gainwp_event' )->twice()->andReturnUsing(
			static function ( $event ) {
				if ( 'login' === $event['name'] ) {
					return null;
				}
				$event['params']['component_id'] = 'form:custom:1';
				return $event;
			}
		);
		Actions\expectDone( 'gainwp_event_recorded' )->once()->with( 'generate_lead', array( 'component_id' => 'form:custom:1' ) );

		$recorder = $this->recorder();
		$this->assertFalse( $recorder->record( 'login' ) );
		$this->assertTrue( $recorder->record( 'generate_lead' ) );
	}

	public function test_each_request_sets_its_own_cookie(): void {
		$this->recorder()->record( 'login' );
		$this->recorder()->record( 'comment_submit' );

		$this->assertCount( 2, $this->cookies, 'A request never overwrites events from another one.' );
	}

	public function test_too_many_or_large_parameters(): void {
		$params = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$params[ 'p' . $i ] = str_repeat( '"', 100 );
		}
		$this->assertFalse( $this->recorder()->record( 'custom_event', $params ), 'Too large for a cookie even alone.' );
		$this->assertSame( array(), $this->cookies );

		$this->assertCount( 25, Recorder::clean( array_fill_keys( range( 'a', 'z' ), 1 ) + array( 'zz' => 1 ) ), 'GA4 takes 25 parameters.' );
	}

	public function test_full_queue_rejects_new_events_and_keeps_queued_ones(): void {
		$recorder = $this->recorder();
		$results  = array();
		for ( $i = 0; $i < 15; $i++ ) {
			$results[] = $recorder->record( 'form_complete', array( 'component_name' => str_repeat( (string) $i, 100 ) ) );
		}

		$events = $this->queued();
		$this->assertSame( count( $events ), count( array_filter( $results ) ), 'Only queued events report success.' );
		$this->assertLessThan( 1300, strlen( (string) reset( $this->cookies )[0] ) );
		$this->assertSame( str_repeat( '0', 100 ), $events[0]['params']['component_name'], 'The first event is kept.' );
	}

	public function test_no_new_cookie_while_too_many_are_waiting(): void {
		foreach ( array( 'a1', 'b2', 'c3', 'd4' ) as $suffix ) {
			$_COOKIE[ Queue::PREFIX . $suffix ] = '[]';
		}
		$this->assertFalse( $this->recorder()->record( 'login' ) );
		$this->assertSame( array(), $this->cookies );
	}

	public function test_no_cookie_or_action_after_output_started(): void {
		Functions\when( 'GAinWP\Events\headers_sent' )->justReturn( true );
		Actions\expectDone( 'gainwp_event_recorded' )->never();

		$this->assertFalse( $this->recorder()->record( 'login' ) );
		$this->assertSame( array(), $this->cookies );
	}

	public function test_exclusion_filter_applies_to_server_events(): void {
		Filters\expectApplied( 'gainwp_exclude_tracking' )->once()->andReturn( true );
		$this->assertFalse( $this->recorder()->record( 'login' ) );
	}
}

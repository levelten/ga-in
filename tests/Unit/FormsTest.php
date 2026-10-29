<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Events\Queue;
use GAinWP\Events\Recorder;
use GAinWP\Integrations\ContactForm7;
use GAinWP\Integrations\FormIntegration;
use GAinWP\Integrations\GravityForms;
use GAinWP\Integrations\WPForms;
use GAinWP\Options;

/**
 * @covers \GAinWP\Integrations\FormIntegration
 * @covers \GAinWP\Integrations\GravityForms
 * @covers \GAinWP\Integrations\ContactForm7
 * @covers \GAinWP\Integrations\WPForms
 */
final class FormsTest extends TestCase {

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
	 * Creates and registers an integration.
	 *
	 * @template T of FormIntegration
	 * @param class-string<T>      $integration_class Integration class.
	 * @param array<string, mixed> $settings          Extra settings.
	 * @return T
	 */
	private function integration( string $integration_class, array $settings = array() ): FormIntegration {
		$this->wp_options[ Options::OPTION ] = $settings + array(
			'measurement_id' => 'G-TEST1234',
			'events'         => array( 'forms' => true ),
			'goals'          => array( 'generate_lead' => array( 'goal' => true ) ),
		);

		$options     = new Options();
		$integration = new $integration_class( $options );
		$integration->register( new Recorder( $options, new Queue() ) );
		return $integration;
	}

	/**
	 * Recorded events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function recorded(): array {
		return '' === $this->cookie ? array() : json_decode( rawurldecode( $this->cookie ), true );
	}

	public function test_gravity_forms_submission_set_to_lead(): void {
		$integration = $this->integration( GravityForms::class, array( 'forms' => array( 'form:gravityforms:3' => array( 'event' => 'generate_lead' ) ) ) );
		$this->assertSame( 10, has_action( 'gform_after_submission', array( $integration, 'after_submission' ) ) );

		$integration->after_submission(
			array( 'status' => 'active' ),
			array(
				'id'    => 3,
				'title' => 'Contact &amp; quote <b>us</b>',
			)
		);

		$this->assertSame(
			array(
				array(
					'name'   => 'generate_lead',
					'params' => array(
						'component_id'   => 'form:gravityforms:3',
						'component_name' => 'Contact & quote us',
					),
				),
			),
			$this->recorded()
		);
	}

	public function test_spam_and_malformed_submissions_are_skipped(): void {
		$integration = $this->integration( GravityForms::class );
		$integration->after_submission( array( 'status' => 'spam' ), array( 'id' => 3 ) );
		$integration->after_submission( array(), array() );
		$integration->after_submission( 'x', 'y' );

		$this->assertSame( array(), $this->recorded() );
	}

	public function test_nothing_is_hooked_when_form_tracking_is_off(): void {
		$integration = $this->integration( GravityForms::class, array( 'events' => array() ) );
		$this->assertFalse( has_action( 'gform_after_submission', array( $integration, 'after_submission' ) ) );
	}

	public function test_per_form_events_and_values(): void {
		$integration = $this->integration(
			ContactForm7::class,
			array(
				'currency' => 'EUR',
				'goals'    => array(
					'generate_lead' => array(
						'goal'  => true,
						'value' => 100,
					),
					'sign_up'       => array( 'goal' => true ),
				),
				'forms'    => array(
					'form:contact_form_7:2' => array(
						'event' => 'sign_up',
						'value' => 5,
					),
					'form:contact_form_7:3' => array( 'event' => 'off' ),
					'form:contact_form_7:4' => array(
						'event' => 'form_complete',
						'value' => 9,
					),
					'form:contact_form_7:5' => array(
						'event' => 'generate_lead',
						'value' => 300,
					),
					'form:contact_form_7:6' => array( 'value' => 7 ),
				),
			)
		);

		foreach ( array( 1, 2, 3, 4, 5, 6 ) as $id ) {
			$integration->mail_sent( new \WPCF7_ContactForm( $id, "Form {$id}" ) );
		}
		$integration->mail_sent( 'not a form' );

		// Same events and parameters; value and currency come first.
		$this->assertEquals(
			array(
				array(
					'name'   => 'form_complete',
					'params' => array(
						'component_id'   => 'form:contact_form_7:1',
						'component_name' => 'Form 1',
					),
				),
				array(
					'name'   => 'sign_up',
					'params' => array(
						'method'         => 'form',
						'component_id'   => 'form:contact_form_7:2',
						'component_name' => 'Form 2',
						'value'          => 5,
						'currency'       => 'EUR',
					),
				),
				array(
					'name'   => 'form_complete',
					'params' => array(
						'component_id'   => 'form:contact_form_7:4',
						'component_name' => 'Form 4',
					),
				),
				array(
					'name'   => 'generate_lead',
					'params' => array(
						'component_id'   => 'form:contact_form_7:5',
						'component_name' => 'Form 5',
						'value'          => 300,
						'currency'       => 'EUR',
					),
				),
				array(
					'name'   => 'form_complete',
					'params' => array(
						'component_id'   => 'form:contact_form_7:6',
						'component_name' => 'Form 6',
					),
				),
			),
			$this->recorded(),
			'Forms send form_complete until set otherwise; Off sends nothing; a form value only counts when its event is a goal.'
		);
	}

	public function test_wpforms_submission(): void {
		$integration = $this->integration( WPForms::class );
		$this->assertSame( 10, has_action( 'wpforms_process_complete', array( $integration, 'process_complete' ) ) );

		$integration->process_complete(
			array( 'secret' => 'jane@example.com' ),
			array(),
			array(
				'id'       => '9',
				'settings' => array( 'form_title' => 'Newsletter' ),
			),
			0
		);
		$integration->process_complete( array(), array(), array(), 0 );

		$this->assertSame(
			array(
				array(
					'name'   => 'form_complete',
					'params' => array(
						'component_id'   => 'form:wpforms:9',
						'component_name' => 'Newsletter',
					),
				),
			),
			$this->recorded(),
			'A newsletter form is not a lead unless set to Lead.'
		);
	}

	public function test_form_settings_are_sanitized(): void {
		$forms = Options::sanitize(
			array(
				'forms' => array(
					'form:gravityforms:3'   => array(
						'event' => 'sign_up',
						'value' => '$1,250.50',
					),
					'form:wpforms:9'        => array( 'event' => 'purchase' ),
					'gravityforms:3'        => array( 'event' => 'off' ),
					'form:Bad Provider:1'   => array( 'event' => 'off' ),
					'form:contact_form_7:5' => array( 'value' => '-3' ),
				),
			)
		)['forms'];

		$this->assertSame(
			array(
				'form:gravityforms:3'   => array(
					'event' => 'sign_up',
					'value' => 1250.5,
				),
				'form:wpforms:9'        => array(
					'event' => 'form_complete',
					'value' => null,
				),
				'form:contact_form_7:5' => array(
					'event' => 'form_complete',
					'value' => null,
				),
			),
			$forms
		);
	}
}

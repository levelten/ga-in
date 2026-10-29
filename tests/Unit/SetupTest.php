<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Admin\Checklist;
use GAinWP\Admin\Notices;
use GAinWP\Admin\SiteKit;
use GAinWP\Options;
use GAinWP\Upgrade;

/**
 * @covers \GAinWP\Admin\Checklist
 * @covers \GAinWP\Admin\SiteKit
 * @covers \GAinWP\Admin\Notices::whats_new
 */
final class SetupTest extends TestCase {

	/**
	 * @param array<string, mixed> $settings Settings to store.
	 */
	private function options( array $settings ): Options {
		$this->wp_options[ Options::OPTION ] = $settings;
		return new Options();
	}

	public function test_reminder_until_a_form_counts_as_a_lead_or_sign_up(): void {
		\WPCF7_ContactForm::$forms = array( new \WPCF7_ContactForm( 1, 'Newsletter' ), new \WPCF7_ContactForm( 2, 'Contact us' ) );
		$tag                       = array( 'measurement_id' => 'G-TEST1234' );
		$forms_on                  = $tag + array( 'events' => array( 'forms' => true ) );
		$reminder                  = function ( array $settings ): string {
			return ( new Checklist( $this->options( $settings ) ) )->forms_reminder();
		};

		$this->assertSame( '', $reminder( $tag ), 'Not while form tracking is off.' );
		$this->assertSame( '', $reminder( array( 'events' => array( 'forms' => true ) ) ), 'Not without a tag ID: nothing is sent.' );
		$this->assertSame( '', $reminder( array( 'tracking_type' => 'disabled' ) + $forms_on ), 'Not while tracking is disabled.' );
		$this->assertStringContainsString( 'form_complete', $reminder( $forms_on ) );
		$this->assertSame( '', $reminder( $forms_on + array( 'forms' => array( 'form:contact_form_7:2' => array( 'event' => 'generate_lead' ) ) ) ) );
		$this->assertSame(
			'',
			$reminder(
				$forms_on + array(
					'forms' => array(
						'form:contact_form_7:1' => array( 'event' => 'form_complete' ),
						'form:contact_form_7:2' => array( 'event' => 'off' ),
					),
				)
			),
			'Once the settings are saved, the choice is made.'
		);
		$this->assertStringContainsString( 'form_complete', $reminder( $forms_on + array( 'forms' => array( 'form:contact_form_7:1' => array( 'event' => 'off' ) ) ) ), 'A new form brings it back.' );
		$this->assertSame( '', $reminder( $forms_on + array( 'goals' => array( 'form_complete' => array( 'goal' => true ) ) ) ), 'Not when form_complete is a goal.' );

		\WPCF7_ContactForm::$forms = array();
		$this->assertSame( '', $reminder( $forms_on ), 'Not without forms.' );
	}

	public function test_checklist_lists_dimensions_for_enabled_attributes_and_forms(): void {
		$checklist = new Checklist(
			$this->options(
				array(
					'page_data' => array( 'content_type', 'content_id', 'content_author', 'user_status' ),
					'events'    => array( 'forms' => true ),
				)
			)
		);

		$this->assertSame(
			array(
				'content_author' => 'Author',
				'user_status'    => 'User status',
				'component_id'   => 'Form or component ID',
				'component_name' => 'Form or component name',
			),
			$checklist->dimensions(),
			'Built-in parameters need no registration.'
		);
		$this->assertSame( array(), ( new Checklist( $this->options( array() ) ) )->dimensions() );
	}

	public function test_no_site_kit_settings_no_warnings(): void {
		$this->assertSame(
			array(),
			SiteKit::warnings(
				$this->options(
					array(
						'measurement_id' => 'G-TEST1234',
						'events'         => array( 'forms' => true ),
					)
				)
			)
		);
	}

	public function test_site_kit_overlaps_are_detected(): void {
		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
			define( 'GOOGLESITEKIT_VERSION', '1.190.0' );
		}
		$this->wp_options['googlesitekit_active_modules']       = array( 'analytics-4' );
		$this->wp_options['googlesitekit_analytics-4_settings'] = array(
			'measurementID' => 'G-SITEKIT1',
			'useSnippet'    => true,
		);
		$this->wp_options['googlesitekit_conversion_tracking']  = array( 'enabled' => true );

		\WPCF7_ContactForm::$forms = array( new \WPCF7_ContactForm( 1, 'Contact us' ) );
		$forms_on                  = array(
			'measurement_id' => 'G-TEST1234',
			'events'         => array( 'forms' => true ),
		);
		$this->assertCount( 1, SiteKit::warnings( $this->options( $forms_on ) ), 'Forms on form_complete don\'t overlap with Site Kit\'s lead events.' );
		$lead    = $forms_on + array( 'forms' => array( 'form:contact_form_7:1' => array( 'event' => 'generate_lead' ) ) );
		$options = $this->options( $lead );
		$this->assertCount( 2, SiteKit::warnings( $options ) );
		$this->assertCount( 2, SiteKit::warnings( $this->options( $forms_on + array( 'forms' => array( 'form:contact_form_7:1' => array( 'event' => 'sign_up' ) ) ) ) ), 'Sign-ups count too.' );
		$this->assertCount( 1, SiteKit::warnings( $this->options( array( 'events' => array( 'forms' => false ) ) + $lead ) ), 'Not while form tracking is off.' );
		$this->assertSame( array(), SiteKit::warnings( $this->options( array( 'measurement_id' => '' ) + $lead ) ), 'Nothing is sent without an ID.' );

		$this->wp_options['googlesitekit_analytics-4_settings']['useSnippet'] = false;
		$this->assertFalse( SiteKit::places_ga4_tag() );
		$this->assertFalse( SiteKit::sends_conversions(), 'Site Kit only sends its events when it places a tag.' );
		$this->assertSame( array(), SiteKit::warnings( $options ) );

		$this->wp_options['googlesitekit_ads_settings'] = array( 'conversionID' => 'AW-123' );
		$this->assertSame( array(), SiteKit::warnings( $options ), 'Leftover Ads settings without the module don\'t count.' );
		$this->wp_options['googlesitekit_active_modules'][] = 'ads';
		$this->assertCount( 1, SiteKit::warnings( $options ), 'An Ads tag also sends them.' );
		$this->assertCount(
			1,
			SiteKit::warnings(
				$this->options(
					array(
						'tracking_type'    => 'tagmanager',
						'gtm_container_id' => 'GTM-TEST123',
					) + $lead
				)
			),
			'No page view warning in Tag Manager mode.'
		);
		$this->assertSame( array(), SiteKit::warnings( $this->options( array( 'tracking_type' => 'disabled' ) + $lead ) ) );
	}

	public function test_whats_new_notice_after_upgrading_from_6_0(): void {
		Functions\stubs(
			array(
				'admin_url'    => static function ( $path = '' ) {
					return 'https://example.com/wp-admin/' . $path;
				},
				'wp_nonce_url' => static function ( $url ) {
					return $url . '&_wpnonce=x';
				},
			)
		);
		$this->assertSame( '', Notices::whats_new() );

		$this->wp_options[ Options::OPTION ]         = array( 'measurement_id' => 'G-TEST1234' );
		$this->wp_options[ Upgrade::VERSION_OPTION ] = 2;
		Upgrade::maybe_run();

		$this->assertStringContainsString( 'GAinWP 6.1', Notices::whats_new() );
		$this->assertStringContainsString( 'content_author', Notices::whats_new(), '6.0 sites hear about the renamed parameters.' );
		$this->assertStringContainsString( 'action=gainwp_dismiss_whats_new', Notices::whats_new() );
	}

	public function test_5x_sites_are_not_told_about_6_0_names(): void {
		Functions\stubs(
			array(
				'admin_url'    => static function ( $path = '' ) {
					return 'https://example.com/wp-admin/' . $path;
				},
				'wp_nonce_url' => static function ( $url ) {
					return $url;
				},
			)
		);
		$this->wp_options['gainwp_options'] = '{"tracking_type":"universal"}';
		Upgrade::maybe_run();

		$this->assertStringContainsString( 'GAinWP 6.1', Notices::whats_new() );
		$this->assertStringNotContainsString( 'content_author', Notices::whats_new() );
	}

	public function test_no_whats_new_notice_on_new_installs(): void {
		Upgrade::maybe_run();
		$this->assertArrayNotHasKey( Upgrade::WHATS_NEW_OPTION, $this->wp_options );
	}
}

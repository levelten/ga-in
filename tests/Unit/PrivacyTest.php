<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Functions;
use GAinWP\Options;
use GAinWP\Tracking\Consent;
use GAinWP\Tracking\OptOut;

/**
 * @covers \GAinWP\Tracking\Consent
 * @covers \GAinWP\Tracking\OptOut
 */
final class PrivacyTest extends TestCase {

	/**
	 * @param array<string, mixed> $settings Settings to store.
	 */
	private function options( array $settings ): Options {
		$this->wp_options[ Options::OPTION ] = $settings;
		return new Options();
	}

	public function test_consent_none_outputs_nothing(): void {
		$this->assertSame( '', Consent::script( 'none' ) );
	}

	public function test_consent_denied_everywhere(): void {
		$this->assertSame(
			'gtag("consent", "default", {"ad_storage":"denied","ad_user_data":"denied","ad_personalization":"denied","analytics_storage":"denied","wait_for_update":500});',
			Consent::script( 'denied' )
		);
	}

	public function test_consent_denied_in_eea_only(): void {
		$lines = explode( "\n", Consent::script( 'denied_eea' ) );

		$this->assertCount( 2, $lines );
		$this->assertStringContainsString( '"analytics_storage":"granted"', $lines[0] );
		$this->assertStringNotContainsString( 'region', $lines[0] );
		$this->assertStringContainsString( '"analytics_storage":"denied"', $lines[1] );
		$this->assertMatchesRegularExpression( '/"region":\[[^\]]*"DE"[^\]]*"GB"[^\]]*"CH"\]/', $lines[1] );
	}

	public function test_optout_script_requires_a_feature(): void {
		$this->assertSame( '', OptOut::script( $this->options( array( 'measurement_id' => 'G-TEST1234' ) ) ) );
	}

	public function test_optout_script_sets_google_flag_when_id_known(): void {
		$script = OptOut::script(
			$this->options(
				array(
					'measurement_id' => 'G-TEST1234',
					'respect_dnt'    => true,
				)
			)
		);

		$this->assertStringEndsWith( '({"cookie":"gainwp_optout","flag":"gainwpOptOut","disableKey":"ga-disable-G-TEST1234","respectDnt":true,"optout":false});', $script );
		$this->assertStringContainsString( 'navigator.globalPrivacyControl', $script );
	}

	public function test_optout_script_works_without_measurement_id(): void {
		// Tag Manager sites usually have no Measurement ID; the flag still blocks the container.
		$script = OptOut::script( $this->options( array( 'allow_optout' => true ) ) );

		$this->assertStringContainsString( 'window.gaOptout = window.gaOptout || function', $script, '5.x users may call gaOptout() from their own links.' );
		$this->assertStringEndsWith( '({"cookie":"gainwp_optout","flag":"gainwpOptOut","disableKey":"","respectDnt":false,"optout":true});', $script );
	}

	public function test_shortcode_hidden_when_optout_disabled(): void {
		Functions\expect( 'add_action' )->never();
		$this->assertSame( '', OptOut::shortcode( array(), 'Opt out', $this->options( array() ) ) );
	}

	public function test_shortcode_renders_escaped_link_and_button(): void {
		$options = $this->options( array( 'allow_optout' => true ) );

		$this->assertSame(
			'<a href="#" role="button" class="gainwp_useroptout" data-done-text="You have opted out of Google Analytics tracking.">Stop &lt;tracking&gt;</a>',
			OptOut::shortcode( array(), 'Stop <tracking>', $options )
		);
		$this->assertSame(
			'<button type="button" class="gainwp_useroptout" data-done-text="Done &quot;!&quot;">Opt out of Google Analytics tracking</button>',
			OptOut::shortcode(
				array(
					'html_tag'  => 'button',
					'done_text' => 'Done "!"',
				),
				'',
				$options
			)
		);
	}

	public function test_optout_check_honors_5x_cookies(): void {
		$script = OptOut::script( $this->options( array( 'allow_optout' => true ) ) );
		$this->assertStringContainsString( '/; ga-disable-UA-[^=;]*=true(;|$)/.test(cookies)', $script );
	}

	public function test_handler_is_printed_on_every_page_when_optout_is_on(): void {
		OptOut::register_handler( $this->options( array() ) );
		$this->assertFalse( has_action( 'wp_footer' ) );

		$options = $this->options( array( 'allow_optout' => true ) );
		OptOut::register_handler( $options );
		$this->assertTrue( has_action( 'wp_footer' ) );

		Functions\expect( 'wp_print_inline_script_tag' )->once()->with(
			\Mockery::on(
				static function ( $script ) {
					return false !== strpos( $script, 'window.gaOptout = window.gaOptout || function' )
						&& false !== strpos( $script, ".closest('.gainwp_useroptout')" )
						&& false !== strpos( $script, "event: 'gainwp_optout'" );
				}
			),
			array( 'id' => 'gainwp-optout' )
		);
		OptOut::print_handler( $options );
	}

	public function test_shortcode_does_not_register_hooks(): void {
		OptOut::shortcode( array(), '', $this->options( array( 'allow_optout' => true ) ) );
		$this->assertFalse( has_action( 'wp_footer' ) );
	}
}

<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use GAinWP\Tracking\TagManager;

/**
 * @covers \GAinWP\Tracking\TagManager
 */
final class TagManagerTest extends TrackingTestCase {

	public function test_container_snippet(): void {
		$script = ( new TagManager( $this->options( array( 'gtm_container_id' => 'GTM-ABC123' ) ) ) )->inline_script();

		$this->assertStringStartsWith( 'window.dataLayer = window.dataLayer || [];', $script );
		$this->assertStringContainsString( 'if (!window.gainwpOptOut) {(function(w,d,s,l,i)', $script, 'Stored opt-outs always block the container.' );
		$this->assertStringContainsString( "})(window,document,'script','dataLayer',\"GTM-ABC123\");}", $script );
		$this->assertStringNotContainsString( 'window.dataLayer.push({', $script );
		$this->assertStringNotContainsString( 'function gtag', $script, 'No gtag() unless consent defaults need it.' );
	}

	public function test_data_layer_has_parameter_and_5x_names(): void {
		$script = ( new TagManager(
			$this->options(
				array(
					'gtm_container_id' => 'GTM-ABC123',
					'page_data'        => array( 'content_type', 'content_id', 'content_author', 'content_category', 'content_tags', 'content_publish_month', 'user_status' ),
				)
			)
		) )->inline_script();

		$this->assertSame(
			array(
				'content_type'               => 'post',
				'content_id'                 => 'post:42',
				'content_author'             => 'Jane & Doe',
				'content_category'           => 'News',
				'content_tags'               => 'GA4, WordPress',
				'content_publish_month'      => '2024-03',
				'user_status'                => 'guest',
				'gainwpAuthor'               => 'Jane & Doe',
				'gainwpCategory'             => 'News',
				'gainwpTag'                  => 'GA4, WordPress',
				'gainwpPublicationYearMonth' => '2024-03',
				'gainwpUser'                 => 'guest',
				'gainwpPublicationYear'      => '2024',
			),
			$this->json_after( $script, 'window.dataLayer.push(' )
		);
		$this->assertLessThan( strpos( $script, 'gtm.js' ), strpos( $script, 'window.dataLayer.push(' ) );
	}

	public function test_datalayer_hook_can_change_variables(): void {
		Actions\expectDone( 'gainwp_tagmanager_datalayer' )->once()->whenHappen(
			static function ( TagManager $tracker ) {
				$tracker->set( $tracker->get() + array( 'siteSection' => 'blog' ) );
			}
		);

		$script = ( new TagManager( $this->options( array( 'gtm_container_id' => 'GTM-ABC123' ) ) ) )->inline_script();

		$this->assertSame( array( 'siteSection' => 'blog' ), $this->json_after( $script, 'window.dataLayer.push(' ) );
	}

	public function test_consent_defaults_precede_container(): void {
		$script = ( new TagManager(
			$this->options(
				array(
					'gtm_container_id' => 'GTM-ABC123',
					'consent_default'  => 'denied_eea',
				)
			)
		) )->inline_script();

		$this->assertStringStartsWith( "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag(\"consent\", \"default\"", $script );
		$this->assertLessThan( strpos( $script, 'gtm.js' ), strpos( $script, '"region"' ) );
	}

	public function test_optout_blocks_container(): void {
		$script = ( new TagManager(
			$this->options(
				array(
					'gtm_container_id' => 'GTM-ABC123',
					'allow_optout'     => true,
				)
			)
		) )->inline_script();

		$this->assertStringContainsString( 'if (!window.gainwpOptOut) {(function(w,d,s,l,i)', $script );
		$this->assertLessThan( strpos( $script, 'if (!window.gainwpOptOut)' ), strpos( $script, 'gainwp_optout' ) );
	}

	public function test_footer_placement_keeps_privacy_in_head(): void {
		$tracker = new TagManager(
			$this->options(
				array(
					'gtm_container_id' => 'GTM-ABC123',
					'in_footer'        => true,
					'consent_default'  => 'denied',
					'respect_dnt'      => true,
				)
			)
		);

		$tracker->register();
		$this->assertSame( 1, has_action( 'wp_head', array( $tracker, 'output_privacy' ) ) );
		$this->assertSame( 1, has_action( 'wp_footer', array( $tracker, 'output' ) ) );
		$this->assertFalse( has_action( 'wp_head', array( $tracker, 'output' ) ) );

		$footer = $tracker->inline_script();
		$this->assertStringNotContainsString( 'consent', $footer );
		$this->assertStringContainsString( 'if (!window.gainwpOptOut)', $footer );

		Functions\expect( 'wp_print_inline_script_tag' )->once()->with(
			\Mockery::on(
				static function ( $script ) {
					return 0 === strpos( $script, 'window.dataLayer' ) && false !== strpos( $script, '"consent", "default"' ) && false !== strpos( $script, 'gainwpOptOut' );
				}
			),
			array( 'id' => 'gainwp-privacy' )
		);
		$tracker->output_privacy();
	}

	public function test_noscript_fallback(): void {
		$this->expectOutputString( '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC123" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n" );
		( new TagManager( $this->options( array( 'gtm_container_id' => 'GTM-ABC123' ) ) ) )->output_noscript();
	}
}

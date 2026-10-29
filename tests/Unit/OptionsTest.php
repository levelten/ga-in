<?php
/**
 * @package GAinWP
 */

namespace GAinWP\Tests\Unit;

use GAinWP\Options;

/**
 * @covers \GAinWP\Options
 */
final class OptionsTest extends TestCase {

	public function test_empty_input_returns_defaults(): void {
		$this->assertSame( Options::defaults(), Options::sanitize( array() ) );
	}

	public function test_defaults_have_no_true_booleans(): void {
		// The settings form omits unchecked boxes, so a true default could never be turned off.
		$defaults = Options::defaults();
		array_walk_recursive(
			$defaults,
			function ( $value, $key ) {
				$this->assertNotTrue( $value, "Default for {$key} must not be true." );
			}
		);
	}

	public function test_measurement_id_is_normalized(): void {
		$this->assertSame( 'G-ABC123XYZ', Options::sanitize( array( 'measurement_id' => ' g-abc123xyz ' ) )['measurement_id'] );
	}

	/**
	 * @dataProvider invalid_measurement_ids
	 */
	public function test_invalid_measurement_ids_are_dropped( string $id ): void {
		$this->assertSame( '', Options::sanitize( array( 'measurement_id' => $id ) )['measurement_id'] );
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function invalid_measurement_ids(): array {
		return array(
			'universal analytics' => array( 'UA-12345-1' ),
			'tag manager'         => array( 'GTM-ABCD12' ),
			'script injection'    => array( "G-ABC'</script>" ),
			'too short'           => array( 'G-AB' ),
		);
	}

	public function test_container_ids_are_validated(): void {
		$values = Options::sanitize(
			array(
				'gtm_container_id'     => 'gtm-ab12cd',
				'gtm_amp_container_id' => 'G-NOTGTM1',
			)
		);
		$this->assertSame( 'GTM-AB12CD', $values['gtm_container_id'] );
		$this->assertSame( '', $values['gtm_amp_container_id'] );
	}

	public function test_enumerations_fall_back_to_defaults(): void {
		$values = Options::sanitize(
			array(
				'tracking_type'   => 'universal',
				'content_group'   => 'author',
				'consent_default' => 'granted',
			)
		);
		$this->assertSame( 'gtag', $values['tracking_type'] );
		$this->assertSame( 'none', $values['content_group'] );
		$this->assertSame( 'none', $values['consent_default'] );
	}

	public function test_lists_keep_only_known_values(): void {
		$values = Options::sanitize(
			array(
				'page_data' => array( 'content_author', 'author', 'content_tags' ),
				'events'    => array(
					'email' => '1',
					'bogus' => '1',
				),
			)
		);
		$this->assertSame( array( 'content_author', 'content_tags' ), $values['page_data'], '6.0 keys are dropped; Upgrade maps them.' );
		$this->assertSame(
			array(
				'forms'      => false,
				'login'      => false,
				'sign_up'    => false,
				'comments'   => false,
				'email'      => true,
				'phone'      => false,
				'anchors'    => false,
				'affiliates' => false,
				'scroll'     => false,
			),
			$values['events']
		);
	}

	public function test_linker_domains_accept_text(): void {
		$values = Options::sanitize( array( 'linker_domains' => "https://Example.com/, shop.example.org\nnot a domain\nexample.com" ) );
		$this->assertSame( array( 'example.com', 'shop.example.org' ), $values['linker_domains'] );
	}

	public function test_cookie_settings_are_bounded(): void {
		$values = Options::sanitize(
			array(
				'cookie_domain'  => 'example.com"; alert(1)',
				'cookie_expires' => 999999999,
			)
		);
		$this->assertSame( '', $values['cookie_domain'] );
		$this->assertSame( 63072000, $values['cookie_expires'] );
		$this->assertSame( 'auto', Options::sanitize( array( 'cookie_domain' => 'AUTO' ) )['cookie_domain'] );
	}

	public function test_affiliate_links_accept_text_or_array(): void {
		$this->assertSame( array( '/go/', '?ref=a|b&y=<1>' ), Options::sanitize( array( 'affiliate_links' => "/go/\r\n\n ?ref=a|b&y=<1>\x00 \n/go/" ) )['affiliate_links'] );
		$this->assertSame( array( '/go/' ), Options::sanitize( array( 'affiliate_links' => array( ' /go/ ', '' ) ) )['affiliate_links'] );
	}

	public function test_long_affiliate_links_are_cut_on_character_boundaries(): void {
		$links = Options::sanitize( array( 'affiliate_links' => str_repeat( 'é', 250 ) ) )['affiliate_links'];
		$this->assertSame( 200, mb_strlen( $links[0] ) );
		$this->assertTrue( mb_check_encoding( $links[0], 'UTF-8' ) );
	}

	public function test_empty_affiliate_links_use_default(): void {
		$this->assertSame( array( '/out/' ), Options::sanitize( array( 'affiliate_links' => "  \n" ) )['affiliate_links'] );
	}

	public function test_hostnames_allow_leading_dot_and_punycode(): void {
		$values = Options::sanitize(
			array(
				'cookie_domain'  => '.Example.com',
				'linker_domains' => "shop.example.xn--p1ai\nhttps://a.example.org:8443/path\n-bad-.com\nlocalhost",
			)
		);
		$this->assertSame( 'example.com', $values['cookie_domain'] );
		$this->assertSame( array( 'shop.example.xn--p1ai' ), $values['linker_domains'] );
	}

	public function test_tag_id_follows_tracking_type(): void {
		$this->wp_options[ Options::OPTION ] = array(
			'measurement_id'   => 'G-TEST1234',
			'gtm_container_id' => 'GTM-ABC123',
		);
		$this->assertSame( 'G-TEST1234', ( new Options() )->tag_id() );

		$this->wp_options[ Options::OPTION ]['tracking_type'] = 'tagmanager';
		$this->assertSame( 'GTM-ABC123', ( new Options() )->tag_id() );

		$this->wp_options[ Options::OPTION ]['tracking_type'] = 'disabled';
		$this->assertSame( '', ( new Options() )->tag_id() );
	}

	public function test_reads_are_sanitized_and_cached(): void {
		$this->wp_options[ Options::OPTION ] = array( 'measurement_id' => 'g-abcd1234' );
		$options                             = new Options();

		$this->assertSame( 'G-ABCD1234', $options->get( 'measurement_id' ) );
		$this->assertNull( $options->get( 'unknown' ) );

		$this->wp_options[ Options::OPTION ] = array( 'measurement_id' => 'G-OTHER123' );
		$this->assertSame( 'G-ABCD1234', $options->get( 'measurement_id' ) );

		$options->flush();
		$this->assertSame( 'G-OTHER123', $options->get( 'measurement_id' ) );
	}

	public function test_corrupt_stored_value_returns_defaults(): void {
		$this->wp_options[ Options::OPTION ] = 'not an array';
		$this->assertSame( Options::defaults(), ( new Options() )->all() );
	}

	/**
	 * @dataProvider money_inputs
	 *
	 * @param mixed      $input    Raw amount.
	 * @param float|null $expected Parsed amount.
	 */
	public function test_money_amounts( $input, ?float $expected ): void {
		$this->assertSame( $expected, Options::money( $input ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: float|null}>
	 */
	public function money_inputs(): array {
		return array(
			'number'              => array( 100, 100.0 ),
			'dollars'             => array( '$1,250.50', 1250.5 ),
			'comma cents'         => array( '12,50', 12.5 ),
			'european thousands'  => array( '1.250,50 €', 1250.5 ),
			'thousands separator' => array( '1,250', 1250.0 ),
			'rounded to cents'    => array( '9.999', 10.0 ),
			'empty'               => array( '', null ),
			'negative'            => array( '-5', null ),
			'text'                => array( 'a lot', null ),
			'null'                => array( null, null ),
		);
	}
}

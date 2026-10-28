<?php
/**
 * Shared tracker behavior.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use GAinWP\Options;

/**
 * Handles tag placement, consent and opt-out for both trackers.
 */
abstract class AbstractTracker implements Tracker {

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		if ( $this->options->get( 'in_footer' ) ) {
			// Consent defaults and opt-out must run before anything else on the
			// page, such as a consent banner's update, so they stay in the head.
			add_action( 'wp_head', array( $this, 'output_privacy' ), 1 );
			add_action( 'wp_footer', array( $this, 'output' ), 1 );
		} else {
			add_action( 'wp_head', array( $this, 'output' ), 1 );
		}
	}

	/**
	 * Prints the tag.
	 */
	abstract public function output(): void;

	/**
	 * Prints consent defaults and opt-out on their own, for footer placement.
	 */
	public function output_privacy(): void {
		$script = $this->privacy_script();
		if ( '' !== $script ) {
			wp_print_inline_script_tag( Script::GTAG_STUB . "\n" . $script, array( 'id' => 'gainwp-privacy' ) );
		}
	}

	/**
	 * Consent defaults and opt-out, or '' when neither is configured.
	 * Expects gtag() to be defined.
	 */
	protected function privacy_script(): string {
		return implode(
			"\n",
			array_filter(
				array(
					Consent::script( $this->options->get( 'consent_default' ) ),
					OptOut::script( $this->options ),
				)
			)
		);
	}

	/**
	 * Whether the main output includes privacy_script(), i.e. the tag is in
	 * the head. Otherwise output_privacy() has already printed it.
	 */
	protected function privacy_in_output(): bool {
		return ! $this->options->get( 'in_footer' );
	}

	/**
	 * Collects page attributes, renamed for this tracker.
	 *
	 * @param array<string, string> $names Output name for each attribute key.
	 * @return array<string, string>
	 */
	protected function page_data( array $names ): array {
		$data = array();
		foreach ( PageData::collect( $this->options->get( 'page_data' ), $this->options->get( 'author_format' ) ) as $key => $value ) {
			$data[ $names[ $key ] ?? $key ] = $value;
		}
		return $data;
	}
}

<?php
/**
 * Google Tag Manager container.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

/**
 * Outputs a Google Tag Manager container and data layer variables.
 *
 * Page attributes are pushed under their GA4 parameter names and, so
 * existing containers keep working, under GAinWP 5.x's variable names.
 */
final class TagManager extends AbstractTracker {

	/**
	 * GAinWP 5.x data layer variable names for page attributes.
	 */
	public const LEGACY_VARIABLES = array(
		'content_author'        => 'gainwpAuthor',
		'content_category'      => 'gainwpCategory',
		'content_tags'          => 'gainwpTag',
		'content_publish_month' => 'gainwpPublicationYearMonth',
		'user_status'           => 'gainwpUser',
	);

	/**
	 * Data layer variables to push before the container loads.
	 *
	 * @var array<string, string>
	 */
	private array $datalayer = array();

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		parent::register();
		add_action( 'wp_body_open', array( $this, 'output_noscript' ) );

		if ( '' !== $this->options->get( 'gtm_amp_container_id' ) ) {
			add_filter( 'amp_post_template_data', array( $this, 'amp_add_component' ) );
			add_action( 'amp_post_template_footer', array( $this, 'amp_output' ) );
		}
	}

	/**
	 * Returns the data layer variables.
	 *
	 * @return array<string, string>
	 */
	public function get(): array {
		return $this->datalayer;
	}

	/**
	 * Replaces the data layer variables.
	 *
	 * @param array<string, string> $datalayer Variables.
	 */
	public function set( array $datalayer ): void {
		$this->datalayer = $datalayer;
	}

	/**
	 * Prints the data layer and container snippet.
	 */
	public function output(): void {
		Script::print_marker( 'Google Tag Manager' );
		wp_print_inline_script_tag( $this->inline_script(), array( 'id' => 'gainwp-gtm' ) );
	}

	/**
	 * Builds the inline script: consent defaults and opt-out (unless already
	 * printed in the head), data layer variables, then Google's container loader.
	 */
	public function inline_script(): string {
		$this->build_datalayer();

		$lines = array(
			$this->script_start(),
			$this->privacy_in_output() ? $this->privacy_script() : '',
		);
		if ( $this->datalayer ) {
			$lines[] = 'window.dataLayer.push(' . Script::json( $this->datalayer ) . ');';
		}

		$loader = sprintf(
			"(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',%s);",
			Script::json( $this->options->get( 'gtm_container_id' ) )
		);
		// Without a Measurement ID there is no Google flag to set, so visitors
		// who opted out don't get the container at all.
		$lines[] = 'if (!window.' . OptOut::FLAG . ') {' . $loader . '}';

		return implode( "\n", array_filter( $lines ) );
	}

	/**
	 * Prints the container's no-JavaScript fallback after the opening <body> tag.
	 */
	public function output_noscript(): void {
		printf(
			'<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
			esc_url( add_query_arg( 'id', $this->options->get( 'gtm_container_id' ), 'https://www.googletagmanager.com/ns.html' ) )
		);
	}

	/**
	 * Collects page attributes into data layer variables.
	 */
	private function build_datalayer(): void {
		$this->datalayer = $this->page_data();
		foreach ( self::LEGACY_VARIABLES as $key => $variable ) {
			if ( isset( $this->datalayer[ $key ] ) ) {
				$this->datalayer[ $variable ] = $this->datalayer[ $key ];
			}
		}
		if ( isset( $this->datalayer['content_publish_month'] ) ) {
			$this->datalayer['gainwpPublicationYear'] = substr( $this->datalayer['content_publish_month'], 0, 4 );
		}

		/**
		 * Fires after the data layer variables are built.
		 *
		 * @param TagManager $tracker Use get() and set() to change the variables.
		 */
		do_action( 'gainwp_tagmanager_datalayer', $this );
	}

	/**
	 * Adds the amp-analytics component on AMP Reader mode pages.
	 *
	 * @param array<string, mixed> $data AMP template data.
	 * @return array<string, mixed>
	 */
	public function amp_add_component( $data ) {
		$data                          = (array) $data;
		$data['amp_component_scripts'] = (array) ( $data['amp_component_scripts'] ?? array() );
		$data['amp_component_scripts']['amp-analytics'] = 'https://cdn.ampproject.org/v0/amp-analytics-0.1.js';
		return $data;
	}

	/**
	 * Prints the AMP container on AMP Reader mode pages.
	 */
	public function amp_output(): void {
		$this->build_datalayer();

		$config = add_query_arg(
			array(
				'id'      => $this->options->get( 'gtm_amp_container_id' ),
				'gtm.url' => 'SOURCE_URL',
			),
			'https://www.googletagmanager.com/amp.json'
		);

		printf(
			'<amp-analytics config="%s" data-credentials="include"><script type="application/json">%s</script></amp-analytics>' . "\n",
			esc_url( $config ),
			Script::json( array( 'vars' => (object) $this->datalayer ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and > escaped.
		);
	}
}

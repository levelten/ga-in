<?php
/**
 * Google Analytics 4 tag.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

/**
 * Outputs the Google tag (gtag.js) configured for a GA4 Measurement ID.
 */
final class Gtag extends AbstractTracker {

	/**
	 * GA4 event parameter names for each page attribute.
	 */
	public const PARAMETERS = array(
		'author'        => 'post_author',
		'category'      => 'post_category',
		'tags'          => 'post_tags',
		'publish_year'  => 'publish_year',
		'publish_month' => 'publish_month',
		'user_type'     => 'user_type',
	);

	/**
	 * Commands to output, in the shape used by GAinWP 5.x so existing
	 * `gainwp_gtag_commands` callbacks keep working: each entry has
	 * 'command', 'fields' (positional values) and 'fieldsobject' (parameters).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $commands = array();

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		parent::register();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_events' ) );
	}

	/**
	 * Returns the queued commands.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get(): array {
		return $this->commands;
	}

	/**
	 * Replaces the queued commands.
	 *
	 * @param array<int, array<string, mixed>> $commands Commands.
	 */
	public function set( array $commands ): void {
		$this->commands = $commands;
	}

	/**
	 * Builds a command entry, for `gainwp_gtag_commands` callbacks written for 5.x.
	 *
	 * @param string                    $command      gtag command, e.g. 'event'.
	 * @param array<mixed>              $fields       Positional arguments.
	 * @param array<string, mixed>|null $fieldsobject Parameters.
	 * @return array<string, mixed>
	 */
	public function prepare( $command, $fields, $fieldsobject = null ): array {
		return array(
			'command'      => $command,
			'fields'       => $fields,
			'fieldsobject' => $fieldsobject,
		);
	}

	/**
	 * Prints the tag.
	 */
	public function output(): void {
		$id = $this->options->get( 'measurement_id' );

		/**
		 * Filters the gtag.js URL, e.g. to serve it through a proxy.
		 *
		 * @param string $url Script URL, without the id parameter.
		 */
		$src = add_query_arg( 'id', rawurlencode( $id ), apply_filters( 'gainwp_gtag_script_path', 'https://www.googletagmanager.com/gtag/js' ) );

		Script::print_marker( 'Google Analytics 4' );

		if ( ! OptOut::enabled( $this->options ) ) {
			wp_print_script_tag(
				array(
					'src'   => esc_url_raw( $src ),
					'async' => true,
				)
			);
			wp_print_inline_script_tag( $this->inline_script(), array( 'id' => 'gainwp-gtag' ) );
			return;
		}

		// Visitors who opted out don't load gtag.js at all, so no Google
		// destination linked to the tag (such as Ads) receives their data.
		$loader = sprintf(
			"if (!window.%s) {var gainwpTag = document.createElement('script'); gainwpTag.async = true; gainwpTag.src = %s; document.head.appendChild(gainwpTag);}",
			OptOut::FLAG,
			Script::json( esc_url_raw( $src ) )
		);
		wp_print_inline_script_tag( $this->inline_script() . "\n" . $loader, array( 'id' => 'gainwp-gtag' ) );
	}

	/**
	 * Builds the inline script that sets consent, opt-out and the tag config.
	 */
	public function inline_script(): string {
		$this->build_commands();

		$lines = array(
			Script::GTAG_STUB,
			$this->privacy_in_output() ? $this->privacy_script() : '',
			"gtag('js', new Date());",
		);

		foreach ( $this->commands as $command ) {
			$args = array_merge( array( $command['command'] ?? '' ), array_values( (array) ( $command['fields'] ?? array() ) ) );
			if ( ! empty( $command['fieldsobject'] ) ) {
				$args[] = (array) $command['fieldsobject'];
			}
			$lines[] = Script::gtag( ...$args );
		}

		return implode( "\n", array_filter( $lines ) );
	}

	/**
	 * Queues the config command, preceded by page attributes.
	 */
	private function build_commands(): void {
		$this->commands = array();

		$params = $this->page_data( self::PARAMETERS );
		if ( $params ) {
			// "set" applies the attributes to every event on the page, not just page_view.
			$this->commands[] = array(
				'command'      => 'set',
				'fields'       => array(),
				'fieldsobject' => $params,
			);
		}

		$this->commands[] = array(
			'command'      => 'config',
			'fields'       => array( 'trackingId' => $this->options->get( 'measurement_id' ) ),
			'fieldsobject' => $this->config(),
		);

		$before = $this->commands;
		do_action_deprecated( 'gainwp_gtag_commands', array( $this ), '6.0.0', 'gainwp_gtag_config' );
		foreach ( $this->commands as $index => $command ) {
			if ( ! in_array( $command, $before, true ) ) {
				$this->commands[ $index ] = self::from_legacy( (array) $command, (array) ( $before[ $index ] ?? array() ) );
			}
		}
	}

	/**
	 * Converts values a 5.x callback added or changed. 5.x printed 'true',
	 * 'false', numbers and [...] or {...} strings as raw JavaScript, so those
	 * become real values here instead of quoted strings. Like 5.x, numbers
	 * under `gainwp_` keys stay strings, and values the callback left alone
	 * are not touched.
	 *
	 * @param array<string, mixed> $command  Command entry after the callbacks.
	 * @param array<string, mixed> $original Entry at the same position before them.
	 * @return array<string, mixed>
	 */
	private static function from_legacy( array $command, array $original ): array {
		$convert = static function ( $value, bool $keep_numbers ) {
			if ( ! is_string( $value ) ) {
				return $value;
			}
			if ( 'true' === $value || 'false' === $value ) {
				return 'true' === $value;
			}
			if ( is_numeric( $value ) ) {
				return $keep_numbers ? $value : $value + 0;
			}
			$wrapped = substr( $value, 0, 1 ) . substr( $value, -1 );
			if ( '[]' === $wrapped || '{}' === $wrapped ) {
				// JavaScript literals from 5.x callbacks usually use single quotes.
				$decoded = json_decode( str_replace( "'", '"', $value ), true );
				return null === $decoded ? $value : $decoded;
			}
			return $value;
		};

		foreach ( array( 'fields', 'fieldsobject' ) as $part ) {
			if ( ! isset( $command[ $part ] ) || ! is_array( $command[ $part ] ) ) {
				continue;
			}
			$unchanged = isset( $original[ $part ] ) && is_array( $original[ $part ] ) ? $original[ $part ] : array();
			foreach ( $command[ $part ] as $key => $value ) {
				if ( ! array_key_exists( $key, $unchanged ) || $unchanged[ $key ] !== $value ) {
					$is_dimension             = 0 === strpos( (string) $key, 'gainwp_' );
					$command[ $part ][ $key ] = $convert( $value, $is_dimension );
				}
			}
		}
		return $command;
	}

	/**
	 * Builds the parameters for the config command.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		$config = array();

		if ( '' !== $this->options->get( 'cookie_domain' ) ) {
			$config['cookie_domain'] = $this->options->get( 'cookie_domain' );
		}
		if ( $this->options->get( 'cookie_expires' ) > 0 ) {
			$config['cookie_expires'] = $this->options->get( 'cookie_expires' );
		}
		if ( $this->options->get( 'linker_domains' ) ) {
			$config['linker'] = array( 'domains' => $this->options->get( 'linker_domains' ) );
		}

		/**
		 * Filters the parameters of the gtag('config') command.
		 *
		 * @since 6.0.0
		 *
		 * @param array<string, mixed> $config Config parameters.
		 * @param string               $id     GA4 Measurement ID.
		 */
		return (array) apply_filters( 'gainwp_gtag_config', $config, $this->options->get( 'measurement_id' ) );
	}

	/**
	 * Enqueues the event tracking script when any event is enabled.
	 */
	public function enqueue_events(): void {
		$config = array();
		foreach ( array( 'email', 'phone', 'anchors' ) as $event ) {
			if ( $this->options->event_enabled( $event ) ) {
				$config[ $event ] = true;
			}
		}
		if ( $this->options->event_enabled( 'affiliates' ) ) {
			$config['affiliates'] = $this->options->get( 'affiliate_links' );
		}
		if ( $this->options->event_enabled( 'scroll' ) ) {
			/**
			 * Filters the scroll depth percentages that send a scroll_depth event.
			 *
			 * @since 6.0.0
			 *
			 * @param array<int> $thresholds Percentages.
			 */
			$config['scroll'] = array_values( array_map( 'absint', (array) apply_filters( 'gainwp_scroll_thresholds', array( 25, 50, 75, 100 ) ) ) );
		}

		if ( ! $config ) {
			return;
		}

		wp_enqueue_script(
			'gainwp-events',
			plugins_url( 'assets/js/events.js', GAINWP_FILE ),
			array(),
			GAINWP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( 'gainwp-events', 'window.gainwpEvents = ' . Script::json( $config ) . ';', 'before' );
	}
}

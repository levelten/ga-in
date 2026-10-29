<?php
/**
 * Integration registry.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

use GAinWP\Options;

/**
 * Lists the integrations whose targets are present.
 */
final class Registry {

	/**
	 * Built-in integrations, by class. Each class is only loaded when
	 * instantiated here; it checks for its target in is_available().
	 *
	 * @var array<int, class-string<Integration>>
	 */
	private const BUILT_IN = array( WordPress::class, GravityForms::class, ContactForm7::class, WPForms::class );

	/**
	 * Returns the available integrations.
	 *
	 * @param Options $options Plugin settings.
	 * @return array<int, Integration>
	 */
	public static function available( Options $options ): array {
		$integrations = array();
		foreach ( self::BUILT_IN as $class ) {
			$integrations[] = new $class( $options );
		}

		/**
		 * Filters the integrations. Add your own (implementing
		 * \GAinWP\Integrations\Integration, or FormSource for form plugins)
		 * or remove built-in ones.
		 *
		 * @since 6.1.0
		 *
		 * @param array<int, object> $integrations Integration instances.
		 * @param Options            $options      Plugin settings.
		 */
		$integrations = (array) apply_filters( 'gainwp_integrations', $integrations, $options );

		return array_values(
			array_filter(
				$integrations,
				static function ( $integration ): bool {
					return $integration instanceof Integration && $integration->is_available();
				}
			)
		);
	}

	/**
	 * Returns the available form integrations.
	 *
	 * @param Options $options Plugin settings.
	 * @return array<int, FormSource>
	 */
	public static function form_sources( Options $options ): array {
		return array_values(
			array_filter(
				self::available( $options ),
				static function ( Integration $integration ): bool {
					return $integration instanceof FormSource;
				}
			)
		);
	}

	/**
	 * Each listed form's event, by component ID, and whether the owner has
	 * saved a choice for it (saving the settings stores every listed form).
	 *
	 * @param Options $options Plugin settings.
	 * @return array<string, array{event: string, saved: bool}>
	 */
	public static function form_choices( Options $options ): array {
		$saved   = $options->get( 'forms' );
		$choices = array();
		foreach ( self::form_sources( $options ) as $source ) {
			foreach ( array_keys( $source->forms() ) as $id ) {
				$component_id = Options::form_id( $source->provider(), $id );
				if ( '' !== $component_id ) {
					$choices[ $component_id ] = array(
						'event' => $options->form( $component_id )['event'],
						'saved' => isset( $saved[ $component_id ] ),
					);
				}
			}
		}
		return $choices;
	}

	/**
	 * Browser events after which the page sends events recorded during an
	 * AJAX request. Names jQuery would read as namespaces or lists (with
	 * '.' or spaces) are dropped, and so are values that aren't strings.
	 *
	 * @param Options $options Plugin settings.
	 * @return array<int, string>
	 */
	public static function ajax_events( Options $options ): array {
		$events = array();
		foreach ( self::available( $options ) as $integration ) {
			$valid  = preg_grep( '#^[A-Za-z][A-Za-z0-9_:/-]{0,99}$#D', array_filter( $integration->ajax_events(), 'is_string' ) );
			$events = array_merge( $events, is_array( $valid ) ? $valid : array() );
		}
		return array_values( array_unique( $events ) );
	}
}

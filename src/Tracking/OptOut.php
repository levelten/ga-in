<?php
/**
 * Visitor opt-out and Do Not Track support.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use GAinWP\Options;

/**
 * Lets visitors opt out of tracking, and optionally honors Do Not Track and
 * Global Privacy Control.
 */
final class OptOut {

	public const SHORTCODE = 'gainwp_useroptout';

	/**
	 * Cookie remembering a visitor's opt-out.
	 */
	public const COOKIE = 'gainwp_optout';

	/**
	 * Global JavaScript flag set for visitors who must not be tracked.
	 */
	public const FLAG = 'gainwpOptOut';

	/**
	 * Registers the opt-out shortcode.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function register_shortcode( Options $options ): void {
		if ( ! shortcode_exists( self::SHORTCODE ) ) {
			add_shortcode(
				self::SHORTCODE,
				static function ( $atts, $content = '' ) use ( $options ) {
					return self::shortcode( (array) $atts, (string) $content, $options );
				}
			);
		}
	}

	/**
	 * Loads the opt-out click handler on every front-end page while opt-out
	 * is on, so links work wherever they appear: shortcodes rendered late,
	 * content loaded by AJAX, or hand-written links with the class. It
	 * doesn't depend on a tracker being output, so links still record the
	 * opt-out when the visitor isn't tracked right now.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function register_handler( Options $options ): void {
		if ( $options->get( 'allow_optout' ) ) {
			add_action(
				'wp_enqueue_scripts',
				static function () use ( $options ) {
					self::enqueue_handler( $options );
				}
			);
		}
	}

	/**
	 * Enqueues assets/js/optout.js with its settings.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function enqueue_handler( Options $options ): void {
		wp_enqueue_script(
			'gainwp-optout',
			plugins_url( 'assets/js/optout.js', GAINWP_FILE ),
			array(),
			GAINWP_VERSION,
			array(
				// Not deferred, so opt-out links work as soon as the page has
				// rendered, as the 5.x inline script did.
				'in_footer' => true,
			)
		);
		wp_add_inline_script( 'gainwp-optout', 'window.gainwpOptOutSettings = ' . self::settings( $options ) . ';', 'before' );
	}

	/**
	 * Renders the opt-out link or button.
	 *
	 * Usage: [gainwp_useroptout html_tag="button" done_text="Opted out"]Opt out[/gainwp_useroptout]
	 *
	 * @param array<string, string> $atts    Shortcode attributes.
	 * @param string                $content Link text.
	 * @param Options               $options Plugin settings.
	 */
	public static function shortcode( array $atts, string $content, Options $options ): string {
		if ( ! $options->get( 'allow_optout' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'html_tag'  => 'a',
				'done_text' => __( 'You have opted out of Google Analytics tracking.', 'ga-in' ),
			),
			$atts,
			self::SHORTCODE
		);

		$text = '' !== trim( $content ) ? $content : __( 'Opt out of Google Analytics tracking', 'ga-in' );

		if ( 'button' === $atts['html_tag'] ) {
			return sprintf(
				'<button type="button" class="gainwp_useroptout" data-done-text="%s">%s</button>',
				esc_attr( $atts['done_text'] ),
				esc_html( $text )
			);
		}

		return sprintf(
			'<a href="#" role="button" class="gainwp_useroptout" data-done-text="%s">%s</a>',
			esc_attr( $atts['done_text'] ),
			esc_html( $text )
		);
	}

	/**
	 * Whether opt-out or Do Not Track handling is on.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function enabled( Options $options ): bool {
		return (bool) $options->get( 'allow_optout' ) || (bool) $options->get( 'respect_dnt' );
	}

	/**
	 * Returns the opt-out check, or '' when neither feature is on. Must run
	 * before any Google tag loads, so trackers print it first.
	 *
	 * For visitors who opted out (or send DNT/GPC, when respected) it sets
	 * window.gainwpOptOut, which stops gtag.js or the Tag Manager container
	 * from loading, and Google's `ga-disable-<ID>` flag when a Measurement ID
	 * is set. Opt-outs recorded by 5.x (`ga-disable-UA-...=true` cookies) are
	 * honored too. When opt-out is on it also defines gaOptout() right away,
	 * as 5.x did, for consent tools or scripts that call it while the page
	 * loads; assets/js/optout.js reuses that definition.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function script( Options $options ): string {
		if ( ! self::enabled( $options ) ) {
			return '';
		}

		return implode(
			"\n",
			array(
				'(function (s) {',
				'var signal = navigator.doNotTrack === "1" || window.doNotTrack === "1" || navigator.globalPrivacyControl === true;',
				'var cookies = "; " + document.cookie;',
				'var optedOut = s.optout && (cookies.indexOf("; " + s.cookie + "=1") > -1 || /; ga-disable-UA-[^=;]*=true(;|$)/.test(cookies));',
				'if ((s.respectDnt && signal) || optedOut) {',
				'window[s.flag] = true;',
				'if (s.disableKey) { window[s.disableKey] = true; }',
				'}',
				'if (s.optout && !window.gaOptout) {',
				'window.gaOptout = function () {',
				'document.cookie = s.cookie + "=1; max-age=315360000; path=/; SameSite=Lax";',
				'window[s.flag] = true;',
				'if (s.disableKey) { window[s.disableKey] = true; }',
				'(window.dataLayer = window.dataLayer || []).push({ event: "gainwp_optout" });',
				'};',
				'}',
				'})(' . self::settings( $options ) . ');',
			)
		);
	}

	/**
	 * Settings shared by the head check and assets/js/optout.js.
	 *
	 * @param Options $options Plugin settings.
	 */
	private static function settings( Options $options ): string {
		$id = $options->get( 'measurement_id' );
		return Script::json(
			array(
				'cookie'     => self::COOKIE,
				'flag'       => self::FLAG,
				'disableKey' => '' !== $id ? 'ga-disable-' . $id : '',
				'respectDnt' => (bool) $options->get( 'respect_dnt' ),
				'optout'     => (bool) $options->get( 'allow_optout' ),
			)
		);
	}
}

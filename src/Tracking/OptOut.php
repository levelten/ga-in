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
	 * Defines gaOptout() unless already defined. It records the opt-out,
	 * stops the GA4 tag on the current page and pushes a `gainwp_optout`
	 * event so Tag Manager containers can react; an already-loaded container
	 * otherwise keeps running until the next page. Expects settings as `s`.
	 */
	private const OPTOUT_FUNCTION = <<<'JS'
window.gaOptout = window.gaOptout || function () {
		document.cookie = s.cookie + '=1; max-age=315360000; path=/; SameSite=Lax';
		window[s.flag] = true;
		if (s.disableKey) {
			window[s.disableKey] = true;
		}
		(window.dataLayer = window.dataLayer || []).push({ event: 'gainwp_optout' });
	};
JS;

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
	 * Prints the opt-out click handler on every front-end page while opt-out
	 * is on, so links work wherever they appear: shortcodes rendered late,
	 * content loaded by AJAX, or hand-written links with the class.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function register_handler( Options $options ): void {
		if ( $options->get( 'allow_optout' ) ) {
			add_action(
				'wp_footer',
				static function () use ( $options ) {
					self::print_handler( $options );
				},
				20
			);
		}
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
	 * honored too. It also defines gaOptout(), which 5.x users may call from
	 * their own links.
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function script( Options $options ): string {
		if ( ! self::enabled( $options ) ) {
			return '';
		}

		$settings = self::settings( $options );
		$optout   = self::OPTOUT_FUNCTION;

		return <<<JS
(function (s) {
	var signal = navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true;
	var cookies = '; ' + document.cookie;
	var optedOut = s.optout && (cookies.indexOf('; ' + s.cookie + '=1') > -1 || /; ga-disable-UA-[^=;]*=true(;|$)/.test(cookies));
	if ((s.respectDnt && signal) || optedOut) {
		window[s.flag] = true;
		if (s.disableKey) {
			window[s.disableKey] = true;
		}
	}
	if (s.optout) {
		{$optout}
	}
})({$settings});
JS;
	}

	/**
	 * Prints the click handler for opt-out links. It doesn't depend on a
	 * tracker being output, so links still record the opt-out when the
	 * visitor isn't tracked right now (excluded role, or no ID set yet).
	 *
	 * @param Options $options Plugin settings.
	 */
	public static function print_handler( Options $options ): void {
		if ( ! $options->get( 'allow_optout' ) ) {
			return;
		}

		$settings = self::settings( $options );
		$optout   = self::OPTOUT_FUNCTION;

		$script = <<<JS
(function (s) {
	{$optout}
	document.addEventListener('click', function (e) {
		var el = e.target instanceof Element ? e.target.closest('.gainwp_useroptout') : null;
		if (!el) {
			return;
		}
		e.preventDefault();
		window.gaOptout();
		if (el.getAttribute('data-done-text')) {
			el.textContent = el.getAttribute('data-done-text');
		}
	});
})({$settings});
JS;

		wp_print_inline_script_tag( $script, array( 'id' => 'gainwp-optout' ) );
	}

	/**
	 * Settings shared by the check and the click handler.
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

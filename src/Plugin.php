<?php
/**
 * Plugin bootstrap.
 *
 * @package GAinWP
 */

namespace GAinWP;

/**
 * Wires the plugin's components into WordPress.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self( new Options() );
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	private function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Returns the plugin settings.
	 */
	public function options(): Options {
		return $this->options;
	}

	/**
	 * Registers hooks. Called once from the main plugin file.
	 */
	public function register(): void {
		add_action( 'init', array( Upgrade::class, 'maybe_run' ), 0 );
		add_action( 'init', array( $this, 'init' ) );
		// The first save goes through add_option(), later ones through update_option().
		add_action( 'add_option_' . Options::OPTION, array( $this->options, 'flush' ) );
		add_action( 'update_option_' . Options::OPTION, array( $this->options, 'flush' ) );
	}

	/**
	 * Loads the admin or front-end components.
	 */
	public function init(): void {
		// Registered everywhere, including admin-ajax requests that render
		// post content, so the shortcode never shows as raw text.
		Tracking\OptOut::register_shortcode( $this->options );

		if ( is_admin() ) {
			( new Admin\SettingsPage( $this->options ) )->register();
			( new Admin\Notices( $this->options ) )->register();
			return;
		}

		if ( wp_doing_cron() ) {
			return;
		}

		Tracking\OptOut::register_handler( $this->options );

		// Decided once the main query is parsed, so exclusions (and
		// gainwp_exclude_tracking callbacks) can use conditional tags such
		// as is_preview(). Early priority so the tracker's hooks are in
		// place before anything renders, including AMP Reader mode.
		add_action( 'template_redirect', array( $this, 'start_tracking' ), 0 );
	}

	/**
	 * Hooks the configured tracker into page output, unless this request
	 * is excluded.
	 */
	public function start_tracking(): void {
		$tracker = Tracking\Factory::create( $this->options );
		if ( null !== $tracker ) {
			$tracker->register();
		}
	}
}

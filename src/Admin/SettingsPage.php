<?php
/**
 * Settings screen.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Integrations\FormSource;
use GAinWP\Integrations\Registry;
use GAinWP\Options;
use GAinWP\Upgrade;

/**
 * Settings > Google Analytics, built on the Settings API.
 */
final class SettingsPage {

	public const SLUG = 'gainwp_settings';

	/**
	 * HTML allowed in field descriptions and notices.
	 */
	public const ALLOWED_HTML = array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'code'   => array(),
		'strong' => array(),
	);

	private const GROUP = 'gainwp';

	/**
	 * Styles of the goals and forms tables. The settings table's own `th`
	 * style reaches their header cells, and below 782px WordPress turns
	 * every settings cell and text input into a full-width block, which
	 * would pull these tables apart.
	 */
	private const TABLE_CSS = '.gainwp-values { max-width: 44em; }
.form-table .gainwp-values th { width: auto; padding: 8px 10px; }
.gainwp-values .gainwp-value { white-space: nowrap; }
@media screen and (max-width: 782px) {
	.form-table .gainwp-values th, .form-table .gainwp-values td { display: table-cell; padding: 8px 10px; }
	.form-table .gainwp-values td input[type="text"] { display: inline-block; width: 5em; }
}';

	/**
	 * Plugin settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GAINWP_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * URL of the settings screen.
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Adds the screen under Settings.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'Google Analytics (GAinWP)', 'ga-in' ),
			__( 'Google Analytics', 'ga-in' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array<string> $links Action links.
	 * @return array<string>
	 */
	public function action_links( $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'ga-in' ) ) );
		return $links;
	}

	/**
	 * Registers the setting, sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Options::defaults(),
				'show_in_rest'      => false,
			)
		);

		$this->register_tracking_section();
		$this->register_goals_section();
		$this->register_forms_section();
		$this->register_events_section();
		$this->register_page_data_section();
		$this->register_privacy_section();
		$this->register_exclusions_section();
		$this->register_advanced_section();
	}

	/**
	 * Validates submitted settings, reporting problems as admin errors.
	 * Invalid IDs keep their previous value instead of being cleared.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$previous = $this->options->all();

		// Migration warnings and the 6.1 news stay until the form is
		// submitted, even without changes (WordPress then skips the update hooks).
		delete_option( Upgrade::WARNINGS_OPTION );
		delete_option( Upgrade::WHATS_NEW_OPTION );

		$ids = array(
			'measurement_id'       => array(
				Options::MEASUREMENT_ID_PATTERN,
				__( 'Measurement ID', 'ga-in' ),
				'G-XXXXXXXXXX',
			),
			'gtm_container_id'     => array(
				Options::CONTAINER_ID_PATTERN,
				__( 'Tag Manager container ID', 'ga-in' ),
				'GTM-XXXXXXX',
			),
			'gtm_amp_container_id' => array(
				Options::CONTAINER_ID_PATTERN,
				__( 'AMP container ID', 'ga-in' ),
				'GTM-XXXXXXX',
			),
		);

		foreach ( $ids as $key => list( $pattern, $label, $example ) ) {
			$raw = trim( (string) ( $input[ $key ] ?? '' ) );
			if ( '' === $raw || '' !== Options::match_id( $raw, $pattern ) ) {
				continue;
			}
			if ( 0 === stripos( $raw, 'UA-' ) ) {
				$this->error( $key, __( 'Universal Analytics IDs (UA-) no longer collect data. Enter the Measurement ID of a Google Analytics 4 web data stream (G-XXXXXXXXXX).', 'ga-in' ) );
			} else {
				/* translators: 1: field name, 2: entered value, 3: example of a valid value. */
				$this->error( $key, sprintf( __( '%1$s "%2$s" is not valid. It should look like %3$s.', 'ga-in' ), $label, $raw, $example ) );
			}
			$input[ $key ] = $previous[ $key ];
		}

		// Keep the settings of forms not listed right now, such as forms of
		// a form plugin that is deactivated for a while.
		$input['forms'] = (array) ( $input['forms'] ?? array() ) + $previous['forms'];

		return Options::sanitize( $input );
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Only selects a tab.
		$tab = isset( $_GET['tab'] ) && 'setup' === $_GET['tab'] ? 'setup' : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_status(); ?>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url() ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'ga-in' ); ?></a>
				<a href="<?php echo esc_url( self::url() . '&tab=setup' ); ?>" class="nav-tab<?php echo 'setup' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'GA4 setup', 'ga-in' ); ?></a>
			</nav>
			<?php if ( 'setup' === $tab ) : ?>
				<?php ( new Checklist( $this->options ) )->render(); ?>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( self::GROUP );
					do_settings_sections( self::SLUG );
					submit_button();
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Summarizes what is being output, which answers most "is it working?" questions.
	 */
	private function render_status(): void {
		$problem = Notices::problem( $this->options );
		$type    = $this->options->get( 'tracking_type' );

		if ( null !== $problem ) {
			$class   = 'notice-warning';
			$message = $problem;
		} elseif ( 'disabled' === $type ) {
			$class   = 'notice-info';
			$message = __( 'Tracking is disabled. No Google tags are added to your site.', 'ga-in' );
		} else {
			$class   = 'notice-success';
			$message = sprintf(
				/* translators: %s: Measurement ID or Tag Manager container ID. */
				'gtag' === $type ? __( 'Sending page views to Google Analytics 4 property %s.', 'ga-in' ) : __( 'Loading Google Tag Manager container %s.', 'ga-in' ),
				'<code>' . esc_html( $this->options->tag_id() ) . '</code>'
			) . ' ' . __( 'Logged-in users in excluded roles are not tracked, so check from a private browser window.', 'ga-in' );
		}

		printf( '<div class="notice inline %s"><p>%s</p></div>', esc_attr( $class ), wp_kses( $message, self::ALLOWED_HTML ) );

		$reminder = ( new Checklist( $this->options ) )->forms_reminder();
		if ( '' !== $reminder ) {
			printf( '<div class="notice inline notice-info"><p>%s</p></div>', wp_kses( $reminder, self::ALLOWED_HTML ) );
		}

		$news = Notices::whats_new();
		if ( '' !== $news ) {
			printf( '<div class="notice inline notice-info"><p>%s</p></div>', wp_kses( $news, self::ALLOWED_HTML ) );
		}
		foreach ( SiteKit::warnings( $this->options ) as $warning ) {
			printf( '<div class="notice inline notice-warning"><p>%s</p></div>', wp_kses( $warning, self::ALLOWED_HTML ) );
		}

		$warnings = (array) get_option( Upgrade::WARNINGS_OPTION, array() );
		if ( isset( $warnings['affiliate_pattern'] ) ) {
			printf(
				'<div class="notice inline notice-warning"><p>%s</p></div>',
				sprintf(
					/* translators: %s: the 5.x regular expression. */
					esc_html__( 'GAinWP 5.x matched affiliate links with the regular expression %s, which cannot be converted to plain URL fragments. Affiliate link events are off until you enter fragments under Engagement events and turn them back on.', 'ga-in' ),
					'<code>' . esc_html( (string) $warnings['affiliate_pattern'] ) . '</code>'
				)
			);
		}
	}

	/**
	 * Tracking section.
	 */
	private function register_tracking_section(): void {
		$this->section( 'tracking', __( 'Tracking', 'ga-in' ) );

		$this->field(
			'tracking',
			'tracking_type',
			__( 'Tracking method', 'ga-in' ),
			'radios',
			array(
				'choices' => array(
					'gtag'       => __( 'Google Analytics 4 (gtag.js)', 'ga-in' ),
					'tagmanager' => __( 'Google Tag Manager', 'ga-in' ),
					'disabled'   => __( 'Disabled', 'ga-in' ),
				),
			)
		);

		$this->field(
			'tracking',
			'measurement_id',
			__( 'GA4 Measurement ID', 'ga-in' ),
			'text',
			array(
				'placeholder' => 'G-XXXXXXXXXX',
				'description' => sprintf(
					/* translators: %s: help article URL. */
					__( 'In Google Analytics, go to Admin &rarr; Data streams and open your web stream. <a href="%s" target="_blank" rel="noopener">Where to find it</a>.', 'ga-in' ),
					'https://support.google.com/analytics/answer/12270356'
				),
			)
		);

		$this->field(
			'tracking',
			'gtm_container_id',
			__( 'Tag Manager container ID', 'ga-in' ),
			'text',
			array(
				'placeholder' => 'GTM-XXXXXXX',
				'description' => __( 'Used in Tag Manager mode. Set up your GA4 tags inside the container.', 'ga-in' ),
			)
		);

		$this->field(
			'tracking',
			'gtm_amp_container_id',
			__( 'AMP container ID', 'ga-in' ),
			'text',
			array(
				'placeholder' => 'GTM-XXXXXXX',
				'description' => __( 'Optional. Tag Manager container for pages served by the AMP plugin in Reader mode.', 'ga-in' ),
			)
		);

		$this->field(
			'tracking',
			'in_footer',
			__( 'Placement', 'ga-in' ),
			'checkbox',
			array(
				'label'       => __( 'Load the tag in the footer instead of the head', 'ga-in' ),
				'description' => __( 'The head records more visits. Use the footer only if another script has to load first.', 'ga-in' ),
			)
		);
	}

	/**
	 * Page attributes section.
	 */
	private function register_page_data_section(): void {
		$this->section(
			'page_data',
			__( 'Page attributes', 'ga-in' ),
			sprintf(
				/* translators: %s: help article URL. */
				__( 'Sent with every event under the parameter names shown. Type, ID and content group are built into Google Analytics 4. <a href="%s" target="_blank" rel="noopener">Register the others as event-scoped custom dimensions</a>, or they will not appear in reports. In Tag Manager mode they are pushed to the data layer under the same names, and under the GAinWP 5.x names too.', 'ga-in' ),
				'https://support.google.com/analytics/answer/10075209'
			)
		);

		$labels  = array(
			'content_type'          => __( 'Content type (post type, archive, search or 404)', 'ga-in' ),
			'content_id'            => __( 'Content ID', 'ga-in' ),
			'content_author'        => __( 'Author display name', 'ga-in' ),
			'content_category'      => __( 'Primary category', 'ga-in' ),
			'content_tags'          => __( 'Tags', 'ga-in' ),
			'content_publish_month' => __( 'Publication month', 'ga-in' ),
			'user_status'           => __( 'Logged in or guest', 'ga-in' ),
		);
		$choices = array();
		foreach ( Options::PAGE_DATA_KEYS as $key ) {
			$choices[ $key ] = sprintf( '%1$s &mdash; <code>%2$s</code>', esc_html( $labels[ $key ] ), $key );
		}

		$this->field( 'page_data', 'page_data', __( 'Attributes', 'ga-in' ), 'checkboxes', array( 'choices' => $choices ) );

		$this->field(
			'page_data',
			'content_group',
			__( 'Content group', 'ga-in' ),
			'select',
			array(
				'choices'     => array(
					'none'      => __( 'Not set', 'ga-in' ),
					'post_type' => __( 'Post type (Posts, Pages, ...)', 'ga-in' ),
					'category'  => __( 'Primary category', 'ga-in' ),
				),
				'description' => __( 'Fills GA4&rsquo;s built-in <code>content_group</code>, which the standard Pages and screens report can show instead of page titles.', 'ga-in' ),
			)
		);
	}

	/**
	 * Goals section.
	 */
	private function register_goals_section(): void {
		$this->section(
			'goals',
			__( 'Goals', 'ga-in' ),
			__( 'The results that matter on your site. Goals send their value to Google Analytics 4 and, through it, Google Ads; other events never send a value. In GA4, mark each goal as a key event (see the GA4 setup tab).', 'ga-in' )
		);

		$this->field(
			'goals',
			'currency',
			__( 'Currency', 'ga-in' ),
			'text',
			array(
				'placeholder' => 'USD',
				'description' => __( 'Three-letter code such as USD or EUR. Use the currency set in your GA4 property.', 'ga-in' ),
			)
		);

		$this->field(
			'goals',
			'goals',
			__( 'Goals and values', 'ga-in' ),
			'goals',
			array(
				'choices'     => self::goal_labels(),
				'description' => __( 'Estimate what each result is worth to your business, for example what a customer is worth multiplied by the share of leads that become customers. Enter numbers such as 100 or 12.50. Leave the value empty to count a goal without a value.', 'ga-in' ),
			)
		);
	}

	/**
	 * Forms section: one table per active form plugin.
	 */
	private function register_forms_section(): void {
		$sources = Registry::form_sources( $this->options );

		$this->section(
			'forms',
			__( 'Forms', 'ga-in' ),
			$sources
				? __( 'Only successful submissions count: not failed validation or spam. Each form sends <code>form_complete</code> until you choose otherwise. Set the forms that bring you business, such as contact, quote or booking forms, to Lead, so they send <code>generate_lead</code> and count as a goal. A value here counts only when the form&rsquo;s event is a goal, and replaces that goal&rsquo;s value for the form. Field values are never sent.', 'ga-in' )
				: __( 'GAinWP tracks Gravity Forms, Contact Form 7 and WPForms. None of them is active.', 'ga-in' )
		);

		$this->field(
			'forms',
			'events',
			__( 'Form tracking', 'ga-in' ),
			'flags',
			array( 'choices' => array( 'forms' => __( 'Send an event when a form is submitted successfully', 'ga-in' ) ) ),
			'forms_enabled'
		);

		foreach ( $sources as $source ) {
			$this->field( 'forms', 'forms', $source->label(), 'forms', array( 'source' => $source ), 'forms_' . $source->provider() );
		}
	}

	/**
	 * Events section.
	 */
	private function register_events_section(): void {
		$this->section(
			'events',
			__( 'Events', 'ga-in' ),
			sprintf(
				/* translators: %s: help article URL. */
				__( 'Sent in Google Analytics 4 and Tag Manager modes. GA4 <a href="%s" target="_blank" rel="noopener">enhanced measurement</a> already records page views, outbound clicks, file downloads, form interactions, site search, video engagement and 90%% scroll, so GAinWP leaves those to it.', 'ga-in' ),
				'https://support.google.com/analytics/answer/9216061'
			)
		);

		$this->field(
			'events',
			'events',
			__( 'WordPress', 'ga-in' ),
			'flags',
			array(
				'choices' => array(
					'login'    => __( 'Logins', 'ga-in' ) . ' &mdash; <code>login</code>',
					'sign_up'  => __( 'Registrations', 'ga-in' ) . ' &mdash; <code>sign_up</code>',
					'comments' => __( 'Comments, except spam', 'ga-in' ) . ' &mdash; <code>comment_submit</code>',
				),
			)
		);

		$this->field(
			'events',
			'events',
			__( 'Engagement', 'ga-in' ),
			'flags',
			array(
				'choices' => array(
					'email'      => __( 'Email link clicks', 'ga-in' ) . ' &mdash; <code>email_link_click</code>',
					'phone'      => __( 'Phone link clicks', 'ga-in' ) . ' &mdash; <code>phone_link_click</code>',
					'anchors'    => __( 'Same-page anchor link clicks', 'ga-in' ) . ' &mdash; <code>anchor_link_click</code>',
					'affiliates' => __( 'Affiliate link clicks', 'ga-in' ) . ' &mdash; <code>affiliate_link_click</code>',
					'scroll'     => __( 'Scroll depth at 25, 50 and 75%', 'ga-in' ) . ' &mdash; <code>scroll_depth</code>',
				),
			),
			'engagement'
		);

		$this->field(
			'events',
			'affiliate_links',
			__( 'Affiliate links', 'ga-in' ),
			'textarea',
			array(
				'value'       => implode( "\n", $this->options->get( 'affiliate_links' ) ),
				'description' => __( 'Links whose URL contains any of these, one per line, count as affiliate links, as do links marked <code>rel="sponsored"</code>. For example <code>/out/</code> or <code>/go/</code>.', 'ga-in' ),
			)
		);
	}

	/**
	 * Labels for events that can be goals.
	 *
	 * @return array<string, string>
	 */
	public static function goal_labels(): array {
		return array(
			'generate_lead'        => __( 'Lead: a form set to Lead is submitted', 'ga-in' ),
			'sign_up'              => __( 'Sign-up: a registration, or a form set to Sign-up', 'ga-in' ),
			'form_complete'        => __( 'Form submitted: any form not set to Lead or Sign-up', 'ga-in' ),
			'comment_submit'       => __( 'Comment posted', 'ga-in' ),
			'email_link_click'     => __( 'Email link click', 'ga-in' ),
			'phone_link_click'     => __( 'Phone link click', 'ga-in' ),
			'affiliate_link_click' => __( 'Affiliate link click', 'ga-in' ),
		);
	}

	/**
	 * Formats a stored value for an input: "100", "12.5", or "" for none.
	 *
	 * @param float|null $value Stored value.
	 */
	public static function format_value( ?float $value ): string {
		return null === $value ? '' : rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Privacy section.
	 */
	private function register_privacy_section(): void {
		$this->section( 'privacy', __( 'Privacy', 'ga-in' ) );

		$this->field(
			'privacy',
			'consent_default',
			__( 'Consent mode default', 'ga-in' ),
			'select',
			array(
				'choices'     => array(
					'none'       => __( 'Not set (my consent banner handles it)', 'ga-in' ),
					'denied_eea' => __( 'Denied in the EEA, UK and Switzerland only', 'ga-in' ),
					'denied'     => __( 'Denied everywhere', 'ga-in' ),
				),
				'description' => __( 'Google Consent Mode v2. Choose a denied option only if your consent banner sends consent updates; otherwise Google Analytics stays in cookieless mode for those visitors.', 'ga-in' ),
			)
		);

		$this->field(
			'privacy',
			'respect_dnt',
			__( 'Browser privacy signals', 'ga-in' ),
			'checkbox',
			array(
				'label'       => __( 'Do not track visitors who send Do Not Track or Global Privacy Control', 'ga-in' ),
				'description' => __( 'In Tag Manager mode, the container is not loaded for these visitors.', 'ga-in' ),
			)
		);

		$this->field(
			'privacy',
			'allow_optout',
			__( 'Visitor opt-out', 'ga-in' ),
			'checkbox',
			array(
				'label'       => __( 'Enable the opt-out shortcode', 'ga-in' ),
				'description' => __( 'Add <code>[gainwp_useroptout]Opt out of analytics[/gainwp_useroptout]</code> to your privacy policy. In Tag Manager mode, visitors who opt out do not load the container at all.', 'ga-in' ),
			)
		);
	}

	/**
	 * Exclusions section.
	 */
	private function register_exclusions_section(): void {
		$this->section( 'exclusions', __( 'Exclusions', 'ga-in' ) );

		$this->field(
			'exclusions',
			'exclude_roles',
			__( 'Do not track these roles', 'ga-in' ),
			'checkboxes',
			array( 'choices' => array_map( 'translate_user_role', wp_roles()->get_names() ) )
		);

		if ( is_multisite() ) {
			$this->field(
				'exclusions',
				'exclude_super_admins',
				__( 'Super admins', 'ga-in' ),
				'checkbox',
				array( 'label' => __( 'Do not track network super admins', 'ga-in' ) )
			);
		}
	}

	/**
	 * Advanced section.
	 */
	private function register_advanced_section(): void {
		$this->section( 'advanced', __( 'Advanced', 'ga-in' ), __( 'Google Analytics 4 mode only. Leave these empty unless you need them.', 'ga-in' ) );

		$this->field(
			'advanced',
			'cookie_domain',
			__( 'Cookie domain', 'ga-in' ),
			'text',
			array(
				'placeholder' => 'auto',
				'description' => __( 'Defaults to the top-level domain, so subdomains share visitors.', 'ga-in' ),
			)
		);

		$this->field(
			'advanced',
			'cookie_expires',
			__( 'Cookie lifetime (seconds)', 'ga-in' ),
			'number',
			array( 'description' => __( 'Defaults to two years (63072000). 0 uses the default.', 'ga-in' ) )
		);

		$this->field(
			'advanced',
			'linker_domains',
			__( 'Cross-domain measurement', 'ga-in' ),
			'textarea',
			array(
				'value'       => implode( "\n", $this->options->get( 'linker_domains' ) ),
				'description' => __( 'One domain per line. You can also set this in Google Analytics under Data streams &rarr; Configure tag settings &rarr; Configure your domains.', 'ga-in' ),
			)
		);
	}

	/**
	 * Adds a settings section.
	 *
	 * @param string $id          Section ID.
	 * @param string $title       Heading.
	 * @param string $description Optional HTML shown under the heading.
	 */
	private function section( string $id, string $title, string $description = '' ): void {
		add_settings_section(
			'gainwp_' . $id,
			$title,
			static function () use ( $description ) {
				if ( '' !== $description ) {
					echo '<p>' . wp_kses( $description, self::ALLOWED_HTML ) . '</p>';
				}
			},
			self::SLUG
		);
	}

	/**
	 * Adds a field.
	 *
	 * @param string               $section Section ID (without prefix).
	 * @param string               $key     Setting key.
	 * @param string               $title   Row label.
	 * @param string               $type    text, number, textarea, checkbox, checkboxes, flags, radios, select, goals or forms.
	 * @param array<string, mixed> $args    choices, label, description, placeholder, value.
	 * @param string               $id      Field ID when it isn't the setting key.
	 */
	private function field( string $section, string $key, string $title, string $type, array $args = array(), string $id = '' ): void {
		$args += array(
			'key'   => $key,
			'type'  => $type,
			'value' => $this->options->get( $key ),
		);
		if ( in_array( $type, array( 'text', 'number', 'select', 'textarea' ), true ) ) {
			$args['label_for'] = 'gainwp-' . $key;
		}
		// $id tells apart two fields for parts of the same setting.
		add_settings_field( 'gainwp_' . ( '' !== $id ? $id : $key ), $title, array( $this, 'render_field' ), self::SLUG, 'gainwp_' . $section, $args );
	}

	/**
	 * Renders a field.
	 *
	 * @param array<string, mixed> $args Field arguments from field().
	 */
	public function render_field( array $args ): void {
		$name  = Options::OPTION . '[' . $args['key'] . ']';
		$id    = 'gainwp-' . $args['key'];
		$value = $args['value'];

		switch ( $args['type'] ) {
			case 'radios':
				echo '<fieldset>';
				foreach ( $args['choices'] as $choice => $label ) {
					printf(
						'<label><input type="radio" name="%s" value="%s"%s> %s</label><br>',
						esc_attr( $name ),
						esc_attr( $choice ),
						checked( $value, $choice, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['choices'] as $choice => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $choice ), selected( $value, $choice, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					wp_kses( $args['label'] ?? '', self::ALLOWED_HTML )
				);
				break;

			case 'flags':
				// One checkbox per key of an array of booleans.
				echo '<fieldset>';
				foreach ( $args['choices'] as $choice => $label ) {
					printf(
						'<label><input type="checkbox" name="%s[%s]" value="1"%s> %s</label><br>',
						esc_attr( $name ),
						esc_attr( $choice ),
						checked( ! empty( $value[ $choice ] ), true, false ),
						wp_kses( $label, self::ALLOWED_HTML )
					);
				}
				echo '</fieldset>';
				break;

			case 'checkboxes':
				echo '<fieldset>';
				foreach ( $args['choices'] as $choice => $label ) {
					printf(
						'<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label><br>',
						esc_attr( $name ),
						esc_attr( $choice ),
						checked( in_array( $choice, (array) $value, true ), true, false ),
						wp_kses( $label, self::ALLOWED_HTML )
					);
				}
				echo '</fieldset>';
				break;

			case 'goals':
				self::table_start( array( __( 'Event', 'ga-in' ), __( 'Goal', 'ga-in' ), __( 'Value', 'ga-in' ) ) );
				foreach ( $args['choices'] as $event => $label ) {
					$goal = $value[ $event ] ?? array(
						'goal'  => false,
						'value' => null,
					);
					printf(
						'<tr><td>%1$s<br><code>%2$s</code></td><td><input type="checkbox" name="%3$s[%2$s][goal]" value="1" aria-label="%4$s"%5$s></td><td class="gainwp-value"><input type="text" inputmode="decimal" name="%3$s[%2$s][value]" value="%6$s" size="8" aria-label="%7$s"> %8$s</td></tr>',
						esc_html( $label ),
						esc_attr( $event ),
						esc_attr( $name ),
						/* translators: %s: event description. */
						esc_attr( sprintf( __( '%s is a goal', 'ga-in' ), $label ) ),
						checked( (bool) $goal['goal'], true, false ),
						esc_attr( self::format_value( $goal['value'] ) ),
						/* translators: %s: event description. */
						esc_attr( sprintf( __( 'Value of %s', 'ga-in' ), $label ) ),
						esc_html( $this->options->get( 'currency' ) )
					);
				}
				echo '</tbody></table>';
				break;

			case 'forms':
				$this->render_forms( $args['source'], $name );
				break;

			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="3" class="regular-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;

			case 'number':
				printf( '<input type="number" id="%s" name="%s" value="%s" min="0" step="1" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;

			default:
				printf(
					'<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="regular-text">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $args['placeholder'] ?? '' )
				);
		}//end switch

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . wp_kses( $args['description'], self::ALLOWED_HTML ) . '</p>';
		}
	}

	/**
	 * Adds the styles of the goals and forms tables on the settings screen.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_register_style( 'gainwp-settings', false, array(), GAINWP_VERSION );
		wp_enqueue_style( 'gainwp-settings' );
		wp_add_inline_style( 'gainwp-settings', self::TABLE_CSS );
	}

	/**
	 * Opens a goals or forms table inside a settings row, up to the start of
	 * its body.
	 *
	 * @param array<int, string> $headings Column headings.
	 */
	private static function table_start( array $headings ): void {
		echo '<table class="widefat striped gainwp-values"><thead><tr>';
		foreach ( $headings as $heading ) {
			printf( '<th scope="col">%s</th>', esc_html( $heading ) );
		}
		echo '</tr></thead><tbody>';
	}

	/**
	 * Renders the forms of one form plugin with the event each sends and
	 * its own value.
	 *
	 * @param FormSource $source Form plugin integration.
	 * @param string     $name   Input name of the forms setting.
	 */
	private function render_forms( FormSource $source, string $name ): void {
		$forms = $source->forms();
		if ( ! $forms ) {
			echo '<p>' . esc_html__( 'No forms yet.', 'ga-in' ) . '</p>';
			return;
		}

		$events = array(
			'form_complete' => __( 'Form submitted', 'ga-in' ) . ' (form_complete)',
			'generate_lead' => __( 'Lead', 'ga-in' ) . ' (generate_lead)',
			'sign_up'       => __( 'Sign-up', 'ga-in' ) . ' (sign_up)',
			'off'           => __( 'Off', 'ga-in' ),
		);

		self::table_start( array( __( 'Form', 'ga-in' ), __( 'Sends', 'ga-in' ), __( 'Value', 'ga-in' ) ) );
		foreach ( $forms as $id => $title ) {
			$component_id = Options::form_id( $source->provider(), $id );
			if ( '' === $component_id ) {
				continue;
			}
			$form  = $this->options->form( $component_id );
			$field = $name . '[' . $component_id . ']';

			printf( '<tr><td>%s<br><code>%s</code></td><td>', esc_html( $title ), esc_html( $component_id ) );
			printf(
				'<select name="%s[event]" aria-label="%s">',
				esc_attr( $field ),
				/* translators: %s: form title. */
				esc_attr( sprintf( __( 'Event sent by %s', 'ga-in' ), $title ) )
			);
			foreach ( $events as $event => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $event ), selected( $form['event'], $event, false ), esc_html( $label ) );
			}
			printf(
				'</select></td><td class="gainwp-value"><input type="text" inputmode="decimal" name="%s[value]" value="%s" size="8" placeholder="%s" aria-label="%s"> %s</td></tr>',
				esc_attr( $field ),
				esc_attr( self::format_value( $form['value'] ) ),
				esc_attr__( 'Goal value', 'ga-in' ),
				/* translators: %s: form title. */
				esc_attr( sprintf( __( 'Value of %s', 'ga-in' ), $title ) ),
				esc_html( $this->options->get( 'currency' ) )
			);
		}//end foreach
		echo '</tbody></table>';
	}

	/**
	 * Adds a settings error once, even though WordPress may run sanitize
	 * twice on the first save.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 */
	private function error( string $code, string $message ): void {
		foreach ( get_settings_errors( Options::OPTION ) as $existing ) {
			if ( $existing['code'] === $code ) {
				return;
			}
		}
		add_settings_error( Options::OPTION, $code, $message );
	}
}

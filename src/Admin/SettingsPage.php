<?php
/**
 * Settings screen.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Options;
use GAinWP\Upgrade;
use GAinWP\Tracking\Gtag;
use GAinWP\Tracking\TagManager;

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
		add_filter( 'plugin_action_links_' . plugin_basename( GAINWP_FILE ), array( $this, 'action_links' ) );

		// Migration warnings stay until the settings are saved.
		$clear = static function () {
			delete_option( Upgrade::WARNINGS_OPTION );
		};
		add_action( 'add_option_' . Options::OPTION, $clear );
		add_action( 'update_option_' . Options::OPTION, $clear );
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
		$this->register_page_data_section();
		$this->register_events_section();
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

		return Options::sanitize( $input );
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_status(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
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
				__( 'Sent with every page view. In Google Analytics 4, <a href="%s" target="_blank" rel="noopener">register each one as an event-scoped custom dimension</a> using the parameter name shown, or it will not appear in reports. In Tag Manager mode they are pushed to the data layer under the variable name shown.', 'ga-in' ),
				'https://support.google.com/analytics/answer/10075209'
			)
		);

		$labels  = array(
			'author'        => __( 'Author', 'ga-in' ),
			'category'      => __( 'Category', 'ga-in' ),
			'tags'          => __( 'Tags', 'ga-in' ),
			'publish_year'  => __( 'Publication year', 'ga-in' ),
			'publish_month' => __( 'Publication month', 'ga-in' ),
			'user_type'     => __( 'Logged in or guest', 'ga-in' ),
		);
		$choices = array();
		foreach ( Options::PAGE_DATA_KEYS as $key ) {
			$choices[ $key ] = sprintf( '%1$s &mdash; <code>%2$s</code> / <code>%3$s</code>', esc_html( $labels[ $key ] ), Gtag::PARAMETERS[ $key ], TagManager::VARIABLES[ $key ] );
		}

		$this->field( 'page_data', 'page_data', __( 'Attributes', 'ga-in' ), 'checkboxes', array( 'choices' => $choices ) );

		$this->field(
			'page_data',
			'author_format',
			__( 'Author format', 'ga-in' ),
			'select',
			array(
				'choices'     => array(
					'display_name' => __( 'Display name', 'ga-in' ),
					'user_login'   => __( 'Username', 'ga-in' ),
				),
				'description' => __( 'Display names are safer: usernames are half of a login.', 'ga-in' ),
			)
		);
	}

	/**
	 * Engagement events section.
	 */
	private function register_events_section(): void {
		$this->section(
			'events',
			__( 'Engagement events', 'ga-in' ),
			sprintf(
				/* translators: %s: help article URL. */
				__( 'Google Analytics 4 mode only. GA4 <a href="%s" target="_blank" rel="noopener">enhanced measurement</a> already records outbound clicks, file downloads, form interactions, site search, video engagement and 90%% scroll. These events cover what it does not.', 'ga-in' ),
				'https://support.google.com/analytics/answer/9216061'
			)
		);

		$this->field(
			'events',
			'events',
			__( 'Events', 'ga-in' ),
			'flags',
			array(
				'choices' => array(
					'email'      => __( 'Email link clicks', 'ga-in' ) . ' &mdash; <code>email_link_click</code>',
					'phone'      => __( 'Phone link clicks', 'ga-in' ) . ' &mdash; <code>phone_link_click</code>',
					'anchors'    => __( 'Same-page anchor link clicks', 'ga-in' ) . ' &mdash; <code>anchor_link_click</code>',
					'affiliates' => __( 'Affiliate link clicks', 'ga-in' ) . ' &mdash; <code>affiliate_link_click</code>',
					'scroll'     => __( 'Scroll depth at 25, 50, 75 and 100%', 'ga-in' ) . ' &mdash; <code>scroll_depth</code>',
				),
			)
		);

		$this->field(
			'events',
			'affiliate_links',
			__( 'Affiliate links', 'ga-in' ),
			'textarea',
			array(
				'value'       => implode( "\n", $this->options->get( 'affiliate_links' ) ),
				'description' => __( 'Links whose URL contains any of these, one per line, count as affiliate links. For example <code>/out/</code> or <code>/go/</code>.', 'ga-in' ),
			)
		);
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
					'none'       => __( 'Not set: my consent banner plugin handles it', 'ga-in' ),
					'denied_eea' => __( 'Denied in the EEA, UK and Switzerland; granted elsewhere', 'ga-in' ),
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
	 * @param string               $type    text, number, textarea, checkbox, checkboxes, flags, radios or select.
	 * @param array<string, mixed> $args    choices, label, description, placeholder, value.
	 */
	private function field( string $section, string $key, string $title, string $type, array $args = array() ): void {
		$args += array(
			'key'   => $key,
			'type'  => $type,
			'value' => $this->options->get( $key ),
		);
		if ( in_array( $type, array( 'text', 'number', 'select', 'textarea' ), true ) ) {
			$args['label_for'] = 'gainwp-' . $key;
		}
		add_settings_field( 'gainwp_' . $key, $title, array( $this, 'render_field' ), self::SLUG, 'gainwp_' . $section, $args );
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

<?php
/**
 * GA4 setup checklist.
 *
 * @package GAinWP
 */

namespace GAinWP\Admin;

use GAinWP\Integrations\Registry;
use GAinWP\Options;

/**
 * Lists what to set up in Google Analytics 4 for the current settings, with
 * exact names: custom dimensions, key events, currency, data retention,
 * enhanced measurement and data redaction. Nothing is changed in GA4.
 */
final class Checklist {

	/**
	 * Custom dimensions for page attributes: parameter => display name.
	 */
	private const PAGE_DIMENSIONS = array(
		'content_author'        => 'Author',
		'content_category'      => 'Category',
		'content_tags'          => 'Tags',
		'content_publish_month' => 'Publish month',
		'user_status'           => 'User status',
	);

	/**
	 * Parameters GA4 reports without registering them.
	 */
	private const BUILT_IN = array( 'content_type', 'content_id', 'content_group', 'method', 'link_url', 'link_text', 'link_domain', 'percent_scrolled' );

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
	 * Custom dimensions to register: parameter => display name.
	 *
	 * @return array<string, string>
	 */
	public function dimensions(): array {
		$dimensions = array_intersect_key( self::PAGE_DIMENSIONS, array_flip( $this->options->get( 'page_data' ) ) );
		if ( $this->options->event_enabled( 'forms' ) ) {
			$dimensions['component_id']   = 'Form or component ID';
			$dimensions['component_name'] = 'Form or component name';
		}
		return $dimensions;
	}

	/**
	 * A reminder while no form counts as a lead or sign-up and some form
	 * still sends form_complete only because nobody chose otherwise. Saving
	 * the settings stores every form's choice, which ends it. '' otherwise,
	 * including when form_complete is itself a goal.
	 *
	 * @return string HTML.
	 */
	public function forms_reminder(): string {
		// Without a tag ID nothing is tracked, so forms send nothing either.
		if ( '' === $this->options->tag_id() || ! $this->options->event_enabled( 'forms' ) || $this->options->is_goal( 'form_complete' ) ) {
			return '';
		}
		$unchosen = false;
		foreach ( Registry::form_choices( $this->options ) as $choice ) {
			if ( 'generate_lead' === $choice['event'] || 'sign_up' === $choice['event'] ) {
				return '';
			}
			$unchosen = $unchosen || ! $choice['saved'];
		}
		return $unchosen
			? __( 'Your forms send <code>form_complete</code>, which isn&rsquo;t a goal. Under Forms on the Settings tab, set the forms that bring you business, such as contact, quote or booking forms, to Lead. If none of them are leads, saving the settings hides this reminder.', 'ga-in' )
			: '';
	}

	/**
	 * Renders the checklist.
	 */
	public function render(): void {
		$tag_manager = 'tagmanager' === $this->options->get( 'tracking_type' );

		echo '<p>' . esc_html__( 'GAinWP sends the data; these steps in Google Analytics 4 make it show up in reports. Each needs to be done once, by someone with the Editor role on the GA4 property.', 'ga-in' ) . '</p>';

		echo '<ol class="gainwp-checklist">';

		$this->step(
			__( 'Register custom dimensions', 'ga-in' ),
			__( 'Admin &rarr; Data display &rarr; Custom definitions &rarr; Create custom dimension. Use the scope Event and the exact parameter name. Reports only include data from the day a dimension is registered.', 'ga-in' ),
			'https://support.google.com/analytics/answer/14239696'
		);
		$dimensions = $this->dimensions();
		if ( $dimensions ) {
			echo '<table class="widefat striped" style="max-width:36em"><thead><tr>';
			printf( '<th>%s</th><th>%s</th>', esc_html__( 'Dimension name', 'ga-in' ), esc_html__( 'Event parameter', 'ga-in' ) );
			echo '</tr></thead><tbody>';
			foreach ( $dimensions as $parameter => $label ) {
				printf( '<tr><td>%s</td><td><code>%s</code></td></tr>', esc_html( $label ), esc_html( $parameter ) );
			}
			echo '</tbody></table>';
		}
		printf(
			'<p>%s %s</p></li>',
			esc_html__( 'Built in, nothing to register:', 'ga-in' ),
			wp_kses( '<code>' . implode( '</code>, <code>', self::BUILT_IN ) . '</code>', SettingsPage::ALLOWED_HTML )
		);

		$goals = array_keys( $this->options->goal_values() );
		$this->step(
			__( 'Mark your goals as key events', 'ga-in' ),
			$goals
				? __( 'Admin &rarr; Data display &rarr; Events &rarr; mark each of these as a key event once it has been received. GA4 counts key events from the moment they are marked, and allows up to 30.', 'ga-in' ) . ' <code>' . implode( '</code>, <code>', array_map( 'esc_html', $goals ) ) . '</code>'
				: __( 'No goals are set on the Settings tab yet.', 'ga-in' ),
			'https://support.google.com/analytics/answer/13128484'
		);
		echo '</li>';

		$this->step(
			__( 'Match the currency', 'ga-in' ),
			sprintf(
				/* translators: %s: currency code. */
				__( 'Admin &rarr; Property details &rarr; Currency displayed as: %s, the currency your goal values use.', 'ga-in' ),
				'<code>' . esc_html( $this->options->get( 'currency' ) ) . '</code>'
			),
			'https://support.google.com/analytics/answer/9796179'
		);
		echo '</li>';

		$this->step(
			__( 'Keep data for 14 months', 'ga-in' ),
			__( 'Admin &rarr; Data collection and modification &rarr; Data retention &rarr; Event data retention: 14 months. The default of 2 months limits explorations to the last two months.', 'ga-in' ),
			'https://support.google.com/analytics/answer/7667196'
		);
		echo '</li>';

		$this->step(
			__( 'Keep enhanced measurement on', 'ga-in' ),
			__( 'Admin &rarr; Data collection and modification &rarr; Data streams &rarr; your web stream &rarr; Enhanced measurement. It records page views, outbound clicks, file downloads, site search, video and 90% scroll, which GAinWP leaves to it. Its form interactions send <code>form_submit</code> on every submit attempt, so mark <code>generate_lead</code> as the key event, never <code>form_submit</code>.', 'ga-in' ),
			'https://support.google.com/analytics/answer/9216061'
		);
		echo '</li>';

		$this->step(
			__( 'Redact email addresses', 'ga-in' ),
			__( 'Admin &rarr; Data collection and modification &rarr; Data streams &rarr; your web stream &rarr; Redact data &rarr; turn on email redaction. GAinWP never sends personal data on purpose; this catches anything another script adds.', 'ga-in' ),
			'https://support.google.com/analytics/answer/13544947'
		);
		echo '</li>';

		if ( $tag_manager ) {
			$this->step(
				__( 'Send GAinWP&rsquo;s events from Tag Manager', 'ga-in' ),
				__( 'In Tag Manager mode GAinWP pushes events to the data layer; your container sends them to GA4.', 'ga-in' ),
				'https://support.google.com/tagmanager/answer/13034206'
			);
			echo '<ul style="list-style:disc;margin-left:2em">';
			printf(
				'<li>%s <code>%s</code></li>',
				esc_html__( 'Trigger: Custom Event, event name matches the regular expression', 'ga-in' ),
				esc_html( '^(' . implode( '|', Options::EVENT_NAMES ) . ')$' )
			);
			printf( '<li>%s <code>{{Event}}</code></li>', esc_html__( 'Tag: Google Analytics: GA4 Event, event name', 'ga-in' ) );
			printf(
				'<li>%s <code>intelligence.component_id</code>, <code>intelligence.value</code>, <code>intelligence.currency</code> &hellip;</li>',
				esc_html__( 'Event parameters: Data Layer Variables (version 2) named after each parameter under intelligence, for example', 'ga-in' )
			);
			printf(
				'<li>%s <code>content_type</code>, <code>content_author</code> &hellip;</li>',
				esc_html__( 'Page attributes: Data Layer Variables with the parameter names, in a Google Tag: Event Settings variable used by every GA4 tag, for example', 'ga-in' )
			);
			echo '</ul></li>';
		}//end if

		echo '</ol>';
	}

	/**
	 * Opens a checklist step. The caller closes the list item.
	 *
	 * @param string $title Step title.
	 * @param string $text  HTML description.
	 * @param string $help  Google help URL.
	 */
	private function step( string $title, string $text, string $help ): void {
		printf(
			'<li><h3 style="margin-bottom:.3em">%s</h3><p>%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html( $title ),
			wp_kses( $text, SettingsPage::ALLOWED_HTML ),
			esc_url( $help ),
			esc_html__( 'Google&rsquo;s help', 'ga-in' )
		);
	}
}

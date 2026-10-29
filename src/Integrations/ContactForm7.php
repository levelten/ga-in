<?php
/**
 * Contact Form 7.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

/**
 * Records Contact Form 7 submissions that passed validation and the spam
 * check and whose email was sent, which is when Contact Form 7 reports success.
 */
final class ContactForm7 extends FormIntegration {

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function provider(): string {
		return 'contact_form_7';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Contact Form 7';
	}

	/**
	 * Forms, by title.
	 *
	 * @return array<int|string, string>
	 */
	public function forms(): array {
		$forms = array();
		foreach ( (array) \WPCF7_ContactForm::find( array( 'orderby' => 'title' ) ) as $form ) {
			$forms[ (string) $form->id() ] = (string) $form->title();
		}
		return $forms;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hook(): void {
		add_action( 'wpcf7_mail_sent', array( $this, 'mail_sent' ) );
	}

	/**
	 * The DOM event fired when the message was sent.
	 *
	 * @return array<int, string>
	 */
	protected function success_events(): array {
		return array( 'wpcf7mailsent' );
	}

	/**
	 * Records a successful submission.
	 *
	 * @param mixed $contact_form The submitted form.
	 */
	public function mail_sent( $contact_form ): void {
		if ( $contact_form instanceof \WPCF7_ContactForm ) {
			$this->submitted( (int) $contact_form->id(), (string) $contact_form->title() );
		}
	}
}

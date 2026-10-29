<?php
/**
 * Gravity Forms.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

/**
 * Records successful Gravity Forms submissions, whether the form posts the
 * page, uses AJAX or redirects to a confirmation page.
 */
final class GravityForms extends FormIntegration {

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return class_exists( 'GFFormsModel' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function provider(): string {
		return 'gravityforms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Gravity Forms';
	}

	/**
	 * Active forms, by title.
	 *
	 * @return array<int|string, string>
	 */
	public function forms(): array {
		$forms = array();
		foreach ( (array) \GFFormsModel::get_forms( true ) as $form ) {
			$forms[ (string) $form->id ] = (string) $form->title;
		}
		return $forms;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hook(): void {
		// Runs after the entry is saved, for every submission that passed validation.
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );
	}

	/**
	 * The jQuery events gform_confirmation_loaded, when an AJAX form (iframe
	 * submission, the default) shows its confirmation message, and
	 * gform_post_render, the only one fired by the opt-in 'ajax' submission
	 * method. gform_post_render also fires on load and after validation
	 * errors, when there is nothing to send. Redirect confirmations load the
	 * next page instead.
	 *
	 * @return array<int, string>
	 */
	protected function success_events(): array {
		return array( 'gform_confirmation_loaded', 'gform_post_render' );
	}

	/**
	 * Records a submission unless Gravity Forms marked it as spam.
	 *
	 * @param mixed $entry The entry.
	 * @param mixed $form  The form.
	 */
	public function after_submission( $entry, $form ): void {
		if ( ! is_array( $entry ) || ! is_array( $form ) || 'spam' === ( $entry['status'] ?? '' ) || empty( $form['id'] ) ) {
			return;
		}
		$this->submitted( (int) $form['id'], (string) ( $form['title'] ?? '' ) );
	}
}

<?php
/**
 * WPForms.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

/**
 * Records successful WPForms submissions (Lite and Pro), with or without
 * AJAX.
 */
final class WPForms extends FormIntegration {

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return function_exists( 'wpforms' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function provider(): string {
		return 'wpforms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WPForms';
	}

	/**
	 * Published forms, by title.
	 *
	 * @return array<int|string, string>
	 */
	public function forms(): array {
		$forms = array();
		foreach (
			get_posts(
				array(
					'post_type'      => 'wpforms',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			) as $form
		) {
			$forms[ (string) $form->ID ] = $form->post_title;
		}
		return $forms;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hook(): void {
		// Runs once a submission passed validation and the spam checks.
		add_action( 'wpforms_process_complete', array( $this, 'process_complete' ), 10, 4 );
	}

	/**
	 * The jQuery event fired on success, before the confirmation message
	 * or redirect.
	 *
	 * @return array<int, string>
	 */
	protected function success_events(): array {
		return array( 'wpformsAjaxSubmitSuccess' );
	}

	/**
	 * Records a successful submission.
	 *
	 * @param mixed $fields    Submitted fields (not read).
	 * @param mixed $entry     Raw entry (not read).
	 * @param mixed $form_data Form settings.
	 * @param mixed $entry_id  Entry ID, in WPForms Pro.
	 */
	public function process_complete( $fields, $entry, $form_data, $entry_id = 0 ): void {
		if ( ! is_array( $form_data ) || empty( $form_data['id'] ) ) {
			return;
		}
		$this->submitted( (int) $form_data['id'], (string) ( $form_data['settings']['form_title'] ?? '' ) );
	}
}

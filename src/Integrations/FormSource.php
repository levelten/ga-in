<?php
/**
 * Form plugin integration contract.
 *
 * @package GAinWP
 */

namespace GAinWP\Integrations;

/**
 * An integration for a form plugin. Its forms are listed on the settings
 * screen, where each can be a lead, a sign-up, another form or off.
 */
interface FormSource extends Integration {

	/**
	 * Provider slug used in component IDs (`form:{provider}:{id}`): lowercase
	 * letters, digits, underscores or hyphens, e.g. 'gravityforms'. Form IDs
	 * may use letters, digits, '_', '-' and '.'.
	 */
	public function provider(): string;

	/**
	 * The form plugin's name, e.g. 'Gravity Forms'.
	 */
	public function label(): string;

	/**
	 * The plugin's forms.
	 *
	 * @return array<int|string, string> Form ID => title.
	 */
	public function forms(): array;
}

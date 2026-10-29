<?php
/**
 * The parts of form plugins' APIs that GAinWP uses, for PHPStan.
 *
 * @package GAinWP
 */

// phpcs:disable

class GFFormsModel {
	/**
	 * @param bool|null $is_active
	 * @param string    $sort_column
	 * @param string    $sort_dir
	 * @param bool      $is_trash
	 * @return array<int, object{id: int|string, title: string}>
	 */
	public static function get_forms( $is_active = null, $sort_column = 'title', $sort_dir = 'ASC', $is_trash = false ) {}
}

class WPCF7_ContactForm {
	/**
	 * @param array<string, mixed>|string $args
	 * @return array<int, WPCF7_ContactForm>
	 */
	public static function find( $args = '' ) {}

	/** @return int */
	public function id() {}

	/** @return string */
	public function title() {}
}

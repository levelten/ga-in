<?php
/**
 * Minimal WordPress classes used by the code under test.
 *
 * @package GAinWP
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.NamingConventions.PrefixAllGlobals

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stand-in for WP_Post.
	 */
	class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_author = '0';

		/**
		 * @param array<string, mixed> $props Properties.
		 */
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

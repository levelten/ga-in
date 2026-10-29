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
		/** @var string */
		public $post_type = 'post';

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

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * Stand-in for WP_User.
	 */
	class WP_User {
		/** @var int */
		public $ID = 0;
		/** @var array<string> */
		public $roles = array();

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

if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	/**
	 * Stand-in for Contact Form 7's form class.
	 */
	class WPCF7_ContactForm {
		/**
		 * Forms find() returns.
		 *
		 * @var array<int, WPCF7_ContactForm>
		 */
		public static $forms = array();

		/** @var int */
		private $id;
		/** @var string */
		private $title;

		/**
		 * @param int    $id    Form ID.
		 * @param string $title Form title.
		 */
		public function __construct( int $id, string $title ) {
			$this->id    = $id;
			$this->title = $title;
		}

		/**
		 * @return int
		 */
		public function id() {
			return $this->id;
		}

		/**
		 * @return string
		 */
		public function title() {
			return $this->title;
		}

		/**
		 * Ignores its query arguments.
		 *
		 * @return array<int, WPCF7_ContactForm>
		 */
		public static function find() {
			return self::$forms;
		}
	}
}

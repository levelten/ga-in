<?php
/**
 * Page attributes sent with page views.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use WP_Post;

/**
 * Collects attributes of the current page (author, category, ...).
 *
 * Which pages get which attributes matches GAinWP 5.x so existing reports
 * and Tag Manager variables keep working.
 */
final class PageData {

	/**
	 * GA4 caps event parameter values at 100 characters.
	 */
	private const MAX_LENGTH = 100;

	/**
	 * Collects the requested attributes for the current request.
	 *
	 * @param array<string> $keys          Attributes to collect (Options::PAGE_DATA_KEYS).
	 * @param string        $author_format 'display_name' or 'user_login'.
	 * @return array<string, string> Attribute values keyed by attribute name.
	 */
	public static function collect( array $keys, string $author_format ): array {
		$data   = array();
		$post   = get_queried_object();
		$post   = $post instanceof WP_Post ? $post : null;
		$single = null !== $post && is_single();

		if ( in_array( 'author', $keys, true ) && null !== $post && ( is_single() || is_page() ) ) {
			$field  = 'user_login' === $author_format ? 'user_login' : 'display_name';
			$author = (string) get_the_author_meta( $field, (int) $post->post_author );
			if ( '' !== $author ) {
				$data['author'] = $author;
			}
		}

		if ( in_array( 'publish_year', $keys, true ) && $single ) {
			$data['publish_year'] = (string) get_the_date( 'Y', $post );
		}

		if ( in_array( 'publish_month', $keys, true ) && $single ) {
			$data['publish_month'] = (string) get_the_date( 'Y-m', $post );
		}

		if ( in_array( 'category', $keys, true ) ) {
			if ( is_category() ) {
				$data['category'] = (string) single_term_title( '', false );
			} elseif ( $single ) {
				$categories = get_the_category( $post->ID );
				if ( $categories ) {
					$data['category'] = $categories[0]->name;
				}
			}
		}

		if ( in_array( 'tags', $keys, true ) && $single ) {
			$tags = get_the_tags( $post->ID );
			if ( is_array( $tags ) && $tags ) {
				$data['tags'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
			}
		}

		if ( in_array( 'user_type', $keys, true ) ) {
			$data['user_type'] = is_user_logged_in() ? 'registered' : 'guest';
		}

		/**
		 * Filters the page attributes sent to Google Analytics or Tag Manager.
		 *
		 * @since 6.0.0
		 *
		 * @param array<string, string> $data Attribute values keyed by attribute name.
		 * @param array<string>         $keys Attributes enabled in settings.
		 */
		$data = (array) apply_filters( 'gainwp_page_data', $data, $keys );

		return array_map(
			static function ( $value ): string {
				return mb_substr( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ), 0, self::MAX_LENGTH );
			},
			$data
		);
	}
}

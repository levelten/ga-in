<?php
/**
 * Page attributes sent with every event.
 *
 * @package GAinWP
 */

namespace GAinWP\Tracking;

use GAinWP\Options;
use WP_Post;

/**
 * Collects attributes of the current page (type, author, category, ...),
 * keyed by the GA4 event parameter they are sent as.
 */
final class PageData {

	/**
	 * GA4 caps event parameter values at 100 characters.
	 */
	private const MAX_LENGTH = 100;

	/**
	 * Post meta holding the primary category chosen in Yoast SEO or Rank Math.
	 */
	private const PRIMARY_CATEGORY_META = array( '_yoast_wpseo_primary_category', 'rank_math_primary_category' );

	/**
	 * Collects the requested attributes for the current request.
	 *
	 * @param array<string> $keys          Attributes to collect (Options::PAGE_DATA_KEYS).
	 * @param string        $content_group What fills content_group (Options::CONTENT_GROUPS).
	 * @return array<string, string> Attribute values keyed by parameter name.
	 */
	public static function collect( array $keys, string $content_group = 'none' ): array {
		$object   = get_queried_object();
		$post     = $object instanceof WP_Post ? $object : null;
		$singular = null !== $post && is_singular();
		$data     = array();

		if ( in_array( 'content_type', $keys, true ) ) {
			$data['content_type'] = self::content_type( $singular ? $post : null );
		}

		if ( $singular && in_array( 'content_id', $keys, true ) ) {
			// GA4 needs a non-numeric character in content IDs.
			$data['content_id'] = $post->post_type . ':' . $post->ID;
		}

		if ( $singular && in_array( 'content_author', $keys, true ) && (int) $post->post_author > 0 ) {
			// Always the public display name: usernames are half of a login.
			$data['content_author'] = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
		}

		$category = '';
		if ( in_array( 'content_category', $keys, true ) || 'category' === $content_group ) {
			$category = self::category( null !== $post && is_single() ? $post : null );
		}
		if ( in_array( 'content_category', $keys, true ) ) {
			$data['content_category'] = $category;
		}

		if ( null !== $post && is_single() ) {
			if ( in_array( 'content_tags', $keys, true ) ) {
				$tags = get_the_tags( $post->ID );
				if ( is_array( $tags ) && $tags ) {
					$data['content_tags'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
				}
			}
			if ( in_array( 'content_publish_month', $keys, true ) ) {
				$data['content_publish_month'] = (string) get_the_date( 'Y-m', $post );
			}
		}

		if ( 'post_type' === $content_group && $singular ) {
			$type_object           = get_post_type_object( $post->post_type );
			$data['content_group'] = null !== $type_object ? (string) $type_object->labels->name : '';
		} elseif ( 'category' === $content_group ) {
			$data['content_group'] = $category;
		}

		if ( in_array( 'user_status', $keys, true ) ) {
			$data['user_status'] = is_user_logged_in() ? 'registered' : 'guest';
		}

		/**
		 * Filters the page attributes sent with every event, keyed by GA4
		 * parameter name. In Tag Manager mode they are pushed to the data
		 * layer under the same names.
		 *
		 * @since 6.0.0
		 * @since 6.1.0 Keys are parameter names such as content_author.
		 *
		 * @param array<string, string> $data Attribute values keyed by parameter name.
		 * @param array<string>         $keys Attributes enabled in settings.
		 */
		$data = self::rename_6_0_keys( (array) apply_filters( 'gainwp_page_data', $data, $keys ) );

		$data = array_map(
			static function ( $value ): string {
				return mb_substr( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ), 0, self::MAX_LENGTH );
			},
			$data
		);

		return array_filter(
			$data,
			static function ( string $value ): bool {
				return '' !== $value;
			}
		);
	}

	/**
	 * Renames 6.0 attribute keys that callbacks written for 6.0 add or set.
	 *
	 * @param array<string, mixed> $data Filtered attributes.
	 * @return array<string, mixed>
	 */
	private static function rename_6_0_keys( array $data ): array {
		// A year isn't a month, so 6.0's publish_year isn't mapped here.
		$renamed = array_diff_key( Options::RENAMED_6_0, array( 'publish_year' => true ) );
		foreach ( $renamed as $old => $new ) {
			if ( array_key_exists( $old, $data ) ) {
				$data[ $new ] = $data[ $old ];
				unset( $data[ $old ] );
			}
		}
		return $data;
	}

	/**
	 * The content_type value: the post type on single views, otherwise the
	 * kind of page, or '' when none applies (e.g. a feed).
	 *
	 * @param WP_Post|null $post The queried post on single views.
	 */
	private static function content_type( ?WP_Post $post ): string {
		if ( is_404() ) {
			return 'not_found';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( null !== $post ) {
			return $post->post_type;
		}
		return is_archive() || is_home() ? 'archive' : '';
	}

	/**
	 * The category name on category archives, or the post's primary
	 * category: the one chosen in Yoast SEO or Rank Math, else the first.
	 *
	 * @param WP_Post|null $post The queried post on single views.
	 */
	private static function category( ?WP_Post $post ): string {
		if ( is_category() ) {
			return (string) single_term_title( '', false );
		}

		if ( null === $post ) {
			return '';
		}
		$categories = get_the_category( $post->ID );
		if ( ! $categories ) {
			return '';
		}

		foreach ( self::PRIMARY_CATEGORY_META as $meta_key ) {
			$primary = (int) get_post_meta( $post->ID, $meta_key, true );
			foreach ( $categories as $category ) {
				if ( $primary > 0 && (int) $category->term_id === $primary ) {
					return (string) $category->name;
				}
			}
		}

		return (string) $categories[0]->name;
	}
}

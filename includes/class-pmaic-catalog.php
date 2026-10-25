<?php
/**
 * Compact product catalogue sent with every question.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keyword search only returns a few passages, which is not enough for questions about
 * "all" products ("which themes are free?", "list your pro themes with prices").
 * This builds one short line per product (name, price, category, link) so the AI always
 * sees the complete list.
 */
class PMAIC_Catalog {

	const CACHE   = 'pmaic_catalog';
	const MAX     = 300;
	const EXCERPT = 25; // Words of description per product.

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'maybe_flush' ), 30, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'trashed_post', array( __CLASS__, 'flush' ) );
		add_action( 'update_option_' . PMAIC_Settings::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Post types treated as products.
	 *
	 * @return string[]
	 */
	public static function product_types() {
		$candidates = array_intersect( array( 'download', 'product' ), (array) PMAIC_Settings::get( 'post_types' ) );
		return array_values( array_filter( (array) apply_filters( 'pmaic_catalog_post_types', $candidates ), 'post_type_exists' ) );
	}

	/**
	 * Clear the cache when a product changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function maybe_flush( $post_id, $post ) {
		if ( $post && in_array( $post->post_type, self::product_types(), true ) ) {
			self::flush();
		}
	}

	/**
	 * Clear the cache.
	 */
	public static function flush() {
		delete_transient( self::CACHE );
	}

	/**
	 * Catalogue text (empty when disabled or there are no products).
	 *
	 * @return string
	 */
	public static function text() {
		if ( ! PMAIC_Settings::get( 'catalog' ) ) {
			return '';
		}
		$cached = get_transient( self::CACHE );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$types = self::product_types();
		$lines = array();
		if ( $types ) {
			$q = new WP_Query(
				array(
					'post_type'      => $types,
					'post_status'    => 'publish',
					'has_password'   => false,
					'posts_per_page' => self::MAX,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $q->posts as $post ) {
				if ( get_post_meta( $post->ID, '_pmaic_exclude', true ) ) {
					continue;
				}
				$lines[] = self::line( $post );
			}
		}

		$text = implode( "\n", array_filter( $lines ) );
		set_transient( self::CACHE, $text, DAY_IN_SECONDS );
		return $text;
	}

	/**
	 * One catalogue line.
	 *
	 * @param WP_Post $post Product.
	 * @return string
	 */
	private static function line( $post ) {
		$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$parts = array( '- ' . $title );

		$price = PMAIC_Indexer::price_text( $post );
		$parts[] = 'Price: ' . ( $price ? $price : 'not listed' );

		$terms = wp_get_post_terms( $post->ID, get_object_taxonomies( $post->post_type ), array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			$parts[] = 'Category: ' . implode( ', ', array_slice( $terms, 0, 4 ) );
		}

		$desc = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$desc = wp_trim_words( PMAIC_Indexer::html_to_text( do_blocks( $desc ) ), self::EXCERPT, '…' );
		if ( $desc ) {
			$parts[] = $desc;
		}

		$parts[] = 'URL: ' . get_permalink( $post );

		return (string) apply_filters( 'pmaic_catalog_line', implode( ' | ', $parts ), $post );
	}
}

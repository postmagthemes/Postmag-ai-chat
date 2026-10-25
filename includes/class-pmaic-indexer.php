<?php
/**
 * Content indexer.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns site content into searchable text chunks and keeps them in sync.
 */
class PMAIC_Indexer {

	const WORDS_PER_CHUNK = 180;
	const MAX_CHUNKS      = 40;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_post' ) );
		add_action( 'trashed_post', array( __CLASS__, 'delete_post' ) );
	}

	/**
	 * Chunks table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pmaic_chunks';
	}

	/**
	 * Re-index on save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::index_post( $post );
	}

	/**
	 * Remove a post from the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
	}

	/**
	 * Whether a post belongs in the index.
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	public static function should_index( $post ) {
		$ok = $post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& in_array( $post->post_type, (array) PMAIC_Settings::get( 'post_types' ), true )
			&& ! get_post_meta( $post->ID, '_pmaic_exclude', true );
		return (bool) apply_filters( 'pmaic_should_index', $ok, $post );
	}

	/**
	 * (Re)index one post. Removes it from the index if it should not be there.
	 *
	 * @param int|WP_Post $post Post.
	 * @return int Number of chunks stored.
	 */
	public static function index_post( $post ) {
		global $wpdb;
		$post = get_post( $post );
		if ( ! $post ) {
			return 0;
		}

		self::delete_post( $post->ID );
		if ( ! self::should_index( $post ) ) {
			return 0;
		}

		$title  = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$url    = get_permalink( $post );
		$chunks = self::chunk( self::post_text( $post ) );

		foreach ( $chunks as $i => $chunk ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
				self::table(),
				array(
					'post_id'     => $post->ID,
					'chunk_index' => $i,
					'post_type'   => $post->post_type,
					'title'       => $title,
					'url'         => $url,
					'content'     => $chunk,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}
		return count( $chunks );
	}

	/**
	 * Plain-text version of a post, including product prices and terms.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function post_text( $post ) {
		$html = '';

		// Elementor-built pages keep their real content in post meta.
		if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) && class_exists( '\Elementor\Plugin' ) ) {
			try {
				$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID, false );
			} catch ( \Throwable $e ) {
				$html = '';
			}
		}

		if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
			$html = do_shortcode( do_blocks( $post->post_content ) );
		}

		$parts = array();
		if ( $post->post_excerpt ) {
			$parts[] = $post->post_excerpt;
		}
		$parts[] = self::html_to_text( $html );

		$price = self::price_text( $post );
		if ( $price ) {
			$parts[] = 'Price: ' . $price;
		}

		$terms = wp_get_post_terms( $post->ID, get_object_taxonomies( $post->post_type ), array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			$parts[] = 'Topics: ' . implode( ', ', $terms );
		}

		$parts[] = 'Last updated: ' . get_the_modified_date( 'F j, Y', $post );

		/**
		 * Add custom text for a post before indexing (e.g. changelog, demo URL).
		 *
		 * @param string[] $parts Text parts.
		 * @param WP_Post  $post  Post.
		 */
		$parts = apply_filters( 'pmaic_post_text_parts', $parts, $post );

		return trim( implode( "\n\n", array_filter( (array) $parts ) ) );
	}

	/**
	 * Price for Easy Digital Downloads and WooCommerce products.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function price_text( $post ) {
		// WooCommerce.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$html = $product->get_price_html();
				return $html ? trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) : '';
			}
		}

		// Easy Digital Downloads.
		if ( 'download' !== $post->post_type || ! function_exists( 'edd_get_download_price' ) ) {
			return '';
		}
		$fmt = function ( $amount ) {
			if ( function_exists( 'edd_currency_filter' ) && function_exists( 'edd_format_amount' ) ) {
				return html_entity_decode( edd_currency_filter( edd_format_amount( $amount ) ), ENT_QUOTES, 'UTF-8' );
			}
			return (string) $amount;
		};

		if ( function_exists( 'edd_has_variable_prices' ) && edd_has_variable_prices( $post->ID ) ) {
			$out = array();
			foreach ( (array) edd_get_variable_prices( $post->ID ) as $price ) {
				if ( isset( $price['amount'] ) ) {
					$out[] = ( $price['name'] ?? '' ) . ' – ' . $fmt( $price['amount'] );
				}
			}
			return implode( '; ', $out );
		}

		$amount = edd_get_download_price( $post->ID );
		if ( '' === $amount || null === $amount ) {
			return '';
		}
		return (float) $amount > 0 ? $fmt( $amount ) : 'Free';
	}

	/**
	 * HTML to readable plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		$html = preg_replace( '#<(script|style|noscript|svg|form)[^>]*>.*?</\1>#is', ' ', (string) $html );
		$html = preg_replace( '#</?(p|div|h[1-6]|li|tr|br|section|article|blockquote)[^>]*>#i', "\n", $html );
		$html = strip_shortcodes( $html );
		$text = wp_strip_all_tags( $html, false );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = preg_replace( "/\n\s*\n+/", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Split text into chunks of about WORDS_PER_CHUNK words on paragraph boundaries.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function chunk( $text ) {
		$paras  = preg_split( "/\n+/", (string) $text );
		$chunks = array();
		$buf    = array();
		$count  = 0;

		foreach ( $paras as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			$words = preg_split( '/\s+/u', $p );

			while ( count( $words ) > self::WORDS_PER_CHUNK ) {
				if ( $buf ) {
					$chunks[] = implode( "\n", $buf );
					$buf      = array();
					$count    = 0;
				}
				$chunks[] = implode( ' ', array_splice( $words, 0, self::WORDS_PER_CHUNK ) );
			}

			if ( $count + count( $words ) > self::WORDS_PER_CHUNK && $buf ) {
				$chunks[] = implode( "\n", $buf );
				$buf      = array();
				$count    = 0;
			}
			$buf[]  = implode( ' ', $words );
			$count += count( $words );
		}
		if ( $buf ) {
			$chunks[] = implode( "\n", $buf );
		}

		return array_slice( $chunks, 0, self::MAX_CHUNKS );
	}

	/**
	 * One batch of a full rebuild.
	 *
	 * @param int $offset Posts already processed.
	 * @param int $size   Batch size.
	 * @return array{done:int,total:int,finished:bool}
	 */
	public static function reindex_batch( $offset, $size = 15 ) {
		global $wpdb;
		$types = (array) PMAIC_Settings::get( 'post_types' );

		if ( 0 === (int) $offset ) {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
		}

		$q = new WP_Query(
			array(
				'post_type'      => $types ? $types : array( 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => (int) $size,
				'offset'         => (int) $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $q->posts as $post ) {
			self::index_post( $post );
		}

		$done     = (int) $offset + count( $q->posts );
		$finished = $done >= (int) $q->found_posts || ! $q->posts;
		if ( $finished ) {
			delete_option( 'pmaic_index_stale' );
			update_option( 'pmaic_indexed_at', time() );
		}

		return array(
			'done'     => $done,
			'total'    => (int) $q->found_posts,
			'finished' => $finished,
		);
	}

	/**
	 * Index size.
	 *
	 * @return array{chunks:int,posts:int}
	 */
	public static function stats() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table, live counts.
		$chunks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
		$posts  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT post_id) FROM %i', self::table() ) );
		// phpcs:enable
		return array(
			'chunks' => $chunks,
			'posts'  => $posts,
		);
	}
}

<?php
/**
 * Keyword search over the content index.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds the most relevant chunks of site content for a question.
 */
class PMAIC_Search {

	const MAX_RESULTS  = 6;
	const MAX_PER_POST = 3;

	/**
	 * English filler words ignored when searching.
	 *
	 * @var string[]
	 */
	private static $stopwords = array(
		'a','an','the','and','or','but','is','are','was','were','be','been','being','to','of','in','on','for','with','at','by','from','as',
		'it','its','this','that','these','those','i','me','my','we','our','you','your','he','she','they','them','their','what','which','who',
		'whom','how','why','when','where','do','does','did','can','could','should','would','will','shall','may','might','must','have','has',
		'had','not','no','yes','so','if','then','than','there','here','about','into','any','some','all','also','just','please','tell','know',
		'want','need','get','give','show','hi','hello','hey','thanks','thank','much','many','more','most','very','really','like','use','using',
	);

	/**
	 * Extract search keywords.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function keywords( $text ) {
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text  = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		$words = preg_split( '/\s+/u', trim( $text ) );
		$stop  = (array) apply_filters( 'pmaic_stopwords', self::$stopwords );
		$out   = array();
		foreach ( $words as $w ) {
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $w, 'UTF-8' ) : strlen( $w );
			if ( $len < 3 || in_array( $w, $stop, true ) ) {
				continue;
			}
			$out[ $w ] = true;
		}
		return array_slice( array_keys( $out ), 0, 12 );
	}

	/**
	 * Find relevant chunks.
	 *
	 * @param string $query Current question plus recent context.
	 * @return array[] Each: post_id, title, url, content.
	 */
	public static function find( $query ) {
		global $wpdb;
		$table = PMAIC_Indexer::table();
		$terms = self::keywords( $query );
		if ( ! $terms ) {
			return array();
		}

		// Boolean mode with prefix matching: "theme" also matches "themes".
		$boolean = implode( ' ', array_map( fn( $t ) => $t . '*', $terms ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table; results depend on the visitor's question.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, post_id, title, url, content, MATCH(title,content) AGAINST(%s IN BOOLEAN MODE) AS score
				 FROM %i
				 WHERE MATCH(title,content) AGAINST(%s IN BOOLEAN MODE)
				 ORDER BY score DESC
				 LIMIT 40',
				$boolean,
				$table,
				$boolean
			),
			ARRAY_A
		);

		// Fallback, e.g. for words shorter than MySQL's minimum full-text word length.
		if ( ! $rows ) {
			// Keywords contain only letters and digits, so they are safe inside a REGEXP alternation.
			$pattern = implode( '|', array_slice( $terms, 0, 5 ) );
			$rows    = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, post_id, title, url, content, 1 AS score FROM %i WHERE title REGEXP %s OR content REGEXP %s LIMIT 40',
					$table,
					$pattern,
					$pattern
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		if ( ! $rows ) {
			return array();
		}

		// Re-rank: boost chunks whose title contains the search words (product names!).
		foreach ( $rows as &$r ) {
			$title = strtolower( $r['title'] );
			$body  = strtolower( $r['content'] );
			$hits  = 0;
			$cover = 0;
			foreach ( $terms as $t ) {
				if ( false !== strpos( $title, $t ) ) {
					++$hits;
				}
				if ( false !== strpos( $title . ' ' . $body, $t ) ) {
					++$cover;
				}
			}
			$r['score'] = (float) $r['score'] + ( $hits * 3 ) + ( $cover / count( $terms ) ) * 2;
		}
		unset( $r );
		usort( $rows, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		// Drop weak matches far below the best one (less noise, lower cost).
		$min      = (float) $rows[0]['score'] * 0.35;
		$out      = array();
		$per_post = array();
		foreach ( $rows as $r ) {
			if ( (float) $r['score'] < $min ) {
				break;
			}
			$pid = (int) $r['post_id'];
			if ( ( $per_post[ $pid ] ?? 0 ) >= self::MAX_PER_POST ) {
				continue;
			}
			$per_post[ $pid ] = ( $per_post[ $pid ] ?? 0 ) + 1;
			$out[]            = array(
				'post_id' => $pid,
				'title'   => $r['title'],
				'url'     => $r['url'],
				'content' => $r['content'],
			);
			if ( count( $out ) >= self::MAX_RESULTS ) {
				break;
			}
		}

		return (array) apply_filters( 'pmaic_search_results', $out, $query );
	}
}

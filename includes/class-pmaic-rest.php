<?php
/**
 * Public chat endpoint.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/pmaic/v1/chat
 */
class PMAIC_Rest {

	const MAX_QUESTION = 500;
	const MAX_HISTORY  = 6;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register the route.
	 */
	public static function routes() {
		register_rest_route(
			'pmaic/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => array( __CLASS__, 'can_chat' ),
				'args'                => array(
					'message' => array(
						'type'     => 'string',
						'required' => true,
					),
					'history' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * Public when enabled; administrators only while in preview mode.
	 *
	 * @return bool
	 */
	public static function can_chat() {
		return (bool) PMAIC_Settings::get( 'enabled' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Handle a question.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function chat( WP_REST_Request $req ) {
		$question = trim( sanitize_textarea_field( (string) $req['message'] ) );
		if ( '' === $question ) {
			return self::error( __( 'Please type a question.', 'postmag-ai-chat' ), 400 );
		}
		$question = mb_substr( $question, 0, self::MAX_QUESTION );

		$limited = self::rate_limited();
		if ( $limited ) {
			return self::error( $limited, 429 );
		}

		$history    = self::clean_history( (array) $req['history'] );
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $question,
		);

		// Search with the current question plus the previous one, so follow-ups like "how much is it?" work.
		$search_text = $question;
		for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
			if ( 'user' === $history[ $i ]['role'] ) {
				$search_text .= ' ' . $history[ $i ]['content'];
				break;
			}
		}
		$chunks = PMAIC_Search::find( $search_text );

		$answer = PMAIC_AI::ask( PMAIC_Prompt::build( $chunks ), $messages );
		if ( is_wp_error( $answer ) ) {
			return self::error( __( 'Sorry, I could not answer right now. Please try again in a moment.', 'postmag-ai-chat' ), 503 );
		}

		self::count_usage();

		$sources = self::linked_sources( $answer, $chunks );
		self::log( $question, $answer, $sources, ! empty( $chunks ) );

		return rest_ensure_response(
			array(
				'answer'  => $answer,
				'sources' => $sources,
			)
		);
	}

	/**
	 * Keep only well-formed, alternating turns that start with "user" and end with "assistant".
	 *
	 * @param array $raw Raw history from the browser.
	 * @return array
	 */
	public static function clean_history( array $raw ) {
		$out = array();
		foreach ( array_slice( $raw, -self::MAX_HISTORY ) as $m ) {
			if ( ! is_array( $m ) || ! isset( $m['role'], $m['content'] ) ) {
				continue;
			}
			$role = 'assistant' === $m['role'] ? 'assistant' : 'user';
			$text = trim( mb_substr( sanitize_textarea_field( (string) $m['content'] ), 0, 1500 ) );
			if ( '' === $text ) {
				continue;
			}
			if ( ! $out && 'user' !== $role ) {
				continue;
			}
			if ( $out && end( $out )['role'] === $role ) {
				continue;
			}
			$out[] = array(
				'role'    => $role,
				'content' => $text,
			);
		}
		if ( $out && 'user' === end( $out )['role'] ) {
			array_pop( $out );
		}
		return $out;
	}

	/**
	 * Pages the answer linked to.
	 *
	 * @param string $answer Answer.
	 * @param array  $chunks Search results.
	 * @return array
	 */
	private static function linked_sources( $answer, array $chunks ) {
		$out = array();
		foreach ( $chunks as $c ) {
			if ( isset( $out[ $c['url'] ] ) ) {
				continue;
			}
			if ( false !== strpos( $answer, untrailingslashit( $c['url'] ) ) ) {
				$out[ $c['url'] ] = array(
					'title' => $c['title'],
					'url'   => $c['url'],
				);
			}
		}
		return array_values( $out );
	}

	/**
	 * Anonymous visitor fingerprint for rate limiting (the IP itself is never stored).
	 *
	 * @return string
	 */
	private static function client_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		// Behind Cloudflare: add_filter( 'pmaic_trust_cloudflare', '__return_true' ).
		if ( apply_filters( 'pmaic_trust_cloudflare', false ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		return md5( $ip . wp_salt( 'nonce' ) );
	}

	/**
	 * Check limits.
	 *
	 * @return string|false Message when limited.
	 */
	private static function rate_limited() {
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		$daily = (int) get_option( 'pmaic_usage_' . gmdate( 'Ymd' ), 0 );
		if ( $daily >= (int) PMAIC_Settings::get( 'daily_limit' ) ) {
			return __( 'The assistant is very busy today. Please contact us directly instead.', 'postmag-ai-chat' );
		}

		$key   = 'pmaic_ip_' . self::client_hash();
		$count = (int) get_transient( $key );
		if ( $count >= (int) PMAIC_Settings::get( 'ip_limit' ) ) {
			return __( 'You have asked a lot of questions in the last hour. Please try again later.', 'postmag-ai-chat' );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Count today's answered questions.
	 */
	private static function count_usage() {
		$key = 'pmaic_usage_' . gmdate( 'Ymd' );
		update_option( $key, (int) get_option( $key, 0 ) + 1, false );
		delete_option( 'pmaic_usage_' . gmdate( 'Ymd', time() - 2 * DAY_IN_SECONDS ) );
	}

	/**
	 * Save the question to the log (if enabled).
	 *
	 * @param string $question Question.
	 * @param string $answer   Answer.
	 * @param array  $sources  Linked pages.
	 * @param bool   $found    Whether matching content existed.
	 */
	private static function log( $question, $answer, $sources, $found ) {
		if ( ! PMAIC_Settings::get( 'log_chats' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'pmaic_log';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table.
		$wpdb->insert(
			$table,
			array(
				'created'  => current_time( 'mysql', true ),
				'question' => $question,
				'answer'   => $answer,
				'sources'  => wp_json_encode( wp_list_pluck( $sources, 'url' ) ),
				'found'    => $found ? 1 : 0,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
		if ( 1 === wp_rand( 1, 50 ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created < %s', $table, gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Error response.
	 *
	 * @param string $msg    Message.
	 * @param int    $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function error( $msg, $status ) {
		return new WP_REST_Response( array( 'error' => $msg ), $status );
	}
}

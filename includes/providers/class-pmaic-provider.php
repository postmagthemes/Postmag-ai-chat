<?php
/**
 * Base class for AI providers.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every provider turns (system prompt + chat messages) into an answer string.
 * Messages always use the roles "user" and "assistant", alternating, ending with "user".
 */
abstract class PMAIC_Provider {

	/**
	 * Unique id, e.g. "claude".
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Model used when none is chosen.
	 *
	 * @return string
	 */
	abstract public function default_model();

	/**
	 * Where users create an API key.
	 *
	 * @return string
	 */
	abstract public function key_url();

	/**
	 * Send the conversation and return the answer.
	 *
	 * @param string $system     System instructions.
	 * @param array  $messages   Conversation turns.
	 * @param int    $max_tokens Answer length limit.
	 * @return string|WP_Error
	 */
	abstract public function ask( $system, array $messages, $max_tokens );

	/**
	 * Available model ids for the configured key.
	 *
	 * @return string[]|WP_Error
	 */
	abstract public function list_models();

	/**
	 * Whether the model can be chosen in the plugin settings.
	 *
	 * @return bool
	 */
	public function supports_model_choice() {
		return true;
	}

	/**
	 * Whether an API key is required.
	 *
	 * @return bool
	 */
	public function needs_key() {
		return true;
	}

	/**
	 * Name of the wp-config.php constant that can hold the key.
	 *
	 * @return string
	 */
	public function key_constant() {
		return 'PMAIC_' . strtoupper( $this->id() ) . '_API_KEY';
	}

	/**
	 * API key: wp-config.php constant first, then saved setting.
	 *
	 * @return string
	 */
	public function api_key() {
		$const = $this->key_constant();
		if ( defined( $const ) && constant( $const ) ) {
			return (string) constant( $const );
		}
		$keys = (array) PMAIC_Settings::get( 'keys' );
		return isset( $keys[ $this->id() ] ) ? (string) $keys[ $this->id() ] : '';
	}

	/**
	 * Whether the key comes from wp-config.php.
	 *
	 * @return bool
	 */
	public function key_from_constant() {
		$const = $this->key_constant();
		return defined( $const ) && constant( $const );
	}

	/**
	 * Selected model for this provider.
	 *
	 * @return string
	 */
	public function model() {
		$models = (array) PMAIC_Settings::get( 'models' );
		return ! empty( $models[ $this->id() ] ) ? (string) $models[ $this->id() ] : $this->default_model();
	}

	/**
	 * JSON HTTP helper with consistent error handling.
	 *
	 * @param string     $method  GET or POST.
	 * @param string     $url     Endpoint.
	 * @param array      $headers Extra headers.
	 * @param array|null $body    JSON body.
	 * @return array|WP_Error Decoded response.
	 */
	protected function request( $method, $url, array $headers = array(), $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => (int) apply_filters( 'pmaic_http_timeout', 45 ),
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code < 200 || $code >= 300 ) {
			// Claude, OpenAI and Gemini all use { "error": { "message": "..." } }.
			$msg = '';
			if ( is_array( $data ) && isset( $data['error'] ) ) {
				$msg = is_array( $data['error'] ) ? (string) ( $data['error']['message'] ?? '' ) : (string) $data['error'];
			}
			/* translators: %d: HTTP status code. */
			$msg = $msg ? $msg : sprintf( __( 'HTTP error %d', 'postmag-ai-chat' ), $code );
			return new WP_Error( 'pmaic_http_' . $code, $msg, array( 'status' => $code ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'pmaic_bad_json', __( 'The AI service returned an unexpected response.', 'postmag-ai-chat' ) );
		}

		return $data;
	}

	/**
	 * Error for an empty answer.
	 *
	 * @return WP_Error
	 */
	protected function empty_answer() {
		return new WP_Error( 'pmaic_empty', __( 'The AI returned an empty answer. Try increasing "Max answer length" or choose a different model.', 'postmag-ai-chat' ) );
	}

	/**
	 * Sort and de-duplicate model ids.
	 *
	 * @param string[] $ids Model ids.
	 * @return string[]
	 */
	protected function clean_model_list( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'strval', $ids ) ) ) );
		sort( $ids, SORT_NATURAL );
		return $ids;
	}
}

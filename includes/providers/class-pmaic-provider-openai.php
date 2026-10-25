<?php
/**
 * OpenAI provider.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI Chat Completions API. Also the base for OpenAI-compatible services.
 */
class PMAIC_Provider_OpenAI extends PMAIC_Provider {

	/** {@inheritDoc} */
	public function id() {
		return 'openai';
	}

	/** {@inheritDoc} */
	public function label() {
		return 'OpenAI';
	}

	/** {@inheritDoc} */
	public function default_model() {
		return 'gpt-5-mini';
	}

	/** {@inheritDoc} */
	public function key_url() {
		return 'https://platform.openai.com/api-keys';
	}

	/**
	 * API base URL without trailing slash.
	 *
	 * @return string
	 */
	protected function base_url() {
		return 'https://api.openai.com/v1';
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	protected function headers() {
		$key = $this->api_key();
		return $key ? array( 'Authorization' => 'Bearer ' . $key ) : array();
	}

	/**
	 * Reasoning models (o-series, gpt-5 family) spend hidden "thinking" tokens from the
	 * same budget, so they need a larger limit and a low reasoning effort.
	 *
	 * @param string $model Model id.
	 * @return bool
	 */
	protected function is_reasoning_model( $model ) {
		return (bool) preg_match( '/^(o\d|gpt-5)/i', $model );
	}

	/**
	 * Request body.
	 *
	 * @param string $system     System prompt.
	 * @param array  $messages   Turns.
	 * @param int    $max_tokens Answer length.
	 * @return array
	 */
	protected function body( $system, array $messages, $max_tokens ) {
		$model = $this->model();
		$body  = array(
			'model'    => $model,
			'messages' => array_merge(
				array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
				),
				$messages
			),
		);

		if ( $this->is_reasoning_model( $model ) ) {
			$body['max_completion_tokens'] = max( 2000, (int) $max_tokens * 4 );
			$body['reasoning_effort']      = 'low';
		} else {
			$body['max_tokens'] = (int) $max_tokens;
		}
		return $body;
	}

	/** {@inheritDoc} */
	public function ask( $system, array $messages, $max_tokens ) {
		$data = $this->request( 'POST', $this->base_url() . '/chat/completions', $this->headers(), $this->body( $system, $messages, $max_tokens ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = $data['choices'][0]['message']['content'] ?? '';
		$text = is_string( $text ) ? trim( $text ) : '';
		return '' === $text ? $this->empty_answer() : $text;
	}

	/** {@inheritDoc} */
	public function list_models() {
		$data = $this->request( 'GET', $this->base_url() . '/models', $this->headers() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$ids = wp_list_pluck( (array) ( $data['data'] ?? array() ), 'id' );
		// Hide models that cannot chat (embeddings, audio, images…).
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return ! preg_match( '/(embed|whisper|tts|dall-e|image|audio|realtime|transcribe|moderation|search|computer|davinci|babbage|sora)/i', (string) $id );
			}
		);
		return $this->clean_model_list( $ids );
	}
}

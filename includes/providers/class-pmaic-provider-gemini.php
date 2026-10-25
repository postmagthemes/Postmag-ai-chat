<?php
/**
 * Google Gemini provider.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gemini generateContent API.
 */
class PMAIC_Provider_Gemini extends PMAIC_Provider {

	const API = 'https://generativelanguage.googleapis.com/v1beta/';

	/** {@inheritDoc} */
	public function id() {
		return 'gemini';
	}

	/** {@inheritDoc} */
	public function label() {
		return 'Google Gemini';
	}

	/** {@inheritDoc} */
	public function default_model() {
		return 'gemini-3.5-flash-lite';
	}

	/** {@inheritDoc} */
	public function key_url() {
		return 'https://aistudio.google.com/app/apikey';
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'x-goog-api-key' => $this->api_key() );
	}

	/** {@inheritDoc} */
	public function ask( $system, array $messages, $max_tokens ) {
		$contents = array();
		foreach ( $messages as $m ) {
			$contents[] = array(
				'role'  => 'assistant' === $m['role'] ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			);
		}

		$model = rawurlencode( $this->model() );
		$data  = $this->request(
			'POST',
			self::API . 'models/' . $model . ':generateContent',
			$this->headers(),
			array(
				'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
				'contents'          => $contents,
				// Newer Gemini models "think" using the same token budget, so allow headroom.
				'generationConfig'  => array( 'maxOutputTokens' => max( 2000, (int) $max_tokens * 4 ) ),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! empty( $data['promptFeedback']['blockReason'] ) ) {
			return new WP_Error( 'pmaic_blocked', __( 'The request was blocked by the AI provider’s safety filter.', 'postmag-ai-chat' ) );
		}

		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
				$text .= $part['text'];
			}
		}
		$text = trim( $text );
		return '' === $text ? $this->empty_answer() : $text;
	}

	/** {@inheritDoc} */
	public function list_models() {
		$data = $this->request( 'GET', self::API . 'models?pageSize=200', $this->headers() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$ids = array();
		foreach ( (array) ( $data['models'] ?? array() ) as $m ) {
			$methods = (array) ( $m['supportedGenerationMethods'] ?? array() );
			$id      = preg_replace( '#^models/#', '', (string) ( $m['name'] ?? '' ) );
			if ( in_array( 'generateContent', $methods, true ) && ! preg_match( '/(embed|imagen|image|tts|aqa|veo)/i', $id ) ) {
				$ids[] = $id;
			}
		}
		return $this->clean_model_list( $ids );
	}
}

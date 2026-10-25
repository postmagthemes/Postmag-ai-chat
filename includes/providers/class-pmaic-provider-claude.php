<?php
/**
 * Anthropic Claude provider.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Claude Messages API.
 */
class PMAIC_Provider_Claude extends PMAIC_Provider {

	const API = 'https://api.anthropic.com/v1/';

	/** {@inheritDoc} */
	public function id() {
		return 'claude';
	}

	/** {@inheritDoc} */
	public function label() {
		return 'Anthropic Claude';
	}

	/** {@inheritDoc} */
	public function default_model() {
		return 'claude-haiku-5-5';
	}

	/** {@inheritDoc} */
	public function key_url() {
		return 'https://console.anthropic.com/settings/keys';
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'x-api-key'         => $this->api_key(),
			'anthropic-version' => '2023-06-01',
		);
	}

	/** {@inheritDoc} */
	public function ask( $system, array $messages, $max_tokens ) {
		$data = $this->request(
			'POST',
			self::API . 'messages',
			$this->headers(),
			array(
				'model'      => $this->model(),
				'max_tokens' => (int) $max_tokens,
				'system'     => $system,
				'messages'   => $messages,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'];
			}
		}
		$text = trim( $text );
		return '' === $text ? $this->empty_answer() : $text;
	}

	/** {@inheritDoc} */
	public function list_models() {
		$data = $this->request( 'GET', self::API . 'models?limit=100', $this->headers() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return $this->clean_model_list( wp_list_pluck( (array) ( $data['data'] ?? array() ), 'id' ) );
	}
}

<?php
/**
 * OpenAI-compatible provider (OpenRouter, DeepSeek, Mistral, Groq, Ollama, LM Studio…).
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Any service that implements the OpenAI Chat Completions format at a custom base URL.
 */
class PMAIC_Provider_Compatible extends PMAIC_Provider_OpenAI {

	/** {@inheritDoc} */
	public function id() {
		return 'compatible';
	}

	/** {@inheritDoc} */
	public function label() {
		return __( 'OpenAI-compatible (OpenRouter, DeepSeek, Mistral, Groq, Ollama…)', 'postmag-ai-chat' );
	}

	/** {@inheritDoc} */
	public function default_model() {
		return '';
	}

	/** {@inheritDoc} */
	public function key_url() {
		return '';
	}

	/** {@inheritDoc} */
	public function needs_key() {
		return false; // Local servers such as Ollama need no key.
	}

	/** {@inheritDoc} */
	protected function base_url() {
		return untrailingslashit( (string) PMAIC_Settings::get( 'base_url' ) );
	}

	/** {@inheritDoc} */
	protected function is_reasoning_model( $model ) {
		return false; // Third-party services use the classic max_tokens parameter.
	}

	/** {@inheritDoc} */
	public function ask( $system, array $messages, $max_tokens ) {
		if ( ! $this->base_url() ) {
			return new WP_Error( 'pmaic_no_base', __( 'Enter the API base URL for your OpenAI-compatible service.', 'postmag-ai-chat' ) );
		}
		if ( ! $this->model() ) {
			return new WP_Error( 'pmaic_no_model', __( 'Choose a model.', 'postmag-ai-chat' ) );
		}
		return parent::ask( $system, $messages, $max_tokens );
	}

	/** {@inheritDoc} */
	public function list_models() {
		if ( ! $this->base_url() ) {
			return new WP_Error( 'pmaic_no_base', __( 'Enter the API base URL first, then save.', 'postmag-ai-chat' ) );
		}
		$data = $this->request( 'GET', $this->base_url() . '/models', $this->headers() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return $this->clean_model_list( wp_list_pluck( (array) ( $data['data'] ?? array() ), 'id' ) );
	}
}

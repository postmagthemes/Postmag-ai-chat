<?php
/**
 * WordPress AI Client provider (WordPress 7.0+).
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Uses the AI service the site owner configured once in Settings → Connectors,
 * through WordPress core's AI Client. No key is stored by this plugin.
 */
class PMAIC_Provider_WordPress extends PMAIC_Provider {

	/**
	 * Whether this WordPress version has the AI Client.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'wp_ai_client_prompt' ) && class_exists( '\WordPress\AiClient\Messages\DTO\Message' );
	}

	/** {@inheritDoc} */
	public function id() {
		return 'wordpress';
	}

	/** {@inheritDoc} */
	public function label() {
		return __( 'WordPress AI (Settings → Connectors)', 'postmag-ai-chat' );
	}

	/** {@inheritDoc} */
	public function default_model() {
		return '';
	}

	/** {@inheritDoc} */
	public function key_url() {
		return admin_url( 'options-connectors.php' );
	}

	/** {@inheritDoc} */
	public function needs_key() {
		return false;
	}

	/** {@inheritDoc} */
	public function supports_model_choice() {
		return false;
	}

	/** {@inheritDoc} */
	public function model() {
		return __( 'chosen by WordPress', 'postmag-ai-chat' );
	}

	/** {@inheritDoc} */
	public function ask( $system, array $messages, $max_tokens ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pmaic_no_ai_client', __( 'The WordPress AI Client requires WordPress 7.0 or newer.', 'postmag-ai-chat' ) );
		}
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return new WP_Error( 'pmaic_ai_disabled', __( 'AI features are disabled on this site.', 'postmag-ai-chat' ) );
		}

		try {
			$list = array();
			foreach ( $messages as $m ) {
				$list[] = \WordPress\AiClient\Messages\DTO\Message::fromArray(
					array(
						'role'  => 'assistant' === $m['role'] ? 'model' : 'user',
						'parts' => array(
							array(
								'type' => 'text',
								'text' => $m['content'],
							),
						),
					)
				);
			}

			$builder = wp_ai_client_prompt( $list )
				->using_system_instruction( $system )
				->using_max_tokens( max( 2000, (int) $max_tokens * 4 ) );

			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error( 'pmaic_no_connector', __( 'No working AI service is connected. Check Settings → Connectors.', 'postmag-ai-chat' ) );
			}

			$text = $builder->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'pmaic_ai_client', $e->getMessage() );
		}

		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = trim( (string) $text );
		return '' === $text ? $this->empty_answer() : $text;
	}

	/** {@inheritDoc} */
	public function list_models() {
		return new WP_Error( 'pmaic_no_models', __( 'The model is chosen in Settings → Connectors.', 'postmag-ai-chat' ) );
	}
}

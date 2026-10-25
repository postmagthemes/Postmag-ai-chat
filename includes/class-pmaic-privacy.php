<?php
/**
 * Privacy policy text.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suggests privacy-policy text under Settings → Privacy → Policy Guide.
 */
class PMAIC_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_policy_text' ) );
	}

	/**
	 * Register suggested text.
	 */
	public static function add_policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for sites that use PostMag AI Chat. Edit it to match the AI provider you selected and whether question logging is turned on.', 'postmag-ai-chat' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'postmag-ai-chat' ) . ' </strong>';
		$content .= esc_html__( 'This website offers an AI chat assistant. When you ask it a question, your question, recent messages from the same conversation, and relevant public content from this website are sent to a third-party AI provider to generate an answer. Please do not enter personal or sensitive information in the chat. Your IP address is used only in anonymised form to prevent abuse and is not stored with your questions.', 'postmag-ai-chat' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If question logging is enabled: questions and answers are stored on this website for up to 90 days to improve our content. They are not linked to your name, account or IP address.', 'postmag-ai-chat' ) . '</p>';

		wp_add_privacy_policy_content( 'PostMag AI Chat', wp_kses_post( $content ) );
	}
}

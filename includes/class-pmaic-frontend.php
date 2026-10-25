<?php
/**
 * Front-end chat widget loader.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the floating chat widget.
 */
class PMAIC_Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Whether the widget should load on this request.
	 *
	 * @return bool
	 */
	public static function should_show() {
		$show = (bool) PMAIC_Settings::get( 'enabled' ) || current_user_can( 'manage_options' );
		return (bool) apply_filters( 'pmaic_show_widget', $show );
	}

	/**
	 * Enqueue assets.
	 */
	public static function enqueue() {
		if ( ! self::should_show() ) {
			return;
		}
		$s = PMAIC_Settings::get();

		wp_enqueue_style( 'pmaic-chat', PMAIC_URL . 'assets/chat.css', array(), PMAIC_VERSION );
		wp_enqueue_script( 'pmaic-chat', PMAIC_URL . 'assets/chat.js', array(), PMAIC_VERSION, true );

		$config = array(
			'endpoint' => esc_url_raw( rest_url( 'pmaic/v1/chat' ) ),
			// Only logged-in users get a nonce, so logged-out pages stay safe to cache.
			'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'name'     => $s['bot_name'],
			'welcome'  => $s['welcome'],
			'contact'  => $s['contact_url'],
			'accent'   => $s['accent'],
			'position' => $s['position'],
			'preview'  => ! $s['enabled'],
			'i18n'     => array(
				'open'        => __( 'Open chat', 'postmag-ai-chat' ),
				'close'       => __( 'Close chat', 'postmag-ai-chat' ),
				'reset'       => __( 'Start a new conversation', 'postmag-ai-chat' ),
				'subtitle'    => __( 'AI answers from this website', 'postmag-ai-chat' ),
				'preview'     => __( 'Preview – only administrators see this', 'postmag-ai-chat' ),
				'placeholder' => __( 'Type your question…', 'postmag-ai-chat' ),
				'send'        => __( 'Send', 'postmag-ai-chat' ),
				'question'    => __( 'Your question', 'postmag-ai-chat' ),
				'sources'     => __( 'Sources:', 'postmag-ai-chat' ),
				'disclaimer'  => __( 'AI can make mistakes.', 'postmag-ai-chat' ),
				'contact'     => __( 'Contact us', 'postmag-ai-chat' ),
				'error'       => __( 'Something went wrong. Please try again.', 'postmag-ai-chat' ),
			),
		);
		wp_add_inline_script( 'pmaic-chat', 'window.PMAIC = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}

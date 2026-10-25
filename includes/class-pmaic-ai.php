<?php
/**
 * Provider registry and the single entry point used by the rest of the plugin.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Picks the active provider and records the last error for the admin screen.
 */
class PMAIC_AI {

	/**
	 * Cached provider objects.
	 *
	 * @var PMAIC_Provider[]|null
	 */
	private static $providers = null;

	/**
	 * All registered providers, keyed by id.
	 *
	 * Developers can add their own: extend PMAIC_Provider and use the pmaic_providers filter.
	 *
	 * @return PMAIC_Provider[]
	 */
	public static function providers() {
		if ( null === self::$providers ) {
			$list = array();
			if ( PMAIC_Provider_WordPress::available() ) {
				$list[] = new PMAIC_Provider_WordPress();
			}
			$list = array_merge(
				$list,
				array(
					new PMAIC_Provider_Claude(),
					new PMAIC_Provider_OpenAI(),
					new PMAIC_Provider_Gemini(),
					new PMAIC_Provider_Compatible(),
				)
			);
			$list            = apply_filters( 'pmaic_providers', $list );
			self::$providers = array();
			foreach ( $list as $p ) {
				if ( $p instanceof PMAIC_Provider ) {
					self::$providers[ $p->id() ] = $p;
				}
			}
		}
		return self::$providers;
	}

	/**
	 * A provider by id (defaults to the selected one).
	 *
	 * @param string|null $id Provider id.
	 * @return PMAIC_Provider|null
	 */
	public static function provider( $id = null ) {
		$all = self::providers();
		$id  = $id ? $id : (string) PMAIC_Settings::get( 'provider' );
		return $all[ $id ] ?? ( $all['claude'] ?? reset( $all ) );
	}

	/**
	 * Ask the active provider.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Turns.
	 * @return string|WP_Error
	 */
	public static function ask( $system, array $messages ) {
		$p = self::provider();
		if ( ! $p ) {
			return self::fail( new WP_Error( 'pmaic_no_provider', __( 'No AI provider is available.', 'postmag-ai-chat' ) ) );
		}
		if ( $p->needs_key() && ! $p->api_key() ) {
			/* translators: %s: provider name. */
			return self::fail( new WP_Error( 'pmaic_no_key', sprintf( __( 'No API key is set for %s.', 'postmag-ai-chat' ), $p->label() ) ) );
		}

		$result = $p->ask( $system, $messages, (int) PMAIC_Settings::get( 'max_tokens' ) );
		if ( is_wp_error( $result ) ) {
			return self::fail( $result );
		}
		delete_option( 'pmaic_last_error' );
		return $result;
	}

	/**
	 * Remember an error so the settings page can show it.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	private static function fail( WP_Error $error ) {
		update_option(
			'pmaic_last_error',
			array(
				'time'     => time(),
				'provider' => (string) PMAIC_Settings::get( 'provider' ),
				'message'  => sanitize_text_field( $error->get_error_message() ),
			),
			false
		);
		return $error;
	}
}

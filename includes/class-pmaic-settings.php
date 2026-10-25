<?php
/**
 * Settings storage and sanitizing.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 */
class PMAIC_Settings {

	const OPTION = 'pmaic_settings';

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		$types = array( 'post', 'page' );
		if ( post_type_exists( 'download' ) ) {
			$types[] = 'download'; // Easy Digital Downloads.
		}
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product'; // WooCommerce.
		}

		return array(
			'enabled'     => 0,
			'provider'    => PMAIC_Provider_WordPress::available() ? 'wordpress' : 'claude',
			'keys'        => array(),
			'models'      => array(),
			'base_url'    => '',
			'post_types'  => $types,
			'bot_name'    => __( 'Site Assistant', 'postmag-ai-chat' ),
			'welcome'     => __( 'Hi! 👋 Ask me anything about this website.', 'postmag-ai-chat' ),
			'contact_url' => '',
			'site_info'   => '',
			'catalog'     => 1,
			'accent'      => '#2563eb',
			'position'    => 'right',
			'ip_limit'    => 30,
			'daily_limit' => 500,
			'max_tokens'  => 700,
			'log_chats'   => 0,
		);
	}

	/**
	 * Get all settings or one value.
	 *
	 * @param string|null $key Setting name.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $opts : ( $opts[ $key ] ?? null );
	}

	/**
	 * Sanitize the settings form.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$old   = self::get();
		$out   = array();
		$ids   = array_keys( PMAIC_AI::providers() );

		$out['enabled']   = empty( $input['enabled'] ) ? 0 : 1;
		$out['log_chats'] = empty( $input['log_chats'] ) ? 0 : 1;
		$out['catalog']   = empty( $input['catalog'] ) ? 0 : 1;

		$out['provider'] = isset( $input['provider'] ) && in_array( $input['provider'], $ids, true ) ? $input['provider'] : $old['provider'];

		// API keys: a blank field keeps the saved key (keys are never printed back into the page).
		$out['keys'] = array();
		foreach ( $ids as $id ) {
			$new = isset( $input['keys'][ $id ] ) ? trim( sanitize_text_field( $input['keys'][ $id ] ) ) : '';
			$cur = isset( $old['keys'][ $id ] ) ? $old['keys'][ $id ] : '';
			$val = '' === $new ? $cur : $new;
			if ( ! empty( $input['clear_keys'][ $id ] ) ) {
				$val = '';
			}
			if ( '' !== $val ) {
				$out['keys'][ $id ] = $val;
			}
		}

		$out['models'] = array();
		foreach ( $ids as $id ) {
			$model = isset( $input['models'][ $id ] ) ? trim( sanitize_text_field( $input['models'][ $id ] ) ) : '';
			if ( '' !== $model && preg_match( '#^[A-Za-z0-9._:/@\-]+$#', $model ) ) {
				$out['models'][ $id ] = $model;
			}
		}

		$base            = isset( $input['base_url'] ) ? esc_url_raw( trim( $input['base_url'] ), array( 'http', 'https' ) ) : '';
		$out['base_url'] = untrailingslashit( $base );

		$types             = isset( $input['post_types'] ) ? (array) $input['post_types'] : array();
		$out['post_types'] = array_values( array_filter( array_map( 'sanitize_key', $types ), 'post_type_exists' ) );

		$out['bot_name']    = isset( $input['bot_name'] ) && '' !== trim( $input['bot_name'] ) ? sanitize_text_field( $input['bot_name'] ) : $d['bot_name'];
		$out['welcome']     = isset( $input['welcome'] ) ? sanitize_textarea_field( $input['welcome'] ) : $d['welcome'];
		$out['contact_url'] = isset( $input['contact_url'] ) ? esc_url_raw( $input['contact_url'] ) : '';
		$out['site_info']   = isset( $input['site_info'] ) ? sanitize_textarea_field( $input['site_info'] ) : '';
		$accent             = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		$out['accent']      = $accent ? $accent : $d['accent'];
		$out['position']    = isset( $input['position'] ) && 'left' === $input['position'] ? 'left' : 'right';

		$out['ip_limit']    = isset( $input['ip_limit'] ) ? max( 1, min( 500, absint( $input['ip_limit'] ) ) ) : $d['ip_limit'];
		$out['daily_limit'] = isset( $input['daily_limit'] ) ? max( 1, min( 100000, absint( $input['daily_limit'] ) ) ) : $d['daily_limit'];
		$out['max_tokens']  = isset( $input['max_tokens'] ) ? max( 150, min( 4000, absint( $input['max_tokens'] ) ) ) : $d['max_tokens'];

		if ( $out['post_types'] !== $old['post_types'] ) {
			update_option( 'pmaic_index_stale', 1 );
		}

		return $out;
	}
}

<?php
/**
 * Plugin Name:       PostMag AI Chat – Website Chatbot for Claude, OpenAI & Gemini
 * Plugin URI:        https://postmagthemes.com/
 * Description:       An AI chatbot that answers visitor questions using only your own posts, pages and products. Works with Claude, OpenAI, Gemini or any OpenAI-compatible service, using your own API key.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            PostMag Themes
 * Author URI:        https://postmagthemes.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       postmag-ai-chat
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

define( 'PMAIC_VERSION', '1.1.0' );
define( 'PMAIC_FILE', __FILE__ );
define( 'PMAIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMAIC_URL', plugin_dir_url( __FILE__ ) );
define( 'PMAIC_DB_VERSION', '1' );

require_once PMAIC_DIR . 'includes/class-pmaic-settings.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider-claude.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider-openai.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider-gemini.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider-compatible.php';
require_once PMAIC_DIR . 'includes/providers/class-pmaic-provider-wordpress.php';
require_once PMAIC_DIR . 'includes/class-pmaic-ai.php';
require_once PMAIC_DIR . 'includes/class-pmaic-prompt.php';
require_once PMAIC_DIR . 'includes/class-pmaic-catalog.php';
require_once PMAIC_DIR . 'includes/class-pmaic-indexer.php';
require_once PMAIC_DIR . 'includes/class-pmaic-search.php';
require_once PMAIC_DIR . 'includes/class-pmaic-rest.php';
require_once PMAIC_DIR . 'includes/class-pmaic-admin.php';
require_once PMAIC_DIR . 'includes/class-pmaic-frontend.php';
require_once PMAIC_DIR . 'includes/class-pmaic-privacy.php';

/**
 * Create or upgrade the plugin's database tables.
 */
function pmaic_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$chunks  = $wpdb->prefix . 'pmaic_chunks';
	$log     = $wpdb->prefix . 'pmaic_log';

	// FULLTEXT index: fast keyword search with no external vector database,
	// and no re-indexing when switching AI provider.
	dbDelta(
		"CREATE TABLE {$chunks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			chunk_index smallint(5) unsigned NOT NULL DEFAULT 0,
			post_type varchar(20) NOT NULL DEFAULT '',
			title text NOT NULL,
			url varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			FULLTEXT KEY ft_search (title,content)
		) ENGINE=InnoDB {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			question text NOT NULL,
			answer text NOT NULL,
			sources text NOT NULL,
			found tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY created (created)
		) {$charset};"
	);

	update_option( 'pmaic_db_version', PMAIC_DB_VERSION );

	if ( false === get_option( PMAIC_Settings::OPTION ) ) {
		add_option( PMAIC_Settings::OPTION, PMAIC_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'pmaic_install' );

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'pmaic_db_version' ) !== PMAIC_DB_VERSION ) {
			pmaic_install();
		}
		PMAIC_Indexer::init();
		PMAIC_Catalog::init();
		PMAIC_Rest::init();
		PMAIC_Admin::init();
		PMAIC_Frontend::init();
		PMAIC_Privacy::init();
	}
);

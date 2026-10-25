<?php
/**
 * Admin screens.
 *
 * @package PostMag_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → AI Chat: provider setup, index rebuild, question log, per-post exclude box.
 */
class PMAIC_Admin {

	const SLUG = 'postmag-ai-chat';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_pmaic_reindex', array( __CLASS__, 'ajax_reindex' ) );
		add_action( 'wp_ajax_pmaic_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_pmaic_models', array( __CLASS__, 'ajax_models' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PMAIC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Menu item.
	 */
	public static function menu() {
		add_options_page(
			__( 'PostMag AI Chat', 'postmag-ai-chat' ),
			__( 'AI Chat', 'postmag-ai-chat' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'postmag-ai-chat' ) . '</a>' );
		return $links;
	}

	/**
	 * Register the option.
	 */
	public static function register() {
		register_setting(
			'pmaic',
			PMAIC_Settings::OPTION,
			array( 'sanitize_callback' => array( 'PMAIC_Settings', 'sanitize' ) )
		);
	}

	/**
	 * Admin CSS/JS on our page only.
	 *
	 * @param string $hook Page hook.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'pmaic-admin', PMAIC_URL . 'assets/admin.css', array(), PMAIC_VERSION );
		wp_enqueue_script( 'pmaic-admin', PMAIC_URL . 'assets/admin.js', array(), PMAIC_VERSION, true );
		wp_localize_script(
			'pmaic-admin',
			'PMAIC_ADMIN',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'pmaic_admin' ),
				'i18n'  => array(
					/* translators: 1: items done, 2: total items. */
					'indexed'   => __( 'Indexed %1$d of %2$d…', 'postmag-ai-chat' ),
					/* translators: %d: number of items. */
					'done'      => __( 'Done – indexed %d items. Reloading…', 'postmag-ai-chat' ),
					'testing'   => __( 'Testing…', 'postmag-ai-chat' ),
					'connected' => __( 'Connected. The AI replied:', 'postmag-ai-chat' ),
					'loading'   => __( 'Loading models…', 'postmag-ai-chat' ),
					/* translators: %d: number of models. */
					'models'    => __( '%d models found – pick one from the list in the Model field.', 'postmag-ai-chat' ),
					'failed'    => __( 'Failed', 'postmag-ai-chat' ),
					'unsaved'   => __( 'You have unsaved changes – click “Save Changes” first so the test uses them.', 'postmag-ai-chat' ),
				),
			)
		);
	}

	/**
	 * Remind to build the index.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::SLUG === $screen->id ) {
			return;
		}
		if ( get_option( 'pmaic_index_stale' ) || ! get_option( 'pmaic_indexed_at' ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'PostMag AI Chat:', 'postmag-ai-chat' ),
				esc_html__( 'the content index needs to be built.', 'postmag-ai-chat' ),
				esc_url( self::url() ),
				esc_html__( 'Build it now', 'postmag-ai-chat' )
			);
		}
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Render the settings page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) && 'log' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'log' : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		?>
		<div class="wrap pmaic-admin">
			<h1><?php esc_html_e( 'PostMag AI Chat', 'postmag-ai-chat' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url() ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'postmag-ai-chat' ); ?></a>
				<a href="<?php echo esc_url( self::url( array( 'tab' => 'log' ) ) ); ?>" class="nav-tab <?php echo 'log' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Visitor questions', 'postmag-ai-chat' ); ?></a>
			</nav>
			<?php
			if ( 'log' === $tab ) {
				self::log_tab();
			} else {
				self::status_card();
				self::settings_form();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Status box with index and connection tools.
	 */
	private static function status_card() {
		$s        = PMAIC_Settings::get();
		$stats    = PMAIC_Indexer::stats();
		$indexed  = get_option( 'pmaic_indexed_at' );
		$error    = get_option( 'pmaic_last_error' );
		$usage    = (int) get_option( 'pmaic_usage_' . gmdate( 'Ymd' ), 0 );
		$provider = PMAIC_AI::provider();
		?>
		<div class="pmaic-card">
			<h2><?php esc_html_e( 'Status', 'postmag-ai-chat' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Chat widget:', 'postmag-ai-chat' ); ?></strong>
				<?php if ( $s['enabled'] ) : ?>
					<span class="pmaic-ok"><?php esc_html_e( 'Live for all visitors', 'postmag-ai-chat' ); ?></span>
				<?php else : ?>
					<span class="pmaic-warn"><?php esc_html_e( 'Preview mode – only visible to administrators', 'postmag-ai-chat' ); ?></span>
				<?php endif; ?>
			</p>
			<p><strong><?php esc_html_e( 'AI provider:', 'postmag-ai-chat' ); ?></strong> <?php echo esc_html( $provider->label() . ' · ' . ( $provider->model() ? $provider->model() : '—' ) ); ?></p>
			<p><strong><?php esc_html_e( 'Index:', 'postmag-ai-chat' ); ?></strong>
				<?php
				/* translators: 1: number of pages, 2: number of text chunks. */
				echo esc_html( sprintf( __( '%1$d pages, %2$d chunks', 'postmag-ai-chat' ), $stats['posts'], $stats['chunks'] ) );
				echo ' · ';
				if ( $indexed ) {
					/* translators: %s: time ago, e.g. "5 mins". */
					echo esc_html( sprintf( __( 'built %s ago', 'postmag-ai-chat' ), human_time_diff( (int) $indexed ) ) );
				} else {
					echo '<em>' . esc_html__( 'not built yet', 'postmag-ai-chat' ) . '</em>';
				}
				?>
			</p>
			<p><strong><?php esc_html_e( 'Questions today:', 'postmag-ai-chat' ); ?></strong> <?php echo esc_html( $usage . ' / ' . $s['daily_limit'] ); ?></p>
			<?php if ( is_array( $error ) && ! empty( $error['message'] ) ) : ?>
				<p class="pmaic-err">
					<strong><?php esc_html_e( 'Last AI error:', 'postmag-ai-chat' ); ?></strong>
					<?php
					/* translators: %s: time ago. */
					echo esc_html( sprintf( __( '%s ago', 'postmag-ai-chat' ), human_time_diff( (int) $error['time'] ) ) . ' – ' . $error['message'] );
					?>
				</p>
			<?php endif; ?>
			<p>
				<button type="button" class="button button-primary" id="pmaic-reindex"><?php esc_html_e( 'Rebuild index', 'postmag-ai-chat' ); ?></button>
				<button type="button" class="button" id="pmaic-test"><?php esc_html_e( 'Test AI connection', 'postmag-ai-chat' ); ?></button>
			</p>
			<p id="pmaic-progress" aria-live="polite"></p>
			<p class="description"><?php esc_html_e( 'Content is re-indexed automatically when you publish or update it. Rebuild after first setup or after changing the content types below. Switching AI provider does not require a rebuild.', 'postmag-ai-chat' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Main settings form.
	 */
	private static function settings_form() {
		$s     = PMAIC_Settings::get();
		$opt   = PMAIC_Settings::OPTION;
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		?>
		<form method="post" action="options.php" id="pmaic-form">
			<?php settings_fields( 'pmaic' ); ?>

			<h2 class="title"><?php esc_html_e( 'AI provider', 'postmag-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pmaic-provider"><?php esc_html_e( 'Provider', 'postmag-ai-chat' ); ?></label></th>
					<td>
						<select id="pmaic-provider" name="<?php echo esc_attr( $opt ); ?>[provider]">
							<?php foreach ( PMAIC_AI::providers() as $p ) : ?>
								<option value="<?php echo esc_attr( $p->id() ); ?>" <?php selected( $s['provider'], $p->id() ); ?>><?php echo esc_html( $p->label() ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'You pay the provider directly for usage with your own API key. Keys for every provider are kept, so you can switch at any time.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
			</table>

			<?php foreach ( PMAIC_AI::providers() as $p ) : ?>
				<?php
				$id      = $p->id();
				$saved   = ! empty( $s['keys'][ $id ] );
				$list_id = 'pmaic-models-' . $id;
				?>
				<table class="form-table pmaic-provider-section" role="presentation" data-provider="<?php echo esc_attr( $id ); ?>">
					<?php if ( 'wordpress' === $id ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connection', 'postmag-ai-chat' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Uses the AI service connected to WordPress itself, so you manage API keys in one place and share them with other AI plugins.', 'postmag-ai-chat' ); ?></p>
								<p><a class="button" href="<?php echo esc_url( $p->key_url() ); ?>"><?php esc_html_e( 'Open Settings → Connectors', 'postmag-ai-chat' ); ?></a></p>
							</td>
						</tr>
					</table>
					<?php continue; ?>
					<?php endif; ?>
					<?php if ( 'compatible' === $id ) : ?>
						<tr>
							<th scope="row"><label for="pmaic-base"><?php esc_html_e( 'API base URL', 'postmag-ai-chat' ); ?></label></th>
							<td>
								<input type="url" id="pmaic-base" class="regular-text code" name="<?php echo esc_attr( $opt ); ?>[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>" placeholder="https://openrouter.ai/api/v1">
								<p class="description"><?php esc_html_e( 'Examples: https://openrouter.ai/api/v1 · https://api.deepseek.com/v1 · https://api.mistral.ai/v1 · https://api.groq.com/openai/v1 · http://localhost:11434/v1 (Ollama)', 'postmag-ai-chat' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="pmaic-key-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'API key', 'postmag-ai-chat' ); ?></label></th>
						<td>
							<?php if ( $p->key_from_constant() ) : ?>
								<p class="pmaic-ok">
									<?php
									/* translators: %s: PHP constant name. */
									echo esc_html( sprintf( __( 'Using %s from wp-config.php', 'postmag-ai-chat' ), $p->key_constant() ) );
									?>
								</p>
							<?php else : ?>
								<input type="password" id="pmaic-key-<?php echo esc_attr( $id ); ?>" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[keys][<?php echo esc_attr( $id ); ?>]" value="" autocomplete="new-password"
									placeholder="<?php echo $saved ? esc_attr__( 'Saved – leave blank to keep', 'postmag-ai-chat' ) : ( $p->needs_key() ? '' : esc_attr__( 'Optional', 'postmag-ai-chat' ) ); ?>">
								<?php if ( $saved ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[clear_keys][<?php echo esc_attr( $id ); ?>]" value="1"> <?php esc_html_e( 'Remove saved key', 'postmag-ai-chat' ); ?></label>
								<?php endif; ?>
								<p class="description">
									<?php if ( $p->key_url() ) : ?>
										<a href="<?php echo esc_url( $p->key_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get an API key', 'postmag-ai-chat' ); ?></a> ·
									<?php endif; ?>
									<?php
									/* translators: %s: PHP code to add to wp-config.php. */
									echo esc_html( sprintf( __( 'More secure: add %s to wp-config.php instead.', 'postmag-ai-chat' ), "define( '" . $p->key_constant() . "', '...' );" ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pmaic-model-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Model', 'postmag-ai-chat' ); ?></label></th>
						<td>
							<input type="text" id="pmaic-model-<?php echo esc_attr( $id ); ?>" class="regular-text code" list="<?php echo esc_attr( $list_id ); ?>" name="<?php echo esc_attr( $opt ); ?>[models][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $s['models'][ $id ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $p->default_model() ); ?>">
							<datalist id="<?php echo esc_attr( $list_id ); ?>"></datalist>
							<button type="button" class="button pmaic-load-models" data-provider="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Load models', 'postmag-ai-chat' ); ?></button>
							<p class="description">
								<?php
								if ( $p->default_model() ) {
									/* translators: %s: model id. */
									echo esc_html( sprintf( __( 'Leave blank to use %s. Small, fast models are cheapest and work well for website questions.', 'postmag-ai-chat' ), $p->default_model() ) );
								} else {
									esc_html_e( 'Required. Save the base URL and key, then click “Load models”.', 'postmag-ai-chat' );
								}
								?>
							</p>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>

			<h2 class="title"><?php esc_html_e( 'Content', 'postmag-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Content to read', 'postmag-ai-chat' ); ?></th>
					<td>
						<?php foreach ( $types as $type ) : ?>
							<label class="pmaic-inline"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $s['post_types'], true ) ); ?>> <?php echo esc_html( $type->labels->name ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Only published, public content is used. WooCommerce and Easy Digital Downloads prices are included automatically. Any single page can be hidden from its edit screen.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pmaic-info"><?php esc_html_e( 'Extra site info', 'postmag-ai-chat' ); ?></label></th>
					<td>
						<textarea id="pmaic-info" class="large-text" rows="6" name="<?php echo esc_attr( $opt ); ?>[site_info]" placeholder="<?php esc_attr_e( "e.g. We sell WordPress themes.\nRefund policy: 14 days.\nSupport hours: Mon–Fri, 9am–5pm.", 'postmag-ai-chat' ); ?>"><?php echo esc_textarea( $s['site_info'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Facts the assistant should always know, even if they are not on a page: what you do, policies, support hours, etc.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Product catalogue', 'postmag-ai-chat' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[catalog]" value="1" <?php checked( $s['catalog'] ); ?>> <?php esc_html_e( 'Always give the assistant the full list of products with prices and links', 'postmag-ai-chat' ); ?></label>
						<?php $pmaic_count = count( array_filter( explode( "\n", PMAIC_Catalog::text() ) ) ); ?>
						<p class="description">
							<?php
							/* translators: %d: number of products. */
							echo esc_html( sprintf( __( 'Lets it answer questions like “which themes are free?” or “list all pro themes with prices”. Uses WooCommerce products and Easy Digital Downloads ticked above. Currently %d products.', 'postmag-ai-chat' ), $pmaic_count ) );
							?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Chat widget', 'postmag-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable for visitors', 'postmag-ai-chat' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the chat widget to everyone', 'postmag-ai-chat' ); ?></label>
						<p class="description"><?php esc_html_e( 'While unchecked, only logged-in administrators see the widget, so you can test it on the live site first.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pmaic-name"><?php esc_html_e( 'Assistant name', 'postmag-ai-chat' ); ?></label></th>
					<td><input type="text" id="pmaic-name" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[bot_name]" value="<?php echo esc_attr( $s['bot_name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="pmaic-welcome"><?php esc_html_e( 'Welcome message', 'postmag-ai-chat' ); ?></label></th>
					<td><textarea id="pmaic-welcome" class="large-text" rows="2" name="<?php echo esc_attr( $opt ); ?>[welcome]"><?php echo esc_textarea( $s['welcome'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="pmaic-contact"><?php esc_html_e( 'Contact page URL', 'postmag-ai-chat' ); ?></label></th>
					<td>
						<input type="url" id="pmaic-contact" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[contact_url]" value="<?php echo esc_attr( $s['contact_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/contact/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Where the assistant sends visitors when it does not know the answer.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Appearance', 'postmag-ai-chat' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Accent colour', 'postmag-ai-chat' ); ?> <input type="color" name="<?php echo esc_attr( $opt ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></label>
						<label class="pmaic-gap"><?php esc_html_e( 'Position', 'postmag-ai-chat' ); ?>
							<select name="<?php echo esc_attr( $opt ); ?>[position]">
								<option value="right" <?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'postmag-ai-chat' ); ?></option>
								<option value="left" <?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'postmag-ai-chat' ); ?></option>
							</select>
						</label>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Cost protection & privacy', 'postmag-ai-chat' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Limits', 'postmag-ai-chat' ); ?></th>
					<td>
						<p><label><?php esc_html_e( 'Max questions per visitor per hour', 'postmag-ai-chat' ); ?> <input type="number" min="1" max="500" class="small-text" name="<?php echo esc_attr( $opt ); ?>[ip_limit]" value="<?php echo esc_attr( $s['ip_limit'] ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Max questions per day (whole site)', 'postmag-ai-chat' ); ?> <input type="number" min="1" class="small-text" name="<?php echo esc_attr( $opt ); ?>[daily_limit]" value="<?php echo esc_attr( $s['daily_limit'] ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Max answer length (tokens)', 'postmag-ai-chat' ); ?> <input type="number" min="150" max="4000" class="small-text" name="<?php echo esc_attr( $opt ); ?>[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>"></label></p>
						<p class="description"><?php esc_html_e( 'Administrators are never limited. For full protection, also set a monthly spending limit in your AI provider’s dashboard.', 'postmag-ai-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Question log', 'postmag-ai-chat' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[log_chats]" value="1" <?php checked( $s['log_chats'] ); ?>> <?php esc_html_e( 'Save visitor questions and answers for 90 days', 'postmag-ai-chat' ); ?></label>
						<p class="description">
							<?php esc_html_e( 'Helps you find questions your site does not answer yet. No names or IP addresses are stored.', 'postmag-ai-chat' ); ?>
							<a href="<?php echo esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ); ?>"><?php esc_html_e( 'Suggested privacy policy text', 'postmag-ai-chat' ); ?></a>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Visitor questions tab.
	 */
	private static function log_tab() {
		global $wpdb;
		$only = isset( $_GET['missing'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table.
		if ( $only ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE found = 0 ORDER BY id DESC LIMIT 100', $wpdb->prefix . 'pmaic_log' ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', $wpdb->prefix . 'pmaic_log' ) );
		}
		// phpcs:enable
		?>
		<?php if ( ! PMAIC_Settings::get( 'log_chats' ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Question logging is turned off. Enable it under Settings → “Question log”.', 'postmag-ai-chat' ); ?></p></div>
		<?php endif; ?>
		<p>
			<a href="<?php echo esc_url( self::url( array( 'tab' => 'log' ) ) ); ?>" class="<?php echo $only ? '' : 'current'; ?>"><?php esc_html_e( 'All recent', 'postmag-ai-chat' ); ?></a> |
			<a href="<?php echo esc_url( self::url( array( 'tab' => 'log', 'missing' => 1 ) ) ); ?>" class="<?php echo $only ? 'current' : ''; ?>"><?php esc_html_e( 'No matching content found', 'postmag-ai-chat' ); ?></a>
			— <?php esc_html_e( 'questions in the second list are good ideas for new FAQ entries, docs or blog posts.', 'postmag-ai-chat' ); ?>
		</p>
		<?php if ( ! $rows ) : ?>
			<p><em><?php esc_html_e( 'No questions yet.', 'postmag-ai-chat' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped pmaic-log">
				<thead><tr><th class="pmaic-when"><?php esc_html_e( 'When', 'postmag-ai-chat' ); ?></th><th><?php esc_html_e( 'Question & answer', 'postmag-ai-chat' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td>
							<?php
							/* translators: %s: time ago. */
							echo esc_html( sprintf( __( '%s ago', 'postmag-ai-chat' ), human_time_diff( strtotime( $r->created . ' UTC' ) ) ) );
							?>
						</td>
						<td>
							<div class="pmaic-q">
								<?php echo esc_html( $r->question ); ?>
								<?php if ( ! $r->found ) : ?>
									<span class="pmaic-badge"><?php esc_html_e( 'no matching content', 'postmag-ai-chat' ); ?></span>
								<?php endif; ?>
							</div>
							<details><summary><?php esc_html_e( 'Answer', 'postmag-ai-chat' ); ?></summary><div class="pmaic-answer"><?php echo esc_html( $r->answer ); ?></div></details>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/* ---------------------------- AJAX -------------------------------- */

	/**
	 * Common AJAX checks.
	 */
	private static function ajax_guard() {
		check_ajax_referer( 'pmaic_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'postmag-ai-chat' ), 403 );
		}
	}

	/**
	 * Selected provider from the request.
	 *
	 * @return PMAIC_Provider
	 */
	private static function requested_provider() {
		$id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in ajax_guard().
		return PMAIC_AI::provider( $id ? $id : null );
	}

	/**
	 * Rebuild one batch.
	 */
	public static function ajax_reindex() {
		self::ajax_guard();
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in ajax_guard().
		wp_send_json_success( PMAIC_Indexer::reindex_batch( $offset ) );
	}

	/**
	 * Tiny test request to the selected provider.
	 */
	public static function ajax_test() {
		self::ajax_guard();
		$p = self::requested_provider();
		if ( $p->needs_key() && ! $p->api_key() ) {
			wp_send_json_error( __( 'Save an API key for this provider first.', 'postmag-ai-chat' ) );
		}
		$r = $p->ask(
			'You are a connection test.',
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with just the word OK.',
				),
			),
			50
		);
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( mb_substr( $r, 0, 200 ) );
	}

	/**
	 * Model list for the datalist.
	 */
	public static function ajax_models() {
		self::ajax_guard();
		$p = self::requested_provider();
		if ( $p->needs_key() && ! $p->api_key() ) {
			wp_send_json_error( __( 'Save an API key for this provider first.', 'postmag-ai-chat' ) );
		}
		$models = $p->list_models();
		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message() );
		}
		wp_send_json_success( array_slice( $models, 0, 300 ) );
	}

	/* -------------------- Per-post "exclude" checkbox ------------------ */

	/**
	 * Register the box on indexed post types.
	 */
	public static function meta_box() {
		foreach ( (array) PMAIC_Settings::get( 'post_types' ) as $type ) {
			add_meta_box( 'pmaic_exclude', __( 'AI Chat', 'postmag-ai-chat' ), array( __CLASS__, 'render_meta_box' ), $type, 'side', 'low' );
		}
	}

	/**
	 * Box markup.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'pmaic_exclude', 'pmaic_exclude_nonce' );
		?>
		<label><input type="checkbox" name="pmaic_exclude" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_pmaic_exclude', true ) ); ?>> <?php esc_html_e( 'Hide this from the AI chat', 'postmag-ai-chat' ); ?></label>
		<?php
	}

	/**
	 * Save the box. Runs before the indexer (priority 20) so the new value is respected.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['pmaic_exclude_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pmaic_exclude_nonce'] ) ), 'pmaic_exclude' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['pmaic_exclude'] ) ) {
			update_post_meta( $post_id, '_pmaic_exclude', 1 );
		} else {
			delete_post_meta( $post_id, '_pmaic_exclude' );
		}
	}
}

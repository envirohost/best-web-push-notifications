<?php
/**
 * Admin screens.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BWPN_PATH . 'admin/class-bwpn-admin-tables.php';

/**
 * Menu "Push Notifications": Dashboard, Send, Notifications, Subscribers,
 * Settings, Tools, Logs. Every form posts to admin-post.php with a nonce
 * and redirects back (POST/redirect/GET), so reloading never re-sends.
 */
class BWPN_Admin {

	public const SLUG = 'bwpn';

	/**
	 * Registers hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( 'BWPN_Notices', 'render' ) );
		add_filter( 'plugin_action_links_' . BWPN_BASENAME, array( $this, 'action_links' ) );

		foreach ( array( 'send', 'jobs', 'subscribers', 'export', 'import', 'migrate', 'keys', 'test' ) as $action ) {
			add_action( 'admin_post_bwpn_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Menu and assets
	 * ------------------------------------------------------------------- */

	/**
	 * Adds the menu.
	 */
	public function menu() {
		add_menu_page( __( 'Push Notifications', 'best-web-push-notifications' ), __( 'Push Notifications', 'best-web-push-notifications' ), 'bwpn_send_notifications', self::SLUG, array( $this, 'page_dashboard' ), 'dashicons-megaphone', 80 );
		$pages = array(
			array( self::SLUG, __( 'Dashboard', 'best-web-push-notifications' ), 'bwpn_send_notifications', 'page_dashboard' ),
			array( 'bwpn-send', __( 'Send', 'best-web-push-notifications' ), 'bwpn_send_notifications', 'page_send' ),
			array( 'bwpn-notifications', __( 'Notifications', 'best-web-push-notifications' ), 'bwpn_send_notifications', 'page_notifications' ),
			array( 'bwpn-subscribers', __( 'Subscribers', 'best-web-push-notifications' ), 'bwpn_manage', 'page_subscribers' ),
			array( 'bwpn-settings', __( 'Settings', 'best-web-push-notifications' ), 'bwpn_manage', 'page_settings' ),
			array( 'bwpn-tools', __( 'Tools', 'best-web-push-notifications' ), 'bwpn_manage', 'page_tools' ),
			array( 'bwpn-logs', __( 'Logs', 'best-web-push-notifications' ), 'bwpn_manage', 'page_logs' ),
		);
		foreach ( $pages as $page ) {
			add_submenu_page( self::SLUG, $page[1], $page[1], $page[2], $page[0], array( $this, $page[3] ) );
		}
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=bwpn-settings' ) ) . '">' . esc_html__( 'Settings', 'best-web-push-notifications' ) . '</a>' );
		return $links;
	}

	/**
	 * Enqueues admin assets on our pages.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'bwpn' ) ) {
			return;
		}
		wp_enqueue_style( 'bwpn-admin', BWPN_URL . 'admin/css/bwpn-admin.css', array(), BWPN_VERSION );
		if ( in_array( $page, array( 'bwpn-send', 'bwpn-settings' ), true ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'bwpn-admin', BWPN_URL . 'admin/js/bwpn-admin.js', array( 'wp-i18n' ), BWPN_VERSION, true );
		wp_set_script_translations( 'bwpn-admin', 'best-web-push-notifications', BWPN_PATH . 'languages' );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Verifies capability and nonce for a handler.
	 *
	 * @param string $action Action name.
	 * @param string $cap    Capability.
	 */
	private function verify( $action, $cap = 'bwpn_manage' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'best-web-push-notifications' ), 403 );
		}
		check_admin_referer( 'bwpn_' . $action );
	}

	/**
	 * Redirects back to a page with a message.
	 *
	 * @param string $page    Page slug.
	 * @param string $message Message.
	 * @param string $type    success|error|warning.
	 */
	private function back( $page, $message, $type = 'success' ) {
		set_transient(
			'bwpn_flash_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	/**
	 * Prints and clears the flash message.
	 */
	private function flash() {
		$key   = 'bwpn_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $flash['type'] ), esc_html( $flash['message'] ) );
	}

	/**
	 * Page header.
	 *
	 * @param string $title Title.
	 */
	private function header( $title ) {
		echo '<div class="wrap bwpn-wrap"><h1>' . esc_html( $title ) . '</h1>';
		$this->flash();
	}

	/**
	 * Selected ids from a bulk form.
	 *
	 * @return int[]
	 */
	private function posted_ids() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Bulk action from either dropdown.
	 *
	 * @return string
	 */
	private function bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller.
		foreach ( array( 'action', 'action2' ) as $field ) {
			if ( isset( $_POST[ $field ] ) && '-1' !== $_POST[ $field ] ) {
				return sanitize_key( wp_unslash( $_POST[ $field ] ) );
			}
		}
		// phpcs:enable
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------- */

	/**
	 * Dashboard page.
	 */
	public function page_dashboard() {
		$this->header( __( 'Push Notifications', 'best-web-push-notifications' ) );
		$stats = BWPN_Lib_Db::get_notifications_stats();
		$jobs  = BWPN_Lib_Db::get_jobs_stats();
		$tiles = array(
			__( 'Subscribers', 'best-web-push-notifications' ) => BWPN_Lib_Db::count_subscribers(),
			__( 'Delivered', 'best-web-push-notifications' )   => $stats['succeeded'],
			__( 'Failed', 'best-web-push-notifications' )      => $stats['failed'],
			__( 'Expired and removed', 'best-web-push-notifications' ) => $stats['expired'],
			__( 'Scheduled or sending', 'best-web-push-notifications' ) => $jobs[ BWPN_Lib_Db::STATUS_SCHEDULED ] + $jobs[ BWPN_Lib_Db::STATUS_RUNNING ],
		);
		echo '<div class="bwpn-tiles">';
		foreach ( $tiles as $label => $value ) {
			printf( '<div class="bwpn-tile"><div class="bwpn-tile-value">%s</div><div class="bwpn-tile-label">%s</div></div>', esc_html( number_format_i18n( (int) $value ) ), esc_html( $label ) );
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Health', 'best-web-push-notifications' ) . '</h2><table class="widefat striped bwpn-health"><tbody>';
		foreach ( BWPN_Lib_Utils::health_checks() as $check ) {
			printf(
				'<tr><td class="bwpn-health-icon"><span class="dashicons %s" aria-hidden="true"></span><span class="screen-reader-text">%s</span></td><th scope="row">%s</th><td>%s</td></tr>',
				esc_attr( $check['ok'] ? 'dashicons-yes-alt bwpn-ok' : 'dashicons-warning bwpn-bad' ),
				esc_html( $check['ok'] ? __( 'OK', 'best-web-push-notifications' ) : __( 'Problem', 'best-web-push-notifications' ) ),
				esc_html( $check['label'] ),
				esc_html( $check['detail'] )
			);
		}
		echo '</tbody></table>';

		$hosts = BWPN_Lib_Db::count_subscribers_by_host();
		if ( ! empty( $hosts ) ) {
			echo '<h2>' . esc_html__( 'Subscribers by push service', 'best-web-push-notifications' ) . '</h2><table class="widefat striped bwpn-narrow"><tbody>';
			foreach ( $hosts as $host => $count ) {
				printf( '<tr><td>%s</td><td class="num">%s</td></tr>', esc_html( BWPN_Subscription_Validator::service_label( 'https://' . $host . '/' ) ), esc_html( number_format_i18n( $count ) ) );
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Send
	 * ------------------------------------------------------------------- */

	/**
	 * Send page.
	 */
	public function page_send() {
		$this->header( __( 'Send a notification', 'best-web-push-notifications' ) );
		if ( ! BWPN_Settings::has_keys() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'VAPID keys are missing. Generate or import them on the Tools page first.', 'best-web-push-notifications' ) . '</p></div>';
		}
		$count = BWPN_Lib_Db::count_subscribers();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bwpn-form">
			<input type="hidden" name="action" value="bwpn_send">
			<?php wp_nonce_field( 'bwpn_send' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bwpn-title"><?php esc_html_e( 'Title', 'best-web-push-notifications' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bwpn-title" name="title" maxlength="<?php echo esc_attr( (string) BWPN_Lib_Payload::MAX_TITLE_CHARS ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="bwpn-body"><?php esc_html_e( 'Message', 'best-web-push-notifications' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="bwpn-body" name="body" required maxlength="<?php echo esc_attr( (string) BWPN_Lib_Payload::MAX_BODY_CHARS ); ?>"></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="bwpn-url"><?php esc_html_e( 'Link', 'best-web-push-notifications' ); ?></label></th>
					<td><input type="url" class="regular-text" id="bwpn-url" name="url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="bwpn-image"><?php esc_html_e( 'Image (optional)', 'best-web-push-notifications' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="bwpn-image" name="image">
						<button type="button" class="button bwpn-media" data-target="bwpn-image" data-mode="url"><?php esc_html_e( 'Choose', 'best-web-push-notifications' ); ?></button>
						<p class="description"><?php esc_html_e( 'Large images show on Chrome, Edge and Android. Safari and Firefox ignore them.', 'best-web-push-notifications' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bwpn-when"><?php esc_html_e( 'Send at', 'best-web-push-notifications' ); ?></label></th>
					<td>
						<input type="datetime-local" id="bwpn-when" name="when">
						<p class="description">
						<?php
						/* translators: %s: timezone name. */
						echo esc_html( sprintf( __( 'Leave empty to send now. Site timezone: %s.', 'best-web-push-notifications' ), wp_timezone_string() ) );
						?>
						</p>
					</td>
				</tr>
			</table>
			<?php
			/* translators: %s: number of subscribers. */
			submit_button( sprintf( _n( 'Send to %s subscriber', 'Send to %s subscribers', $count, 'best-web-push-notifications' ), number_format_i18n( $count ) ) );
			?>
		</form>
		</div>
		<?php
	}

	/**
	 * Handles the send form.
	 */
	public function handle_send() {
		$this->verify( 'send', 'bwpn_send_notifications' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$image = isset( $_POST['image'] ) ? esc_url_raw( wp_unslash( $_POST['image'] ) ) : '';
		$when  = isset( $_POST['when'] ) ? sanitize_text_field( wp_unslash( $_POST['when'] ) ) : '';
		// phpcs:enable
		if ( '' === trim( $body ) ) {
			$this->back( 'bwpn-send', __( 'The message is empty.', 'best-web-push-notifications' ), 'error' );
		}
		$timestamp = null;
		if ( '' !== $when ) {
			$date = date_create_immutable( $when, wp_timezone() );
			if ( false === $date ) {
				$this->back( 'bwpn-send', __( 'The date is not valid.', 'best-web-push-notifications' ), 'error' );
			}
			$timestamp = $date->getTimestamp();
		}
		$result = BWPN_Lib_Push_Server::schedule_broadcast( BWPN_Lib_Payload::build( $body, $title, $image, $url ), $timestamp );
		if ( is_wp_error( $result ) ) {
			$this->back( 'bwpn-send', $result->get_error_message(), 'error' );
		}
		$this->back(
			'bwpn-notifications',
			null === $timestamp || $timestamp <= time()
				? __( 'The notification is being sent.', 'best-web-push-notifications' )
				: __( 'The notification is scheduled.', 'best-web-push-notifications' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Notifications and subscribers
	 * ------------------------------------------------------------------- */

	/**
	 * Notifications page.
	 */
	public function page_notifications() {
		$this->header( __( 'Notifications', 'best-web-push-notifications' ) );
		$table = new BWPN_Admin_Notifications_Table();
		$table->prepare_items();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bwpn_jobs">';
		wp_nonce_field( 'bwpn_jobs' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Bulk actions on jobs.
	 */
	public function handle_jobs() {
		$this->verify( 'jobs', 'bwpn_send_notifications' );
		$ids   = $this->posted_ids();
		$count = 0;
		switch ( $this->bulk_action() ) {
			case 'bwpn_cancel':
				foreach ( $ids as $id ) {
					$count += BWPN_Lib_Push_Server::cancel_job( $id ) ? 1 : 0;
				}
				break;
			case 'bwpn_resume':
				foreach ( $ids as $id ) {
					$count += BWPN_Lib_Push_Server::resume_job( $id ) ? 1 : 0;
				}
				break;
			case 'bwpn_delete':
				$count = BWPN_Lib_Db::delete_notifications( $ids );
				break;
		}
		/* translators: %d: number of items. */
		$this->back( 'bwpn-notifications', sprintf( _n( '%d notification updated.', '%d notifications updated.', $count, 'best-web-push-notifications' ), $count ) );
	}

	/**
	 * Subscribers page.
	 */
	public function page_subscribers() {
		$this->header( __( 'Subscribers', 'best-web-push-notifications' ) );
		$table = new BWPN_Admin_Subscribers_Table();
		$table->prepare_items();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bwpn_subscribers">';
		wp_nonce_field( 'bwpn_subscribers' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Bulk actions on subscribers.
	 */
	public function handle_subscribers() {
		$this->verify( 'subscribers' );
		$count = 'bwpn_delete' === $this->bulk_action() ? BWPN_Lib_Db::delete_subscribers( $this->posted_ids() ) : 0;
		/* translators: %d: number of subscribers. */
		$this->back( 'bwpn-subscribers', sprintf( _n( '%d subscriber deleted.', '%d subscribers deleted.', $count, 'best-web-push-notifications' ), $count ) );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	/**
	 * Settings schema: section => [ key => [ type, label, description ] ].
	 *
	 * @return array<string, array{title: string, fields: array<string, array<string, mixed>>}>
	 */
	private function schema() {
		return array(
			'prompt'   => array(
				'title'  => __( 'Opt-in prompt', 'best-web-push-notifications' ),
				'fields' => array(
					'widget_enabled'            => array( 'checkbox', __( 'Show the subscription prompt and bell', 'best-web-push-notifications' ) ),
					'prompt_visits'             => array( 'number', __( 'Show the prompt from this visit on', 'best-web-push-notifications' ), '', 1, 100 ),
					'prompt_delay'              => array( 'number', __( 'Delay before the prompt (seconds)', 'best-web-push-notifications' ), '', 0, 600 ),
					'prompt_scroll'             => array( 'number', __( 'Show after scrolling (% of page, 0 = off)', 'best-web-push-notifications' ), '', 0, 100 ),
					'hide_bell_after_subscribe' => array( 'checkbox', __( 'Hide the bell after subscribing', 'best-web-push-notifications' ) ),
					'dialog_title'              => array( 'text', __( 'Prompt text', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'dialog_title' ) ),
					'dialog_submit'             => array( 'text', __( 'Accept button', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'dialog_submit' ) ),
					'dialog_cancel'             => array( 'text', __( 'Dismiss button', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'dialog_cancel' ) ),
					'settings_title'            => array( 'text', __( 'Bell panel title', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'settings_title' ) ),
					'settings_opt_in'           => array( 'text', __( 'Bell panel opt-in label', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'settings_opt_in' ) ),
					'settings_update_error'     => array( 'text', __( 'Bell panel error', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'settings_update_error' ) ),
					'send_welcome_message'      => array( 'checkbox', __( 'Send a welcome notification', 'best-web-push-notifications' ) ),
					'welcome_message'           => array( 'text', __( 'Welcome notification', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'welcome_message' ) ),
					'ios_hint_enabled'          => array( 'checkbox', __( 'Show the "Add to Home Screen" hint on iPhone and iPad', 'best-web-push-notifications' ) ),
					'ios_hint_text'             => array( 'text', __( 'iPhone/iPad hint', 'best-web-push-notifications' ), BWPN_Settings::default_text( 'ios_hint_text' ) ),
				),
			),
			'content'  => array(
				'title'  => __( 'Notifications', 'best-web-push-notifications' ),
				'fields' => array(
					'default_icon'            => array( 'media', __( 'Icon', 'best-web-push-notifications' ), __( 'Square, at least 192x192. Defaults to the site icon.', 'best-web-push-notifications' ) ),
					'require_interaction'     => array( 'checkbox', __( 'Keep notifications on screen until clicked (desktop)', 'best-web-push-notifications' ) ),
					'send_on_publish_default' => array( 'checkbox', __( 'Tick "Send a notification when published" by default', 'best-web-push-notifications' ) ),
					'utm'                     => array( 'text', __( 'Tracking parameters added to links', 'best-web-push-notifications' ), 'utm_source=push&utm_medium=web-push' ),
					'ttl'                     => array( 'number', __( 'Keep undelivered notifications for (seconds)', 'best-web-push-notifications' ), '', 0, 2419200 ),
				),
			),
			'delivery' => array(
				'title'  => __( 'Delivery', 'best-web-push-notifications' ),
				'fields' => array(
					'vapid_subject'        => array( 'text', __( 'VAPID contact', 'best-web-push-notifications' ), 'mailto:you@example.com', __( 'mailto: address or https: URL push services can contact. Defaults to the site URL or the admin email.', 'best-web-push-notifications' ) ),
					'batch_size'           => array( 'number', __( 'Subscribers per batch', 'best-web-push-notifications' ), '', 10, 10000 ),
					'parallel_requests'    => array( 'number', __( 'Parallel requests', 'best-web-push-notifications' ), '', 1, 200 ),
					'service_worker_scope' => array( 'text', __( 'Service worker scope', 'best-web-push-notifications' ), BWPN_Settings::DEFAULT_SCOPE, __( 'Changing this re-registers the worker; existing subscriptions stay with the old scope until visitors return.', 'best-web-push-notifications' ) ),
					'manifest_enabled'     => array( 'checkbox', __( 'Add a web app manifest (needed for iPhone and iPad)', 'best-web-push-notifications' ) ),
					'server_url'           => array( 'text', __( 'Custom REST URL', 'best-web-push-notifications' ), '', __( 'Only if the REST API is served from another URL. Leave empty.', 'best-web-push-notifications' ) ),
				),
			),
			'security' => array(
				'title'  => __( 'Security and housekeeping', 'best-web-push-notifications' ),
				'fields' => array(
					'rate_limit_per_ip'        => array( 'number', __( 'Subscribe requests per IP per minute (0 = off)', 'best-web-push-notifications' ), '', 0, 1000 ),
					'trusted_proxy'            => array(
						'select',
						__( 'Client IP source', 'best-web-push-notifications' ),
						array(
							'none'       => __( 'Direct connection (REMOTE_ADDR)', 'best-web-push-notifications' ),
							'cloudflare' => __( 'Cloudflare (CF-Connecting-IP from Cloudflare IPs only)', 'best-web-push-notifications' ),
							'x-forwarded-for' => __( 'X-Forwarded-For (only behind your own reverse proxy; spoofable otherwise)', 'best-web-push-notifications' ),
						),
					),
					'max_failures'             => array( 'number', __( 'Remove a subscriber after this many failed sends in a row', 'best-web-push-notifications' ), '', 1, 100 ),
					'log_driver'               => array(
						'select',
						__( 'Log to', 'best-web-push-notifications' ),
						array(
							'errorlog' => __( 'PHP error log', 'best-web-push-notifications' ),
							'db'       => __( 'Database (Logs page)', 'best-web-push-notifications' ),
						),
					),
					'log_level'                => array(
						'select',
						__( 'Log level', 'best-web-push-notifications' ),
						array(
							'error'   => 'error',
							'warning' => 'warning',
							'info'    => 'info',
							'debug'   => 'debug',
						),
					),
					'client_debug'             => array( 'checkbox', __( 'Browser console debugging', 'best-web-push-notifications' ) ),
					'delete_data_on_uninstall' => array( 'checkbox', __( 'Delete all data, keys and subscribers when the plugin is deleted', 'best-web-push-notifications' ) ),
				),
			),
		);
	}

	/**
	 * Registers the settings with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'bwpn_settings_group',
			BWPN_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitizes the settings form.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		if ( empty( $input['_bwpn_form'] ) ) {
			// Internal update (migrator, import): values were already validated.
			return array_intersect_key( $input, BWPN_Settings::defaults() );
		}
		$current = BWPN_Settings::all();
		$clean   = $current;
		foreach ( $this->schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$type = $field[0];
				$raw  = $input[ $key ] ?? null;
				switch ( $type ) {
					case 'checkbox':
						$clean[ $key ] = empty( $raw ) ? 0 : 1;
						break;
					case 'number':
					case 'media':
						$value = absint( $raw );
						if ( 'number' === $type ) {
							$value = max( (int) ( $field[3] ?? 0 ), min( (int) ( $field[4] ?? PHP_INT_MAX ), $value ) );
						}
						$clean[ $key ] = $value;
						break;
					case 'select':
						$clean[ $key ] = array_key_exists( (string) $raw, $field[2] ) ? (string) $raw : $current[ $key ];
						break;
					default:
						$clean[ $key ] = sanitize_text_field( (string) $raw );
				}
			}
		}
		if ( '' !== $clean['vapid_subject'] && ! preg_match( '#^(mailto:.+@.+|https://.+)$#', $clean['vapid_subject'] ) ) {
			add_settings_error( BWPN_Settings::OPTION, 'bwpn_subject', __( 'The VAPID contact must start with mailto: or https://. It was cleared.', 'best-web-push-notifications' ) );
			$clean['vapid_subject'] = '';
		}
		if ( '' !== $clean['server_url'] ) {
			$clean['server_url'] = esc_url_raw( $clean['server_url'], array( 'https', 'http' ) );
		}
		return $clean;
	}

	/**
	 * Settings page.
	 */
	public function page_settings() {
		$this->header( __( 'Settings', 'best-web-push-notifications' ) );
		settings_errors( BWPN_Settings::OPTION );
		$values = BWPN_Settings::all();
		echo '<form method="post" action="options.php" class="bwpn-form">';
		settings_fields( 'bwpn_settings_group' );
		echo '<input type="hidden" name="' . esc_attr( BWPN_Settings::OPTION ) . '[_bwpn_form]" value="1">';
		foreach ( $this->schema() as $section ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2><table class="form-table" role="presentation">';
			foreach ( $section['fields'] as $key => $field ) {
				$this->render_field( $key, $field, $values[ $key ] ?? '' );
			}
			echo '</table>';
		}
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Renders one field row.
	 *
	 * @param string              $key   Setting key.
	 * @param array<int, mixed>   $field Field definition.
	 * @param mixed               $value Current value.
	 */
	private function render_field( $key, array $field, $value ) {
		$id   = 'bwpn-' . str_replace( '_', '-', $key );
		$name = BWPN_Settings::OPTION . '[' . $key . ']';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field[1] ) . '</label></th><td>';
		switch ( $field[0] ) {
			case 'checkbox':
				printf( '<input type="checkbox" id="%s" name="%s" value="1" %s>', esc_attr( $id ), esc_attr( $name ), checked( (int) $value, 1, false ) );
				break;
			case 'number':
				printf( '<input type="number" class="small-text" id="%s" name="%s" value="%d" min="%d" max="%d">', esc_attr( $id ), esc_attr( $name ), (int) $value, (int) ( $field[3] ?? 0 ), (int) ( $field[4] ?? 0 ) );
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field[2] as $option => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'media':
				$url = $value ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
				printf( '<input type="hidden" id="%s" name="%s" value="%d">', esc_attr( $id ), esc_attr( $name ), (int) $value );
				printf( '<img class="bwpn-media-preview" id="%s-preview" src="%s" alt="" %s> ', esc_attr( $id ), esc_url( (string) $url ), $url ? '' : 'hidden' );
				printf( '<button type="button" class="button bwpn-media" data-target="%s" data-mode="id">%s</button> ', esc_attr( $id ), esc_html__( 'Choose', 'best-web-push-notifications' ) );
				printf( '<button type="button" class="button-link bwpn-media-clear" data-target="%s">%s</button>', esc_attr( $id ), esc_html__( 'Remove', 'best-web-push-notifications' ) );
				break;
			default:
				printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s" placeholder="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( (string) ( $field[2] ?? '' ) ) );
		}
		$description = 'media' === $field[0] ? ( $field[2] ?? '' ) : ( $field[3] ?? '' );
		if ( is_string( $description ) && '' !== $description && in_array( $field[0], array( 'text', 'media' ), true ) ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/* ---------------------------------------------------------------------
	 * Tools
	 * ------------------------------------------------------------------- */

	/**
	 * Tools page: backup, import, Perfecty migration, keys, test send.
	 */
	public function page_tools() {
		$this->header( __( 'Tools', 'best-web-push-notifications' ) );
		$post_url = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="bwpn-card">
			<h2><?php esc_html_e( 'Backup', 'best-web-push-notifications' ); ?></h2>
			<p><?php esc_html_e( 'Download the subscribers, settings and VAPID keys. Restoring the keys is what keeps existing subscribers working after moving or reinstalling the site.', 'best-web-push-notifications' ); ?></p>
			<p class="bwpn-warning"><?php esc_html_e( 'The JSON backup contains your private key. Store it like a password.', 'best-web-push-notifications' ); ?></p>
			<form method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
				<input type="hidden" name="action" value="bwpn_export">
				<?php wp_nonce_field( 'bwpn_export' ); ?>
				<button class="button button-primary" name="format" value="json"><?php esc_html_e( 'Download JSON backup', 'best-web-push-notifications' ); ?></button>
				<button class="button" name="format" value="csv"><?php esc_html_e( 'Download subscribers (CSV)', 'best-web-push-notifications' ); ?></button>
			</form>
		</div>

		<div class="bwpn-card">
			<h2><?php esc_html_e( 'Restore or import', 'best-web-push-notifications' ); ?></h2>
			<form method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="bwpn_import">
				<?php wp_nonce_field( 'bwpn_import' ); ?>
				<p><input type="file" name="file" accept=".json,.csv,application/json,text/csv" required></p>
				<p><label><input type="checkbox" name="settings" value="1"> <?php esc_html_e( 'Also restore settings (JSON only)', 'best-web-push-notifications' ); ?></label></p>
				<p><label><input type="checkbox" name="overwrite_keys" value="1"> <?php esc_html_e( 'Replace the current VAPID keys with the ones in the backup', 'best-web-push-notifications' ); ?></label></p>
				<?php submit_button( __( 'Import', 'best-web-push-notifications' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<div class="bwpn-card">
			<h2><?php esc_html_e( 'Import from Perfecty Push', 'best-web-push-notifications' ); ?></h2>
			<?php
			$state = BWPN_Migrator::state();
			if ( ! BWPN_Migrator::perfecty_present() ) {
				echo '<p>' . esc_html__( 'No Perfecty Push data was found on this site.', 'best-web-push-notifications' ) . '</p>';
			} else {
				if ( 'none' !== $state['status'] ) {
					/* translators: 1: status, 2: imported count, 3: skipped count. */
					echo '<p>' . esc_html( sprintf( __( 'Last import: %1$s - %2$d imported, %3$d skipped.', 'best-web-push-notifications' ), $state['status'], $state['imported'], $state['skipped'] ) ) . '</p>';
				}
				?>
				<p><?php esc_html_e( 'Copies the VAPID keys, settings, service worker scope and subscribers. Perfecty Push data is not changed. Deactivate Perfecty Push afterwards so subscribers do not get notifications twice.', 'best-web-push-notifications' ); ?></p>
				<form method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
					<input type="hidden" name="action" value="bwpn_migrate">
					<?php wp_nonce_field( 'bwpn_migrate' ); ?>
					<p><label><input type="checkbox" name="overwrite_keys" value="1"> <?php esc_html_e( 'Replace the current VAPID keys with Perfecty\'s', 'best-web-push-notifications' ); ?></label></p>
					<?php submit_button( __( 'Import from Perfecty Push', 'best-web-push-notifications' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php
			}
			?>
		</div>

		<div class="bwpn-card">
			<h2><?php esc_html_e( 'VAPID keys', 'best-web-push-notifications' ); ?></h2>
			<p><?php esc_html_e( 'Public key:', 'best-web-push-notifications' ); ?> <code class="bwpn-key"><?php echo esc_html( BWPN_Settings::public_key() ? BWPN_Settings::public_key() : '-' ); ?></code></p>
			<?php if ( BWPN_Settings::keys_from_constants() ) : ?>
				<p><?php esc_html_e( 'The keys are defined in wp-config.php.', 'best-web-push-notifications' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" class="bwpn-confirm" data-confirm="<?php esc_attr_e( 'New keys make every existing subscription stop working. Continue?', 'best-web-push-notifications' ); ?>">
					<input type="hidden" name="action" value="bwpn_keys">
					<?php wp_nonce_field( 'bwpn_keys' ); ?>
					<?php submit_button( BWPN_Settings::has_keys() ? __( 'Generate new keys', 'best-web-push-notifications' ) : __( 'Generate keys', 'best-web-push-notifications' ), BWPN_Settings::has_keys() ? 'delete' : 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<?php if ( is_user_logged_in() ) : ?>
		<div class="bwpn-card">
			<h2><?php esc_html_e( 'Test', 'best-web-push-notifications' ); ?></h2>
			<p><?php esc_html_e( 'Sends a test notification to the browsers where you subscribed while logged in.', 'best-web-push-notifications' ); ?></p>
			<form method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
				<input type="hidden" name="action" value="bwpn_test">
				<?php wp_nonce_field( 'bwpn_test' ); ?>
				<?php submit_button( __( 'Send me a test', 'best-web-push-notifications' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Streams an export.
	 */
	public function handle_export() {
		$this->verify( 'export' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$format = isset( $_POST['format'] ) && 'csv' === $_POST['format'] ? 'csv' : 'json';
		$host   = sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$file   = 'bwpn-' . $host . '-' . gmdate( 'Ymd-His' ) . '.' . $format;
		nocache_headers();
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- download stream.
			BWPN_Transfer::export_csv( $out );
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		} else {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo BWPN_Transfer::export_json( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		}
		BWPN_Lib_Log::info( 'Export downloaded by user ' . get_current_user_id() . " ($format)" );
		exit;
	}

	/**
	 * Handles an uploaded backup or CSV.
	 */
	public function handle_import() {
		$this->verify( 'import' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path checked by is_uploaded_file.
			$this->back( 'bwpn-tools', __( 'No file was uploaded.', 'best-web-push-notifications' ), 'error' );
		}
		$tmp  = $_FILES['file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
		$args = array(
			'keys'           => true,
			'overwrite_keys' => ! empty( $_POST['overwrite_keys'] ),
			'settings'       => ! empty( $_POST['settings'] ),
		);
		// phpcs:enable
		if ( '.csv' === strtolower( substr( $name, -4 ) ) ) {
			$result = BWPN_Transfer::import_csv( $tmp );
		} else {
			$result = BWPN_Transfer::import_json( (string) file_get_contents( $tmp ), $args ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
		}
		if ( is_wp_error( $result ) ) {
			$this->back( 'bwpn-tools', $result->get_error_message(), 'error' );
		}
		$message = sprintf(
			/* translators: 1: imported, 2: updated, 3: skipped. */
			__( 'Import finished: %1$d new, %2$d updated, %3$d skipped.', 'best-web-push-notifications' ),
			$result['imported'],
			$result['updated'],
			$result['skipped']
		);
		if ( isset( $result['keys'] ) && 'restored' === $result['keys'] ) {
			$message .= ' ' . __( 'VAPID keys restored.', 'best-web-push-notifications' );
		}
		$this->back( 'bwpn-tools', $message );
	}

	/**
	 * Runs the Perfecty import.
	 */
	public function handle_migrate() {
		$this->verify( 'migrate' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$overwrite = ! empty( $_POST['overwrite_keys'] );
		$result    = BWPN_Migrator::run( $overwrite );
		if ( is_wp_error( $result ) ) {
			$this->back( 'bwpn-tools', $result->get_error_message(), 'error' );
		}
		$message = 'done' === $result['status']
			/* translators: 1: imported, 2: skipped. */
			? sprintf( __( 'Perfecty Push import finished: %1$d imported, %2$d skipped. Deactivate Perfecty Push now.', 'best-web-push-notifications' ), $result['imported'], $result['skipped'] )
			/* translators: %d: imported so far. */
			: sprintf( __( 'Import in progress (%d so far); it continues in the background.', 'best-web-push-notifications' ), $result['imported'] );
		$this->back( 'bwpn-tools', $message );
	}

	/**
	 * Generates a new key pair.
	 */
	public function handle_keys() {
		$this->verify( 'keys' );
		try {
			$keys = BWPN_External_Webpush::create_vapid_keys();
		} catch ( Throwable $e ) {
			$this->back( 'bwpn-tools', $e->getMessage(), 'error' );
		}
		BWPN_Settings::set_keys( $keys['publicKey'], $keys['privateKey'] );
		BWPN_Lib_Log::warning( 'VAPID keys were regenerated by user ' . get_current_user_id() );
		$this->back( 'bwpn-tools', __( 'New VAPID keys were generated. Existing subscribers need to subscribe again.', 'best-web-push-notifications' ), 'warning' );
	}

	/**
	 * Sends a test to the current user's browsers.
	 */
	public function handle_test() {
		$this->verify( 'test', 'bwpn_send_notifications' );
		$result = BWPN_Lib_Push_Server::notify_user( get_current_user_id(), BWPN_Lib_Payload::build( __( 'Test notification from Best Web Push Notifications.', 'best-web-push-notifications' ) ) );
		if ( is_wp_error( $result ) ) {
			$this->back( 'bwpn-tools', $result->get_error_message(), 'error' );
		}
		if ( 0 === $result['ok'] + $result['failed'] + $result['expired'] ) {
			$this->back( 'bwpn-tools', __( 'You have no subscribed browsers. Subscribe on the site while logged in, then try again.', 'best-web-push-notifications' ), 'warning' );
		}
		/* translators: 1: delivered, 2: failed. */
		$this->back( 'bwpn-tools', sprintf( __( 'Test sent: %1$d delivered, %2$d failed.', 'best-web-push-notifications' ), $result['ok'], $result['failed'] + $result['expired'] ), $result['ok'] > 0 ? 'success' : 'error' );
	}

	/* ---------------------------------------------------------------------
	 * Logs
	 * ------------------------------------------------------------------- */

	/**
	 * Logs page.
	 */
	public function page_logs() {
		$this->header( __( 'Logs', 'best-web-push-notifications' ) );
		if ( 'db' !== BWPN_Settings::str( 'log_driver' ) ) {
			echo '<p>' . esc_html__( 'Logs are written to the PHP error log. Choose "Database" under Settings > Log to, to see them here.', 'best-web-push-notifications' ) . '</p></div>';
			return;
		}
		$table = new BWPN_Admin_Logs_Table();
		$table->prepare_items();
		$table->display();
		echo '</div>';
	}
}

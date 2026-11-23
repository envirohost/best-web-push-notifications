<?php
/**
 * Sends a notification when a post is published.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post publishing integration for the block editor and the classic editor.
 *
 * Upstream hooked transition_post_status and read a checkbox that Gutenberg
 * keeps ticked, so every later "Update" sent the post again (#101, #167).
 * Here the send runs on wp_after_insert_post (after meta is saved, for both
 * editors), only on the first transition into "publish", only once per post,
 * and the flag is cleared after it is used.
 */
class BWPN_Post_Publisher {

	public const META_SEND  = '_bwpn_send_on_publish';
	public const META_TITLE = '_bwpn_custom_title';
	public const NONCE      = 'bwpn_post_metabox';

	/**
	 * Registers hooks.
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'after_insert_post' ), 10, 4 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_panel' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Post types that can send notifications.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_post_types(
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		unset( $types['attachment'] );
		/**
		 * Filters the post types that show the "send notification" option.
		 *
		 * @param string[] $types Post type names.
		 */
		return array_values( (array) apply_filters( 'bwpn_post_types', array_values( $types ) ) );
	}

	/**
	 * Registers the post meta used by the editor panel.
	 */
	public static function register_meta() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', (int) $post_id ) && current_user_can( 'bwpn_send_notifications' );
		};
		foreach ( self::post_types() as $type ) {
			register_post_meta(
				$type,
				self::META_SEND,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'default'       => BWPN_Settings::bool( 'send_on_publish_default' ),
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
			register_post_meta(
				$type,
				self::META_TITLE,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}
	}

	/**
	 * Whether the "send" flag is set for a post. Uses the stored value, or
	 * the site default when the post never saved one.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function wants_send( $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, self::META_SEND ) ) {
			return BWPN_Settings::bool( 'send_on_publish_default' );
		}
		return (bool) get_post_meta( $post_id, self::META_SEND, true );
	}

	/**
	 * Sends on the first publish.
	 *
	 * @param int          $post_id     Post id.
	 * @param WP_Post      $post        Post.
	 * @param bool         $update      Whether this is an update.
	 * @param WP_Post|null $post_before Post before the update.
	 */
	public static function after_insert_post( $post_id, $post, $update, $post_before ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}
		if ( $post_before instanceof WP_Post && 'publish' === $post_before->post_status ) {
			return; // An update of a published post.
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::post_types(), true ) || '' !== (string) $post->post_password ) {
			return;
		}
		if ( ! self::wants_send( $post_id ) || BWPN_Lib_Db::post_has_notification( $post_id ) ) {
			return;
		}

		// Clear the flag first so a retry of this request cannot send twice.
		update_post_meta( $post_id, self::META_SEND, false );

		$result = self::schedule_for_post( $post );
		if ( is_wp_error( $result ) ) {
			BWPN_Lib_Log::error( "Post $post_id: " . $result->get_error_message() );
		}
	}

	/**
	 * Creates the job for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return int|WP_Error
	 */
	public static function schedule_for_post( WP_Post $post ) {
		$title = trim( (string) get_post_meta( $post->ID, self::META_TITLE, true ) );
		$body  = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$image = (string) get_the_post_thumbnail_url( $post, 'large' );

		$payload = BWPN_Lib_Payload::build( $body, $title, $image, (string) get_permalink( $post ) );

		/**
		 * Filters the payload sent when a post is published.
		 *
		 * @param array<string, mixed> $payload Payload.
		 * @param WP_Post              $post    Post.
		 */
		$payload = (array) apply_filters( 'bwpn_post_payload', $payload, $post );

		return BWPN_Lib_Push_Server::schedule_broadcast(
			$payload,
			null,
			array(
				'source'  => 'post',
				'post_id' => $post->ID,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Block editor
	 * ------------------------------------------------------------------- */

	/**
	 * Enqueues the document settings panel.
	 */
	public static function enqueue_editor_panel() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) || ! current_user_can( 'bwpn_send_notifications' ) ) {
			return;
		}
		wp_enqueue_script(
			'bwpn-editor',
			BWPN_URL . 'admin/js/bwpn-editor.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			BWPN_VERSION,
			true
		);
		wp_set_script_translations( 'bwpn-editor', 'best-web-push-notifications', BWPN_PATH . 'languages' );
		wp_add_inline_script(
			'bwpn-editor',
			'window.BWPNEditor = ' . wp_json_encode(
				array(
					'metaSend'  => self::META_SEND,
					'metaTitle' => self::META_TITLE,
					'hasKeys'   => BWPN_Settings::has_keys(),
				)
			) . ';',
			'before'
		);
	}

	/* ---------------------------------------------------------------------
	 * Classic editor
	 * ------------------------------------------------------------------- */

	/**
	 * Adds the meta box for screens that do not use the block editor.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_meta_box( $post_type ) {
		if ( ! in_array( $post_type, self::post_types(), true ) || ! current_user_can( 'bwpn_send_notifications' ) ) {
			return;
		}
		add_meta_box(
			'bwpn-post-metabox',
			__( 'Push notification', 'best-web-push-notifications' ),
			array( __CLASS__, 'render_meta_box' ),
			$post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true ) // Hidden in the block editor, which uses the panel.
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$sent = BWPN_Lib_Db::post_has_notification( $post->ID );
		if ( $sent ) {
			echo '<p>' . esc_html__( 'A notification was already sent for this post.', 'best-web-push-notifications' ) . '</p>';
			return;
		}
		$checked = 'publish' !== $post->post_status && self::wants_send( $post->ID );
		?>
		<p>
			<label>
				<input type="checkbox" name="bwpn_send_on_publish" value="1" <?php checked( $checked ); ?> <?php disabled( 'publish' === $post->post_status ); ?>>
				<?php esc_html_e( 'Send a notification when published', 'best-web-push-notifications' ); ?>
			</label>
		</p>
		<p>
			<label for="bwpn_custom_title"><?php esc_html_e( 'Title (optional)', 'best-web-push-notifications' ); ?></label>
			<input type="text" class="widefat" id="bwpn_custom_title" name="bwpn_custom_title" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, self::META_TITLE, true ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</p>
		<?php
		if ( 'publish' === $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Already published. Use Push Notifications > Send to notify about it.', 'best-web-push-notifications' ) . '</p>';
		}
	}

	/**
	 * Saves the meta box (classic editor). Runs before wp_after_insert_post.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'bwpn_send_notifications' ) ) {
			return;
		}
		if ( 'publish' === $post->post_status && BWPN_Lib_Db::post_has_notification( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_SEND, ! empty( $_POST['bwpn_send_on_publish'] ) );
		$title = isset( $_POST['bwpn_custom_title'] ) ? sanitize_text_field( wp_unslash( $_POST['bwpn_custom_title'] ) ) : '';
		update_post_meta( $post_id, self::META_TITLE, $title );
	}
}

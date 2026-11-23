<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/rwngallego
 * @since      1.0.0
 *
 * @package    BWPN
 * @subpackage BWPN/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    BWPN
 * @subpackage BWPN/admin
 * @author     Rowinson Gallego <rwn.gallego@gmail.com>
 */
class BWPN_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in BWPN_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The BWPN_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bwpn-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'jquery.timepicker', plugin_dir_url( __FILE__ ) . 'css/jquery.timepicker.min.css', array(), '1.3.5', 'screen' );
		wp_enqueue_style( 'jquery-ui', plugin_dir_url( __FILE__ ) . 'css/jquery-ui/themes/smoothness/jquery-ui.min.css', array(), '1.12.1', 'screen' );
	}


	/**
	 * Print the WordPress directory plugin links
	 *
	 * @param array $actions Array with links.
	 *
	 * @return array
	 * @since    1.3.2
	 */
	public function plugin_directory_links( $actions ) {
		$links   = array(
			'<a href="' . admin_url( 'admin.php?page=bwpn-options' ) . '">' . esc_html__( 'Settings', 'best-web-push-notifications' ) . '</a>',
			'<a href="https://docs.perfecty.org/" target="_blank">' . esc_html__( 'Documentation', 'best-web-push-notifications' ) . '</a>',
		);
		$actions = array_merge( $actions, $links );
		return $actions;
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @param string $hook_suffix The page this is being called
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in BWPN_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The BWPN_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		if ( $hook_suffix === 'toplevel_page_bwpn' ) {
			// only load it in the Dashboard page
			// ChartJs has known conflict issues: https://github.com/chartjs/Chart.js/issues/3168.
			wp_enqueue_script( 'chartjs', plugin_dir_url( __FILE__ ) . 'js/chart.bundle.min.js', array( 'jquery' ), '2.9.4', false );
		}
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bwpn-admin.js', array( 'jquery', 'wp-i18n' ), $this->version, false );
		wp_enqueue_script( 'jquery-timepicker', plugin_dir_url( __FILE__ ) . 'js/jquery.timepicker.min.js', array( 'jquery' ), '1.3.5', false );
		wp_enqueue_script( 'html5-fallback', plugin_dir_url( __FILE__ ) . 'js/html5-fallback.js', array( 'jquery-ui-datepicker', 'jquery-timepicker' ), $this->version, false );
	}

	 /**
	  * Register the plugin page in the admin menu
	  *
	  * @since 1.0.0
	  */
	public function register_admin_menu() {
		add_menu_page(
			'Best Web Push',
			'Best Web Push',
			'manage_options',
			'bwpn',
			array( $this, 'print_dashboard_page' ),
			'dashicons-smiley'
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Dashboard', 'best-web-push-notifications' ),
			esc_html__( 'Dashboard', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn',
			array( $this, 'print_dashboard_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Send notification', 'best-web-push-notifications' ),
			esc_html__( 'Send notification', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn-send-notification',
			array( $this, 'print_send_notification_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Notification jobs', 'best-web-push-notifications' ),
			esc_html__( 'Notification jobs', 'best-web-push-notifications' ),
			'manage_options',
			'best-web-push-notifications',
			array( $this, 'print_notifications_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Users', 'best-web-push-notifications' ),
			esc_html__( 'Users', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn-users',
			array( $this, 'print_users_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Settings', 'best-web-push-notifications' ),
			esc_html__( 'Settings', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn-options',
			array( $this, 'print_options_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'Logs', 'best-web-push-notifications' ),
			esc_html__( 'Logs', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn-logs',
			array( $this, 'print_logs_page' )
		);

		add_submenu_page(
			'bwpn',
			esc_html__( 'About', 'best-web-push-notifications' ),
			esc_html__( 'About', 'best-web-push-notifications' ),
			'manage_options',
			'bwpn-about',
			array( $this, 'print_about_page' )
		);
	}

	/**
	 * Register the plugin options
	 *
	 * @since 1.0.0
	 */
	public function register_options() {
		register_setting(
			'bwpn_settings_group',
			'bwpn_settings',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'bwpn_widget_settings',
			esc_html__( 'Public widget', 'best-web-push-notifications' ),
			array( $this, 'print_dialog_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'widget_enabled',
			esc_html__( 'Enabled', 'best-web-push-notifications' ),
			array( $this, 'print_widget_enabled' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'visits_to_display_prompt',
			esc_html__( 'Display after this number of visits', 'best-web-push-notifications' ),
			array( $this, 'print_visits_to_display_prompt' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'widget_ask_permissions_directly',
			esc_html__( 'Do not use widgets (ask permissions directly)', 'best-web-push-notifications' ),
			array( $this, 'print_widget_ask_permissions_directly' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'widget_hide_bell_after_subscribe',
			esc_html__( 'Hide bell after subscribing', 'best-web-push-notifications' ),
			array( $this, 'print_widget_hide_bell_after_subscribe' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'dialog_title',
			esc_html__( 'Subscribe text', 'best-web-push-notifications' ),
			array( $this, 'print_dialog_title' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'dialog_submit',
			esc_html__( 'Continue text', 'best-web-push-notifications' ),
			array( $this, 'print_dialog_submit' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'dialog_cancel',
			esc_html__( 'Cancel text', 'best-web-push-notifications' ),
			array( $this, 'print_dialog_cancel' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'settings_title',
			esc_html__( 'Bell title', 'best-web-push-notifications' ),
			array( $this, 'print_settings_title' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'settings_subscribed',
			esc_html__( 'Opt-in text', 'best-web-push-notifications' ),
			array( $this, 'print_settings_opt_in' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'settings_update_error',
			esc_html__( 'Message on update error', 'best-web-push-notifications' ),
			array( $this, 'print_settings_update_error' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'settings_send_welcome_message',
			esc_html__( 'Send a confirmation notification after subscribe', 'best-web-push-notifications' ),
			array( $this, 'print_settings_send_welcome_message' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_field(
			'settings_welcome_message',
			esc_html__( 'Welcome notification text', 'best-web-push-notifications' ),
			array( $this, 'print_settings_welcome_message' ),
			'bwpn-options',
			'bwpn_widget_settings'
		);

		add_settings_section(
			'bwpn_javascript_sdk_settings',
			esc_html__( 'Javascript SDK', 'best-web-push-notifications' ),
			array( $this, 'print_javascript_sdk_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'service_worker_scope',
			esc_html__( 'Service Worker Scope', 'best-web-push-notifications' ),
			array( $this, 'print_service_worker_scope' ),
			'bwpn-options',
			'bwpn_javascript_sdk_settings'
		);

		add_settings_field(
			'unregister_conflicts',
			esc_html__( 'Remove conflicting workers (Push Services only)', 'best-web-push-notifications' ),
			array( $this, 'print_unregister_conflicts' ),
			'bwpn-options',
			'bwpn_javascript_sdk_settings'
		);

		add_settings_field(
			'unregister_conflicts_expression',
			esc_html__( 'Custom conflict detection', 'best-web-push-notifications' ),
			array( $this, 'print_unregister_conflicts_expression' ),
			'bwpn-options',
			'bwpn_javascript_sdk_settings'
		);

		add_settings_field(
			'widget_debugging_enabled',
			esc_html__( 'Enable client logs', 'best-web-push-notifications' ),
			array( $this, 'print_widget_debugging_enabled' ),
			'bwpn-options',
			'bwpn_javascript_sdk_settings'
		);

		add_settings_section(
			'bwpn_notifications_settings',
			esc_html__( 'Notifications', 'best-web-push-notifications' ),
			array( $this, 'print_notifications_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'notifications_interaction_required',
			esc_html__( 'Fixed notifications (do not auto hide)', 'best-web-push-notifications' ),
			array( $this, 'print_notifications_interaction_required' ),
			'bwpn-options',
			'bwpn_notifications_settings'
		);

		add_settings_field(
			'notifications_default_icon',
			esc_html__( 'Default Icon', 'best-web-push-notifications' ),
			array( $this, 'print_notifications_default_icon' ),
			'bwpn-options',
			'bwpn_notifications_settings'
		);

		add_settings_section(
			'bwpn_segmentation_settings',
			esc_html__( 'Segmentation and Tracking', 'best-web-push-notifications' ),
			array( $this, 'print_segmentation_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'segmentation_enabled',
			esc_html__( 'Enable and collect data from users', 'best-web-push-notifications' ),
			array( $this, 'print_segmentation_enabled' ),
			'bwpn-options',
			'bwpn_segmentation_settings'
		);

		add_settings_field(
			'segmentation_tracking_utm',
			esc_html__( 'UTM analytics', 'best-web-push-notifications' ),
			array( $this, 'print_segmentation_tracking_utm' ),
			'bwpn-options',
			'bwpn_segmentation_settings'
		);

		add_settings_section(
			'bwpn_metabox_settings',
			esc_html__( 'Post publishing', 'best-web-push-notifications' ),
			array( $this, 'print_metabox_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'check_send_on_publish',
			esc_html__( 'Always send a notification', 'best-web-push-notifications' ),
			array( $this, 'print_default_send_on_publish' ),
			'bwpn-options',
			'bwpn_metabox_settings'
		);

		add_settings_section(
			'bwpn_self_hosted_settings',
			esc_html__( 'Self-hosted server', 'best-web-push-notifications' ),
			array( $this, 'print_self_hosted_section' ),
			'bwpn-options'
		);

		add_settings_field(
			'vapid_private_key',
			esc_html__( 'Vapid private key', 'best-web-push-notifications' ),
			array( $this, 'print_vapid_private_key' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);
		add_settings_field(
			'vapid_public_key',
			esc_html__( 'Vapid public key', 'best-web-push-notifications' ),
			array( $this, 'print_vapid_public_key' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);

		add_settings_field(
			'server_url',
			esc_html__( 'Custom REST Url', 'best-web-push-notifications' ),
			array( $this, 'print_server_url' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);

		add_settings_field(
			'batch_size',
			esc_html__( 'Batch size', 'best-web-push-notifications' ),
			array( $this, 'print_batch_size' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);

		add_settings_field(
			'parallel_flushing_size',
			esc_html__( 'Parallel flushing size', 'best-web-push-notifications' ),
			array( $this, 'print_parallel_flushing_size' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);

		add_settings_field(
			'log_driver',
			esc_html__( 'Log driver', 'best-web-push-notifications' ),
			array( $this, 'print_log_driver' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);

		add_settings_field(
			'log_level',
			esc_html__( 'Log level', 'best-web-push-notifications' ),
			array( $this, 'print_log_level' ),
			'bwpn-options',
			'bwpn_self_hosted_settings'
		);
	}

	/**
	 * Executes the cron check. Note that this will only run
	 * when the admin area is loaded (uses the `admin_init` hook)
	 */
	public function check_cron() {
		// This cron check is only intended to be run from the admin area, or
		// when the `admin_init` hook is called
		BWPN_Lib_Cron_Check::run();
	}

	/**
	 * Execute the next broadcast batch
	 * This is a WordPress action called from wp-cron
	 *
	 * @param $notification_id int Notification ID
	 */
	public function execute_broadcast_batch( $notification_id ) {
		BWPN_Lib_Push_Server::execute_broadcast_batch( $notification_id );
	}

	/**
	 * Register the metaboxes
	 */
	public function register_metaboxes() {
		$post_types   = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			)
		);
		$post_types[] = 'post';

		add_meta_box( 'bwpn_post_metabox', 'Best Web Push', array( $this, 'display_post_metabox' ), $post_types, 'side', 'high' );
	}

	/**
	 * Displays the metabox in the post
	 *
	 * @param $post object Contains the post
	 */
	public function display_post_metabox( $post ) {
		wp_nonce_field( 'bwpn_post_metabox', 'bwpn_post_metabox_nonce' );
		$options               = get_option( 'bwpn_settings', array() );
		$check_send_on_publish = isset( $options['check_send_on_publish'] ) && $options['check_send_on_publish'] == 1;

		$send_notification = ! empty( get_post_meta( $post->ID, '_bwpn_send_on_publish', true ) );

		$notification_title = get_post_meta( $post->ID, '_bwpn_notification_custom_title', true );
		$notification_body  = get_post_meta( $post->ID, '_bwpn_notification_custom_body', true );

		$is_customized = ! empty( trim( $notification_body ) ) || ! empty( trim( $notification_title ) );

		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-post-metabox.php';
	}

	/**
	 * Actions triggered when saving posts
	 * Hook triggered when saving the posts
	 *
	 * @param $post_id
	 */
	public function on_save_post( $post_id ) {
		// check the nonce
		if ( ! isset( $_POST['bwpn_post_metabox_nonce'] ) ) {
			return $post_id;
		}
		$nonce = $_POST['bwpn_post_metabox_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'bwpn_post_metabox' ) ) {
			return $post_id;
		}

		// nothing on auto save
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// check capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_page', $post_id ) ) {
			return $post_id;
		}

		$send_notification = ! empty( $_POST['bwpn_send_on_publish'] );
		update_post_meta( $post_id, '_bwpn_send_on_publish', $send_notification );

		$notification_title = $_POST['bwpn_notification_custom_title'] ?? '';
		update_post_meta( $post_id, '_bwpn_notification_custom_title', esc_html( $notification_title ) );

		$notification_body = $_POST['bwpn_notification_custom_body'] ?? '';
		update_post_meta( $post_id, '_bwpn_notification_custom_body', esc_html( $notification_body ) );
	}

	/**
	 * Action triggered when a post changes status
	 *
	 * @param $new_status string New status
	 * @param $old_status string Old status
	 * @param $post WP_Post Post
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		// related: https://github.com/WordPress/gutenberg/issues/15094
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$send_notification = false;
		$is_customized     = false;
		if ( isset( $_POST['bwpn_post_metabox_nonce'] ) &&
			wp_verify_nonce( $_POST['bwpn_post_metabox_nonce'], 'bwpn_post_metabox' ) ) {
			// we do this because on_transition_post_status is triggered before on_save_post by WordPress
			$send_notification  = ! empty( $_POST['bwpn_send_on_publish'] );
			$notification_title = $_POST['bwpn_notification_custom_title'] ?? '';
			$notification_body  = $_POST['bwpn_notification_custom_body'] ?? '';
		} else {
			$send_notification  = ! empty( get_post_meta( $post->ID, '_bwpn_send_on_publish', true ) );
			$notification_title = get_post_meta( $post->ID, '_bwpn_notification_custom_title', true );
			$notification_body  = get_post_meta( $post->ID, '_bwpn_notification_custom_body', true );
		}

		if ( 'publish' == $new_status && $send_notification ) {
			$body               = trim( $notification_body ) ? $notification_body : html_entity_decode( get_the_title( $post ) );
			$url_to_open        = get_the_permalink( $post );
			$notification_title = trim( $notification_title ) ? $notification_title : '';

			// we use this to check if the post has a thumbnail because has_post_thumbnail could return true even if no post thumbnail is set.
			$featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
			if ( ! empty( $featured_image_url ) ) {
				$post_thumbnail = get_the_post_thumbnail_url( $post->ID );
			} else {
				$post_thumbnail = $this->get_first_image_url( $post );
			}

			$payload = BWPN_Lib_Payload::build( $body, $notification_title, $post_thumbnail, $url_to_open );
			$result  = BWPN_Lib_Push_Server::schedule_broadcast_async( $payload );

			if ( $result === false ) {
				error_log( esc_html__( 'Could not schedule the broadcast async, check the logs', 'best-web-push-notifications' ) );
				BWPN_Lib_Utils::show_message( esc_html__( 'Could not send the notification', 'best-web-push-notifications' ), 'error' );
			} else {
				if ( isset( $_POST['bwpn_post_metabox_nonce'] ) ) {
					// once we sent the notification, we reset the checkbox when the
					// hook was triggered after clicking the save button.
					unset( $_POST['bwpn_send_on_publish'] );
				}
				update_post_meta( $post->ID, '_bwpn_send_on_publish', false );

				BWPN_Lib_Utils::show_message( '<strong>Best Web Push</strong> ' . esc_html__( 'has sent a notification for the recently published post:', 'best-web-push-notifications' ) . ' ' . $body );
			}
		}
	}

	/**
	 * Handles scheduled posts that are published by WP-Cron
	 *
	 * @param WP_Post $post The post being published
	 * @since 1.6.4
	 */
	public function on_publish_future_post( $post ) {
		$send_notification = ! empty( get_post_meta( $post->ID, '_bwpn_send_on_publish', true ) );

		if ( $send_notification ) {
			$notification_title = get_post_meta( $post->ID, '_bwpn_notification_custom_title', true );
			$notification_body  = get_post_meta( $post->ID, '_bwpn_notification_custom_body', true );

			$body               = trim( $notification_body ) ? $notification_body : html_entity_decode( get_the_title( $post ) );
			$url_to_open        = get_the_permalink( $post );
			$notification_title = trim( $notification_title ) ? $notification_title : '';

			// We use this to check if the post has a thumbnail because has_post_thumbnail could return true even if no post thumbnail is set.
			$featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
			if ( ! empty( $featured_image_url ) ) {
				$post_thumbnail = get_the_post_thumbnail_url( $post->ID );
			} else {
				$post_thumbnail = $this->get_first_image_url( $post );
			}

			$payload = BWPN_Lib_Payload::build( $body, $notification_title, $post_thumbnail, $url_to_open );
			$result  = BWPN_Lib_Push_Server::schedule_broadcast_async( $payload );

			if ( $result === false ) {
				error_log( esc_html__( 'Could not schedule the broadcast async for scheduled post, check the logs', 'best-web-push-notifications' ) );
			} else {
				// Reset the notification flag after sending the notification
				update_post_meta( $post->ID, '_bwpn_send_on_publish', false );
			}
		}
	}

	/**
	 * Show the admin notices
	 *
	 * Gets the transient 'bwpn_admin_notice' which is an array with the type as key and a message as value
	 */
	public function show_admin_notice() {
		$notice = get_transient( 'bwpn_admin_notice' );

		if ( $notice && is_array( $notice ) ) {
			$type    = $notice['type'];
			$message = $notice['message'];

			if ( $type === 'warning' ) {
				printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', $message );
			} elseif ( $type === 'error' ) {
				printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', $message );
			} else {
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', $message );
			}

			delete_transient( 'bwpn_admin_notice' );
		}
	}

	/**
	 * Renders the dashboard page
	 *
	 * @since 1.0.0
	 */
	public function print_dashboard_page() {
		$end_date   = new DateTime();
		$start_date = new DateTime();
		$start_date->sub( new DateInterval( 'P7D' ) );

		$users_stats         = BWPN_Lib_Db::get_users_stats();
		$notifications_stats = BWPN_Lib_Db::get_notifications_stats();
		$jobs_stats          = BWPN_Lib_Db::get_jobs_stats();
		$daily_stats         = BWPN_Lib_Db::get_notifications_daily_stats( $start_date, $end_date );

		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-dashboard.php';
	}

	/**
	 * Renders the options page
	 *
	 * @since 1.0.0
	 */
	public function print_options_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-options.php';
	}

	/**
	 * Renders the notifications page
	 *
	 * @since 1.0.0
	 */
	public function print_notifications_page() {
		$page = esc_html( sanitize_key( $_REQUEST['page'] ) );

		if ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) &&
			isset( $_GET['action'] ) && $_GET['action'] == 'view' ) {
			$id   = intval( $_REQUEST['id'] );
			$item = BWPN_Lib_Db::get_notification( $id );

			$item->payload = json_decode( $item->payload );

			require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-notifications-view.php';
			return;
		}

		$table    = new BWPN_Admin_Notifications_Table();
		$affected = $table->prepare_items();
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-notifications.php';
	}

	/**
	 * Renders the users page
	 *
	 * @since 1.0.0
	 */
	public function print_users_page() {
		$page = esc_html( sanitize_key( $_REQUEST['page'] ) );

		if ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) &&
			isset( $_GET['action'] ) && $_GET['action'] == 'view' ) {
			$id                   = intval( $_REQUEST['id'] );
			$item                 = BWPN_Lib_Db::get_user( $id );
			$options              = get_option( 'bwpn_settings', array() );
			$segmentation_enabled = isset( $options['segmentation_enabled'] ) && $options['segmentation_enabled'] == 1;

			require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-users-view.php';
			return true;
		}

		$table    = new BWPN_Admin_Users_Table();
		$affected = $table->prepare_items();
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-users.php';
	}

	/**
	 * Renders the send notification page
	 *
	 * @since 1.0.0
	 */
	public function print_send_notification_page() {
		$message = '';
		$notice  = '';

		$default = array(
			'bwpn-send-notification-title'       => '',
			'bwpn-send-notification-message'     => '',
			'bwpn-send-notification-url-to-open' => '',
			'bwpn-send-notification-image'       => '',
			'bwpn-send-notification-timeoffset'  => '',
		);

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'bwpn_send_notification' ) ) {
			$item = shortcode_atts( $default, $_REQUEST );

			$validation_result = $this->validate_notification_message( $item );
			if ( $validation_result === true ) {
				// filter
				$item['bwpn-send-notification-title']       = sanitize_text_field( $item['bwpn-send-notification-title'] );
				$item['bwpn-send-notification-message']     = sanitize_textarea_field( $item['bwpn-send-notification-message'] );
				$item['bwpn-send-notification-url-to-open'] = sanitize_text_field( $item['bwpn-send-notification-url-to-open'] );
				$item['bwpn-send-notification-image']       = sanitize_text_field( $item['bwpn-send-notification-image'] );
				$item['bwpn-send-notification-timeoffset']  = sanitize_text_field( $item['bwpn-send-notification-timeoffset'] );

				$payload        = BWPN_Lib_Payload::build( $item['bwpn-send-notification-message'], $item['bwpn-send-notification-title'], $item['bwpn-send-notification-image'], $item['bwpn-send-notification-url-to-open'] );
				$timeoffset     = intval( $item['bwpn-send-notification-timeoffset'] );
				$scheduled_time = self::calculate_scheduled_time_from_offset( $timeoffset );

				// send notification
				$result = BWPN_Lib_Push_Server::schedule_broadcast_async( $payload, $scheduled_time );

				if ( $result === false ) {
					  $notice = esc_html__( 'Could not schedule the notification, check the logs', 'best-web-push-notifications' );
				} else {
					$message = esc_html__( 'The notification job has been scheduled', 'best-web-push-notifications' );
					if ( $item['bwpn-send-notification-timeoffset'] ) {
						$message .= ' ' . esc_html__( 'at', 'best-web-push-notifications' ) . ' ';
						$message .= get_date_from_gmt( date( 'Y-m-d H:i:s', $scheduled_time ), 'Y-m-d H:i:s' );
					}

					// we clear the form
					$item = $default;
				}
			} else {
				$notice = $validation_result;
			}
		} else {
			$item = $default;
		}

		add_meta_box(
			'bwpn_send_notification_meta_box',
			esc_html__( 'Notification details', 'best-web-push-notifications' ),
			array( $this, 'print_send_notification_metabox' ),
			'bwpn-send-notification',
			'normal'
		);

		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-send-notification.php';
	}

	/**
	 * Calculate scheduled time
	 *
	 * @param array $input Contains the settings
	 * @return int The notification scheduled time measured in the number of seconds since the Unix Epoch (January 1 1970 00:00:00 GMT).
	 */
	private function calculate_scheduled_time_from_offset( $timeoffset ) {
		$timeoffset = intval( $timeoffset );
		return time() + $timeoffset;
	}

	/**
	 * Validates the notification details
	 *
	 * @param array $item Contains the entry
	 */
	public function validate_notification_message( $item ) {
		$messages = array();

		if ( empty( $item['bwpn-send-notification-title'] ) ) {
			$messages[] = esc_html__( 'The title is required', 'best-web-push-notifications' );
		}

		if ( empty( $item['bwpn-send-notification-message'] ) ) {
			$messages[] = esc_html__( 'The message is required', 'best-web-push-notifications' );
		}

		if ( empty( $messages ) ) {
			return true;
		} else {
			return implode( '<br />', $messages );
		}
	}

	/**
	 * Renders the send notification metabox
	 *
	 * @since 1.0.0
	 */
	public function print_send_notification_metabox( $item ) {
		$options  = get_option( 'bwpn_settings' );
		$icon_id  = isset( $options['notifications_default_icon'] ) ? esc_attr( $options['notifications_default_icon'] ) : '';
		$icon_url = wp_get_attachment_url( $icon_id );
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-send-notification-metabox.php';
	}

	/**
	 * Renders the logs page
	 *
	 * @since 1.2.0
	 */
	public function print_logs_page() {
		$page = esc_html( sanitize_key( $_REQUEST['page'] ) );

		$table        = new BWPN_Admin_Logs_Table();
		$affected     = $table->prepare_items();
		$options      = get_option( 'bwpn_settings', array() );
		$enabled_logs = isset( $options['log_driver'] ) && $options['log_driver'] == 'db';
		if ( ! $enabled_logs ) {
			$message = esc_html__( 'You are using a logger that does not support the Log viewer (supported: Database)', 'best-web-push-notifications' );
		}
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-logs.php';
	}

	/**
	 * Renders the about page
	 *
	 * @since 1.0.0
	 */
	public function print_about_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/bwpn-admin-about.php';
	}

	/**
	 * Sanitize the settings
	 *
	 * @param array $input Contains the settings
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();
		$options   = get_option( 'bwpn_settings' );

		// checkboxes
		if ( isset( $input['widget_enabled'] ) ) {
			$new_input['widget_enabled'] = 1;
		} else {
			$new_input['widget_enabled'] = 0;
		}
		if ( isset( $input['unregister_conflicts'] ) ) {
			$new_input['unregister_conflicts'] = 1;
		} else {
			$new_input['unregister_conflicts'] = 0;
		}
		if ( isset( $input['widget_debugging_enabled'] ) ) {
			$new_input['widget_debugging_enabled'] = 1;
		} else {
			$new_input['widget_debugging_enabled'] = 0;
		}
		if ( isset( $input['widget_hide_bell_after_subscribe'] ) ) {
			$new_input['widget_hide_bell_after_subscribe'] = 1;
		} else {
			$new_input['widget_hide_bell_after_subscribe'] = 0;
		}
		if ( isset( $input['widget_ask_permissions_directly'] ) ) {
			$new_input['widget_ask_permissions_directly'] = 1;
		} else {
			$new_input['widget_ask_permissions_directly'] = 0;
		}
		if ( isset( $input['segmentation_enabled'] ) ) {
			$new_input['segmentation_enabled'] = 1;
		} else {
			$new_input['segmentation_enabled'] = 0;
		}
		if ( isset( $input['notifications_interaction_required'] ) ) {
			$new_input['notifications_interaction_required'] = 1;
		} else {
			$new_input['notifications_interaction_required'] = 0;
		}
		if ( isset( $input['check_send_on_publish'] ) ) {
			$new_input['check_send_on_publish'] = 1;
		} else {
			$new_input['check_send_on_publish'] = 0;
		}
		if ( isset( $input['settings_send_welcome_message'] ) ) {
			$new_input['settings_send_welcome_message'] = 1;
		} else {
			$new_input['settings_send_welcome_message'] = 0;
		}

		// text
		if ( isset( $input['service_worker_scope'] ) ) {
			$new_input['service_worker_scope'] = sanitize_text_field( $input['service_worker_scope'] );
		}
		if ( isset( $input['dialog_title'] ) ) {
			$new_input['dialog_title'] = sanitize_text_field( $input['dialog_title'] );
		}
		if ( isset( $input['dialog_submit'] ) ) {
			$new_input['dialog_submit'] = sanitize_text_field( $input['dialog_submit'] );
		}
		if ( isset( $input['dialog_cancel'] ) ) {
			$new_input['dialog_cancel'] = sanitize_text_field( $input['dialog_cancel'] );
		}
		if ( isset( $input['settings_title'] ) ) {
			$new_input['settings_title'] = sanitize_text_field( $input['settings_title'] );
		}
		if ( isset( $input['settings_opt_in'] ) ) {
			$new_input['settings_opt_in'] = sanitize_text_field( $input['settings_opt_in'] );
		}
		if ( isset( $input['settings_update_error'] ) ) {
			$new_input['settings_update_error'] = sanitize_text_field( $input['settings_update_error'] );
		}
		if ( isset( $input['settings_welcome_message'] ) ) {
			$new_input['settings_welcome_message'] = sanitize_text_field( $input['settings_welcome_message'] );
		}
		if ( isset( $input['vapid_public_key'] ) ) {
			$new_input['vapid_public_key'] = sanitize_text_field( $input['vapid_public_key'] );
		}
		if ( isset( $input['vapid_private_key'] ) ) {
			$new_input['vapid_private_key'] = sanitize_text_field( $input['vapid_private_key'] );
		}
		if ( isset( $input['server_url'] ) ) {
			$new_input['server_url'] = sanitize_text_field( $input['server_url'] );
		}
		if ( isset( $input['batch_size'] ) ) {
			$new_input['batch_size'] = intval( sanitize_text_field( $input['batch_size'] ) );
		}
		if ( isset( $input['parallel_flushing_size'] ) ) {
			$new_input['parallel_flushing_size'] = intval( sanitize_text_field( $input['parallel_flushing_size'] ) );
		}
		if ( isset( $input['notifications_default_icon'] ) ) {
			$new_input['notifications_default_icon'] = intval( sanitize_text_field( $input['notifications_default_icon'] ) );
		}
		if ( isset( $input['unregister_conflicts_expression'] ) ) {
			$new_input['unregister_conflicts_expression'] = sanitize_text_field( $input['unregister_conflicts_expression'] );
		}
		if ( isset( $input['segmentation_tracking_utm'] ) ) {
			$new_input['segmentation_tracking_utm'] = sanitize_text_field( $input['segmentation_tracking_utm'] );
		}
		if ( isset( $input['visits_to_display_prompt'] ) ) {
			$new_input['visits_to_display_prompt'] = intval( sanitize_text_field( $input['visits_to_display_prompt'] ) );
		}
		if ( isset( $input['log_driver'] ) ) {
			$new_input['log_driver'] = sanitize_text_field( $input['log_driver'] );
		}
		if ( isset( $input['log_level'] ) ) {
			$new_input['log_level'] = sanitize_text_field( $input['log_level'] );
		}

		if ( empty( $options['vapid_public_key'] ) && empty( $options['vapid_private_key'] ) &&
			! empty( $new_input['vapid_public_key'] ) && ! empty( $new_input['vapid_private_key'] ) ) {
			BWPN_Lib_Utils::clean_messages();
		}
		return $new_input;
	}

	/**
	 * Print the general section info
	 *
	 * @since 1.0.0
	 */
	public function print_dialog_section() {
		print esc_html__( 'The controls in your front page (bell control/opt-in box).', 'best-web-push-notifications' );
	}

	/**
	 * Print the general section info
	 *
	 * @since 1.3.0
	 */
	public function print_javascript_sdk_section() {
		print esc_html__( 'Client SDK that handles the subscription/push notifications.', 'best-web-push-notifications' );
	}

	/**
	 * Print the self hosted section info
	 *
	 * @since 1.0.0
	 */
	public function print_self_hosted_section() {
		print esc_html__( 'Configure your self-hosted Best Web Push Server.', 'best-web-push-notifications' );
	}

	/**
	 * Print the notifications section info
	 *
	 * @since 1.3.0
	 */
	public function print_notifications_section() {
		print esc_html__( 'Notifications that the users receive (Mobile/Desktop).', 'best-web-push-notifications' );
	}

	/**
	 * Print the segmentation section info
	 *
	 * @since 1.1.3
	 */
	public function print_segmentation_section() {
		print esc_html__( 'Campaign targeting and behaviour tracking.', 'best-web-push-notifications' );
	}

	/**
	 * Print the Tracking UTM parameters
	 *
	 * @since 1.3.0
	 */
	public function print_segmentation_tracking_utm() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['segmentation_tracking_utm'] ) ? esc_attr( $options['segmentation_tracking_utm'] ) : '';

		printf(
			'Example: <div class="bwpn-options-unregister-conflicts-regex">%s</div>' .
			'<input type="text" id="bwpn_settings[segmentation_tracking_utm]"' .
			'name="bwpn_settings[segmentation_tracking_utm]" value="%s"/>',
			esc_html( 'utm_source=bwpn&utm_medium=web-push&utm_campaign=my-campaign-name' ),
			esc_html( $value )
		);
	}


	/**
	 * Print the Vapid public key
	 *
	 * @since 1.0.0
	 */
	public function print_vapid_public_key() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['vapid_public_key'] ) ? esc_attr( $options['vapid_public_key'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[vapid_public_key]"' .
			'name="bwpn_settings[vapid_public_key]" value="%s"/>',
			esc_html( $value )
		);
	}

	/**
	 * Print the Vapid private key
	 *
	 * @since 1.0.0
	 */
	public function print_vapid_private_key() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['vapid_private_key'] ) ? esc_attr( $options['vapid_private_key'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[vapid_private_key]"' .
			'name="bwpn_settings[vapid_private_key]" value="%s"/>',
			esc_html( $value )
		);
	}

	/**
	 * Print the server_url option
	 *
	 * @since 1.0.0
	 */
	public function print_server_url() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['server_url'] ) ? esc_attr( $options['server_url'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[server_url]"' .
			'name="bwpn_settings[server_url]" value="%s" placeholder="%s"/>',
			esc_html( $value ),
			get_rest_url( null, 'bwpn' )
		);
	}

	/**
	 * Print the batch_size option
	 *
	 * @since 1.0.0
	 */
	public function print_batch_size() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['batch_size'] ) ? esc_attr( $options['batch_size'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[batch_size]"' .
			'name="bwpn_settings[batch_size]" value="%s"/>' .
			'<div>%s <a href="%s" target="_blank">%s</div>',
			esc_html( $value ),
			esc_html( 'High values require a longer script execution time. See: ' ),
			'https://docs.perfecty.org/wp/performance-improvements/#adjusting-the-batch_size-parameter',
			'Batch size'
		);
	}

	/**
	 * Print the parallel_flushing_size option
	 *
	 * @since 1.4.0
	 */
	public function print_parallel_flushing_size() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['parallel_flushing_size'] ) ? esc_attr( $options['parallel_flushing_size'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[parallel_flushing_size]"' .
			'name="bwpn_settings[parallel_flushing_size]" value="%s"/>' .
			'<div>%s <a href="%s" target="_blank">%s</div>',
			esc_html( $value ),
			esc_html( 'Notifications to send in parallel. Please read: ' ),
			'https://docs.perfecty.org/wp/performance-improvements/#adjusting-the-parallel_flushing_size-parameter',
			'Parallel flushing'
		);
	}

	/**
	 * Print the widget_enabled option
	 *
	 * @since 1.0.0
	 */
	public function print_widget_enabled() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['widget_enabled'] ) ? esc_attr( $options['widget_enabled'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[widget_enabled]"' .
			'name="bwpn_settings[widget_enabled]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the debugging public enabled option
	 *
	 * @since 1.0.0
	 */
	public function print_widget_debugging_enabled() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['widget_debugging_enabled'] ) ? esc_attr( $options['widget_debugging_enabled'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[widget_debugging_enabled]"' .
			'name="bwpn_settings[widget_debugging_enabled]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the hide bell after the user has been subscribed
	 *
	 * @since 1.1.3
	 */
	public function print_widget_hide_bell_after_subscribe() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['widget_hide_bell_after_subscribe'] ) ? esc_attr( $options['widget_hide_bell_after_subscribe'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[widget_hide_bell_after_subscribe]"' .
			'name="bwpn_settings[widget_hide_bell_after_subscribe]" %s class="bwpn-options-dialog-group"/>',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the visits to display prompt option
	 *
	 * @since 1.3.0
	 */
	public function print_visits_to_display_prompt() {
		$options     = get_option( 'bwpn_settings' );
		$value       = isset( $options['visits_to_display_prompt'] ) && $options['visits_to_display_prompt'] ? esc_attr( $options['visits_to_display_prompt'] ) : '';
		$placeholder = ! $value ? '0 (Immediately)' : '';

		printf(
			'<input type="text" id="bwpn_settings[visits_to_display_prompt]"' .
			'name="bwpn_settings[visits_to_display_prompt]" value="%s" placeholder="%s"/>',
			esc_html( $value ),
			esc_html__( $placeholder, 'best-web-push-notifications' )
		);
	}

	/**
	 * Print the ask permissions directly option
	 *
	 * @since 1.1.3
	 */
	public function print_widget_ask_permissions_directly() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['widget_ask_permissions_directly'] ) ? esc_attr( $options['widget_ask_permissions_directly'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[widget_ask_permissions_directly]"' .
			'name="bwpn_settings[widget_ask_permissions_directly]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the logs driver setting
	 *
	 * @since 1.6.0
	 */
	public function print_log_driver() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['log_driver'] ) ? esc_attr( $options['log_driver'] ) : 'errorlog';

		$print_selected = function( $val ) use ( $value ) {
			return $val == $value ? 'selected' : '';
		};
		printf(
			'<select name="bwpn_settings[log_driver]" id="bwpn_settings[log_driver]">' .
			'<option value="errorlog" ' . $print_selected( 'errorlog' ) . '>PHP - error_log()</option>' .
			'<option value="db"' . $print_selected( 'db' ) . '>Database</option>' .
			'</select>'
		);
	}

	/**
	 * Print the logs level setting
	 *
	 * @since 1.6.0
	 */
	public function print_log_level() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['log_level'] ) ? esc_attr( $options['log_level'] ) : 'error';

		$print_selected = function( $val ) use ( $value ) {
			return $val == $value ? 'selected' : '';
		};
		printf(
			'<select name="bwpn_settings[log_level]" id="bwpn_settings[log_level]">' .
			'<option value="error" ' . $print_selected( 'error' ) . '>Error</option>' .
			'<option value="warning"' . $print_selected( 'warning' ) . '>Warning</option>' .
			'<option value="info"' . $print_selected( 'info' ) . '>Info</option>' .
			'<option value="debug"' . $print_selected( 'debug' ) . '>Debug</option>' .
			'</select>'
		);
	}

	/**
	 * Print the default notification icon setting
	 *
	 * @since 1.3.0
	 */
	public function print_notifications_default_icon() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['notifications_default_icon'] ) ? esc_attr( $options['notifications_default_icon'] ) : '';

		printf(
			'<div class="bwpn-default-icon-preview-container"><img src="%s" class="bwpn-default-icon-preview"/></div>' .
			'<input type="button" id="bwpn-default-icon-select" class="button" value="%s"/>' .
			'<input type="hidden" id="bwpn_settings[notifications_default_icon]"' .
			'name="bwpn_settings[notifications_default_icon]" value="%s"/>',
			wp_get_attachment_url( $value ),
			esc_html__( 'Select image', 'best-web-push-notifications' ),
			esc_html( $value )
		);
	}

	/**
	 * Print the enable segmentation setting
	 *
	 * @since 1.1.3
	 */
	public function print_segmentation_enabled() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['segmentation_enabled'] ) ? esc_attr( $options['segmentation_enabled'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[segmentation_enabled]"' .
			'name="bwpn_settings[segmentation_enabled]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the unregister_conflicts option
	 *
	 * @since 1.0.0
	 */
	public function print_unregister_conflicts() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['unregister_conflicts'] ) ? esc_attr( $options['unregister_conflicts'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[unregister_conflicts]"' .
			'name="bwpn_settings[unregister_conflicts]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the unregister_conflicts_expression option
	 *
	 * @since 1.3.0
	 */
	public function print_unregister_conflicts_expression() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['unregister_conflicts_expression'] ) ? esc_attr( $options['unregister_conflicts_expression'] ) : '';

		printf(
			'<div>Default expression:</div><div class="bwpn-options-unregister-conflicts-regex">%s</div>' .
			'<textarea id="bwpn_settings[unregister_conflicts_expression]"' .
			'name="bwpn_settings[unregister_conflicts_expression]" class="bwpn-options-unregister-conflicts-group" ' .
			'placeholder="%s">%s</textarea>' .
			'<div><a href="%s" target="_blank">%s</a></div>',
			esc_html( BWPN_UNREGISTER_CONFLICTS_EXPRESSION ),
			esc_html__( 'Custom JS Regular Expression', 'best-web-push-notifications' ),
			esc_html( $value ),
			'https://docs.perfecty.org/wp/conflict-resolution/',
			esc_html__( 'More information', 'best-web-push-notifications' )
		);
	}

	/**
	 * Print the service_worker_scope option
	 *
	 * @since 1.0.7
	 */
	public function print_service_worker_scope() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['service_worker_scope'] ) ? esc_attr( $options['service_worker_scope'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[service_worker_scope]"' .
			'name="bwpn_settings[service_worker_scope]" value="%s" />',
			esc_html( $value )
		);
	}

	/**
	 * Print the notifications_interaction_required option
	 *
	 * @since 1.3.0
	 */
	public function print_notifications_interaction_required() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['notifications_interaction_required'] ) ? esc_attr( $options['notifications_interaction_required'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[notifications_interaction_required]"' .
			'name="bwpn_settings[notifications_interaction_required]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the dialog_title option
	 *
	 * @since 1.0.0
	 */
	public function print_dialog_title() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['dialog_title'] ) && ! empty( $options['dialog_title'] ) ? esc_attr( $options['dialog_title'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[dialog_title]"' .
			'name="bwpn_settings[dialog_title]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_DIALOG_TITLE
		);
	}

	/**
	 * Print the dialog_submit option
	 *
	 * @since 1.0.0
	 */
	public function print_dialog_submit() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['dialog_submit'] ) && ! empty( $options['dialog_submit'] ) ? esc_attr( $options['dialog_submit'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[dialog_submit]"' .
			'name="bwpn_settings[dialog_submit]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_DIALOG_CONTINUE
		);
	}

	/**
	 * Print the dialog_cancel option
	 *
	 * @since 1.0.0
	 */
	public function print_dialog_cancel() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['dialog_cancel'] ) && ! empty( $options['dialog_cancel'] ) ? esc_attr( $options['dialog_cancel'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[dialog_cancel]"' .
			'name="bwpn_settings[dialog_cancel]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_DIALOG_CANCEL
		);
	}

	/**
	 * Print the settings_title option
	 *
	 * @since 1.0.0
	 */
	public function print_settings_title() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['settings_title'] ) && ! empty( $options['settings_title'] ) ? esc_attr( $options['settings_title'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[settings_title]"' .
			'name="bwpn_settings[settings_title]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_SETTINGS_TITLE
		);
	}

	/**
	 * Print the settings_opt_in option
	 *
	 * @since 1.0.0
	 */
	public function print_settings_opt_in() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['settings_opt_in'] ) && ! empty( $options['settings_opt_in'] ) ? esc_attr( $options['settings_opt_in'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[settings_opt_in]"' .
			'name="bwpn_settings[settings_opt_in]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_SETTINGS_OPT_IN
		);
	}

	/**
	 * Print the settings_update_error option
	 *
	 * @since 1.0.0
	 */
	public function print_settings_update_error() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['settings_update_error'] ) && ! empty( $options['settings_update_error'] ) ? esc_attr( $options['settings_update_error'] ) : '';

		printf(
			'<input type="text" id="bwpn_settings[settings_update_error]"' .
			'name="bwpn_settings[settings_update_error]" value="%s" placeholder="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value ),
			BWPN_OPTIONS_SETTINGS_UPDATE_ERROR
		);
	}

	/**
	 * Print the metabox options section
	 *
	 * @since 1.3.0
	 */
	public function print_metabox_section() {
		print esc_html__( 'Configure how to send notifications after publishing a Post.', 'best-web-push-notifications' );
	}

	/**
	 * Print the check send on publish by default
	 *
	 * @since 1.3.0
	 */
	public function print_default_send_on_publish() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['check_send_on_publish'] ) ? esc_attr( $options['check_send_on_publish'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[check_send_on_publish]"' .
			'name="bwpn_settings[check_send_on_publish]" %s/>',
			esc_html( $enabled )
		);
	}

	/**
	 * Get first image URL in post content
	 *
	 * @since 1.0.8
	 *
	 * @return string $thumbnail_url on success, '' on failure
	 */
	public function get_first_image_url( $post ) {
		$content = $post->post_content;
		$regex   = '/src="([^"]*)"/';
		preg_match_all( $regex, $content, $matches );
		$matches = array_reverse( $matches );
		// this is the image url of the first img embedded in content.
		$img_url = $matches[0][0] ?? '';

		// this is the image post id.
		$post_img_id = $this->get_attachment_id( $img_url );

		if ( $post_img_id !== 0 ) {
			// this is an array related to the thumbnail of the first image. If post-thumbnail size is not set, it returns original image.
			$img_thumb_url = wp_get_attachment_image_src( $post_img_id, $size = 'post-thumbnail', $icon = false );
		} else {
			$img_thumb_url[0] = '';
		}
		// we return the URL of the thumbnail.
		return $img_thumb_url[0];
	}

	/**
	 * Get an attachment ID given a URL.
	 * https://wordpress.stackexchange.com/questions/6645/turn-a-url-into-an-attachment-post-id/7094#7094
	 *
	 * @param string $url
	 *
	 * @return int Attachment ID on success, 0 on failure
	 */
	function get_attachment_id( $url ) {
		$attachment_id = 0;
		$dir           = wp_upload_dir();
		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
			$file       = basename( $url );
			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				),
			);

			$query = new WP_Query( $query_args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					$meta                = wp_get_attachment_metadata( $post_id );
					$original_file       = basename( $meta['file'] );
					$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
					if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
						$attachment_id = $post_id;
						break;
					}
				}
			}
		}
		return $attachment_id;
	}

	/**
	 * Print the settings_send_welcome_message option
	 *
	 * @since 1.4.0
	 */
	public function print_settings_send_welcome_message() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['settings_send_welcome_message'] ) ? esc_attr( $options['settings_send_welcome_message'] ) : 0;

		$enabled = $value ? 'checked="checked"' : '';

		printf(
			'<input type="checkbox" id="bwpn_settings[settings_send_welcome_message]"' .
			'name="bwpn_settings[settings_send_welcome_message]" %s />',
			esc_html( $enabled )
		);
	}

	/**
	 * Print the settings_welcome_message option
	 *
	 * @since 1.4.0
	 */
	public function print_settings_welcome_message() {
		$options = get_option( 'bwpn_settings' );
		$value   = isset( $options['settings_welcome_message'] ) && ! empty( $options['settings_welcome_message'] ) ? esc_attr( $options['settings_welcome_message'] ) : BWPN_OPTIONS_SETTINGS_WELCOME_MESSAGE;

		printf(
			'<input type="text" id="bwpn_settings[settings_welcome_message]"' .
			'name="bwpn_settings[settings_welcome_message]" value="%s" class="bwpn-options-dialog-group"/>',
			esc_html( $value )
		);
	}
}

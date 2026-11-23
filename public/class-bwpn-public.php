<?php
/**
 * Front-end: SDK, styles and its configuration.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the browser SDK on the front end.
 */
class BWPN_Public {

	public const HANDLE = 'best-web-push-notifications';

	/**
	 * Registers hooks.
	 */
	public function register_hooks() {
		BWPN_Subscribers_Rest::register_hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the SDK should load on this page.
	 *
	 * @return bool
	 */
	public static function should_load() {
		$load = BWPN_Settings::bool( 'widget_enabled' ) && BWPN_Settings::has_keys() && ! BWPN_Lib_Utils::is_disabled();
		/**
		 * Filters whether the subscription SDK loads on the current page.
		 *
		 * @param bool $load Load.
		 */
		return (bool) apply_filters( 'bwpn_load_sdk', $load );
	}

	/**
	 * SDK configuration. Contains no nonce: the subscription routes do not
	 * need one, so page caches can safely store it.
	 *
	 * @return array<string, mixed>
	 */
	public static function config() {
		$icon_id = BWPN_Settings::int( 'default_icon' );
		$server  = trim( BWPN_Settings::str( 'server_url' ) );
		$config  = array(
			'serverUrl'              => '' !== $server ? untrailingslashit( esc_url_raw( $server ) ) : untrailingslashit( rest_url( BWPN_Subscribers_Rest::NAMESPACE ) ),
			'vapidPublicKey'         => BWPN_Settings::public_key(),
			'serviceWorkerUrl'       => BWPN_Service_Worker::worker_url(),
			'serviceWorkerScope'     => BWPN_Settings::scope(),
			'dialogTitle'            => BWPN_Settings::text( 'dialog_title' ),
			'dialogSubmit'           => BWPN_Settings::text( 'dialog_submit' ),
			'dialogCancel'           => BWPN_Settings::text( 'dialog_cancel' ),
			'settingsTitle'          => BWPN_Settings::text( 'settings_title' ),
			'settingsOptIn'          => BWPN_Settings::text( 'settings_opt_in' ),
			'settingsUpdateError'    => BWPN_Settings::text( 'settings_update_error' ),
			'promptIconUrl'          => $icon_id ? (string) wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '',
			'promptVisits'           => max( 1, BWPN_Settings::int( 'prompt_visits' ) ),
			'promptDelay'            => max( 0, BWPN_Settings::int( 'prompt_delay' ) ),
			'promptScroll'           => min( 100, max( 0, BWPN_Settings::int( 'prompt_scroll' ) ) ),
			'hideBellAfterSubscribe' => BWPN_Settings::bool( 'hide_bell_after_subscribe' ),
			'iosHint'                => BWPN_Settings::bool( 'ios_hint_enabled' ) ? BWPN_Settings::text( 'ios_hint_text' ) : '',
			'adoptLegacy'            => true,
			'loggerLevel'            => BWPN_Settings::bool( 'client_debug' ) ? 'debug' : 'error',
		);
		/**
		 * Filters the SDK configuration printed on the page.
		 *
		 * @param array<string, mixed> $config Config.
		 */
		return (array) apply_filters( 'bwpn_sdk_config', $config );
	}

	/**
	 * Enqueues the SDK.
	 */
	public function enqueue() {
		if ( ! self::should_load() ) {
			return;
		}
		$file = BWPN_PATH . 'public/js/bwpn-sdk.min.js';
		$ver  = BWPN_VERSION . ( is_readable( $file ) ? '.' . filemtime( $file ) : '' );
		wp_enqueue_style( self::HANDLE, BWPN_URL . 'public/css/bwpn-public.css', array(), BWPN_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			BWPN_URL . 'public/js/bwpn-sdk.min.js',
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.BWPNOptions = ' . wp_json_encode( self::config(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}
}

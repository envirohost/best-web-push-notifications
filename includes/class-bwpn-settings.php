<?php
/**
 * Settings and VAPID key storage.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed access to the plugin settings and VAPID keys.
 *
 * Settings live in one autoloaded option (`bwpn_settings`). The VAPID public
 * key is autoloaded because every front-end page needs it; the private key
 * is stored in its own option that is not autoloaded. Both keys can be
 * pinned in wp-config.php with BWPN_VAPID_PUBLIC_KEY / BWPN_VAPID_PRIVATE_KEY.
 */
class BWPN_Settings {

	public const OPTION             = 'bwpn_settings';
	public const OPTION_PUBLIC_KEY  = 'bwpn_vapid_public_key';
	public const OPTION_PRIVATE_KEY = 'bwpn_vapid_private_key';
	public const DEFAULT_SCOPE      = '/bwpn/push/';

	/**
	 * Default values for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			// Opt-in prompt.
			'widget_enabled'            => 1,
			'prompt_visits'             => 1,
			'prompt_delay'              => 5,
			'prompt_scroll'             => 0,
			'hide_bell_after_subscribe' => 0,
			'dialog_title'              => '',
			'dialog_submit'             => '',
			'dialog_cancel'             => '',
			'settings_title'            => '',
			'settings_opt_in'           => '',
			'settings_update_error'     => '',
			'send_welcome_message'      => 1,
			'welcome_message'           => '',
			'ios_hint_enabled'          => 1,
			'ios_hint_text'             => '',
			'client_debug'              => 0,
			// Service worker and manifest.
			'service_worker_scope'      => self::DEFAULT_SCOPE,
			'manifest_enabled'          => 1,
			'server_url'                => '',
			// Notifications.
			'require_interaction'       => 0,
			'default_icon'              => 0,
			'utm'                       => '',
			'ttl'                       => 2419200,
			'send_on_publish_default'   => 0,
			// Delivery.
			'vapid_subject'             => '',
			'batch_size'                => 1000,
			'parallel_requests'         => 50,
			// Security.
			'rate_limit_per_ip'         => 10,
			'trusted_proxy'             => 'cloudflare',
			// Housekeeping.
			'log_driver'                => 'errorlog',
			'log_level'                 => 'error',
			'max_failures'              => 5,
			'delete_data_on_uninstall'  => 0,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	/**
	 * Reads one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Reads an integer setting.
	 *
	 * @param string $key Setting name.
	 * @return int
	 */
	public static function int( $key ) {
		return (int) self::get( $key );
	}

	/**
	 * Reads a boolean setting.
	 *
	 * @param string $key Setting name.
	 * @return bool
	 */
	public static function bool( $key ) {
		return (bool) self::get( $key );
	}

	/**
	 * Reads a string setting.
	 *
	 * @param string $key Setting name.
	 * @return string
	 */
	public static function str( $key ) {
		$value = self::get( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Merges values into the stored settings.
	 *
	 * @param array<string, mixed> $values New values.
	 * @return bool
	 */
	public static function update( array $values ) {
		$current = get_option( self::OPTION, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$merged = array_merge( $current, array_intersect_key( $values, self::defaults() ) );
		return update_option( self::OPTION, $merged, true );
	}

	/**
	 * Whether stored settings exist yet.
	 *
	 * @return bool
	 */
	public static function exists() {
		return false !== get_option( self::OPTION, false );
	}

	/**
	 * Translatable default texts. Only call on or after `init`.
	 *
	 * @param string $key Text key.
	 * @return string
	 */
	public static function default_text( $key ) {
		switch ( $key ) {
			case 'dialog_title':
				return __( 'Do you want to receive notifications?', 'best-web-push-notifications' );
			case 'dialog_submit':
				return __( 'Continue', 'best-web-push-notifications' );
			case 'dialog_cancel':
				return __( 'Not now', 'best-web-push-notifications' );
			case 'settings_title':
				return __( 'Notification preferences', 'best-web-push-notifications' );
			case 'settings_opt_in':
				return __( 'I want to receive notifications', 'best-web-push-notifications' );
			case 'settings_update_error':
				return __( 'Could not change the preference, please try again', 'best-web-push-notifications' );
			case 'welcome_message':
				return __( 'Thanks, you are now subscribed!', 'best-web-push-notifications' );
			case 'ios_hint_text':
				return __( 'To get notifications on iPhone or iPad, tap Share and then Add to Home Screen, and open the site from your Home Screen.', 'best-web-push-notifications' );
		}
		return '';
	}

	/**
	 * A text setting, falling back to its translated default.
	 *
	 * @param string $key Setting name.
	 * @return string
	 */
	public static function text( $key ) {
		$value = trim( self::str( $key ) );
		return '' !== $value ? $value : self::default_text( $key );
	}

	/**
	 * Service worker scope, normalised to start and end with a slash.
	 *
	 * @return string
	 */
	public static function scope() {
		$scope = trim( self::str( 'service_worker_scope' ) );
		if ( '' === $scope ) {
			$scope = self::DEFAULT_SCOPE;
		}
		$scope = '/' . trim( $scope, '/' ) . '/';
		$scope = preg_replace( '#/+#', '/', $scope );
		$home  = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home  = is_string( $home ) ? rtrim( $home, '/' ) : '';
		if ( '' !== $home && 0 !== strpos( $scope, $home . '/' ) ) {
			$scope = $home . $scope;
		}
		return (string) $scope;
	}

	/* ---------------------------------------------------------------------
	 * VAPID keys
	 * ------------------------------------------------------------------- */

	/**
	 * Whether the keys are pinned in wp-config.php.
	 *
	 * @return bool
	 */
	public static function keys_from_constants() {
		return defined( 'BWPN_VAPID_PUBLIC_KEY' ) && defined( 'BWPN_VAPID_PRIVATE_KEY' )
			&& '' !== (string) constant( 'BWPN_VAPID_PUBLIC_KEY' ) && '' !== (string) constant( 'BWPN_VAPID_PRIVATE_KEY' );
	}

	/**
	 * VAPID public key (base64url).
	 *
	 * @return string
	 */
	public static function public_key() {
		if ( self::keys_from_constants() ) {
			return (string) constant( 'BWPN_VAPID_PUBLIC_KEY' );
		}
		return (string) get_option( self::OPTION_PUBLIC_KEY, '' );
	}

	/**
	 * VAPID private key (base64url).
	 *
	 * @return string
	 */
	public static function private_key() {
		if ( self::keys_from_constants() ) {
			return (string) constant( 'BWPN_VAPID_PRIVATE_KEY' );
		}
		return (string) get_option( self::OPTION_PRIVATE_KEY, '' );
	}

	/**
	 * Whether both keys are available.
	 *
	 * @return bool
	 */
	public static function has_keys() {
		return '' !== self::public_key() && '' !== self::private_key();
	}

	/**
	 * Stores a key pair. Does nothing when keys are pinned in wp-config.php.
	 *
	 * @param string $public_key  Public key.
	 * @param string $private_key Private key.
	 * @return bool
	 */
	public static function set_keys( $public_key, $private_key ) {
		if ( self::keys_from_constants() ) {
			return false;
		}
		$public_key  = trim( (string) $public_key );
		$private_key = trim( (string) $private_key );
		if ( '' === $public_key || '' === $private_key ) {
			return false;
		}
		update_option( self::OPTION_PUBLIC_KEY, $public_key, true );
		update_option( self::OPTION_PRIVATE_KEY, $private_key, false );
		return true;
	}

	/**
	 * VAPID subject. Push services require a mailto: or https: URL.
	 *
	 * @return string
	 */
	public static function vapid_subject() {
		$subject = trim( self::str( 'vapid_subject' ) );
		if ( '' !== $subject && ( 0 === strpos( $subject, 'mailto:' ) || 0 === strpos( $subject, 'https://' ) ) ) {
			return $subject;
		}
		$home = home_url( '/' );
		if ( 0 === strpos( $home, 'https://' ) && ! preg_match( '#^https://(localhost|127\.|\[::1\])#', $home ) ) {
			return untrailingslashit( $home );
		}
		return 'mailto:' . get_option( 'admin_email' );
	}
}

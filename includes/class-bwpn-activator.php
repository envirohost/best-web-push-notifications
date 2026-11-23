<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/rwngallego
 * @since      1.0.0
 *
 * @package    BWPN
 * @subpackage BWPN/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    BWPN
 * @subpackage BWPN/includes
 * @author     Rowinson Gallego <rwn.gallego@gmail.com>
 */
class BWPN_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		BWPN_Lib_Db::db_create();
		self::default_options();
	}

	private static function default_options() {
		// Verifies if we already have both the vapid_public_key and vapid_private_key
		// If we don't, we generate them and save them
		$options = get_option( 'bwpn_settings', array() );
		if ( empty( $options['vapid_public_key'] ) && empty( $options['vapid_private_key'] ) && BWPN_Lib_Utils::is_enabled() ) {
			$vapidKeys                    = BWPN_Lib_Push_Server::create_vapid_keys();
			$options['vapid_public_key']  = $vapidKeys['publicKey'];
			$options['vapid_private_key'] = $vapidKeys['privateKey'];
		}
		if ( empty( $options['batch_size'] ) ) {
			$options['batch_size'] = BWPN_Lib_Push_Server::DEFAULT_BATCH_SIZE;
		}
		if ( empty( $options['parallel_flushing_size'] ) ) {
			$options['parallel_flushing_size'] = BWPN_Lib_Push_Server::DEFAULT_PARALLEL_FLUSHING_SIZE;
		}
		if ( empty( $options['service_worker_scope'] ) ) {
			$options['service_worker_scope'] = '/bwpn/push/';
		}
		if ( ! isset( $options['widget_enabled'] ) ) {
			$options['widget_enabled'] = 1;
		}
		if ( ! isset( $options['settings_send_welcome_message'] ) ) {
			$options['settings_send_welcome_message'] = 1;
		}
		if ( empty( $options['notifications_default_icon'] ) && get_option( 'site_icon' ) ) {
			// if the default icon is not set, we fallback to the site_icon.
			$options['notifications_default_icon'] = get_option( 'site_icon' );
		}

		if ( get_option( 'bwpn_settings' ) == false ) {
			if ( ! add_option( 'bwpn_settings', $options ) ) {
				error_log( 'Could not set the default options' );
			}
		} else {
			update_option( 'bwpn_settings', $options );
		}

		add_option( 'bwpn_activated', 1 );
	}

}

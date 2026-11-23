<?php
/**
 * Imports data from Perfecty Push Notifications.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-way import from Perfecty Push 1.x: VAPID keys, settings, service
 * worker scope and subscribers. Perfecty's own data is never changed, so
 * the import is safe to run while Perfecty is still active, and repeatable.
 *
 * Existing browsers keep working because the key pair is adopted (push
 * subscriptions are bound to the VAPID public key) and the service worker
 * scope stays the same, so our worker replaces Perfecty's at the same
 * registration and keeps its push subscription.
 *
 * Large lists are copied in batches; if a request runs out of time the rest
 * continues on a cron event.
 */
class BWPN_Migrator {

	public const OPTION_STATE = 'bwpn_perfecty_migration';
	public const CRON_HOOK    = 'bwpn_migrate_perfecty';
	public const BATCH        = 500;

	/**
	 * Registers hooks.
	 */
	public static function register_hooks() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'continue_import' ) );
	}

	/**
	 * Perfecty subscribers table.
	 *
	 * @return string
	 */
	public static function users_table() {
		global $wpdb;
		return $wpdb->prefix . 'perfecty_push_users';
	}

	/**
	 * Whether Perfecty data exists.
	 *
	 * @return bool
	 */
	public static function perfecty_present() {
		global $wpdb;
		$table = self::users_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		return $found || is_array( get_option( 'perfecty_push' ) );
	}

	/**
	 * Import state.
	 *
	 * @return array{status: string, cursor: int, imported: int, skipped: int, finished_at: int}
	 */
	public static function state() {
		$state = get_option( self::OPTION_STATE, array() );
		return array_merge(
			array(
				'status'      => 'none',
				'cursor'      => 0,
				'imported'    => 0,
				'skipped'     => 0,
				'finished_at' => 0,
			),
			is_array( $state ) ? $state : array()
		);
	}

	/**
	 * Saves the import state.
	 *
	 * @param array<string, mixed> $state State.
	 */
	private static function save_state( array $state ) {
		update_option( self::OPTION_STATE, $state, false );
	}

	/**
	 * Whether the automatic import on activation should run.
	 *
	 * @return bool
	 */
	public static function should_run() {
		return 'none' === self::state()['status'] && self::perfecty_present();
	}

	/**
	 * Runs the whole import (keys, settings, subscribers).
	 *
	 * @param bool $overwrite_keys Replace our keys with Perfecty's even if we have some.
	 * @return array{status: string, cursor: int, imported: int, skipped: int, finished_at: int}|WP_Error
	 */
	public static function run( $overwrite_keys = false ) {
		if ( ! self::perfecty_present() ) {
			return new WP_Error( 'bwpn_no_perfecty', __( 'No Perfecty Push data was found.', 'best-web-push-notifications' ) );
		}
		$keys = self::import_keys( $overwrite_keys );
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}
		self::import_settings();
		self::save_state(
			array(
				'status'      => 'running',
				'cursor'      => 0,
				'imported'    => 0,
				'skipped'     => 0,
				'finished_at' => 0,
			)
		);
		BWPN_Lib_Log::info( 'Perfecty Push import started' );
		return self::continue_import();
	}

	/**
	 * Adopts Perfecty's VAPID keys.
	 *
	 * @param bool $overwrite Replace existing keys.
	 * @return true|WP_Error
	 */
	public static function import_keys( $overwrite = false ) {
		$options = get_option( 'perfecty_push', array() );
		$public  = is_array( $options ) ? trim( (string) ( $options['vapid_public_key'] ?? '' ) ) : '';
		$private = is_array( $options ) ? trim( (string) ( $options['vapid_private_key'] ?? '' ) ) : '';
		if ( '' === $public || '' === $private ) {
			return true; // Nothing to adopt; new keys will be generated.
		}
		if ( BWPN_Settings::keys_from_constants() ) {
			if ( BWPN_Settings::public_key() !== $public ) {
				BWPN_Lib_Log::warning( 'Perfecty keys were not adopted: keys are pinned in wp-config.php and differ. Imported subscribers will not receive notifications.' );
			}
			return true;
		}
		if ( BWPN_Settings::has_keys() && ! $overwrite ) {
			if ( BWPN_Settings::public_key() !== $public ) {
				return new WP_Error( 'bwpn_keys_differ', __( 'This site already has different VAPID keys. Imported Perfecty subscribers only work with Perfecty\'s keys; rerun the import with "replace keys" (existing Best Web Push subscribers would then stop working).', 'best-web-push-notifications' ) );
			}
			return true;
		}
		$valid = BWPN_External_Webpush::validate_keys( $public, $private );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		BWPN_Settings::set_keys( $public, $private );
		BWPN_Lib_Log::info( 'Adopted the Perfecty Push VAPID keys' );
		return true;
	}

	/**
	 * Copies compatible settings, including the service worker scope.
	 */
	public static function import_settings() {
		$options = get_option( 'perfecty_push', array() );
		if ( ! is_array( $options ) ) {
			return;
		}
		$map     = array(
			'widget_enabled'                     => 'widget_enabled',
			'visits_to_display_prompt'           => 'prompt_visits',
			'widget_hide_bell_after_subscribe'   => 'hide_bell_after_subscribe',
			'dialog_title'                       => 'dialog_title',
			'dialog_submit'                      => 'dialog_submit',
			'dialog_cancel'                      => 'dialog_cancel',
			'settings_title'                     => 'settings_title',
			'settings_opt_in'                    => 'settings_opt_in',
			'settings_update_error'              => 'settings_update_error',
			'settings_send_welcome_message'      => 'send_welcome_message',
			'settings_welcome_message'           => 'welcome_message',
			'notifications_interaction_required' => 'require_interaction',
			'notifications_default_icon'         => 'default_icon',
			'segmentation_tracking_utm'          => 'utm',
			'batch_size'                         => 'batch_size',
		);
		$updates = array();
		foreach ( $map as $from => $to ) {
			if ( isset( $options[ $from ] ) && '' !== $options[ $from ] ) {
				$updates[ $to ] = is_numeric( $options[ $from ] ) ? (int) $options[ $from ] : sanitize_text_field( (string) $options[ $from ] );
			}
		}
		// Keep Perfecty's scope so our worker replaces its registration in place.
		$scope                            = ! empty( $options['service_worker_scope'] ) ? (string) $options['service_worker_scope'] : '/perfecty/push';
		$updates['service_worker_scope'] = sanitize_text_field( $scope );
		if ( ! empty( $options['server_url'] ) && false === strpos( (string) $options['server_url'], '/perfecty' ) ) {
			$updates['server_url'] = esc_url_raw( (string) $options['server_url'] );
		}
		BWPN_Settings::update( $updates );
	}

	/**
	 * Copies subscribers in batches until done or out of time.
	 *
	 * @param int|null $budget Seconds.
	 * @return array{status: string, cursor: int, imported: int, skipped: int, finished_at: int}
	 */
	public static function continue_import( $budget = null ) {
		global $wpdb;
		$state = self::state();
		if ( 'running' !== $state['status'] ) {
			return $state;
		}
		$start  = time();
		$budget = null === $budget ? BWPN_Lib_Push_Server::time_budget() : (int) $budget;
		$table  = self::users_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;

		while ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off import.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d', $table, (int) $state['cursor'], self::BATCH ) );
			if ( empty( $rows ) ) {
				break;
			}
			foreach ( $rows as $row ) {
				$state['cursor'] = (int) $row->id;
				if ( self::import_row( $row ) ) {
					++$state['imported'];
				} else {
					++$state['skipped'];
				}
			}
			self::save_state( $state );
			if ( time() - $start >= $budget ) {
				wp_schedule_single_event( time(), self::CRON_HOOK );
				return $state;
			}
		}

		$state['status']      = 'done';
		$state['finished_at'] = time();
		self::save_state( $state );
		BWPN_Lib_Log::info( "Perfecty Push import finished: {$state['imported']} imported, {$state['skipped']} skipped" );
		return $state;
	}

	/**
	 * Imports one Perfecty subscriber.
	 *
	 * @param object $row Perfecty row.
	 * @return bool True if a new subscriber was stored.
	 */
	public static function import_row( $row ) {
		if ( isset( $row->is_active ) && ! (int) $row->is_active ) {
			return false;
		}
		// Perfecty never told us the encoding; every browser that can still
		// receive push supports aes128gcm (RFC 8291).
		$valid = BWPN_Subscription_Validator::validate( (string) $row->endpoint, (string) $row->key_p256dh, (string) $row->key_auth, 'aes128gcm' );
		if ( is_wp_error( $valid ) ) {
			return false;
		}
		$created = ! empty( $row->created_at ) ? (string) $row->created_at : '';
		if ( '' !== $created && false !== strtotime( $created ) ) {
			// Perfecty stored MySQL server time; treat it as UTC (close enough for "subscribed on").
			$created = gmdate( 'Y-m-d H:i:s', (int) strtotime( $created . ' UTC' ) );
		} else {
			$created = '';
		}
		$uuid = ( isset( $row->uuid ) && wp_is_uuid( (string) $row->uuid ) && ! BWPN_Lib_Db::get_subscriber_by_uuid( (string) $row->uuid ) ) ? (string) $row->uuid : '';
		$data = array_merge(
			$valid,
			array(
				'uuid'       => $uuid,
				'wp_user_id' => ! empty( $row->wp_user_id ) ? (int) $row->wp_user_id : 0,
				'source'     => 'perfecty',
				'created_at' => $created,
			)
		);
		return false !== BWPN_Lib_Db::create_subscriber( $data );
	}
}

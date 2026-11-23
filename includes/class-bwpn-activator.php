<?php
/**
 * Activation routine.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation.
 */
class BWPN_Activator {

	/**
	 * Creates tables, imports Perfecty data when present, makes sure VAPID
	 * keys exist and schedules the background events.
	 *
	 * The Perfecty import runs before key generation so that an existing
	 * Perfecty key pair (and every subscriber bound to it) is adopted rather
	 * than replaced by a fresh pair.
	 */
	public static function activate() {
		BWPN_Lib_Db::db_create();

		$fresh_install = ! BWPN_Settings::exists();
		if ( $fresh_install ) {
			add_option( BWPN_Settings::OPTION, BWPN_Settings::defaults(), '', true );
		}

		if ( BWPN_Migrator::should_run() ) {
			$result = BWPN_Migrator::run();
			if ( is_wp_error( $result ) ) {
				if ( 'bwpn_keys_differ' === $result->get_error_code() ) {
					BWPN_Notices::add( 'migration_key_conflict' );
				}
				BWPN_Lib_Log::warning( 'Perfecty import on activation: ' . $result->get_error_message() );
			} else {
				BWPN_Notices::add( 'migration_done', array( 'imported' => $result['imported'] ) );
			}
		}

		self::ensure_keys();

		update_option( 'bwpn_version', BWPN_VERSION, true );
		BWPN_Cron::schedule_recurring();
	}

	/**
	 * Generates a VAPID key pair if none exists.
	 *
	 * @return bool True if keys exist after the call.
	 */
	public static function ensure_keys() {
		if ( BWPN_Settings::has_keys() ) {
			return true;
		}
		try {
			$keys = BWPN_External_Webpush::create_vapid_keys();
		} catch ( Throwable $e ) {
			BWPN_Lib_Log::error( 'Could not generate the VAPID keys: ' . $e->getMessage() );
			BWPN_Notices::add( 'keys_generation_failed' );
			return false;
		}
		BWPN_Lib_Log::info( 'VAPID keys were created' );
		return BWPN_Settings::set_keys( $keys['publicKey'], $keys['privateKey'] );
	}
}

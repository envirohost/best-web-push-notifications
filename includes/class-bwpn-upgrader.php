<?php
/**
 * Database and option upgrades.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the schema current. Activation hooks do not run on plugin updates or
 * on network-activated multisite subsites, so this runs on every load and
 * exits after two cheap option reads when nothing changed.
 */
class BWPN_Upgrader {

	/**
	 * Upgrades the schema and options when the stored versions differ.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'bwpn_db_version', 0 ) !== BWPN_DB_VERSION ) {
			BWPN_Lib_Db::db_create();
		}

		if ( get_option( 'bwpn_version' ) !== BWPN_VERSION ) {
			if ( ! BWPN_Settings::exists() ) {
				add_option( BWPN_Settings::OPTION, BWPN_Settings::defaults(), '', true );
			}
			update_option( 'bwpn_version', BWPN_VERSION, true );
			BWPN_Cron::schedule_recurring();
		}
	}
}

<?php
/**
 * Deactivation routine.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin deactivation. Data is kept: subscribers and keys survive a
 * deactivate/activate cycle. Pending jobs are rescheduled by the watchdog
 * after reactivation.
 */
class BWPN_Deactivator {

	/**
	 * Clears scheduled events and transient notices.
	 */
	public static function deactivate() {
		BWPN_Cron::clear_all();
		BWPN_Notices::clear();
	}
}

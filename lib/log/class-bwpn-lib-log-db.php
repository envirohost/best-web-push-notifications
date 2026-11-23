<?php
/**
 * Database log writer.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes logs to the plugin's log table (viewable under Logs). Old entries
 * are removed by the daily maintenance event, not on every request.
 */
class BWPN_Lib_Log_Db implements BWPN_Lib_Log_Writer {

	/**
	 * Writes one line.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @return bool
	 */
	public function write( $level, $message ) {
		return BWPN_Lib_Db::insert_log( $level, $message );
	}
}

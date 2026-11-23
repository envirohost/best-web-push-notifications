<?php

/***
 * Log writer that uses the DB
 */
class BWPN_Lib_Log_Db implements BWPN_Lib_Log_Writer {

	/**
	 * Insert a log entry in the DB
	 *
	 * @param string $level Level code.
	 * @param string $message Message to log.
	 */
	public function write( $level, $message ) {
		return BWPN_Lib_Db::insert_log( $level, $message );
	}

	/**
	 * Delete the logs older than the number of days
	 *
	 * @param int $days Number of days
	 */
	public function delete_old_logs( $days ) {
		BWPN_Lib_Db::delete_old_logs( $days );
	}
}

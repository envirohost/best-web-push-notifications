<?php
/**
 * PHP error_log writer.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes logs with error_log().
 */
class BWPN_Lib_Log_ErrorLog implements BWPN_Lib_Log_Writer {

	/**
	 * Writes one line.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @return bool
	 */
	public function write( $level, $message ) {
		return error_log( '[best-web-push-notifications] ' . strtoupper( $level ) . ' | ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- this is the error_log driver.
	}
}

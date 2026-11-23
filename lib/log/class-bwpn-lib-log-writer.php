<?php
/**
 * Log writer interface.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A destination for log lines.
 */
interface BWPN_Lib_Log_Writer {

	/**
	 * Writes one line.
	 *
	 * @param string $level   Level name.
	 * @param string $message Message.
	 * @return mixed
	 */
	public function write( $level, $message );
}

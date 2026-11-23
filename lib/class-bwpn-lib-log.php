<?php
/**
 * Logger.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal levelled logger with pluggable writers (PHP error_log or the
 * plugin's log table).
 */
class BWPN_Lib_Log {

	public const DEBUG   = 0;
	public const INFO    = 1;
	public const WARNING = 2;
	public const ERROR   = 3;

	/**
	 * Active writer.
	 *
	 * @var BWPN_Lib_Log_Writer|null
	 */
	private static $writer = null;

	/**
	 * Minimum level written.
	 *
	 * @var int
	 */
	private static $level = self::ERROR;

	/**
	 * Configures the logger from the settings.
	 */
	public static function bootstrap() {
		$writer = 'db' === BWPN_Settings::str( 'log_driver' ) ? new BWPN_Lib_Log_Db() : new BWPN_Lib_Log_ErrorLog();
		self::init( $writer, self::string_to_level( BWPN_Settings::str( 'log_level' ) ) );
	}

	/**
	 * Sets the writer and level.
	 *
	 * @param BWPN_Lib_Log_Writer $writer Writer.
	 * @param int                 $level  Minimum level.
	 */
	public static function init( BWPN_Lib_Log_Writer $writer, $level = self::ERROR ) {
		self::$writer = $writer;
		self::$level  = (int) $level;
	}

	/**
	 * Logs a debug message.
	 *
	 * @param string $message Message.
	 */
	public static function debug( $message ) {
		self::write( self::DEBUG, $message );
	}

	/**
	 * Logs an info message.
	 *
	 * @param string $message Message.
	 */
	public static function info( $message ) {
		self::write( self::INFO, $message );
	}

	/**
	 * Logs a warning.
	 *
	 * @param string $message Message.
	 */
	public static function warning( $message ) {
		self::write( self::WARNING, $message );
	}

	/**
	 * Logs an error.
	 *
	 * @param string $message Message.
	 */
	public static function error( $message ) {
		self::write( self::ERROR, $message );
	}

	/**
	 * Writes a message if the level is enabled.
	 *
	 * @param int    $level   Level.
	 * @param string $message Message.
	 */
	private static function write( $level, $message ) {
		if ( null === self::$writer || $level < self::$level ) {
			return;
		}
		self::$writer->write( self::level_to_string( $level ), (string) $message );
	}

	/**
	 * Level code to string.
	 *
	 * @param int $level Level.
	 * @return string
	 */
	public static function level_to_string( $level ) {
		$map = array(
			self::DEBUG   => 'debug',
			self::INFO    => 'info',
			self::WARNING => 'warning',
			self::ERROR   => 'error',
		);
		return $map[ $level ] ?? 'error';
	}

	/**
	 * String to level code.
	 *
	 * @param string $level_string Level name.
	 * @return int
	 */
	public static function string_to_level( $level_string ) {
		$map = array(
			'debug'   => self::DEBUG,
			'info'    => self::INFO,
			'warning' => self::WARNING,
			'error'   => self::ERROR,
		);
		return $map[ $level_string ] ?? self::ERROR;
	}
}

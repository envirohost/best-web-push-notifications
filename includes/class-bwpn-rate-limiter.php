<?php
/**
 * Fixed-window rate limiter.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small rate limiter on transients (or the object cache when present).
 * Keys are salted hashes, so no IP address is written to the database.
 */
class BWPN_Rate_Limiter {

	/**
	 * Registers a hit and reports whether it is within the limit.
	 *
	 * @param string $bucket  Bucket name, e.g. "subscribe".
	 * @param string $subject What is limited (IP, or "global").
	 * @param int    $limit   Allowed hits per window. 0 disables the limit.
	 * @param int    $window  Window length in seconds.
	 * @return bool True if allowed.
	 */
	public static function hit( $bucket, $subject, $limit, $window ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			return true;
		}
		$window = max( 1, (int) $window );
		$slot   = (int) floor( time() / $window );
		$key    = 'bwpn_rl_' . substr( hash_hmac( 'sha256', $bucket . '|' . $subject . '|' . $slot, wp_salt( 'nonce' ) ), 0, 32 );
		$count  = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Seconds until the current window ends.
	 *
	 * @param int $window Window length in seconds.
	 * @return int
	 */
	public static function retry_after( $window ) {
		$window = max( 1, (int) $window );
		return $window - ( time() % $window );
	}
}

<?php
/**
 * Client IP detection for rate limiting.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the visitor IP without trusting spoofable headers.
 *
 * Behind Cloudflare every request arrives from a Cloudflare edge IP, so a
 * naive per-IP limit would throttle unrelated visitors together. The
 * CF-Connecting-IP header is only honoured when the connection really comes
 * from a Cloudflare range. IPs are never stored; the rate limiter only keeps
 * a salted hash for a few minutes.
 */
class BWPN_Client_IP {

	/**
	 * Cloudflare ranges from https://www.cloudflare.com/ips/ (checked 2026-11-23).
	 *
	 * @var string[]
	 */
	private const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Returns the best-effort client IP.
	 *
	 * @return string
	 */
	public static function get() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip     = $remote;
		$mode   = BWPN_Settings::str( 'trusted_proxy' );

		if ( 'cloudflare' === $mode && isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && self::in_ranges( $remote, self::cloudflare_ranges() ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( 'x-forwarded-for' === $mode && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}

		/**
		 * Filters the client IP used for rate limiting.
		 *
		 * @param string $ip     Resolved IP.
		 * @param string $remote REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'bwpn_client_ip', $ip, $remote );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : $remote;
	}

	/**
	 * Cloudflare ranges, filterable.
	 *
	 * @return string[]
	 */
	public static function cloudflare_ranges() {
		return (array) apply_filters( 'bwpn_cloudflare_ranges', self::CLOUDFLARE_RANGES );
	}

	/**
	 * Whether an IP is inside any CIDR range.
	 *
	 * @param string   $ip     IP address.
	 * @param string[] $ranges CIDR ranges.
	 * @return bool
	 */
	public static function in_ranges( $ip, array $ranges ) {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
		if ( false === $packed ) {
			return false;
		}
		foreach ( $ranges as $range ) {
			$parts = explode( '/', (string) $range, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$subnet = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$bits   = (int) $parts[1];
			if ( false === $subnet || strlen( $subnet ) !== strlen( $packed ) ) {
				continue;
			}
			$bytes = intdiv( $bits, 8 );
			$rem   = $bits % 8;
			if ( substr( $packed, 0, $bytes ) !== substr( $subnet, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $rem ) {
				return true;
			}
			$mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
			if ( ( $packed[ $bytes ] & $mask ) === ( $subnet[ $bytes ] & $mask ) ) {
				return true;
			}
		}
		return false;
	}
}

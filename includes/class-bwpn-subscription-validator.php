<?php
/**
 * Validation of push subscriptions.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates a browser push subscription before it is stored.
 *
 * Used by the subscribe endpoint, the importer and the Perfecty migrator.
 * Endpoints must be https URLs on a known push service: the server later
 * POSTs to every stored endpoint, so accepting arbitrary URLs would let
 * anyone make the site send requests to hosts of their choosing (SSRF).
 */
class BWPN_Subscription_Validator {

	public const MAX_ENDPOINT_LENGTH = 2048;
	public const ENCODINGS           = array( 'aes128gcm', 'aesgcm' );

	/**
	 * Push service hosts accepted by default.
	 *
	 * @return string[]
	 */
	public static function allowed_hosts() {
		$hosts = array(
			'fcm.googleapis.com',            // Chrome, Edge on Android/macOS, Opera, Samsung Internet, Brave.
			'android.googleapis.com',        // Legacy GCM endpoints still present in old databases.
			'updates.push.services.mozilla.com', // Firefox.
			'*.push.services.mozilla.com',
			'*.notify.windows.com',          // Edge on Windows (WNS).
			'web.push.apple.com',            // Safari on macOS, iOS and iPadOS.
			'*.push.apple.com',
		);

		/**
		 * Filters the push service hosts that subscriptions may point to.
		 * Entries may start with "*." to match any subdomain.
		 *
		 * @param string[] $hosts Allowed hosts.
		 */
		return (array) apply_filters( 'bwpn_allowed_push_hosts', $hosts );
	}

	/**
	 * Validates and normalises a subscription.
	 *
	 * @param mixed $endpoint Endpoint URL.
	 * @param mixed $p256dh   Public key (base64url).
	 * @param mixed $auth     Auth secret (base64url).
	 * @param mixed $encoding Content encoding.
	 * @return array{endpoint: string, p256dh: string, auth: string, content_encoding: string}|WP_Error
	 */
	public static function validate( $endpoint, $p256dh, $auth, $encoding = 'aes128gcm' ) {
		$endpoint = self::validate_endpoint( $endpoint );
		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}
		$p256dh = is_string( $p256dh ) ? trim( $p256dh ) : '';
		$auth   = is_string( $auth ) ? trim( $auth ) : '';

		$decoded_key = self::base64url_decode( $p256dh );
		if ( null === $decoded_key || 65 !== strlen( $decoded_key ) || "\x04" !== $decoded_key[0] ) {
			return new WP_Error( 'bwpn_invalid_p256dh', 'Invalid p256dh key' );
		}
		$decoded_auth = self::base64url_decode( $auth );
		if ( null === $decoded_auth || 16 !== strlen( $decoded_auth ) ) {
			return new WP_Error( 'bwpn_invalid_auth', 'Invalid auth secret' );
		}

		return array(
			'endpoint'         => $endpoint,
			'p256dh'           => self::base64url_encode( $decoded_key ),
			'auth'             => self::base64url_encode( $decoded_auth ),
			'content_encoding' => self::encoding( $encoding ),
		);
	}

	/**
	 * Validates an endpoint URL.
	 *
	 * @param mixed $endpoint Endpoint.
	 * @return string|WP_Error
	 */
	public static function validate_endpoint( $endpoint ) {
		if ( ! is_string( $endpoint ) || '' === $endpoint ) {
			return new WP_Error( 'bwpn_missing_endpoint', 'No endpoint was provided' );
		}
		$endpoint = trim( $endpoint );
		if ( strlen( $endpoint ) > self::MAX_ENDPOINT_LENGTH ) {
			return new WP_Error( 'bwpn_invalid_endpoint', 'The endpoint is too long' );
		}
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'bwpn_invalid_endpoint', 'The endpoint must be an https URL' );
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] && ! self::port_allowed( (int) $parts['port'] ) ) {
			return new WP_Error( 'bwpn_invalid_endpoint', 'The endpoint port is not allowed' );
		}
		if ( ! self::host_allowed( strtolower( $parts['host'] ) ) ) {
			return new WP_Error( 'bwpn_unknown_push_service', 'The endpoint is not on a known push service' );
		}
		return $endpoint;
	}

	/**
	 * Whether a host matches the allowlist.
	 *
	 * @param string $host Lowercase host.
	 * @return bool
	 */
	public static function host_allowed( $host ) {
		foreach ( self::allowed_hosts() as $allowed ) {
			$allowed = strtolower( (string) $allowed );
			if ( '*' === $allowed ) {
				return true;
			}
			if ( 0 === strpos( $allowed, '*.' ) ) {
				$suffix = substr( $allowed, 1 );
				if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			} elseif ( $host === $allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Non-443 ports are only allowed through a filter (tests, private push servers).
	 *
	 * @param int $port Port.
	 * @return bool
	 */
	private static function port_allowed( $port ) {
		return (bool) apply_filters( 'bwpn_allow_push_port', false, $port );
	}

	/**
	 * Normalises a content encoding.
	 *
	 * @param mixed $encoding Encoding.
	 * @return string
	 */
	public static function encoding( $encoding ) {
		return in_array( $encoding, self::ENCODINGS, true ) ? (string) $encoding : 'aes128gcm';
	}

	/**
	 * Decodes base64url (or standard base64) strictly.
	 *
	 * @param string $value Encoded value.
	 * @return string|null
	 */
	public static function base64url_decode( $value ) {
		if ( '' === $value || ! preg_match( '/^[A-Za-z0-9_\-+\/]+={0,2}$/', $value ) ) {
			return null;
		}
		$value   = strtr( rtrim( $value, '=' ), '-_', '+/' );
		$decoded = base64_decode( $value . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding Web Push keys.
		return false === $decoded ? null : $decoded;
	}

	/**
	 * Encodes base64url without padding.
	 *
	 * @param string $value Raw bytes.
	 * @return string
	 */
	public static function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding Web Push keys.
	}

	/**
	 * Hash used for the unique endpoint index.
	 *
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	public static function endpoint_hash( $endpoint ) {
		return hash( 'sha256', $endpoint );
	}

	/**
	 * Short label of the push service behind an endpoint, for stats.
	 *
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	public static function service_label( $endpoint ) {
		$host = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) );
		if ( str_ends_with( $host, 'googleapis.com' ) ) {
			return 'Chrome / Android (FCM)';
		}
		if ( str_ends_with( $host, 'mozilla.com' ) ) {
			return 'Firefox';
		}
		if ( str_ends_with( $host, 'notify.windows.com' ) ) {
			return 'Edge (Windows)';
		}
		if ( str_ends_with( $host, 'apple.com' ) ) {
			return 'Safari (Apple)';
		}
		return $host;
	}
}

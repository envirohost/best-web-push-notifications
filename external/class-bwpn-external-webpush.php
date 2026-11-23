<?php
/**
 * Wrapper around the bundled minishlink/web-push library.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the namespace-prefixed vendor libraries on demand and exposes the
 * few web-push features the plugin uses.
 *
 * Vendor code is prefixed with BWPN\Vendor\ (Strauss) so this plugin can run
 * next to Perfecty Push or any other plugin that bundles a different
 * version of web-push, Guzzle or the PSR interfaces.
 */
class BWPN_External_Webpush {

	/**
	 * Whether the autoloader ran.
	 *
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * Loads the vendor libraries and the HTTP adapter.
	 *
	 * @throws RuntimeException When the vendor folder is missing.
	 */
	public static function autoload() {
		if ( self::$loaded ) {
			return;
		}
		$autoload = BWPN_PATH . 'vendor-prefixed/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			throw new RuntimeException( 'vendor-prefixed/autoload.php is missing. Install a release zip, or run "composer install" in the plugin folder.' );
		}
		require_once $autoload;
		require_once BWPN_PATH . 'external/class-bwpn-http.php';
		self::$loaded = true;
	}

	/**
	 * Creates a WebPush instance that sends through WordPress' HTTP stack.
	 *
	 * @param array<string, mixed> $vapid    subject, publicKey, privateKey.
	 * @param array<string, mixed> $defaults TTL, urgency, topic, batchSize.
	 * @return \BWPN\Vendor\Minishlink\WebPush\WebPush
	 * @throws ErrorException When the VAPID keys are invalid.
	 */
	public static function create( array $vapid, array $defaults = array() ) {
		self::autoload();
		$factory      = new \BWPN\Vendor\Nyholm\Psr7\Factory\Psr17Factory();
		$concurrency  = max( 1, BWPN_Settings::int( 'parallel_requests' ) );
		$async_client = new BWPN_Http_Async_Client( $factory, $concurrency );
		$client       = new BWPN_Http_Client( $factory );
		$webpush      = new \BWPN\Vendor\Minishlink\WebPush\WebPush(
			array( 'VAPID' => $vapid ),
			$defaults,
			$client,
			$factory,
			$factory,
			$async_client
		);
		$webpush->setReuseVAPIDHeaders( true );
		return $webpush;
	}

	/**
	 * Creates a subscription object.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $p256dh   Public key.
	 * @param string $auth     Auth secret.
	 * @param string $encoding aes128gcm or aesgcm.
	 * @return \BWPN\Vendor\Minishlink\WebPush\Subscription
	 * @throws ErrorException When the subscription is invalid.
	 */
	public static function subscription( $endpoint, $p256dh, $auth, $encoding = 'aes128gcm' ) {
		self::autoload();
		return new \BWPN\Vendor\Minishlink\WebPush\Subscription( $endpoint, $p256dh, $auth, BWPN_Subscription_Validator::encoding( $encoding ) );
	}

	/**
	 * Generates a VAPID key pair.
	 *
	 * @return array{publicKey: string, privateKey: string}
	 * @throws ErrorException When key generation fails.
	 */
	public static function create_vapid_keys() {
		self::autoload();
		return \BWPN\Vendor\Minishlink\WebPush\VAPID::createVapidKeys();
	}

	/**
	 * Checks that a key pair is valid and belongs together.
	 *
	 * @param string $public_key  Public key.
	 * @param string $private_key Private key.
	 * @return true|WP_Error
	 */
	public static function validate_keys( $public_key, $private_key ) {
		try {
			self::autoload();
			\BWPN\Vendor\Minishlink\WebPush\VAPID::validate(
				array(
					'subject'    => 'mailto:test@example.com',
					'publicKey'  => (string) $public_key,
					'privateKey' => (string) $private_key,
				)
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'bwpn_invalid_keys', $e->getMessage() );
		}

		// The library checks lengths; also check the public key matches the private key.
		$private = BWPN_Subscription_Validator::base64url_decode( (string) $private_key );
		$public  = BWPN_Subscription_Validator::base64url_decode( (string) $public_key );
		if ( null === $private || null === $public ) {
			return new WP_Error( 'bwpn_invalid_keys', 'The keys are not valid base64url' );
		}
		$derived = self::public_key_from_private( $private );
		if ( null !== $derived && ! hash_equals( $derived, $public ) ) {
			return new WP_Error( 'bwpn_mismatched_keys', 'The public key does not belong to the private key' );
		}
		return true;
	}

	/**
	 * Derives the uncompressed P-256 public key from a raw private key, using
	 * openssl. Returns null when openssl cannot do it.
	 *
	 * @param string $private_raw 32-byte private key.
	 * @return string|null 65-byte public key.
	 */
	private static function public_key_from_private( $private_raw ) {
		if ( 32 !== strlen( $private_raw ) || ! function_exists( 'openssl_pkey_get_private' ) ) {
			return null;
		}
		// DER for an EC private key on prime256v1 without the public key (RFC 5915).
		$der = "\x30\x31\x02\x01\x01\x04\x20" . $private_raw . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
		$pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END EC PRIVATE KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM encoding.
		$key = openssl_pkey_get_private( $pem );
		if ( false === $key ) {
			return null;
		}
		$details = openssl_pkey_get_details( $key );
		if ( ! is_array( $details ) || empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) ) {
			return null;
		}
		return "\x04" . str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
	}
}

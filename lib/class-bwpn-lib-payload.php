<?php
/**
 * Notification payload builder.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the JSON payload sent to browsers.
 *
 * The shape is kept compatible with Perfecty Push service workers
 * ({title, body, icon, image, require_interaction, extra.url_to_open}) so
 * migrated subscribers whose browser still runs Perfecty's worker keep
 * receiving readable notifications.
 */
class BWPN_Lib_Payload {

	/**
	 * Push services accept 4078 bytes of plaintext, and web-push pads
	 * payloads to 2820 bytes for compatibility with Firefox for Android.
	 */
	public const MAX_BYTES = 2820;

	public const MAX_TITLE_CHARS = 120;
	public const MAX_BODY_CHARS  = 500;

	/**
	 * Builds a payload array.
	 *
	 * @param string $body        Message.
	 * @param string $title       Title (defaults to the site name).
	 * @param string $image       Large image URL.
	 * @param string $url_to_open URL opened on click (defaults to home).
	 * @return array<string, mixed>
	 */
	public static function build( $body, $title = '', $image = '', $url_to_open = '' ) {
		$title = self::clean_text( (string) $title );
		if ( '' === $title ) {
			$title = self::clean_text( (string) get_bloginfo( 'name' ) );
		}
		$url_to_open = esc_url_raw( trim( (string) $url_to_open ) );
		if ( '' === $url_to_open ) {
			$url_to_open = home_url( '/' );
		}
		$utm = trim( BWPN_Settings::str( 'utm' ) );
		if ( '' !== $utm ) {
			wp_parse_str( ltrim( $utm, '?&' ), $utm_args );
			if ( ! empty( $utm_args ) ) {
				$url_to_open = add_query_arg( array_map( 'rawurlencode', $utm_args ), $url_to_open );
			}
		}

		$icon_id  = BWPN_Settings::int( 'default_icon' );
		$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, array( 192, 192 ) ) : '';
		if ( ! $icon_url ) {
			$icon_url = (string) get_site_icon_url( 192 );
		}

		$payload = array(
			'title'               => self::truncate( $title, self::MAX_TITLE_CHARS ),
			'body'                => self::truncate( self::clean_text( (string) $body ), self::MAX_BODY_CHARS ),
			'icon'                => $icon_url ? esc_url_raw( $icon_url ) : '',
			'image'               => $image ? esc_url_raw( (string) $image ) : '',
			'require_interaction' => BWPN_Settings::bool( 'require_interaction' ),
			'extra'               => array(
				'url_to_open' => $url_to_open,
			),
		);

		/**
		 * Filters the notification payload before it is encoded.
		 *
		 * @param array<string, mixed> $payload Payload.
		 */
		return (array) apply_filters( 'bwpn_payload', $payload );
	}

	/**
	 * Encodes a payload, shortening the body if needed to fit the push
	 * service limit. Multibyte text is preserved (upstream cut UTF-8 strings
	 * with substr(), which produced invalid JSON for non-Latin scripts).
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return string|WP_Error
	 */
	public static function encode( array $payload ) {
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
		$json  = wp_json_encode( $payload, $flags );
		if ( false === $json ) {
			return new WP_Error( 'bwpn_payload_encoding', 'The notification could not be encoded as JSON' );
		}
		$body = isset( $payload['body'] ) ? (string) $payload['body'] : '';
		while ( strlen( $json ) > self::MAX_BYTES && mb_strlen( $body ) > 10 ) {
			$excess          = strlen( $json ) - self::MAX_BYTES;
			$body            = self::truncate( $body, max( 10, mb_strlen( $body ) - max( 10, (int) ceil( $excess / 2 ) ) - 1 ) );
			$payload['body'] = $body;
			$json            = (string) wp_json_encode( $payload, $flags );
		}
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'bwpn_payload_too_large', 'The notification is too large. Shorten the title or use shorter URLs.' );
		}
		return $json;
	}

	/**
	 * Strips tags and decodes entities.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
	}

	/**
	 * Multibyte-safe truncation with an ellipsis.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Maximum characters.
	 * @return string
	 */
	public static function truncate( $text, $chars ) {
		if ( mb_strlen( $text ) <= $chars ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, max( 1, $chars - 1 ) ) ) . "\u{2026}";
	}
}

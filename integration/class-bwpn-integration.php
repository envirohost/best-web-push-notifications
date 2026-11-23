<?php
/**
 * Public PHP API for other plugins and themes.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable entry points (same method names as Perfecty Push's
 * Perfecty_Push_Integration, so integrations port by renaming the class).
 *
 *     $push = new BWPN_Integration();
 *     $push->broadcast( 'Sale starts now', 'My Shop', '', 'https://example.com/sale' );
 *     $push->notify( $user_id, 'Your order shipped' );
 */
class BWPN_Integration {

	/**
	 * Sends a notification now to every browser of a WordPress user.
	 *
	 * @param int    $wp_user_id  WordPress user id.
	 * @param string $message     Message.
	 * @param string $title       Title (default: site name).
	 * @param string $image_url   Large image URL.
	 * @param string $url_to_open URL opened on click (default: home).
	 * @return array{ok: int, failed: int, expired: int, auth: int}|WP_Error
	 */
	public function notify( $wp_user_id, $message, $title = '', $image_url = '', $url_to_open = '' ) {
		$payload = BWPN_Lib_Payload::build( $message, $title, $image_url, $url_to_open );
		return BWPN_Lib_Push_Server::notify_user( (int) $wp_user_id, $payload );
	}

	/**
	 * Queues a notification to every subscriber.
	 *
	 * @param string   $message     Message.
	 * @param string   $title       Title (default: site name).
	 * @param string   $image_url   Large image URL.
	 * @param string   $url_to_open URL opened on click (default: home).
	 * @param int|null $timestamp   When to send (Unix time), null for now.
	 * @return int|WP_Error Job id.
	 */
	public function broadcast( $message, $title = '', $image_url = '', $url_to_open = '', $timestamp = null ) {
		$payload = BWPN_Lib_Payload::build( $message, $title, $image_url, $url_to_open );
		return BWPN_Lib_Push_Server::schedule_broadcast( $payload, $timestamp, array( 'source' => 'api' ) );
	}
}

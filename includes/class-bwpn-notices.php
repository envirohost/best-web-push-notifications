<?php
/**
 * Deferred admin notices.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores notice codes (not translated text) so notices can be queued from
 * any point of the request, including before `init`, without loading
 * translations early. Text is resolved when the notice is displayed.
 */
class BWPN_Notices {

	public const TRANSIENT = 'bwpn_admin_notices';

	/**
	 * Queues a notice.
	 *
	 * @param string               $code Notice code.
	 * @param array<string, mixed> $args Values for the message.
	 */
	public static function add( $code, array $args = array() ) {
		$notices = get_transient( self::TRANSIENT );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$notices[ $code ] = $args;
		set_transient( self::TRANSIENT, $notices, HOUR_IN_SECONDS );
	}

	/**
	 * Removes all queued notices.
	 */
	public static function clear() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Prints queued notices for administrators and clears them.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notices = get_transient( self::TRANSIENT );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}
		self::clear();
		foreach ( $notices as $code => $args ) {
			$notice = self::message( (string) $code, is_array( $args ) ? $args : array() );
			if ( null === $notice ) {
				continue;
			}
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html__( 'Best Web Push Notifications:', 'best-web-push-notifications' ),
				wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) )
			);
		}
	}

	/**
	 * Resolves a notice code into a translated message.
	 *
	 * @param string               $code Notice code.
	 * @param array<string, mixed> $args Values.
	 * @return array{type: string, message: string}|null
	 */
	public static function message( $code, array $args ) {
		$tools = esc_url( admin_url( 'admin.php?page=bwpn-tools' ) );
		switch ( $code ) {
			case 'keys_generation_failed':
				return array(
					'type'    => 'error',
					'message' => esc_html__( 'The VAPID keys could not be generated. Check that the PHP openssl extension is enabled, then deactivate and reactivate the plugin.', 'best-web-push-notifications' ),
				);
			case 'post_sent':
				return array(
					'type'    => 'success',
					/* translators: %s: post title. */
					'message' => esc_html( sprintf( __( 'A notification was queued for "%s".', 'best-web-push-notifications' ), (string) ( $args['title'] ?? '' ) ) ),
				);
			case 'post_send_failed':
				return array(
					'type'    => 'error',
					'message' => esc_html__( 'The notification for this post could not be queued. Check the plugin logs.', 'best-web-push-notifications' ),
				);
			case 'migration_done':
				return array(
					'type'    => 'success',
					'message' => sprintf(
						/* translators: 1: number of subscribers, 2: opening link tag, 3: closing link tag. */
						esc_html__( 'Imported %1$s subscribers, the VAPID keys and the settings from Perfecty Push. Deactivate Perfecty Push so subscribers do not get duplicate notifications, and keep its files for a few weeks. %2$sSee the migration notes%3$s.', 'best-web-push-notifications' ),
						esc_html( number_format_i18n( (int) ( $args['imported'] ?? 0 ) ) ),
						'<a href="' . $tools . '">',
						'</a>'
					),
				);
			case 'migration_key_conflict':
				return array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: 1: opening link tag, 2: closing link tag. */
						esc_html__( 'Perfecty Push data was found, but this site already has different VAPID keys, so Perfecty subscribers were not imported (they only work with Perfecty\'s keys). %1$sReview the options on the Tools page%2$s.', 'best-web-push-notifications' ),
						'<a href="' . $tools . '">',
						'</a>'
					),
				);
		}
		return null;
	}
}

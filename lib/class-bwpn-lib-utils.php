<?php
/**
 * Status and health helpers.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health checks shown on the dashboard and by `wp bwpn status`.
 */
class BWPN_Lib_Utils {

	/**
	 * Whether sending is switched off (wp-config BWPN_DISABLED or no keys).
	 *
	 * @return bool
	 */
	public static function is_disabled() {
		if ( defined( 'BWPN_DISABLED' ) && BWPN_DISABLED ) {
			return true;
		}
		return ! BWPN_Settings::has_keys();
	}

	/**
	 * Runs the health checks.
	 *
	 * @return array<string, array{ok: bool, label: string, detail: string}>
	 */
	public static function health_checks() {
		$checks = array();

		$checks['keys'] = array(
			'ok'     => BWPN_Settings::has_keys(),
			'label'  => __( 'VAPID keys', 'best-web-push-notifications' ),
			'detail' => BWPN_Settings::has_keys()
				? ( BWPN_Settings::keys_from_constants() ? __( 'Present (defined in wp-config.php).', 'best-web-push-notifications' ) : __( 'Present. Keep a backup: Tools > Backup.', 'best-web-push-notifications' ) )
				: __( 'Missing. Deactivate and reactivate the plugin, or import a backup.', 'best-web-push-notifications' ),
		);

		$https           = 0 === strpos( home_url( '/' ), 'https://' );
		$checks['https'] = array(
			'ok'     => $https,
			'label'  => __( 'HTTPS', 'best-web-push-notifications' ),
			'detail' => $https ? __( 'The site uses HTTPS.', 'best-web-push-notifications' ) : __( 'Browsers only allow push notifications on HTTPS sites.', 'best-web-push-notifications' ),
		);

		$missing = array();
		foreach ( array( 'openssl', 'mbstring', 'json' ) as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = $ext;
			}
		}
		$checks['php'] = array(
			'ok'     => empty( $missing ),
			'label'  => __( 'PHP extensions', 'best-web-push-notifications' ),
			/* translators: %s: comma separated list of PHP extensions. */
			'detail' => empty( $missing ) ? sprintf( 'PHP %s', PHP_VERSION ) : sprintf( __( 'Missing: %s', 'best-web-push-notifications' ), implode( ', ', $missing ) ),
		);

		$cron           = BWPN_Cron::health();
		$checks['cron'] = array(
			'ok'     => $cron['ok'],
			'label'  => __( 'Background sending (WP-Cron)', 'best-web-push-notifications' ),
			'detail' => $cron['ok']
				? __( 'Running.', 'best-web-push-notifications' )
				: ( $cron['disabled']
					? __( 'DISABLE_WP_CRON is set, but no system cron seems to call wp-cron.php. Scheduled notifications will not go out. See docs/cloudflare.md.', 'best-web-push-notifications' )
					: __( 'WP-Cron has not run recently. With full-page caching (Cloudflare, WP Rocket) visits may never reach WordPress; add a system cron job. See docs/cloudflare.md.', 'best-web-push-notifications' ) ),
		);

		$checks['tables'] = array(
			'ok'     => BWPN_Lib_Db::has_tables(),
			'label'  => __( 'Database tables', 'best-web-push-notifications' ),
			'detail' => BWPN_Lib_Db::has_tables() ? __( 'Present.', 'best-web-push-notifications' ) : __( 'Missing. Deactivate and reactivate the plugin.', 'best-web-push-notifications' ),
		);

		return $checks;
	}
}

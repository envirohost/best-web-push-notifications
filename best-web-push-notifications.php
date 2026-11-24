<?php
/**
 * Best Web Push Notifications
 *
 * Self-hosted web push notifications for WordPress. Forked from
 * Perfecty Push Notifications by Rowinson Gallego (Perfecty).
 *
 * @package           BWPN
 * @copyright         2020-2025 Rowinson Gallego (Perfecty Push Notifications)
 * @copyright         2026 Best Web Push Notifications contributors
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Best Web Push Notifications
 * Plugin URI:        https://github.com/envirohost/best-web-push-notifications
 * Description:       Self-hosted web push notifications. VAPID keys and subscribers stay on your own server - no SaaS. Forked from Perfecty Push Notifications by Rowinson Gallego (Perfecty).
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            Best Web Push Notifications contributors
 * Author URI:        https://github.com/envirohost/best-web-push-notifications
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       best-web-push-notifications
 * Domain Path:       /languages
 * Update URI:        https://github.com/envirohost/best-web-push-notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BWPN_VERSION', '2.0.0' );
define( 'BWPN_DB_VERSION', 1 );
define( 'BWPN_FILE', __FILE__ );
define( 'BWPN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BWPN_URL', plugin_dir_url( __FILE__ ) );
define( 'BWPN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BWPN_MIN_PHP', '8.2' );

if ( version_compare( PHP_VERSION, BWPN_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Best Web Push Notifications requires PHP %1$s or newer. This site runs PHP %2$s, so the plugin is inactive.', 'best-web-push-notifications' ),
						BWPN_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

require_once BWPN_PATH . 'includes/class-bwpn-plugin.php';

BWPN_Plugin::load_files();

/*
 * The service worker and the web app manifest are answered as early as
 * possible, before other plugins get a chance to redirect the request
 * (language plugins, canonical redirects), because a service worker script
 * that redirects fails to install.
 */
BWPN_Service_Worker::maybe_serve_early();

register_activation_hook( __FILE__, array( 'BWPN_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BWPN_Deactivator', 'deactivate' ) );

BWPN_Plugin::instance()->run();

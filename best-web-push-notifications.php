<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/rwngallego
 * @since             1.0.0
 * @package           BWPN
 *
 * @wordpress-plugin
 * Plugin Name:       Best Web Push Notifications
 * Plugin URI:        https://wordpress.org/plugins/best-web-push-notifications
 * Description:       Self-hosted, Open Source and powerful <strong>Web Push Notifications</strong> plugin to send push notifications <strong>from your own server for free!</strong>
 * Version:           1.6.5
 * Author:            Perfecty
 * Author URI:        https://perfecty.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       best-web-push-notifications
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BWPN_VERSION', '1.6.5' );

/**
 * DB Version of the plugin
 */
define( 'BWPN_DB_VERSION', 6 );

/**
 * The basename of the plugin
 */
define( 'BWPN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during the plugin activation.
 * This action is documented in includes/class-bwpn-activator.php
 */
function activate_bwpn() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bwpn-activator.php';
	BWPN_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bwpn-deactivator.php
 */
function deactivate_bwpn() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bwpn-deactivator.php';
	BWPN_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bwpn' );
register_deactivation_hook( __FILE__, 'deactivate_bwpn' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bwpn.php';

/**
 * We load the dependencies and check for the gmp extension
 */
require plugin_dir_path( __FILE__ ) . 'lib/class-bwpn-lib-utils.php';
if ( version_compare( PHP_VERSION, '7.2.0', '>=' ) ) {
	BWPN_Lib_Utils::check_gmp();
} else {
	error_log( sprintf( 'Wrong PHP version: %s', PHP_VERSION ) );
	BWPN_Lib_Utils::show_message( esc_html( 'Best Web Push requires PHP >= 7.2.0', 'best-web-push-notifications' ), 'error' );
	BWPN_Lib_Utils::disable();
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bwpn() {
	$plugin = new BWPN();
	$plugin->run();
}
run_bwpn();

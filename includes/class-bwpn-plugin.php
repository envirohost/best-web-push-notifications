<?php
/**
 * Core plugin class: loads files and registers hooks.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every component of the plugin to WordPress.
 */
final class BWPN_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BWPN_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return BWPN_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Requires the plugin files. Vendor libraries are loaded lazily, only
	 * when a notification is sent or keys are generated.
	 */
	public static function load_files() {
		$files = array(
			'includes/class-bwpn-settings.php',
			'includes/class-bwpn-activator.php',
			'includes/class-bwpn-deactivator.php',
			'includes/class-bwpn-upgrader.php',
			'includes/class-bwpn-cron.php',
			'includes/class-bwpn-notices.php',
			'includes/class-bwpn-client-ip.php',
			'includes/class-bwpn-rate-limiter.php',
			'includes/class-bwpn-subscription-validator.php',
			'includes/class-bwpn-service-worker.php',
			'includes/class-bwpn-post-publisher.php',
			'includes/class-bwpn-migrator.php',
			'includes/class-bwpn-transfer.php',
			'lib/class-bwpn-lib-log.php',
			'lib/log/class-bwpn-lib-log-writer.php',
			'lib/log/class-bwpn-lib-log-db.php',
			'lib/log/class-bwpn-lib-log-errorlog.php',
			'lib/class-bwpn-lib-db.php',
			'lib/class-bwpn-lib-payload.php',
			'lib/class-bwpn-lib-push-server.php',
			'lib/class-bwpn-lib-utils.php',
			'external/class-bwpn-external-webpush.php',
			'public/class-bwpn-public.php',
			'public/class-bwpn-subscribers-rest.php',
			'integration/class-bwpn-integration.php',
		);
		foreach ( $files as $file ) {
			require_once BWPN_PATH . $file;
		}

		if ( is_admin() ) {
			require_once BWPN_PATH . 'admin/class-bwpn-admin.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once BWPN_PATH . 'includes/class-bwpn-cli.php';
		}
	}

	/**
	 * Registers all hooks.
	 */
	public function run() {
		BWPN_Lib_Log::bootstrap();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( 'BWPN_Upgrader', 'maybe_upgrade' ) );

		BWPN_Cron::register_hooks();
		BWPN_Service_Worker::register_hooks();
		BWPN_Post_Publisher::register_hooks();
		BWPN_Migrator::register_hooks();

		$public = new BWPN_Public();
		$public->register_hooks();

		if ( is_admin() ) {
			$admin = new BWPN_Admin();
			$admin->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'bwpn', 'BWPN_CLI' );
		}
	}

	/**
	 * Loads translations. Runs on `init`, never earlier, so WordPress 6.7+
	 * does not raise the `_load_textdomain_just_in_time` notice.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'best-web-push-notifications', false, dirname( BWPN_BASENAME ) . '/languages/' );
	}
}

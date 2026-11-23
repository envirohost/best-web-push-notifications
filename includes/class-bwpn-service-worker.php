<?php
/**
 * Service worker and web app manifest endpoints.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the service worker at /?bwpn_sw=1 and the manifest at
 * /?bwpn_manifest=1.
 *
 * Both are served from the site root (not the plugin folder) so the service
 * worker can control any scope on the site, with Service-Worker-Allowed set
 * to the configured scope and no-store caching so page caches and CDNs
 * (Cloudflare included) never hand out a stale worker.
 */
class BWPN_Service_Worker {

	public const SW_QUERY       = 'bwpn_sw';
	public const MANIFEST_QUERY = 'bwpn_manifest';

	/**
	 * Registers hooks.
	 */
	public static function register_hooks() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve_parsed' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'print_manifest_link' ), 2 );
	}

	/**
	 * Public URL of the service worker script.
	 *
	 * @return string
	 */
	public static function worker_url() {
		return add_query_arg( self::SW_QUERY, self::version_tag(), home_url( '/' ) );
	}

	/**
	 * Public URL of the manifest.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		return add_query_arg( self::MANIFEST_QUERY, '1', home_url( '/' ) );
	}

	/**
	 * Short tag that changes when the plugin or the worker file changes,
	 * so browsers pick up a new worker after an update.
	 *
	 * @return string
	 */
	public static function version_tag() {
		$file  = self::worker_file();
		$mtime = $file ? (string) filemtime( $file ) : '0';
		return substr( md5( BWPN_VERSION . '|' . $mtime ), 0, 8 );
	}

	/**
	 * Path of the built service worker.
	 *
	 * @return string|null
	 */
	public static function worker_file() {
		$file = BWPN_PATH . 'public/js/bwpn-sw.min.js';
		return is_readable( $file ) ? $file : null;
	}

	/**
	 * Adds the query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::SW_QUERY;
		$vars[] = self::MANIFEST_QUERY;
		return $vars;
	}

	/**
	 * Serves the files straight after the plugin loads, before other plugins
	 * can redirect (a redirected worker script fails to register).
	 */
	public static function maybe_serve_early() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only endpoints.
		if ( isset( $_GET[ self::SW_QUERY ] ) ) {
			add_action( 'plugins_loaded', array( __CLASS__, 'serve_worker' ), PHP_INT_MAX );
		} elseif ( isset( $_GET[ self::MANIFEST_QUERY ] ) ) {
			// The manifest needs translations and the site icon: wait for init.
			add_action( 'init', array( __CLASS__, 'serve_manifest' ), PHP_INT_MAX );
		}
		// phpcs:enable
	}

	/**
	 * Fallback for setups where $_GET is rewritten before plugins load.
	 *
	 * @param WP $wp Request.
	 */
	public static function maybe_serve_parsed( $wp ) {
		if ( isset( $wp->query_vars[ self::SW_QUERY ] ) ) {
			self::serve_worker();
		} elseif ( isset( $wp->query_vars[ self::MANIFEST_QUERY ] ) ) {
			self::serve_manifest();
		}
	}

	/**
	 * Common no-cache headers.
	 *
	 * @param string $type Content type.
	 */
	private static function send_headers( $type ) {
		if ( headers_sent() ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: ' . $type . '; charset=utf-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'CDN-Cache-Control: no-store' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Page cache plugins.
		}
	}

	/**
	 * Outputs the service worker and exits.
	 */
	public static function serve_worker() {
		$file = self::worker_file();
		self::send_headers( 'application/javascript' );
		if ( ! headers_sent() ) {
			header( 'Service-Worker-Allowed: ' . BWPN_Settings::scope() );
		}
		if ( null === $file ) {
			echo "/* Best Web Push Notifications: service worker build missing. */\n";
			exit;
		}
		echo '/* Best Web Push Notifications ' . esc_html( BWPN_VERSION ) . " */\n";
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- static asset.
		exit;
	}

	/**
	 * Manifest data.
	 *
	 * @return array<string, mixed>
	 */
	public static function manifest() {
		$name     = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$manifest = array(
			'name'        => '' !== $name ? $name : home_url( '/' ),
			'short_name'  => mb_substr( '' !== $name ? $name : 'Site', 0, 12 ),
			'start_url'   => home_url( '/' ),
			'scope'       => home_url( '/' ),
			'display'     => 'standalone',
			'theme_color' => '#ffffff',
			'icons'       => array(),
		);
		foreach ( array( 192, 512 ) as $size ) {
			$url = get_site_icon_url( $size );
			if ( $url ) {
				$manifest['icons'][] = array(
					'src'   => $url,
					'sizes' => "{$size}x{$size}",
					'type'  => 'image/png',
				);
			}
		}
		/**
		 * Filters the web app manifest. iOS/iPadOS only allow web push for
		 * sites added to the home screen, which needs a manifest.
		 *
		 * @param array<string, mixed> $manifest Manifest.
		 */
		return (array) apply_filters( 'bwpn_manifest', $manifest );
	}

	/**
	 * Outputs the manifest and exits.
	 */
	public static function serve_manifest() {
		if ( ! BWPN_Settings::bool( 'manifest_enabled' ) ) {
			status_header( 404 );
			exit;
		}
		self::send_headers( 'application/manifest+json' );
		echo wp_json_encode( self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Prints the manifest link unless the theme or another plugin has one.
	 */
	public static function print_manifest_link() {
		if ( ! BWPN_Settings::bool( 'manifest_enabled' ) || ! BWPN_Settings::bool( 'widget_enabled' ) ) {
			return;
		}
		/**
		 * Whether to print the manifest link. Return false when another
		 * plugin (a PWA plugin) already provides one.
		 *
		 * @param bool $print Print.
		 */
		if ( ! apply_filters( 'bwpn_print_manifest_link', true ) ) {
			return;
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( self::manifest_url() ) );
	}
}

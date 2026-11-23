<?php
/**
 * Export and import (backup and restore, moving sites).
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON backup: format version, settings, VAPID keys (optional) and
 * subscribers. CSV: subscribers only (endpoint, p256dh, auth, encoding,
 * uuid, wp_user_id, created_at).
 *
 * Restoring the VAPID keys is what keeps imported subscribers working on a
 * new site: push subscriptions are bound to the public key they were made
 * with. The backup therefore contains a private key and must be treated as
 * a secret.
 */
class BWPN_Transfer {

	public const FORMAT      = 'best-web-push-notifications';
	public const VERSION     = 1;
	public const CSV_COLUMNS = array( 'endpoint', 'p256dh', 'auth', 'content_encoding', 'uuid', 'wp_user_id', 'created_at' );

	/**
	 * Builds the JSON backup.
	 *
	 * @param bool $include_keys Include the VAPID key pair.
	 * @return array<string, mixed>
	 */
	public static function export_data( $include_keys = true ) {
		$data = array(
			'format'      => self::FORMAT,
			'version'     => self::VERSION,
			'plugin'      => BWPN_VERSION,
			'site'        => home_url( '/' ),
			'exported_at' => gmdate( 'c' ),
			'settings'    => BWPN_Settings::all(),
			'keys'        => null,
			'subscribers' => array(),
		);
		if ( $include_keys && BWPN_Settings::has_keys() ) {
			$data['keys'] = array(
				'public'  => BWPN_Settings::public_key(),
				'private' => BWPN_Settings::private_key(),
			);
		}
		foreach ( self::iterate_subscribers() as $row ) {
			$data['subscribers'][] = self::row_to_export( $row );
		}
		return $data;
	}

	/**
	 * Yields every subscriber, in id order, without loading all at once.
	 *
	 * @return Generator<object>
	 */
	public static function iterate_subscribers() {
		global $wpdb;
		$after = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- streamed export.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT 1000', BWPN_Lib_Db::subscribers_table(), $after ) );
			foreach ( $rows as $row ) {
				$after = (int) $row->id;
				yield $row;
			}
		} while ( count( $rows ) === 1000 );
	}

	/**
	 * Export shape of a subscriber.
	 *
	 * @param object $row Row.
	 * @return array<string, mixed>
	 */
	private static function row_to_export( $row ) {
		return array(
			'endpoint'         => (string) $row->endpoint,
			'p256dh'           => (string) $row->key_p256dh,
			'auth'             => (string) $row->key_auth,
			'content_encoding' => (string) $row->content_encoding,
			'uuid'             => (string) $row->uuid,
			'wp_user_id'       => $row->wp_user_id ? (int) $row->wp_user_id : null,
			'created_at'       => (string) $row->created_at,
		);
	}

	/**
	 * JSON string of the backup.
	 *
	 * @param bool $include_keys Include keys.
	 * @return string
	 */
	public static function export_json( $include_keys = true ) {
		return (string) wp_json_encode( self::export_data( $include_keys ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Writes subscribers as CSV to a stream.
	 *
	 * @param resource $handle Writable stream.
	 */
	public static function export_csv( $handle ) {
		fputcsv( $handle, self::CSV_COLUMNS, ',', '"', '' );
		foreach ( self::iterate_subscribers() as $row ) {
			$line = self::row_to_export( $row );
			$line = array_map(
				static function ( $value ) {
					$value = (string) $value;
					// Neutralise spreadsheet formulas.
					return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
				},
				$line
			);
			fputcsv( $handle, array_values( $line ), ',', '"', '' );
		}
	}

	/**
	 * Imports a JSON backup.
	 *
	 * @param string $json            File contents.
	 * @param array  $args            keys (bool) restore keys, overwrite_keys (bool), settings (bool) restore settings.
	 * @return array{imported: int, updated: int, skipped: int, keys: string}|WP_Error
	 */
	public static function import_json( $json, array $args = array() ) {
		$args = array_merge(
			array(
				'keys'           => true,
				'overwrite_keys' => false,
				'settings'       => false,
			),
			$args
		);
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== self::FORMAT ) {
			return new WP_Error( 'bwpn_bad_backup', __( 'This is not a Best Web Push Notifications backup file.', 'best-web-push-notifications' ) );
		}
		if ( (int) ( $data['version'] ?? 0 ) > self::VERSION ) {
			return new WP_Error( 'bwpn_backup_too_new', __( 'The backup was made by a newer version of the plugin. Update the plugin first.', 'best-web-push-notifications' ) );
		}

		$keys_result = 'unchanged';
		if ( $args['keys'] && ! empty( $data['keys']['public'] ) && ! empty( $data['keys']['private'] ) ) {
			$keys_result = self::restore_keys( (string) $data['keys']['public'], (string) $data['keys']['private'], (bool) $args['overwrite_keys'] );
			if ( is_wp_error( $keys_result ) ) {
				return $keys_result;
			}
		}
		if ( $args['settings'] && is_array( $data['settings'] ?? null ) ) {
			BWPN_Settings::update( $data['settings'] );
		}

		$result         = self::import_rows( is_array( $data['subscribers'] ?? null ) ? $data['subscribers'] : array() );
		$result['keys'] = $keys_result;
		return $result;
	}

	/**
	 * Restores a key pair.
	 *
	 * @param string $public    Public key.
	 * @param string $private   Private key.
	 * @param bool   $overwrite Replace different existing keys.
	 * @return string|WP_Error "unchanged", "restored".
	 */
	public static function restore_keys( $public, $private, $overwrite ) {
		if ( BWPN_Settings::public_key() === $public && BWPN_Settings::private_key() === $private ) {
			return 'unchanged';
		}
		if ( BWPN_Settings::keys_from_constants() ) {
			return new WP_Error( 'bwpn_keys_pinned', __( 'The VAPID keys are defined in wp-config.php; update them there.', 'best-web-push-notifications' ) );
		}
		if ( BWPN_Settings::has_keys() && ! $overwrite ) {
			return new WP_Error( 'bwpn_keys_differ', __( 'The backup has different VAPID keys. Choose "replace keys" to restore them; subscribers made with the current keys will stop working.', 'best-web-push-notifications' ) );
		}
		$valid = BWPN_External_Webpush::validate_keys( $public, $private );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		BWPN_Settings::set_keys( $public, $private );
		return 'restored';
	}

	/**
	 * Imports a CSV file of subscribers.
	 *
	 * @param string $path File path.
	 * @return array{imported: int, updated: int, skipped: int}|WP_Error
	 */
	public static function import_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed read of an upload.
		if ( false === $handle ) {
			return new WP_Error( 'bwpn_csv_unreadable', __( 'The CSV file could not be read.', 'best-web-push-notifications' ) );
		}
		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'bwpn_csv_empty', __( 'The CSV file is empty.', 'best-web-push-notifications' ) );
		}
		$header = array_map(
			static function ( $h ) {
				return strtolower( trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
			},
			$header
		);
		foreach ( array( 'endpoint', 'p256dh', 'auth' ) as $required ) {
			if ( ! in_array( $required, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: column name. */
				return new WP_Error( 'bwpn_csv_columns', sprintf( __( 'The CSV file has no "%s" column.', 'best-web-push-notifications' ), $required ) );
			}
		}
		$totals = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);
		$batch  = array();
		while ( ( $line = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( $line ) !== count( $header ) ) {
				++$totals['skipped'];
				continue;
			}
			$row     = array_combine( $header, $line );
			$batch[] = array_map(
				static function ( $value ) {
					return 0 === strpos( (string) $value, "'" ) ? substr( (string) $value, 1 ) : $value;
				},
				$row
			);
			if ( count( $batch ) >= 500 ) {
				$totals = self::add_totals( $totals, self::import_rows( $batch ) );
				$batch  = array();
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return self::add_totals( $totals, self::import_rows( $batch ) );
	}

	/**
	 * Sums totals.
	 *
	 * @param array<string, int> $a Totals.
	 * @param array<string, int> $b Totals.
	 * @return array{imported: int, updated: int, skipped: int}
	 */
	private static function add_totals( array $a, array $b ) {
		return array(
			'imported' => $a['imported'] + $b['imported'],
			'updated'  => $a['updated'] + $b['updated'],
			'skipped'  => $a['skipped'] + $b['skipped'],
		);
	}

	/**
	 * Inserts or refreshes subscribers.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows in export shape.
	 * @return array{imported: int, updated: int, skipped: int}
	 */
	public static function import_rows( array $rows ) {
		$totals = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				++$totals['skipped'];
				continue;
			}
			$valid = BWPN_Subscription_Validator::validate( $row['endpoint'] ?? '', $row['p256dh'] ?? '', $row['auth'] ?? '', $row['content_encoding'] ?? 'aes128gcm' );
			if ( is_wp_error( $valid ) ) {
				++$totals['skipped'];
				continue;
			}
			$existing = BWPN_Lib_Db::get_subscriber_by_endpoint( $valid['endpoint'] );
			if ( $existing ) {
				BWPN_Lib_Db::update_subscriber( (int) $existing->id, $valid );
				++$totals['updated'];
				continue;
			}
			$uuid    = (string) ( $row['uuid'] ?? '' );
			$created = (string) ( $row['created_at'] ?? '' );
			$id      = BWPN_Lib_Db::create_subscriber(
				array_merge(
					$valid,
					array(
						'uuid'       => ( wp_is_uuid( $uuid ) && ! BWPN_Lib_Db::get_subscriber_by_uuid( $uuid ) ) ? $uuid : '',
						'wp_user_id' => ! empty( $row['wp_user_id'] ) && get_userdata( (int) $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0,
						'source'     => 'import',
						'created_at' => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created ) ? $created : '',
					)
				)
			);
			if ( false === $id ) {
				++$totals['skipped'];
			} else {
				++$totals['imported'];
			}
		}
		return $totals;
	}
}

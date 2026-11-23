<?php
/**
 * WP-CLI commands.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Best Web Push Notifications.
 *
 * ## EXAMPLES
 *
 *     wp bwpn status
 *     wp bwpn send "New post is live" --title="My blog" --url=https://example.com/post
 *     wp bwpn process
 *     wp bwpn export backup.json
 *     wp bwpn import backup.json --overwrite-keys
 *     wp bwpn migrate-perfecty
 *     wp bwpn keys show
 */
class BWPN_CLI {

	/**
	 * Shows subscribers, jobs and health checks.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function status( $args, $assoc ) {
		$data = array(
			'subscribers' => BWPN_Lib_Db::count_subscribers(),
			'jobs'        => BWPN_Lib_Db::get_jobs_stats(),
			'delivery'    => BWPN_Lib_Db::get_notifications_stats(),
			'health'      => BWPN_Lib_Utils::health_checks(),
		);
		if ( 'json' === ( $assoc['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $data, JSON_PRETTY_PRINT ) );
			return;
		}
		WP_CLI::line( 'Subscribers: ' . $data['subscribers'] );
		foreach ( $data['jobs'] as $status => $count ) {
			WP_CLI::line( sprintf( 'Jobs %s: %d', $status, $count ) );
		}
		WP_CLI::line( sprintf( 'Delivered %d, failed %d, expired %d', $data['delivery']['succeeded'], $data['delivery']['failed'], $data['delivery']['expired'] ) );
		$rows = array();
		foreach ( $data['health'] as $check ) {
			$rows[] = array(
				'check'  => $check['label'],
				'ok'     => $check['ok'] ? 'yes' : 'NO',
				'detail' => $check['detail'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'check', 'ok', 'detail' ) );
	}

	/**
	 * Queues a notification to every subscriber.
	 *
	 * <message>
	 * : Notification body.
	 *
	 * [--title=<title>]
	 * : Title. Defaults to the site name.
	 *
	 * [--url=<url>]
	 * : Link to open. Defaults to the home page.
	 *
	 * [--image=<url>]
	 * : Large image URL.
	 *
	 * [--at=<datetime>]
	 * : When to send, in the site timezone (e.g. "2026-12-01 09:00").
	 *
	 * [--now]
	 * : Process the job in this command instead of waiting for WP-Cron.
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function send( $args, $assoc ) {
		$timestamp = null;
		if ( ! empty( $assoc['at'] ) ) {
			$date = date_create_immutable( $assoc['at'], wp_timezone() );
			if ( false === $date ) {
				WP_CLI::error( 'Invalid --at value.' );
			}
			$timestamp = $date->getTimestamp();
		}
		$payload = BWPN_Lib_Payload::build( $args[0], $assoc['title'] ?? '', $assoc['image'] ?? '', $assoc['url'] ?? '' );
		$id      = BWPN_Lib_Push_Server::schedule_broadcast( $payload, $timestamp, array( 'source' => 'cli' ) );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		WP_CLI::success( "Job $id queued." );
		if ( isset( $assoc['now'] ) && null === $timestamp ) {
			$this->run_job( (int) $id );
		}
	}

	/**
	 * Processes due and unfinished jobs now (use from a system cron when
	 * WP-Cron is unreliable, e.g. behind full-page caching).
	 *
	 * [<id>...]
	 * : Job ids. Defaults to every due job.
	 *
	 * ## EXAMPLES
	 *
	 *     # Every minute from crontab:
	 *     * * * * * wp --path=/var/www/site bwpn process --quiet
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function process( $args, $assoc ) {
		$ids = ! empty( $args ) ? array_map( 'intval', $args ) : BWPN_Lib_Db::get_jobs_to_resume( BWPN_Lib_Push_Server::STALE_AFTER );
		if ( empty( $ids ) ) {
			WP_CLI::log( 'No jobs to process.' );
			return;
		}
		foreach ( $ids as $id ) {
			$this->run_job( $id );
		}
	}

	/**
	 * Runs a job to completion, one budgeted run after another.
	 *
	 * @param int $id Job id.
	 */
	private function run_job( $id ) {
		BWPN_Cron::unschedule_job( $id );
		$status = '';
		for ( $i = 0; $i < 10000; $i++ ) {
			$status = BWPN_Lib_Push_Server::execute_job( $id, 300 );
			if ( BWPN_Lib_Db::STATUS_RUNNING !== $status ) {
				break;
			}
			BWPN_Cron::unschedule_job( $id );
		}
		$job = BWPN_Lib_Db::get_notification( $id );
		if ( ! $job ) {
			WP_CLI::warning( "Job $id not found." );
			return;
		}
		$line = sprintf( 'Job %d: %s (delivered %d, failed %d, expired %d)', $id, $job->status, $job->succeeded, $job->failed, $job->expired );
		if ( BWPN_Lib_Db::STATUS_FAILED === $job->status ) {
			WP_CLI::warning( $line . ' - ' . $job->last_error );
		} elseif ( '' === $status ) {
			WP_CLI::log( $line . ' - not runnable now (scheduled later or locked).' );
		} else {
			WP_CLI::success( $line );
		}
	}

	/**
	 * Exports subscribers, settings and keys.
	 *
	 * <file>
	 * : Output path (.json or .csv). Use - for stdout.
	 *
	 * [--no-keys]
	 * : Leave the VAPID keys out of a JSON export.
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function export( $args, $assoc ) {
		$file = $args[0];
		$csv  = '.csv' === strtolower( substr( $file, -4 ) );
		$out  = '-' === $file ? fopen( 'php://stdout', 'w' ) : fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $out ) {
			WP_CLI::error( "Cannot write $file" );
		}
		if ( $csv ) {
			BWPN_Transfer::export_csv( $out );
		} else {
			fwrite( $out, BWPN_Transfer::export_json( (bool) WP_CLI\Utils\get_flag_value( $assoc, 'keys', true ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( '-' !== $file ) {
			WP_CLI::success( "Exported to $file" . ( $csv ? '' : ' (contains the private key unless --no-keys)' ) );
		}
	}

	/**
	 * Imports a JSON backup or a CSV of subscribers.
	 *
	 * <file>
	 * : Input path.
	 *
	 * [--overwrite-keys]
	 * : Replace the current VAPID keys with the backup's.
	 *
	 * [--settings]
	 * : Also restore settings (JSON only).
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function import( $args, $assoc ) {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read $file" );
		}
		if ( '.csv' === strtolower( substr( $file, -4 ) ) ) {
			$result = BWPN_Transfer::import_csv( $file );
		} else {
			$result = BWPN_Transfer::import_json(
				(string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				array(
					'overwrite_keys' => isset( $assoc['overwrite-keys'] ),
					'settings'       => isset( $assoc['settings'] ),
				)
			);
		}
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( '%d new, %d updated, %d skipped%s.', $result['imported'], $result['updated'], $result['skipped'], isset( $result['keys'] ) ? ', keys ' . $result['keys'] : '' ) );
	}

	/**
	 * Imports keys, settings and subscribers from Perfecty Push.
	 *
	 * [--overwrite-keys]
	 * : Replace the current VAPID keys with Perfecty's.
	 *
	 * @subcommand migrate-perfecty
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function migrate_perfecty( $args, $assoc ) {
		$result = BWPN_Migrator::run( isset( $assoc['overwrite-keys'] ) );
		while ( ! is_wp_error( $result ) && 'running' === $result['status'] ) {
			$result = BWPN_Migrator::continue_import( 300 );
		}
		wp_clear_scheduled_hook( BWPN_Migrator::CRON_HOOK );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Imported %d subscribers, skipped %d. Deactivate Perfecty Push now.', $result['imported'], $result['skipped'] ) );
	}

	/**
	 * Shows or generates the VAPID keys.
	 *
	 * <action>
	 * : show or generate.
	 * ---
	 * options:
	 *   - show
	 *   - generate
	 * ---
	 *
	 * [--yes]
	 * : Skip the confirmation when replacing keys.
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Flags.
	 */
	public function keys( $args, $assoc ) {
		if ( 'show' === $args[0] ) {
			WP_CLI::line( 'Public key: ' . ( BWPN_Settings::public_key() ? BWPN_Settings::public_key() : '(none)' ) );
			WP_CLI::line( 'Source: ' . ( BWPN_Settings::keys_from_constants() ? 'wp-config.php' : 'database' ) );
			return;
		}
		if ( BWPN_Settings::keys_from_constants() ) {
			WP_CLI::error( 'Keys are defined in wp-config.php.' );
		}
		if ( BWPN_Settings::has_keys() ) {
			WP_CLI::confirm( 'New keys make every existing subscription stop working. Continue?', $assoc );
		}
		$keys = BWPN_External_Webpush::create_vapid_keys();
		BWPN_Settings::set_keys( $keys['publicKey'], $keys['privateKey'] );
		WP_CLI::success( 'New keys generated. Public key: ' . $keys['publicKey'] );
	}
}

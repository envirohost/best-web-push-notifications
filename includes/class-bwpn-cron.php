<?php
/**
 * Background events.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and schedules the WP-Cron events used by the plugin.
 *
 * - bwpn_process_job: one run of a notification job (single events).
 * - bwpn_watchdog: every 5 minutes, re-queues jobs that are due or whose
 *   worker died mid-run (fixes jobs stuck forever in "running").
 * - bwpn_daily: log rotation and cleanup of expired subscriptions.
 */
class BWPN_Cron {

	public const JOB_HOOK      = 'bwpn_process_job';
	public const WATCHDOG_HOOK = 'bwpn_watchdog';
	public const DAILY_HOOK    = 'bwpn_daily';
	public const SCHEDULE      = 'bwpn_five_minutes';
	public const LAST_RUN      = 'bwpn_watchdog_last_run';

	/**
	 * Registers hook callbacks.
	 */
	public static function register_hooks() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- 5 minutes is intentional.
		add_action( self::JOB_HOOK, array( 'BWPN_Lib_Push_Server', 'execute_job' ), 10, 1 );
		add_action( self::WATCHDOG_HOOK, array( __CLASS__, 'run_watchdog' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'run_daily' ) );
	}

	/**
	 * Adds the five minute schedule.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Every 5 minutes (Best Web Push Notifications)',
		);
		return $schedules;
	}

	/**
	 * Schedules the recurring events if missing.
	 */
	public static function schedule_recurring() {
		if ( ! wp_next_scheduled( self::WATCHDOG_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::WATCHDOG_HOOK );
		}
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	/**
	 * Removes every scheduled event of the plugin.
	 */
	public static function clear_all() {
		wp_clear_scheduled_hook( self::WATCHDOG_HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_unschedule_hook( self::JOB_HOOK );
	}

	/**
	 * Schedules a job run.
	 *
	 * @param int $notification_id Job id.
	 * @param int $timestamp       Unix timestamp (UTC).
	 * @return bool
	 */
	public static function schedule_job( $notification_id, $timestamp ) {
		$args = array( (int) $notification_id );
		if ( wp_next_scheduled( self::JOB_HOOK, $args ) ) {
			return true;
		}
		$result = wp_schedule_single_event( (int) $timestamp, self::JOB_HOOK, $args, true );
		return true === $result;
	}

	/**
	 * Removes a job's scheduled runs.
	 *
	 * @param int $notification_id Job id.
	 */
	public static function unschedule_job( $notification_id ) {
		wp_clear_scheduled_hook( self::JOB_HOOK, array( (int) $notification_id ) );
	}

	/**
	 * Watchdog callback.
	 */
	public static function run_watchdog() {
		update_option( self::LAST_RUN, time(), false );
		BWPN_Lib_Push_Server::watchdog();
	}

	/**
	 * Daily maintenance callback.
	 */
	public static function run_daily() {
		BWPN_Lib_Db::delete_old_logs( 14 );
		$removed = BWPN_Lib_Db::delete_failing_subscribers( max( 1, BWPN_Settings::int( 'max_failures' ) ) );
		if ( $removed > 0 ) {
			BWPN_Lib_Log::info( "Daily cleanup removed $removed subscriptions that kept failing" );
		}
	}

	/**
	 * Health of WP-Cron as seen by the watchdog.
	 *
	 * @return array{ok: bool, last_run: int, disabled: bool}
	 */
	public static function health() {
		$last_run = (int) get_option( self::LAST_RUN, 0 );
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$ok       = $last_run > 0 && ( time() - $last_run ) < 20 * MINUTE_IN_SECONDS;
		if ( 0 === $last_run ) {
			// Grace period right after install or reactivation.
			$next = wp_next_scheduled( self::WATCHDOG_HOOK );
			$ok   = false !== $next && $next > time() - 20 * MINUTE_IN_SECONDS;
		}
		return array(
			'ok'       => $ok,
			'last_run' => $last_run,
			'disabled' => $disabled,
		);
	}
}

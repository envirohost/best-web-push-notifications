<?php
/**
 * Push server: schedules jobs and delivers notifications in batches.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends notifications.
 *
 * A broadcast is a row in the notifications table (a "job"). WP-Cron runs
 * the job with execute_job(); each run takes the job lock atomically, sends
 * as many batches as fit in the time budget, records progress per batch and
 * schedules the next run. The watchdog re-queues jobs whose run never fired
 * or whose worker died, so no job stays "running" forever.
 *
 * Fixes over upstream Perfecty Push 1.6.5:
 * - A batch with zero successes no longer fails and stops the whole job.
 * - A crashed worker no longer leaves the job locked forever (stale locks).
 * - Subscribers are paged by id (keyset), not OFFSET, so none are skipped
 *   or notified twice when rows are deleted during a send.
 * - 401/403 responses never delete subscribers: they mean our VAPID keys or
 *   subject were rejected, not that the subscription is gone. A job whose
 *   batch is rejected entirely stops with a clear error instead of wiping
 *   the subscriber list.
 */
class BWPN_Lib_Push_Server {

	public const RESULT_OK      = 'ok';
	public const RESULT_EXPIRED = 'expired';
	public const RESULT_AUTH    = 'auth';
	public const RESULT_RETRY   = 'retry';
	public const RESULT_INVALID = 'invalid';

	/** Seconds after which a job lock is considered abandoned. */
	public const STALE_AFTER = 10 * MINUTE_IN_SECONDS;

	/** Unexpected errors tolerated before a job is marked failed. */
	public const MAX_ATTEMPTS = 3;

	/**
	 * Sender override (tests, or a custom transport).
	 *
	 * @var callable|null
	 */
	private static $sender = null;

	/**
	 * Replaces the sender. The callable receives (object[] $subscribers,
	 * string $payload, array $options) and returns array<int, string> of
	 * subscriber id => RESULT_* constant. Pass null to restore web-push.
	 *
	 * @param callable|null $sender Sender.
	 */
	public static function set_sender( $sender ) {
		self::$sender = is_callable( $sender ) ? $sender : null;
	}

	/* ---------------------------------------------------------------------
	 * Scheduling
	 * ------------------------------------------------------------------- */

	/**
	 * Creates a broadcast job and schedules it.
	 *
	 * @param array<string, mixed> $payload   Payload from BWPN_Lib_Payload::build().
	 * @param int|null             $timestamp When to send (UTC), null for now.
	 * @param array<string, mixed> $args      source, post_id.
	 * @return int|WP_Error Job id.
	 */
	public static function schedule_broadcast( array $payload, $timestamp = null, array $args = array() ) {
		$json = BWPN_Lib_Payload::encode( $payload );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! BWPN_Settings::has_keys() ) {
			return new WP_Error( 'bwpn_no_keys', __( 'VAPID keys are missing. Generate them in Settings before sending.', 'best-web-push-notifications' ) );
		}
		$when = null === $timestamp ? time() : max( time(), (int) $timestamp );
		$id   = BWPN_Lib_Db::create_notification(
			$json,
			array(
				'total'        => BWPN_Lib_Db::count_subscribers(),
				'batch_size'   => max( 1, BWPN_Settings::int( 'batch_size' ) ),
				'scheduled_at' => $when,
				'source'       => (string) ( $args['source'] ?? 'manual' ),
				'post_id'      => (int) ( $args['post_id'] ?? 0 ),
			)
		);
		if ( false === $id ) {
			return new WP_Error( 'bwpn_job_not_created', __( 'The notification could not be saved.', 'best-web-push-notifications' ) );
		}
		BWPN_Cron::schedule_job( $id, $when );
		BWPN_Lib_Log::info( "Job $id scheduled for " . BWPN_Lib_Db::now( $when ) . ' UTC' );

		/**
		 * Fires after a broadcast job is created.
		 *
		 * @param int $id Job id.
		 */
		do_action( 'bwpn_job_scheduled', $id );
		return $id;
	}

	/**
	 * Cancels a job that has not finished.
	 *
	 * @param int $id Job id.
	 * @return bool
	 */
	public static function cancel_job( $id ) {
		$job = BWPN_Lib_Db::get_notification( $id );
		if ( ! $job || ! in_array( $job->status, array( BWPN_Lib_Db::STATUS_SCHEDULED, BWPN_Lib_Db::STATUS_RUNNING ), true ) ) {
			return false;
		}
		BWPN_Cron::unschedule_job( $id );
		return BWPN_Lib_Db::set_status( $id, BWPN_Lib_Db::STATUS_CANCELED );
	}

	/**
	 * Re-queues a failed or canceled job from where it stopped.
	 *
	 * @param int $id Job id.
	 * @return bool
	 */
	public static function resume_job( $id ) {
		$job = BWPN_Lib_Db::get_notification( $id );
		if ( ! $job || ! in_array( $job->status, array( BWPN_Lib_Db::STATUS_FAILED, BWPN_Lib_Db::STATUS_CANCELED ), true ) ) {
			return false;
		}
		BWPN_Lib_Db::update_notification(
			$id,
			array(
				'status'      => BWPN_Lib_Db::STATUS_RUNNING,
				'is_taken'    => 0,
				'taken_at'    => null,
				'attempts'    => 0,
				'finished_at' => null,
				'last_error'  => '',
			)
		);
		return BWPN_Cron::schedule_job( $id, time() );
	}

	/**
	 * Watchdog: nudges jobs that are due, paused, or abandoned.
	 *
	 * @return int[] Re-queued job ids.
	 */
	public static function watchdog() {
		$ids = BWPN_Lib_Db::get_jobs_to_resume( self::STALE_AFTER );
		foreach ( $ids as $id ) {
			BWPN_Cron::schedule_job( $id, time() );
		}
		if ( ! empty( $ids ) ) {
			BWPN_Lib_Log::info( 'Watchdog re-queued jobs: ' . implode( ',', $ids ) );
		}
		return $ids;
	}

	/* ---------------------------------------------------------------------
	 * Execution
	 * ------------------------------------------------------------------- */

	/**
	 * Runs a job for up to the time budget. Hooked to the cron event.
	 *
	 * @param int      $id     Job id.
	 * @param int|null $budget Seconds available; defaults to time_budget().
	 * @return string Job status after the run, or '' when the job was not run.
	 */
	public static function execute_job( $id, $budget = null ) {
		$id  = (int) $id;
		$job = BWPN_Lib_Db::get_notification( $id );
		if ( ! $job ) {
			return '';
		}
		if ( BWPN_Lib_Db::STATUS_SCHEDULED === $job->status && strtotime( $job->scheduled_at . ' UTC' ) > time() + 5 ) {
			// Fired early (e.g. rescheduled); the real event or the watchdog runs it.
			BWPN_Cron::schedule_job( $id, strtotime( $job->scheduled_at . ' UTC' ) );
			return '';
		}
		if ( ! BWPN_Lib_Db::claim_notification( $id, self::STALE_AFTER ) ) {
			BWPN_Lib_Log::debug( "Job $id is taken by another worker or not runnable" );
			return '';
		}

		$start  = microtime( true );
		$budget = null === $budget ? self::time_budget() : max( 1, (int) $budget );

		if ( BWPN_Lib_Db::STATUS_SCHEDULED === $job->status ) {
			BWPN_Lib_Db::update_notification(
				$id,
				array(
					'status' => BWPN_Lib_Db::STATUS_RUNNING,
					'total'  => BWPN_Lib_Db::count_subscribers(),
				)
			);
		}

		try {
			$cursor     = (int) $job->last_cursor;
			$batch_size = max( 1, (int) $job->batch_size );
			$options    = self::send_options();
			$sender     = self::sender();

			while ( true ) {
				$subscribers = BWPN_Lib_Db::get_subscribers_after( $cursor, $batch_size );
				if ( empty( $subscribers ) ) {
					return self::finish( $id );
				}

				$results = (array) call_user_func( $sender, $subscribers, (string) $job->payload, $options );
				$summary = self::apply_results( $subscribers, $results );
				$cursor  = (int) end( $subscribers )->id;
				BWPN_Lib_Db::add_progress( $id, $cursor, $summary['ok'], $summary['failed'], $summary['expired'] );

				if ( $summary['auth'] > 0 && $summary['auth'] === count( $subscribers ) ) {
					// Every push service rejected our credentials: stop, keep the subscribers.
					$message = 'Push services rejected the VAPID credentials (401/403). Check the VAPID keys and subject; no subscribers were removed.';
					BWPN_Lib_Log::error( "Job $id: $message" );
					BWPN_Lib_Db::set_status( $id, BWPN_Lib_Db::STATUS_FAILED, $message );
					return BWPN_Lib_Db::STATUS_FAILED;
				}

				// Leave room for one more batch, judged by how long this one took.
				$elapsed = microtime( true ) - $start;
				if ( $elapsed * 1.5 >= $budget ) {
					break;
				}
			}
		} catch ( Throwable $e ) {
			return self::handle_error( $id, $e );
		}

		// Out of time: release and continue in a fresh request.
		BWPN_Lib_Db::release_notification( $id );
		BWPN_Cron::schedule_job( $id, time() );
		return BWPN_Lib_Db::STATUS_RUNNING;
	}

	/**
	 * Marks a job completed.
	 *
	 * @param int $id Job id.
	 * @return string
	 */
	private static function finish( $id ) {
		$job   = BWPN_Lib_Db::get_notification( $id );
		$error = '';
		if ( $job && 0 === (int) $job->succeeded && (int) $job->failed > 0 ) {
			$error = 'No notification was delivered; see the logs for the push service responses.';
		}
		BWPN_Lib_Db::set_status( $id, BWPN_Lib_Db::STATUS_COMPLETED, $error );
		BWPN_Lib_Log::info( "Job $id completed" );

		/**
		 * Fires when a job finishes sending.
		 *
		 * @param int $id Job id.
		 */
		do_action( 'bwpn_job_completed', $id );
		return BWPN_Lib_Db::STATUS_COMPLETED;
	}

	/**
	 * Handles an unexpected error: retry later, or fail after MAX_ATTEMPTS.
	 *
	 * @param int       $id Job id.
	 * @param Throwable $e  Error.
	 * @return string
	 */
	private static function handle_error( $id, Throwable $e ) {
		$job      = BWPN_Lib_Db::get_notification( $id );
		$attempts = $job ? (int) $job->attempts + 1 : self::MAX_ATTEMPTS;
		$message  = get_class( $e ) . ': ' . $e->getMessage();
		BWPN_Lib_Log::error( "Job $id attempt $attempts: $message" );
		BWPN_Lib_Db::update_notification(
			$id,
			array(
				'attempts'   => $attempts,
				'last_error' => substr( $message, 0, 255 ),
			)
		);
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			BWPN_Lib_Db::set_status( $id, BWPN_Lib_Db::STATUS_FAILED, $message );
			return BWPN_Lib_Db::STATUS_FAILED;
		}
		BWPN_Lib_Db::release_notification( $id );
		BWPN_Cron::schedule_job( $id, time() + MINUTE_IN_SECONDS * $attempts );
		return BWPN_Lib_Db::STATUS_RUNNING;
	}

	/**
	 * Updates subscribers from the results of one batch.
	 *
	 * @param object[]              $subscribers Batch.
	 * @param array<int|string, string> $results Subscriber id => result.
	 * @return array{ok: int, failed: int, expired: int, auth: int}
	 */
	public static function apply_results( array $subscribers, array $results ) {
		$buckets = array(
			self::RESULT_OK      => array(),
			self::RESULT_EXPIRED => array(),
			self::RESULT_AUTH    => array(),
			'failure'            => array(),
		);
		foreach ( $subscribers as $subscriber ) {
			$sid    = (int) $subscriber->id;
			$result = $results[ $sid ] ?? self::RESULT_RETRY;
			if ( isset( $buckets[ $result ] ) ) {
				$buckets[ $result ][] = $sid;
			} else {
				$buckets['failure'][] = $sid;
			}
		}
		BWPN_Lib_Db::record_success( $buckets[ self::RESULT_OK ] );
		BWPN_Lib_Db::delete_subscribers( $buckets[ self::RESULT_EXPIRED ] );
		// Credential errors do not count against subscribers.
		BWPN_Lib_Db::record_failure( $buckets['failure'] );

		return array(
			'ok'      => count( $buckets[ self::RESULT_OK ] ),
			'failed'  => count( $buckets['failure'] ) + count( $buckets[ self::RESULT_AUTH ] ),
			'expired' => count( $buckets[ self::RESULT_EXPIRED ] ),
			'auth'    => count( $buckets[ self::RESULT_AUTH ] ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Direct sends (welcome message, single user, test)
	 * ------------------------------------------------------------------- */

	/**
	 * Sends a payload immediately to the given subscribers (small sets only).
	 *
	 * @param object[]             $subscribers Rows with id, endpoint, key_p256dh, key_auth, content_encoding.
	 * @param array<string, mixed> $payload     Payload array.
	 * @return array{ok: int, failed: int, expired: int, auth: int}|WP_Error
	 */
	public static function send_now( array $subscribers, array $payload ) {
		$json = BWPN_Lib_Payload::encode( $payload );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( empty( $subscribers ) ) {
			return array(
				'ok'      => 0,
				'failed'  => 0,
				'expired' => 0,
				'auth'    => 0,
			);
		}
		try {
			$results = (array) call_user_func( self::sender(), $subscribers, $json, self::send_options() );
		} catch ( Throwable $e ) {
			BWPN_Lib_Log::error( 'Direct send failed: ' . $e->getMessage() );
			return new WP_Error( 'bwpn_send_failed', $e->getMessage() );
		}
		return self::apply_results( $subscribers, $results );
	}

	/**
	 * Sends a payload to every subscription of a WordPress user.
	 *
	 * @param int                  $wp_user_id User id.
	 * @param array<string, mixed> $payload    Payload.
	 * @return array{ok: int, failed: int, expired: int, auth: int}|WP_Error
	 */
	public static function notify_user( $wp_user_id, array $payload ) {
		return self::send_now( BWPN_Lib_Db::get_subscribers_by_wp_user( (int) $wp_user_id ), $payload );
	}

	/* ---------------------------------------------------------------------
	 * Web Push transport
	 * ------------------------------------------------------------------- */

	/**
	 * The active sender.
	 *
	 * @return callable
	 */
	private static function sender() {
		if ( null !== self::$sender ) {
			return self::$sender;
		}
		/**
		 * Filters the sender used to deliver batches.
		 *
		 * @param callable $sender Default web-push sender.
		 */
		$sender = apply_filters( 'bwpn_sender', array( __CLASS__, 'webpush_send' ) );
		return is_callable( $sender ) ? $sender : array( __CLASS__, 'webpush_send' );
	}

	/**
	 * Per-message options.
	 *
	 * @return array<string, mixed>
	 */
	private static function send_options() {
		return array(
			'TTL'     => max( 0, BWPN_Settings::int( 'ttl' ) ),
			'urgency' => 'normal',
		);
	}

	/**
	 * Default sender: encrypts and posts through minishlink/web-push.
	 *
	 * @param object[]             $subscribers Batch.
	 * @param string               $payload     JSON payload.
	 * @param array<string, mixed> $options     TTL, urgency.
	 * @return array<int, string> Subscriber id => result.
	 * @throws RuntimeException When the keys are missing.
	 */
	public static function webpush_send( array $subscribers, $payload, array $options ) {
		if ( ! BWPN_Settings::has_keys() ) {
			throw new RuntimeException( 'VAPID keys are missing' );
		}
		$webpush = BWPN_External_Webpush::create(
			array(
				'subject'    => BWPN_Settings::vapid_subject(),
				'publicKey'  => BWPN_Settings::public_key(),
				'privateKey' => BWPN_Settings::private_key(),
			),
			$options
		);

		$by_endpoint = array();
		$results     = array();
		foreach ( $subscribers as $subscriber ) {
			try {
				$webpush->queueNotification(
					BWPN_External_Webpush::subscription( $subscriber->endpoint, $subscriber->key_p256dh, $subscriber->key_auth, $subscriber->content_encoding ),
					$payload
				);
				$by_endpoint[ $subscriber->endpoint ][] = (int) $subscriber->id;
			} catch ( Throwable $e ) {
				BWPN_Lib_Log::warning( "Subscriber {$subscriber->id} could not be queued: " . $e->getMessage() );
				$results[ (int) $subscriber->id ] = self::RESULT_INVALID;
			}
		}

		foreach ( $webpush->flush( count( $subscribers ) ) as $report ) {
			$result = self::classify( $report->isSuccess(), $report->getResponse() ? $report->getResponse()->getStatusCode() : 0 );
			if ( self::RESULT_OK !== $result ) {
				BWPN_Lib_Log::debug( 'Push to ' . BWPN_Subscription_Validator::service_label( $report->getEndpoint() ) . " failed ($result): " . $report->getReason() );
			}
			foreach ( $by_endpoint[ $report->getEndpoint() ] ?? array() as $sid ) {
				$results[ $sid ] = $result;
			}
		}
		return $results;
	}

	/**
	 * Maps a push service response to a result.
	 *
	 * @param bool $success Library success flag.
	 * @param int  $status  HTTP status, 0 for network errors.
	 * @return string
	 */
	public static function classify( $success, $status ) {
		if ( $success ) {
			return self::RESULT_OK;
		}
		if ( 404 === $status || 410 === $status ) {
			return self::RESULT_EXPIRED;
		}
		if ( 401 === $status || 403 === $status ) {
			return self::RESULT_AUTH;
		}
		if ( 400 === $status || 413 === $status ) {
			return self::RESULT_INVALID;
		}
		return self::RESULT_RETRY;
	}

	/**
	 * Seconds a single run may spend sending.
	 *
	 * @return int
	 */
	public static function time_budget() {
		$max = (int) ini_get( 'max_execution_time' );
		if ( $max <= 0 || $max > 60 ) {
			$max = 60;
		}
		/**
		 * Filters the time budget of one job run, in seconds.
		 *
		 * @param int $budget Seconds.
		 */
		return max( 5, (int) apply_filters( 'bwpn_time_budget', (int) floor( $max * 0.6 ) ) );
	}
}

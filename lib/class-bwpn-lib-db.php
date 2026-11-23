<?php
/**
 * Database access layer.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All SQL lives here. Every query is prepared; table names use the %i
 * identifier placeholder. All datetimes are stored in UTC and computed in
 * PHP, never with MySQL NOW() or CURRENT_TIMESTAMP, so the database server's
 * timezone cannot shift schedules (upstream issues #158 and #190).
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables; caching would show stale counters.
 */
class BWPN_Lib_Db {

	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_CANCELED  = 'canceled';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_COMPLETED = 'completed';

	private const SUBSCRIBER_FIELDS   = array( 'id', 'uuid', 'wp_user_id', 'endpoint', 'endpoint_hash', 'key_p256dh', 'key_auth', 'content_encoding', 'source', 'failure_count', 'created_at', 'updated_at', 'last_success_at' );
	private const NOTIFICATION_FIELDS = array( 'id', 'payload', 'source', 'post_id', 'status', 'total', 'succeeded', 'failed', 'expired', 'last_cursor', 'batch_size', 'is_taken', 'taken_at', 'attempts', 'last_error', 'created_at', 'scheduled_at', 'last_execution_at', 'finished_at' );

	/* ---------------------------------------------------------------------
	 * Schema
	 * ------------------------------------------------------------------- */

	/**
	 * Subscribers table name.
	 *
	 * @return string
	 */
	public static function subscribers_table() {
		global $wpdb;
		return $wpdb->prefix . 'bwpn_subscribers';
	}

	/**
	 * Notifications table name.
	 *
	 * @return string
	 */
	public static function notifications_table() {
		global $wpdb;
		return $wpdb->prefix . 'bwpn_notifications';
	}

	/**
	 * Logs table name.
	 *
	 * @return string
	 */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'bwpn_logs';
	}

	/**
	 * Current UTC datetime for the database.
	 *
	 * @param int|null $timestamp Unix timestamp, defaults to now.
	 * @return string
	 */
	public static function now( $timestamp = null ) {
		return gmdate( 'Y-m-d H:i:s', null === $timestamp ? time() : (int) $timestamp );
	}

	/**
	 * Creates or upgrades the tables.
	 */
	public static function db_create() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset       = $wpdb->get_charset_collate();
		$subscribers   = self::subscribers_table();
		$notifications = self::notifications_table();
		$logs          = self::logs_table();

		dbDelta(
			"CREATE TABLE $subscribers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  wp_user_id bigint(20) unsigned NULL,
  endpoint varchar(2048) NOT NULL,
  endpoint_hash char(64) NOT NULL,
  key_p256dh varchar(128) NOT NULL,
  key_auth varchar(64) NOT NULL,
  content_encoding varchar(16) NOT NULL DEFAULT 'aes128gcm',
  source varchar(20) NOT NULL DEFAULT 'subscribe',
  failure_count smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NULL,
  last_success_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY endpoint_hash (endpoint_hash),
  KEY wp_user_id (wp_user_id)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE $notifications (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  payload text NOT NULL,
  source varchar(20) NOT NULL DEFAULT 'manual',
  post_id bigint(20) unsigned NULL,
  status varchar(15) NOT NULL DEFAULT 'scheduled',
  total int(10) unsigned NOT NULL DEFAULT 0,
  succeeded int(10) unsigned NOT NULL DEFAULT 0,
  failed int(10) unsigned NOT NULL DEFAULT 0,
  expired int(10) unsigned NOT NULL DEFAULT 0,
  last_cursor bigint(20) unsigned NOT NULL DEFAULT 0,
  batch_size int(10) unsigned NOT NULL DEFAULT 0,
  is_taken tinyint(1) NOT NULL DEFAULT 0,
  taken_at datetime NULL,
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  last_error varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  scheduled_at datetime NULL,
  last_execution_at datetime NULL,
  finished_at datetime NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY post_id (post_id)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE $logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  level varchar(10) NOT NULL DEFAULT 'debug',
  message text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) $charset;"
		);

		update_option( 'bwpn_db_version', BWPN_DB_VERSION, true );
	}

	/**
	 * Whether the tables exist.
	 *
	 * @return bool
	 */
	public static function has_tables() {
		global $wpdb;
		foreach ( array( self::subscribers_table(), self::notifications_table() ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Drops the tables (uninstall only).
	 */
	public static function drop_tables() {
		global $wpdb;
		foreach ( array( self::subscribers_table(), self::notifications_table(), self::logs_table() ) as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
		}
	}

	/**
	 * Builds "%d,%d,..." for an IN clause.
	 *
	 * @param int[] $ids Ids.
	 * @return string
	 */
	private static function int_placeholders( array $ids ) {
		return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	}

	/**
	 * Cleans an id list.
	 *
	 * @param mixed $ids Ids.
	 * @return int[]
	 */
	private static function clean_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Subscribers
	 * ------------------------------------------------------------------- */

	/**
	 * Inserts a subscriber. Returns the new id, or false (also when the
	 * endpoint already exists).
	 *
	 * @param array<string, mixed> $data endpoint, p256dh, auth, content_encoding, wp_user_id, uuid, source, created_at.
	 * @return int|false
	 */
	public static function create_subscriber( array $data ) {
		global $wpdb;
		$row = array(
			'uuid'             => ! empty( $data['uuid'] ) ? (string) $data['uuid'] : wp_generate_uuid4(),
			'wp_user_id'       => ! empty( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : null,
			'endpoint'         => (string) $data['endpoint'],
			'endpoint_hash'    => BWPN_Subscription_Validator::endpoint_hash( (string) $data['endpoint'] ),
			'key_p256dh'       => (string) $data['p256dh'],
			'key_auth'         => (string) $data['auth'],
			'content_encoding' => BWPN_Subscription_Validator::encoding( $data['content_encoding'] ?? 'aes128gcm' ),
			'source'           => substr( (string) ( $data['source'] ?? 'subscribe' ), 0, 20 ),
			'created_at'       => ! empty( $data['created_at'] ) ? (string) $data['created_at'] : self::now(),
		);
		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->insert( self::subscribers_table(), $row );
		$wpdb->suppress_errors( $suppress );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a subscriber.
	 *
	 * @param int                  $id   Id.
	 * @param array<string, mixed> $data Fields: endpoint, p256dh, auth, content_encoding, wp_user_id.
	 * @return bool
	 */
	public static function update_subscriber( $id, array $data ) {
		global $wpdb;
		$row = array( 'updated_at' => self::now() );
		if ( isset( $data['endpoint'] ) ) {
			$row['endpoint']      = (string) $data['endpoint'];
			$row['endpoint_hash'] = BWPN_Subscription_Validator::endpoint_hash( (string) $data['endpoint'] );
		}
		if ( isset( $data['p256dh'] ) ) {
			$row['key_p256dh'] = (string) $data['p256dh'];
		}
		if ( isset( $data['auth'] ) ) {
			$row['key_auth'] = (string) $data['auth'];
		}
		if ( isset( $data['content_encoding'] ) ) {
			$row['content_encoding'] = BWPN_Subscription_Validator::encoding( $data['content_encoding'] );
		}
		if ( array_key_exists( 'wp_user_id', $data ) ) {
			$row['wp_user_id'] = ! empty( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : null;
		}
		$row['failure_count'] = 0;
		$suppress             = $wpdb->suppress_errors( true );
		$result               = $wpdb->update( self::subscribers_table(), $row, array( 'id' => (int) $id ) );
		$wpdb->suppress_errors( $suppress );
		return false !== $result;
	}

	/**
	 * Gets a subscriber by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get_subscriber( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::subscribers_table(), (int) $id ) );
	}

	/**
	 * Gets a subscriber by uuid.
	 *
	 * @param string $uuid UUID.
	 * @return object|null
	 */
	public static function get_subscriber_by_uuid( $uuid ) {
		global $wpdb;
		if ( ! is_string( $uuid ) || '' === $uuid ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE uuid = %s', self::subscribers_table(), $uuid ) );
	}

	/**
	 * Gets a subscriber by endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return object|null
	 */
	public static function get_subscriber_by_endpoint( $endpoint ) {
		global $wpdb;
		if ( ! is_string( $endpoint ) || '' === $endpoint ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE endpoint_hash = %s', self::subscribers_table(), BWPN_Subscription_Validator::endpoint_hash( $endpoint ) ) );
	}

	/**
	 * Deletes subscribers by id.
	 *
	 * @param int[] $ids Ids.
	 * @return int Rows deleted.
	 */
	public static function delete_subscribers( $ids ) {
		global $wpdb;
		$ids = self::clean_ids( $ids );
		if ( empty( $ids ) ) {
			return 0;
		}
		$sql = 'DELETE FROM %i WHERE id IN (' . self::int_placeholders( $ids ) . ')';
		return (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( self::subscribers_table() ), $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
	}

	/**
	 * Deletes a subscriber by endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return int Rows deleted.
	 */
	public static function delete_subscriber_by_endpoint( $endpoint ) {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE endpoint_hash = %s', self::subscribers_table(), BWPN_Subscription_Validator::endpoint_hash( (string) $endpoint ) ) );
	}

	/**
	 * Lists subscribers for the admin screen.
	 *
	 * @param int    $offset   Offset.
	 * @param int    $size     Page size.
	 * @param string $order_by Column.
	 * @param string $order    asc|desc.
	 * @param string $output   OBJECT or ARRAY_A.
	 * @return array<int, mixed>
	 */
	public static function get_subscribers( $offset, $size, $order_by = 'created_at', $order = 'desc', $output = OBJECT ) {
		global $wpdb;
		$order_by = in_array( $order_by, self::SUBSCRIBER_FIELDS, true ) ? $order_by : 'created_at';
		$order    = 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
		$sql      = "SELECT * FROM %i ORDER BY %i $order, id $order LIMIT %d OFFSET %d";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, self::subscribers_table(), $order_by, (int) $size, (int) $offset ), $output ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is ASC or DESC.
	}

	/**
	 * Keyset pagination used by the sender: rows with id > $after_id.
	 *
	 * Upstream paged with OFFSET over created_at DESC, so subscribers that
	 * expired (and were deleted) or joined during a send shifted the window
	 * and people were skipped or notified twice.
	 *
	 * @param int $after_id Last processed id.
	 * @param int $limit    Rows.
	 * @return object[]
	 */
	public static function get_subscribers_after( $after_id, $limit ) {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, endpoint, key_p256dh, key_auth, content_encoding FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d',
				self::subscribers_table(),
				(int) $after_id,
				max( 1, (int) $limit )
			)
		);
	}

	/**
	 * Subscribers that belong to a WordPress user.
	 *
	 * @param int $wp_user_id User id.
	 * @return object[]
	 */
	public static function get_subscribers_by_wp_user( $wp_user_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE wp_user_id = %d', self::subscribers_table(), (int) $wp_user_id ) );
	}

	/**
	 * Number of subscribers.
	 *
	 * @return int
	 */
	public static function count_subscribers() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::subscribers_table() ) );
	}

	/**
	 * Number of subscribers per push service host.
	 *
	 * @return array<string, int>
	 */
	public static function count_subscribers_by_host() {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(endpoint, '/', 3), '/', -1) AS host, COUNT(*) AS total FROM %i GROUP BY host ORDER BY total DESC",
				self::subscribers_table()
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row->host ] = (int) $row->total;
		}
		return $result;
	}

	/**
	 * New subscribers per day (site timezone) for the last $days days.
	 *
	 * @param int $days Days.
	 * @return array<string, int> date => count.
	 */
	public static function subscribers_daily( $days ) {
		global $wpdb;
		$offset = (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$since  = self::now( time() - ( (int) $days * DAY_IN_SECONDS ) );
		$rows   = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(DATE_ADD(created_at, INTERVAL %d SECOND)) AS day, COUNT(*) AS total FROM %i WHERE created_at >= %s GROUP BY day',
				$offset,
				self::subscribers_table(),
				$since
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row->day ] = (int) $row->total;
		}
		return $result;
	}

	/**
	 * Resets the failure counter of delivered subscribers.
	 *
	 * @param int[] $ids Ids.
	 */
	public static function record_success( array $ids ) {
		global $wpdb;
		$ids = self::clean_ids( $ids );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$sql = 'UPDATE %i SET failure_count = 0, last_success_at = %s WHERE id IN (' . self::int_placeholders( $chunk ) . ')';
			$wpdb->query( $wpdb->prepare( $sql, array_merge( array( self::subscribers_table(), self::now() ), $chunk ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		}
	}

	/**
	 * Increments the failure counter.
	 *
	 * @param int[] $ids Ids.
	 */
	public static function record_failure( array $ids ) {
		global $wpdb;
		$ids = self::clean_ids( $ids );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$sql = 'UPDATE %i SET failure_count = LEAST(failure_count + 1, 65000) WHERE id IN (' . self::int_placeholders( $chunk ) . ')';
			$wpdb->query( $wpdb->prepare( $sql, array_merge( array( self::subscribers_table() ), $chunk ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		}
	}

	/**
	 * Deletes subscribers that failed $max_failures sends in a row.
	 *
	 * @param int $max_failures Threshold.
	 * @return int Rows deleted.
	 */
	public static function delete_failing_subscribers( $max_failures ) {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE failure_count >= %d LIMIT 5000', self::subscribers_table(), max( 1, (int) $max_failures ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Notifications (jobs)
	 * ------------------------------------------------------------------- */

	/**
	 * Creates a job.
	 *
	 * @param string               $payload      JSON payload.
	 * @param array<string, mixed> $data         status, total, batch_size, scheduled_at (timestamp), source, post_id.
	 * @return int|false
	 */
	public static function create_notification( $payload, array $data = array() ) {
		global $wpdb;
		$row    = array(
			'payload'      => (string) $payload,
			'status'       => (string) ( $data['status'] ?? self::STATUS_SCHEDULED ),
			'total'        => (int) ( $data['total'] ?? 0 ),
			'batch_size'   => (int) ( $data['batch_size'] ?? 1000 ),
			'source'       => substr( (string) ( $data['source'] ?? 'manual' ), 0, 20 ),
			'post_id'      => ! empty( $data['post_id'] ) ? (int) $data['post_id'] : null,
			'created_at'   => self::now(),
			'scheduled_at' => self::now( isset( $data['scheduled_at'] ) ? (int) $data['scheduled_at'] : time() ),
		);
		$result = $wpdb->insert( self::notifications_table(), $row );
		if ( false === $result ) {
			BWPN_Lib_Log::error( 'Could not create the notification job: ' . $wpdb->last_error );
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Gets a job.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get_notification( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::notifications_table(), (int) $id ) );
	}

	/**
	 * Lists jobs.
	 *
	 * @param int    $offset   Offset.
	 * @param int    $size     Page size.
	 * @param string $order_by Column.
	 * @param string $order    asc|desc.
	 * @param string $output   OBJECT or ARRAY_A.
	 * @return array<int, mixed>
	 */
	public static function get_notifications( $offset, $size, $order_by = 'created_at', $order = 'desc', $output = OBJECT ) {
		global $wpdb;
		$order_by = in_array( $order_by, self::NOTIFICATION_FIELDS, true ) ? $order_by : 'created_at';
		$order    = 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
		$sql      = "SELECT * FROM %i ORDER BY %i $order, id $order LIMIT %d OFFSET %d";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, self::notifications_table(), $order_by, (int) $size, (int) $offset ), $output ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is ASC or DESC.
	}

	/**
	 * Number of jobs.
	 *
	 * @return int
	 */
	public static function count_notifications() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::notifications_table() ) );
	}

	/**
	 * Atomically takes the lock on a job. A lock older than $stale_after
	 * seconds is considered abandoned (the worker crashed or was killed) and
	 * can be taken over, so jobs no longer stay "running" forever.
	 *
	 * @param int $id          Job id.
	 * @param int $stale_after Seconds.
	 * @return bool True if this process now owns the job.
	 */
	public static function claim_notification( $id, $stale_after ) {
		global $wpdb;
		$rows = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET is_taken = 1, taken_at = %s WHERE id = %d AND status IN (%s, %s) AND (is_taken = 0 OR taken_at IS NULL OR taken_at < %s)',
				self::notifications_table(),
				self::now(),
				(int) $id,
				self::STATUS_SCHEDULED,
				self::STATUS_RUNNING,
				self::now( time() - (int) $stale_after )
			)
		);
		return 1 === (int) $rows;
	}

	/**
	 * Updates job columns.
	 *
	 * @param int                  $id     Job id.
	 * @param array<string, mixed> $fields Columns.
	 * @return bool
	 */
	public static function update_notification( $id, array $fields ) {
		global $wpdb;
		$fields = array_intersect_key( $fields, array_flip( self::NOTIFICATION_FIELDS ) );
		unset( $fields['id'] );
		if ( empty( $fields ) ) {
			return true;
		}
		return false !== $wpdb->update( self::notifications_table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * Adds the result of one batch to the job counters (atomic increment).
	 *
	 * @param int $id        Job id.
	 * @param int $cursor    Last subscriber id processed.
	 * @param int $succeeded Delivered.
	 * @param int $failed    Failed.
	 * @param int $expired   Expired and removed.
	 * @return bool
	 */
	public static function add_progress( $id, $cursor, $succeeded, $failed, $expired ) {
		global $wpdb;
		$rows = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET last_cursor = GREATEST(last_cursor, %d), succeeded = succeeded + %d, failed = failed + %d, expired = expired + %d, last_execution_at = %s, taken_at = %s WHERE id = %d',
				self::notifications_table(),
				(int) $cursor,
				(int) $succeeded,
				(int) $failed,
				(int) $expired,
				self::now(),
				self::now(),
				(int) $id
			)
		);
		return false !== $rows;
	}

	/**
	 * Sets the status of a job and releases its lock.
	 *
	 * @param int    $id     Job id.
	 * @param string $status New status.
	 * @param string $error  Optional error summary.
	 * @return bool
	 */
	public static function set_status( $id, $status, $error = '' ) {
		$fields = array(
			'status'   => $status,
			'is_taken' => 0,
			'taken_at' => null,
		);
		if ( in_array( $status, array( self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELED ), true ) ) {
			$fields['finished_at'] = self::now();
		}
		if ( '' !== $error ) {
			$fields['last_error'] = substr( $error, 0, 255 );
		}
		return self::update_notification( $id, $fields );
	}

	/**
	 * Releases a job lock without changing its status.
	 *
	 * @param int $id Job id.
	 * @return bool
	 */
	public static function release_notification( $id ) {
		return self::update_notification(
			$id,
			array(
				'is_taken' => 0,
				'taken_at' => null,
			)
		);
	}

	/**
	 * Ids of jobs that need a nudge: due but never started, paused between
	 * runs, or locked by a worker that died.
	 *
	 * @param int $stale_after Seconds after which a lock is abandoned.
	 * @return int[]
	 */
	public static function get_jobs_to_resume( $stale_after ) {
		global $wpdb;
		$grace = self::now( time() - MINUTE_IN_SECONDS );
		$stale = self::now( time() - (int) $stale_after );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE
					( status = %s AND is_taken = 0 AND scheduled_at <= %s )
					OR ( status = %s AND is_taken = 0 AND ( last_execution_at IS NULL OR last_execution_at <= %s ) )
					OR ( status IN (%s, %s) AND is_taken = 1 AND ( taken_at IS NULL OR taken_at < %s ) )
				ORDER BY id ASC LIMIT 20',
				self::notifications_table(),
				self::STATUS_SCHEDULED,
				$grace,
				self::STATUS_RUNNING,
				$grace,
				self::STATUS_SCHEDULED,
				self::STATUS_RUNNING,
				$stale
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether a post already has a job.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function post_has_notification( $post_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_id = %d AND status != %s', self::notifications_table(), (int) $post_id, self::STATUS_CANCELED ) ) > 0;
	}

	/**
	 * Deletes jobs and their scheduled events.
	 *
	 * @param int[] $ids Ids.
	 * @return int Rows deleted.
	 */
	public static function delete_notifications( $ids ) {
		global $wpdb;
		$ids = self::clean_ids( $ids );
		if ( empty( $ids ) ) {
			return 0;
		}
		foreach ( $ids as $id ) {
			BWPN_Cron::unschedule_job( $id );
		}
		$sql = 'DELETE FROM %i WHERE id IN (' . self::int_placeholders( $ids ) . ')';
		return (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( self::notifications_table() ), $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
	}

	/**
	 * Delivery totals across finished and running jobs.
	 *
	 * @return array{succeeded: int, failed: int, expired: int}
	 */
	public static function get_notifications_stats() {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(succeeded), 0) AS succeeded, COALESCE(SUM(failed), 0) AS failed, COALESCE(SUM(expired), 0) AS expired FROM %i WHERE status != %s',
				self::notifications_table(),
				self::STATUS_SCHEDULED
			)
		);
		return array(
			'succeeded' => (int) ( $row->succeeded ?? 0 ),
			'failed'    => (int) ( $row->failed ?? 0 ),
			'expired'   => (int) ( $row->expired ?? 0 ),
		);
	}

	/**
	 * Number of jobs per status.
	 *
	 * @return array<string, int>
	 */
	public static function get_jobs_stats() {
		global $wpdb;
		$result = array_fill_keys( array( self::STATUS_SCHEDULED, self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELED ), 0 );
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', self::notifications_table() ) );
		foreach ( $rows as $row ) {
			$result[ (string) $row->status ] = (int) $row->total;
		}
		return $result;
	}

	/**
	 * Delivered and failed notifications per day (site timezone).
	 *
	 * @param int $days Days.
	 * @return array<string, array{succeeded: int, failed: int}>
	 */
	public static function notifications_daily( $days ) {
		global $wpdb;
		$offset = (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$since  = self::now( time() - ( (int) $days * DAY_IN_SECONDS ) );
		$rows   = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(DATE_ADD(COALESCE(scheduled_at, created_at), INTERVAL %d SECOND)) AS day, SUM(succeeded) AS succeeded, SUM(failed) AS failed FROM %i WHERE status != %s AND COALESCE(scheduled_at, created_at) >= %s GROUP BY day',
				$offset,
				self::notifications_table(),
				self::STATUS_SCHEDULED,
				$since
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row->day ] = array(
				'succeeded' => (int) $row->succeeded,
				'failed'    => (int) $row->failed,
			);
		}
		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Logs
	 * ------------------------------------------------------------------- */

	/**
	 * Inserts a log entry.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @return bool
	 */
	public static function insert_log( $level, $message ) {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->insert(
			self::logs_table(),
			array(
				'level'      => substr( (string) $level, 0, 10 ),
				'message'    => substr( (string) $message, 0, 4000 ),
				'created_at' => self::now(),
			)
		);
		$wpdb->suppress_errors( $suppress );
		return false !== $result;
	}

	/**
	 * Number of log entries.
	 *
	 * @return int
	 */
	public static function count_logs() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::logs_table() ) );
	}

	/**
	 * Lists log entries, newest first.
	 *
	 * @param int    $offset Offset.
	 * @param int    $size   Page size.
	 * @param string $output OBJECT or ARRAY_A.
	 * @return array<int, mixed>
	 */
	public static function get_logs( $offset, $size, $output = OBJECT ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT level, message, created_at FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', self::logs_table(), (int) $size, (int) $offset ), $output );
	}

	/**
	 * Deletes old log entries and caps the table size.
	 *
	 * @param int $days Keep this many days.
	 * @return int Rows deleted.
	 */
	public static function delete_old_logs( $days ) {
		global $wpdb;
		$deleted = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 10000', self::logs_table(), self::now( time() - (int) $days * DAY_IN_SECONDS ) ) );
		$cutoff  = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1 OFFSET 5000', self::logs_table() ) );
		if ( null !== $cutoff ) {
			$deleted += (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id <= %d', self::logs_table(), (int) $cutoff ) );
		}
		return $deleted;
	}
}

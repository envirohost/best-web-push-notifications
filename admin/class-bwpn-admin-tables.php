<?php
/**
 * Admin list tables.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Formats a UTC database datetime in the site timezone.
 *
 * @param string|null $utc Datetime.
 * @return string
 */
function bwpn_admin_date( $utc ) {
	if ( empty( $utc ) ) {
		return '-';
	}
	$timestamp = strtotime( $utc . ' UTC' );
	return false === $timestamp ? '-' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Notification jobs.
 */
class BWPN_Admin_Notifications_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'notification',
				'plural'   => 'notifications',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'payload'      => __( 'Notification', 'best-web-push-notifications' ),
			'status'       => __( 'Status', 'best-web-push-notifications' ),
			'progress'     => __( 'Delivered / failed / expired', 'best-web-push-notifications' ),
			'source'       => __( 'Source', 'best-web-push-notifications' ),
			'scheduled_at' => __( 'Scheduled', 'best-web-push-notifications' ),
			'finished_at'  => __( 'Finished', 'best-web-push-notifications' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'status'       => array( 'status', false ),
			'scheduled_at' => array( 'scheduled_at', true ),
			'finished_at'  => array( 'finished_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bwpn_cancel' => __( 'Cancel', 'best-web-push-notifications' ),
			'bwpn_resume' => __( 'Resume', 'best-web-push-notifications' ),
			'bwpn_delete' => __( 'Delete', 'best-web-push-notifications' ),
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d">', (int) $item->id );
	}

	/**
	 * Payload column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_payload( $item ) {
		$payload = json_decode( (string) $item->payload, true );
		$title   = is_array( $payload ) ? (string) ( $payload['title'] ?? '' ) : '';
		$body    = is_array( $payload ) ? (string) ( $payload['body'] ?? '' ) : '';
		$url     = is_array( $payload ) ? (string) ( $payload['extra']['url_to_open'] ?? '' ) : '';
		$out     = '<strong>' . esc_html( $title ) . '</strong><br>' . esc_html( $body );
		if ( $url ) {
			$out .= '<br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
		}
		if ( '' !== (string) $item->last_error ) {
			$out .= '<br><span class="bwpn-error">' . esc_html( $item->last_error ) . '</span>';
		}
		return $out;
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_status( $item ) {
		$labels = array(
			BWPN_Lib_Db::STATUS_SCHEDULED => __( 'Scheduled', 'best-web-push-notifications' ),
			BWPN_Lib_Db::STATUS_RUNNING   => __( 'Sending', 'best-web-push-notifications' ),
			BWPN_Lib_Db::STATUS_COMPLETED => __( 'Completed', 'best-web-push-notifications' ),
			BWPN_Lib_Db::STATUS_FAILED    => __( 'Failed', 'best-web-push-notifications' ),
			BWPN_Lib_Db::STATUS_CANCELED  => __( 'Canceled', 'best-web-push-notifications' ),
		);
		return '<span class="bwpn-status bwpn-status-' . esc_attr( $item->status ) . '">' . esc_html( $labels[ $item->status ] ?? $item->status ) . '</span>';
	}

	/**
	 * Progress column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_progress( $item ) {
		return esc_html( sprintf( '%s / %s / %s', number_format_i18n( (int) $item->succeeded ), number_format_i18n( (int) $item->failed ), number_format_i18n( (int) $item->expired ) ) )
			. '<br><span class="description">' . esc_html(
				/* translators: %s: number of subscribers. */
				sprintf( __( 'of %s', 'best-web-push-notifications' ), number_format_i18n( (int) $item->total ) )
			) . '</span>';
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( in_array( $column_name, array( 'scheduled_at', 'finished_at' ), true ) ) {
			return esc_html( bwpn_admin_date( $item->$column_name ) );
		}
		if ( 'source' === $column_name && 'post' === $item->source && $item->post_id ) {
			$link = get_edit_post_link( (int) $item->post_id );
			return $link ? '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Post', 'best-web-push-notifications' ) . '</a>' : esc_html__( 'Post', 'best-web-push-notifications' );
		}
		return esc_html( (string) ( $item->$column_name ?? '' ) );
	}

	/**
	 * Loads the rows.
	 */
	public function prepare_items() {
		$per_page = 20;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sorting only.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$total                 = BWPN_Lib_Db::count_notifications();
		$this->items           = BWPN_Lib_Db::get_notifications( ( $this->get_pagenum() - 1 ) * $per_page, $per_page, $orderby, $order );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}
}

/**
 * Subscribers.
 */
class BWPN_Admin_Subscribers_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscriber',
				'plural'   => 'subscribers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox">',
			'uuid'            => __( 'Subscriber', 'best-web-push-notifications' ),
			'service'         => __( 'Push service', 'best-web-push-notifications' ),
			'wp_user_id'      => __( 'User', 'best-web-push-notifications' ),
			'source'          => __( 'Source', 'best-web-push-notifications' ),
			'failure_count'   => __( 'Failures in a row', 'best-web-push-notifications' ),
			'last_success_at' => __( 'Last delivery', 'best-web-push-notifications' ),
			'created_at'      => __( 'Subscribed', 'best-web-push-notifications' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at'      => array( 'created_at', true ),
			'failure_count'   => array( 'failure_count', false ),
			'last_success_at' => array( 'last_success_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bwpn_delete' => __( 'Delete', 'best-web-push-notifications' ),
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d">', (int) $item->id );
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'uuid':
				return '<code>' . esc_html( substr( (string) $item->uuid, 0, 8 ) ) . '</code>';
			case 'service':
				return esc_html( BWPN_Subscription_Validator::service_label( (string) $item->endpoint ) );
			case 'wp_user_id':
				$user = $item->wp_user_id ? get_userdata( (int) $item->wp_user_id ) : false;
				return $user ? '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>' : '-';
			case 'created_at':
			case 'last_success_at':
				return esc_html( bwpn_admin_date( $item->$column_name ) );
		}
		return esc_html( (string) ( $item->$column_name ?? '' ) );
	}

	/**
	 * Loads the rows.
	 */
	public function prepare_items() {
		$per_page = 30;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sorting only.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$total                 = BWPN_Lib_Db::count_subscribers();
		$this->items           = BWPN_Lib_Db::get_subscribers( ( $this->get_pagenum() - 1 ) * $per_page, $per_page, $orderby, $order );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}
}

/**
 * Logs (database driver).
 */
class BWPN_Admin_Logs_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'created_at' => __( 'Date', 'best-web-push-notifications' ),
			'level'      => __( 'Level', 'best-web-push-notifications' ),
			'message'    => __( 'Message', 'best-web-push-notifications' ),
		);
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'created_at' === $column_name ) {
			return esc_html( bwpn_admin_date( $item->created_at ) );
		}
		return esc_html( (string) ( $item->$column_name ?? '' ) );
	}

	/**
	 * Loads the rows.
	 */
	public function prepare_items() {
		$per_page              = 50;
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$total                 = BWPN_Lib_Db::count_logs();
		$this->items           = BWPN_Lib_Db::get_logs( ( $this->get_pagenum() - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}
}

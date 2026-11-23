<?php
/**
 * REST endpoints used by the browser SDK and the service worker.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes under /wp-json/bwpn/v1:
 *
 * - POST subscriptions              Create or refresh a subscription.
 * - POST subscriptions/unsubscribe  Remove a subscription.
 * - POST subscriptions/status       Whether an endpoint is subscribed.
 *
 * The endpoint URL itself acts as the credential: it is an unguessable
 * capability URL issued by the push service to that browser. No REST nonce
 * is required, so page-cached HTML with an expired nonce no longer breaks
 * subscribing (upstream returned 403). A stale nonce sent by an old SDK is
 * ignored on these routes instead of rejecting the request.
 *
 * Abuse controls: per-IP and global rate limits, push-host allowlist and
 * strict key validation (BWPN_Subscription_Validator).
 */
class BWPN_Subscribers_Rest {

	public const NAMESPACE = 'bwpn/v1';

	/** Subscribe calls allowed site-wide per minute. */
	public const GLOBAL_LIMIT = 600;

	/**
	 * Registers hooks.
	 */
	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'ignore_stale_nonce' ), 200 );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$subscription_args = array(
			'endpoint'         => array(
				'type'     => 'string',
				'required' => true,
			),
			'keys'             => array(
				'type'       => 'object',
				'required'   => true,
				'properties' => array(
					'p256dh' => array( 'type' => 'string' ),
					'auth'   => array( 'type' => 'string' ),
				),
			),
			'content_encoding' => array(
				'type'    => 'string',
				'default' => 'aes128gcm',
				'enum'    => BWPN_Subscription_Validator::ENCODINGS,
			),
			'old_endpoint'     => array(
				'type' => 'string',
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscriptions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => $subscription_args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/unsubscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'unsubscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'endpoint' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'endpoint' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Whether the request targets one of our routes.
	 *
	 * @return bool
	 */
	private static function is_our_route() {
		$route = '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		} elseif ( isset( $_GET['rest_route'] ) ) {
			$route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
		}
		// phpcs:enable
		return 0 === strpos( '/' . ltrim( $route, '/' ), '/' . self::NAMESPACE . '/subscriptions' );
	}

	/**
	 * Drops the invalid-nonce error on our public routes; the request then
	 * runs as a visitor.
	 *
	 * @param WP_Error|null|true $result Authentication result.
	 * @return WP_Error|null|true
	 */
	public static function ignore_stale_nonce( $result ) {
		if ( is_wp_error( $result ) && 'rest_cookie_invalid_nonce' === $result->get_error_code() && self::is_our_route() ) {
			wp_set_current_user( 0 );
			return null;
		}
		return $result;
	}

	/**
	 * Applies the rate limits.
	 *
	 * @return true|WP_Error
	 */
	private static function check_rate_limit() {
		$ip = BWPN_Client_Ip::get();
		if ( ! BWPN_Rate_Limiter::hit( 'subscribe', $ip, BWPN_Settings::int( 'rate_limit_per_ip' ), MINUTE_IN_SECONDS )
			|| ! BWPN_Rate_Limiter::hit( 'subscribe', 'global', (int) apply_filters( 'bwpn_global_rate_limit', self::GLOBAL_LIMIT ), MINUTE_IN_SECONDS ) ) {
			return new WP_Error(
				'bwpn_rate_limited',
				__( 'Too many requests. Try again shortly.', 'best-web-push-notifications' ),
				array(
					'status'      => 429,
					'retry_after' => BWPN_Rate_Limiter::retry_after( MINUTE_IN_SECONDS ),
				)
			);
		}
		return true;
	}

	/**
	 * POST /subscriptions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function subscribe( WP_REST_Request $request ) {
		if ( ! BWPN_Settings::bool( 'widget_enabled' ) ) {
			return new WP_Error( 'bwpn_disabled', __( 'Subscriptions are disabled.', 'best-web-push-notifications' ), array( 'status' => 403 ) );
		}
		$limited = self::check_rate_limit();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$keys  = (array) $request->get_param( 'keys' );
		$valid = BWPN_Subscription_Validator::validate(
			$request->get_param( 'endpoint' ),
			$keys['p256dh'] ?? '',
			$keys['auth'] ?? '',
			(string) $request->get_param( 'content_encoding' )
		);
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );
			return $valid;
		}

		$user_id  = get_current_user_id();
		$existing = BWPN_Lib_Db::get_subscriber_by_endpoint( $valid['endpoint'] );
		$old      = (string) $request->get_param( 'old_endpoint' );
		if ( ! $existing && '' !== $old && $old !== $valid['endpoint'] ) {
			// pushsubscriptionchange: the browser rotated the endpoint.
			$existing = BWPN_Lib_Db::get_subscriber_by_endpoint( $old );
		}

		if ( $existing ) {
			$data = $valid;
			if ( $user_id ) {
				$data['wp_user_id'] = $user_id;
			}
			BWPN_Lib_Db::update_subscriber( (int) $existing->id, $data );
			return rest_ensure_response(
				array(
					'uuid'    => $existing->uuid,
					'created' => false,
				)
			);
		}

		$valid['wp_user_id'] = $user_id;
		$valid['source']     = 'subscribe';
		$id                  = BWPN_Lib_Db::create_subscriber( $valid );
		if ( false === $id ) {
			// Lost a race with a parallel request for the same endpoint.
			$existing = BWPN_Lib_Db::get_subscriber_by_endpoint( $valid['endpoint'] );
			if ( $existing ) {
				return rest_ensure_response(
					array(
						'uuid'    => $existing->uuid,
						'created' => false,
					)
				);
			}
			return new WP_Error( 'bwpn_not_saved', __( 'The subscription could not be saved.', 'best-web-push-notifications' ), array( 'status' => 500 ) );
		}
		$subscriber = BWPN_Lib_Db::get_subscriber( $id );

		/**
		 * Fires after a new subscription is stored.
		 *
		 * @param object $subscriber Row.
		 */
		do_action( 'bwpn_subscribed', $subscriber );

		if ( BWPN_Settings::bool( 'send_welcome_message' ) && $subscriber ) {
			BWPN_Lib_Push_Server::send_now( array( $subscriber ), BWPN_Lib_Payload::build( BWPN_Settings::text( 'welcome_message' ) ) );
		}

		$response = rest_ensure_response(
			array(
				'uuid'    => $subscriber ? $subscriber->uuid : '',
				'created' => true,
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * POST /subscriptions/unsubscribe.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function unsubscribe( WP_REST_Request $request ) {
		$limited = self::check_rate_limit();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$endpoint = BWPN_Subscription_Validator::validate_endpoint( $request->get_param( 'endpoint' ) );
		if ( is_wp_error( $endpoint ) ) {
			$endpoint->add_data( array( 'status' => 400 ) );
			return $endpoint;
		}
		$deleted = BWPN_Lib_Db::delete_subscriber_by_endpoint( $endpoint );
		return rest_ensure_response( array( 'deleted' => $deleted > 0 ) );
	}

	/**
	 * POST /subscriptions/status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function status( WP_REST_Request $request ) {
		$endpoint = BWPN_Subscription_Validator::validate_endpoint( $request->get_param( 'endpoint' ) );
		if ( is_wp_error( $endpoint ) ) {
			$endpoint->add_data( array( 'status' => 400 ) );
			return $endpoint;
		}
		$subscriber = BWPN_Lib_Db::get_subscriber_by_endpoint( $endpoint );
		return rest_ensure_response(
			array(
				'subscribed' => (bool) $subscriber,
				'uuid'       => $subscriber ? $subscriber->uuid : null,
			)
		);
	}
}

<?php
/**
 * PSR-18 and HTTPlug adapters on top of the WordPress HTTP stack.
 *
 * Loaded only after vendor-prefixed/autoload.php, because the classes
 * implement prefixed vendor interfaces.
 *
 * @package BWPN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWPN\Vendor\Http\Client\HttpAsyncClient;
use BWPN\Vendor\Http\Promise\Promise;
use BWPN\Vendor\Nyholm\Psr7\Factory\Psr17Factory;
use BWPN\Vendor\Psr\Http\Client\ClientInterface;
use BWPN\Vendor\Psr\Http\Client\NetworkExceptionInterface;
use BWPN\Vendor\Psr\Http\Message\RequestInterface;
use BWPN\Vendor\Psr\Http\Message\ResponseInterface;

/**
 * Sends HTTP requests in parallel with the Requests library bundled in
 * WordPress (cURL multi when available). No Guzzle is needed, WordPress
 * proxy settings (WP_PROXY_*) and its CA bundle are respected, and tests can
 * swap the transport for a fake push service.
 */
final class BWPN_Http {

	/**
	 * Test seam: callable( array $requests ): array. Each request is
	 * array{url: string, headers: array<string, string>, body: string};
	 * each result is array{status: int, headers: array<string, string>,
	 * body: string} or a WP_Error, with the same keys.
	 *
	 * @var callable|null
	 */
	public static $handler = null;

	/**
	 * Sends requests in parallel.
	 *
	 * @param array<int|string, array{url: string, headers: array<string, string>, body: string}> $requests Requests.
	 * @return array<int|string, array{status: int, headers: array<string, string>, body: string}|WP_Error>
	 */
	public static function send_multiple( array $requests ) {
		if ( empty( $requests ) ) {
			return array();
		}

		$handler = self::$handler ?? apply_filters( 'bwpn_http_handler', null );
		if ( is_callable( $handler ) ) {
			return (array) call_user_func( $handler, $requests );
		}

		$prepared = array();
		foreach ( $requests as $key => $request ) {
			$prepared[ $key ] = array(
				'url'     => $request['url'],
				'headers' => $request['headers'],
				'data'    => $request['body'],
				'type'    => 'POST',
			);
		}

		$options = array(
			'timeout'          => (float) apply_filters( 'bwpn_http_timeout', 15 ),
			'connect_timeout'  => 8,
			'useragent'        => 'BestWebPushNotifications/' . BWPN_VERSION . '; ' . home_url( '/' ),
			'verify'           => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			'follow_redirects' => false,
		);

		$proxy = new WP_HTTP_Proxy();
		if ( $proxy->is_enabled() ) {
			$auth             = $proxy->use_authentication() ? array( $proxy->username(), $proxy->password() ) : array();
			$options['proxy'] = array_merge( array( $proxy->host() . ':' . $proxy->port() ), $auth );
		}

		$results = array();
		try {
			$responses = \WpOrg\Requests\Requests::request_multiple( $prepared, $options );
		} catch ( Throwable $e ) {
			foreach ( array_keys( $requests ) as $key ) {
				$results[ $key ] = new WP_Error( 'bwpn_http', $e->getMessage() );
			}
			return $results;
		}

		foreach ( $responses as $key => $response ) {
			if ( $response instanceof \WpOrg\Requests\Response ) {
				$headers = array();
				foreach ( $response->headers as $name => $value ) {
					$headers[ (string) $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				}
				$results[ $key ] = array(
					'status'  => (int) $response->status_code,
					'headers' => $headers,
					'body'    => (string) $response->body,
				);
			} else {
				$message         = $response instanceof Throwable ? $response->getMessage() : 'Unknown transport error';
				$results[ $key ] = new WP_Error( 'bwpn_http', $message );
			}
		}
		return $results;
	}

	/**
	 * Converts a PSR-7 request to the transport format.
	 *
	 * @param RequestInterface $request Request.
	 * @return array{url: string, headers: array<string, string>, body: string}
	 */
	public static function from_psr( RequestInterface $request ) {
		$headers = array();
		foreach ( $request->getHeaders() as $name => $values ) {
			if ( 0 === strcasecmp( (string) $name, 'Content-Length' ) ) {
				continue; // The transport computes it.
			}
			$headers[ (string) $name ] = implode( ', ', $values );
		}
		return array(
			'url'     => (string) $request->getUri(),
			'headers' => $headers,
			'body'    => (string) $request->getBody(),
		);
	}

	/**
	 * Converts a transport result to a PSR-7 response.
	 *
	 * @param Psr17Factory                                                  $factory Factory.
	 * @param array{status: int, headers: array<string, string>, body: string} $result Result.
	 * @return ResponseInterface
	 */
	public static function to_psr( Psr17Factory $factory, array $result ) {
		$response = $factory->createResponse( (int) $result['status'] );
		foreach ( (array) $result['headers'] as $name => $value ) {
			try {
				$response = $response->withHeader( (string) $name, (string) $value );
			} catch ( Throwable $e ) {
				continue; // Ignore malformed headers from the push service.
			}
		}
		return $response->withBody( $factory->createStream( (string) $result['body'] ) );
	}
}

/**
 * Transport failure (DNS, TLS, timeout).
 */
final class BWPN_Http_Network_Exception extends RuntimeException implements NetworkExceptionInterface {

	/**
	 * Failed request.
	 *
	 * @var RequestInterface
	 */
	private $request;

	/**
	 * Constructor.
	 *
	 * @param string           $message Message.
	 * @param RequestInterface $request Request.
	 */
	public function __construct( $message, RequestInterface $request ) {
		parent::__construct( $message );
		$this->request = $request;
	}

	/**
	 * Returns the failed request.
	 *
	 * @return RequestInterface
	 */
	public function getRequest(): RequestInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PSR interface.
		return $this->request;
	}
}

/**
 * Synchronous PSR-18 client (used by WebPush::flush()).
 */
final class BWPN_Http_Client implements ClientInterface {

	/**
	 * PSR-17 factory.
	 *
	 * @var Psr17Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @param Psr17Factory $factory Factory.
	 */
	public function __construct( Psr17Factory $factory ) {
		$this->factory = $factory;
	}

	/**
	 * Sends one request.
	 *
	 * @param RequestInterface $request Request.
	 * @return ResponseInterface
	 * @throws BWPN_Http_Network_Exception On transport failure.
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PSR interface.
		$results = BWPN_Http::send_multiple( array( 0 => BWPN_Http::from_psr( $request ) ) );
		$result  = $results[0] ?? new WP_Error( 'bwpn_http', 'No response' );
		if ( is_wp_error( $result ) ) {
			throw new BWPN_Http_Network_Exception( esc_html( $result->get_error_message() ), $request ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return BWPN_Http::to_psr( $this->factory, $result );
	}
}

/**
 * HTTPlug async client used by WebPush::flushPooled(). Requests are queued
 * and sent in parallel, $concurrency at a time, the first time any promise
 * is waited on.
 */
final class BWPN_Http_Async_Client implements HttpAsyncClient {

	/**
	 * Pending requests.
	 *
	 * @var array<int, array{0: RequestInterface, 1: BWPN_Http_Promise}>
	 */
	private $queue = array();

	/**
	 * PSR-17 factory.
	 *
	 * @var Psr17Factory
	 */
	private $factory;

	/**
	 * Requests per parallel batch.
	 *
	 * @var int
	 */
	private $concurrency;

	/**
	 * Constructor.
	 *
	 * @param Psr17Factory $factory     Factory.
	 * @param int          $concurrency Requests per parallel batch.
	 */
	public function __construct( Psr17Factory $factory, $concurrency = 50 ) {
		$this->factory     = $factory;
		$this->concurrency = max( 1, (int) $concurrency );
	}

	/**
	 * Queues a request.
	 *
	 * @param RequestInterface $request Request.
	 * @return BWPN_Http_Promise
	 */
	public function sendAsyncRequest( RequestInterface $request ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- HTTPlug interface.
		$promise       = new BWPN_Http_Promise( $this );
		$this->queue[] = array( $request, $promise );
		return $promise;
	}

	/**
	 * Sends every queued request and settles the promises.
	 */
	public function flush() {
		while ( ! empty( $this->queue ) ) {
			$chunk       = array_splice( $this->queue, 0, $this->concurrency );
			$transported = array();
			foreach ( $chunk as $i => $item ) {
				$transported[ $i ] = BWPN_Http::from_psr( $item[0] );
			}
			$results = BWPN_Http::send_multiple( $transported );
			foreach ( $chunk as $i => $item ) {
				$result = $results[ $i ] ?? new WP_Error( 'bwpn_http', 'No response' );
				if ( is_wp_error( $result ) ) {
					$item[1]->reject( new BWPN_Http_Network_Exception( $result->get_error_message(), $item[0] ) );
				} else {
					$item[1]->resolve( BWPN_Http::to_psr( $this->factory, $result ) );
				}
			}
		}
	}
}

/**
 * Minimal deferred promise compatible with HTTPlug.
 */
final class BWPN_Http_Promise implements Promise {

	/**
	 * State.
	 *
	 * @var string
	 */
	private $state = Promise::PENDING;

	/**
	 * Fulfilled value.
	 *
	 * @var mixed
	 */
	private $value = null;

	/**
	 * Rejection reason.
	 *
	 * @var Throwable|null
	 */
	private $reason = null;

	/**
	 * Registered callbacks.
	 *
	 * @var array<int, array{0: callable|null, 1: callable|null, 2: BWPN_Http_Promise}>
	 */
	private $callbacks = array();

	/**
	 * Client that settles this promise.
	 *
	 * @var BWPN_Http_Async_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param BWPN_Http_Async_Client $client Client.
	 */
	public function __construct( BWPN_Http_Async_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Registers callbacks.
	 *
	 * @param callable|null $on_fulfilled On success.
	 * @param callable|null $on_rejected  On failure.
	 * @return BWPN_Http_Promise
	 */
	public function then( ?callable $on_fulfilled = null, ?callable $on_rejected = null ) {
		$child             = new self( $this->client );
		$this->callbacks[] = array( $on_fulfilled, $on_rejected, $child );
		if ( Promise::PENDING !== $this->state ) {
			$this->settle_callbacks();
		}
		return $child;
	}

	/**
	 * Current state.
	 *
	 * @return string
	 */
	public function getState() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- HTTPlug interface.
		return $this->state;
	}

	/**
	 * Waits for the promise; sends queued requests if needed.
	 *
	 * @param bool $unwrap Return the value / throw the reason.
	 * @return mixed
	 * @throws Throwable When rejected and $unwrap is true.
	 */
	public function wait( $unwrap = true ) {
		if ( Promise::PENDING === $this->state ) {
			$this->client->flush();
		}
		if ( Promise::PENDING === $this->state ) {
			$this->reject( new RuntimeException( 'The request was never sent' ) );
		}
		if ( ! $unwrap ) {
			return null;
		}
		if ( Promise::REJECTED === $this->state && null !== $this->reason ) {
			throw $this->reason;
		}
		return $this->value;
	}

	/**
	 * Fulfils the promise.
	 *
	 * @param mixed $value Value.
	 */
	public function resolve( $value ) {
		if ( Promise::PENDING !== $this->state ) {
			return;
		}
		$this->state = Promise::FULFILLED;
		$this->value = $value;
		$this->settle_callbacks();
	}

	/**
	 * Rejects the promise.
	 *
	 * @param Throwable $reason Reason.
	 */
	public function reject( Throwable $reason ) {
		if ( Promise::PENDING !== $this->state ) {
			return;
		}
		$this->state  = Promise::REJECTED;
		$this->reason = $reason;
		$this->settle_callbacks();
	}

	/**
	 * Runs callbacks registered so far.
	 */
	private function settle_callbacks() {
		$callbacks       = $this->callbacks;
		$this->callbacks = array();
		foreach ( $callbacks as $callback ) {
			list( $on_fulfilled, $on_rejected, $child ) = $callback;
			try {
				if ( Promise::FULFILLED === $this->state ) {
					$child->resolve( $on_fulfilled ? $on_fulfilled( $this->value ) : $this->value );
				} elseif ( $on_rejected ) {
					$child->resolve( $on_rejected( $this->reason ) );
				} elseif ( null !== $this->reason ) {
					$child->reject( $this->reason );
				}
			} catch ( Throwable $e ) {
				$child->reject( $e );
			}
		}
	}
}

<?php
/**
 * Expose the narrow MailPoet operations required by HeySummit.
 *
 * @package HeySummit_MailPoet_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register authenticated REST endpoints for HeySummit attendee syncs.
 */
class HeySummit_MailPoet_REST_Controller extends WP_REST_Controller {
	/**
	 * Build MailPoet gateway instances.
	 *
	 * @var callable
	 */
	private $gateway_factory;

	/**
	 * Configure the API namespace and injectable gateway factory.
	 *
	 * @param callable|null $gateway_factory Factory used to build the MailPoet gateway.
	 */
	public function __construct( $gateway_factory = null ) {
		$this->namespace       = 'heysummit-mailpoet/v1';
		$this->rest_base       = 'connector';
		$this->gateway_factory = $gateway_factory ?: static function () {
			return new HeySummit_MailPoet_Gateway();
		};
	}

	/**
	 * Register status, list, field, sync, and tagging endpoints.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lists',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_lists' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fields' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/subscribers/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_subscriber' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/subscribers/(?P<subscriber_id>\\d+)/tags',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'tag_subscriber' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Restrict connector access to users with the dedicated sync capability.
	 *
	 * WordPress Application Passwords authenticate the remote request before this
	 * capability check runs.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'heysummit_sync_mailpoet' );
	}

	/**
	 * Report connector and MailPoet readiness.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status() {
		$gateway = $this->get_ready_gateway();
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		return rest_ensure_response(
			array(
				'ready'          => true,
				'plugin_version' => HEYSUMMIT_MAILPOET_CONNECTOR_VERSION,
				'api_version'    => 'v1',
			)
		);
	}

	/**
	 * Return active MailPoet list choices.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_lists() {
		$gateway = $this->get_ready_gateway();
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		try {
			$lists = array_map(
				static function ( $list ) {
					return array(
						'id'   => (string) $list['id'],
						'name' => (string) $list['name'],
					);
				},
				$gateway->get_lists()
			);

			return rest_ensure_response( array( 'lists' => $lists ) );
		} catch ( Throwable $exception ) {
			return $this->mailpoet_error( $exception );
		}
	}

	/**
	 * Return MailPoet subscriber fields for HeySummit mapping choices.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_fields() {
		$gateway = $this->get_ready_gateway();
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		try {
			return rest_ensure_response( array( 'fields' => $gateway->get_fields() ) );
		} catch ( Throwable $exception ) {
			return $this->mailpoet_error( $exception );
		}
	}

	/**
	 * Create or update one subscriber while preserving MailPoet consent settings.
	 *
	 * @param WP_REST_Request $request Incoming sync request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_subscriber( $request ) {
		$gateway = $this->get_ready_gateway();
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || ! isset( $payload['subscriber'] ) || ! is_array( $payload['subscriber'] ) ) {
			return new WP_Error( 'invalid_subscriber', 'A subscriber object is required.', array( 'status' => 400 ) );
		}

		try {
			$subscriber = $this->sanitize_subscriber( $payload['subscriber'], $gateway->get_fields() );
			if ( is_wp_error( $subscriber ) ) {
				return $subscriber;
			}

			$list_ids = isset( $payload['list_ids'] ) && is_array( $payload['list_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $payload['list_ids'] ) ) ) ) : array();
			if ( empty( $list_ids ) ) {
				return new WP_Error( 'invalid_lists', 'At least one MailPoet list is required.', array( 'status' => 400 ) );
			}

			return rest_ensure_response( $gateway->sync_subscriber( $subscriber, $list_ids ) );
		} catch ( Throwable $exception ) {
			return $this->mailpoet_error( $exception );
		}
	}

	/**
	 * Append a tag to an existing MailPoet subscriber.
	 *
	 * @param WP_REST_Request $request Incoming tag request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function tag_subscriber( $request ) {
		$gateway = $this->get_ready_gateway();
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}

		$tag = sanitize_text_field( (string) $request->get_param( 'tag' ) );
		if ( '' === $tag ) {
			return new WP_Error( 'invalid_tag', 'A tag name is required.', array( 'status' => 400 ) );
		}

		try {
			$subscriber = $gateway->tag_subscriber( absint( $request->get_param( 'subscriber_id' ) ), $tag );

			return rest_ensure_response( array( 'subscriber' => $subscriber ) );
		} catch ( Throwable $exception ) {
			return $this->mailpoet_error( $exception );
		}
	}

	/**
	 * Build a gateway and verify MailPoet setup before serving data requests.
	 *
	 * @return HeySummit_MailPoet_Gateway|WP_Error
	 */
	private function get_ready_gateway() {
		try {
			$gateway = call_user_func( $this->gateway_factory );
			if ( ! $gateway->is_setup_complete() ) {
				return new WP_Error( 'mailpoet_not_ready', 'MailPoet setup must be completed before connecting HeySummit.', array( 'status' => 503 ) );
			}

			return $gateway;
		} catch ( Throwable $exception ) {
			return new WP_Error( 'mailpoet_unavailable', $exception->getMessage(), array( 'status' => 503 ) );
		}
	}

	/**
	 * Restrict subscriber payloads to fields currently defined by MailPoet.
	 *
	 * @param array $subscriber Raw subscriber fields.
	 * @param array $fields     MailPoet field definitions.
	 *
	 * @return array|WP_Error
	 */
	private function sanitize_subscriber( $subscriber, $fields ) {
		$allowed = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['id'] ) ) {
				$allowed[] = (string) $field['id'];
			}
		}

		$clean = array();
		foreach ( $subscriber as $key => $value ) {
			$key = (string) $key;
			if ( ! in_array( $key, $allowed, true ) || is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$clean[ $key ] = is_bool( $value ) || is_int( $value ) || is_float( $value ) ? $value : sanitize_text_field( (string) $value );
		}

		$email = isset( $clean['email'] ) ? sanitize_email( $clean['email'] ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'A valid subscriber email is required.', array( 'status' => 400 ) );
		}
		$clean['email'] = $email;

		return $clean;
	}

	/**
	 * Convert a MailPoet API exception into a bounded REST error response.
	 *
	 * @param Throwable $exception MailPoet API exception.
	 *
	 * @return WP_Error
	 */
	private function mailpoet_error( $exception ) {
		$code   = (int) $exception->getCode();
		$status = 400;

		if ( 4 === $code ) {
			$status = 404;
		} elseif ( 12 === $code ) {
			$status = 409;
		} elseif ( 0 === $code ) {
			$status = 500;
		}

		return new WP_Error(
			'mailpoet_api_error',
			$exception->getMessage(),
			array(
				'status'        => $status,
				'mailpoet_code' => $code,
			)
		);
	}
}

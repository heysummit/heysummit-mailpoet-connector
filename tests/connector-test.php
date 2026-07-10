<?php
/**
 * Exercise the connector contract with WordPress and MailPoet test doubles.
 */

define( 'ABSPATH', __DIR__ );
define( 'HEYSUMMIT_MAILPOET_CONNECTOR_VERSION', '0.1.0' );

$GLOBALS['heysummit_test_can_manage'] = true;
$GLOBALS['heysummit_test_capability'] = '';
$GLOBALS['heysummit_test_routes']     = array();

/** Minimal WordPress REST controller stand-in. */
class WP_REST_Controller {
	/** @var string */
	public $namespace;
	/** @var string */
	public $rest_base;
}

/** Minimal WordPress REST server constants. */
class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
}

/** Minimal WordPress error object. */
class WP_Error {
	/** @var string */
	public $code;
	/** @var string */
	public $message;
	/** @var array */
	public $data;

	/** Store a WordPress-style error. */
	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

/** Minimal WordPress REST response. */
class WP_REST_Response {
	/** @var mixed */
	public $data;

	/** Store response data. */
	public function __construct( $data ) {
		$this->data = $data;
	}
}

/** Minimal request object supporting JSON, params, and route variables. */
class WP_REST_Request {
	/** @var array */
	private $json;
	/** @var array */
	private $params;

	/** Store request values. */
	public function __construct( $json = array(), $params = array() ) {
		$this->json   = $json;
		$this->params = $params;
	}

	/** Return the JSON body. */
	public function get_json_params() {
		return $this->json;
	}

	/** Return one request parameter. */
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}

}

/** Return the configured capability result. */
function current_user_can( $capability ) {
	$GLOBALS['heysummit_test_capability'] = $capability;
	return $GLOBALS['heysummit_test_can_manage'];
}

/** Store registered route metadata for assertions. */
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['heysummit_test_routes'][ $namespace . $route ] = $args;
}

/** Return whether a value is a WordPress error. */
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

/** Wrap values in a REST response. */
function rest_ensure_response( $value ) {
	return new WP_REST_Response( $value );
}

/** Sanitize a plain text value. */
function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}

/** Sanitize an email address. */
function sanitize_email( $value ) {
	return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

/** Validate an email address. */
function is_email( $value ) {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

/** Convert a value to a positive integer. */
function absint( $value ) {
	return abs( (int) $value );
}

/** Fake the supported MailPoet API for contract tests. */
class Fake_MailPoet_API {
	/** @var array */
	public $subscribers = array();
	/** @var array */
	public $memberships = array();
	/** @var array */
	public $tags = array();

	/** Report completed setup. */
	public function isSetupComplete() {
		return true;
	}

	/** Return active, trashed, and unsupported list examples. */
	public function getLists() {
		return array(
			array( 'id' => '3', 'name' => 'Newsletter', 'type' => 'default', 'deleted_at' => null ),
			array( 'id' => '4', 'name' => 'Deleted', 'type' => 'default', 'deleted_at' => '2026-01-01 00:00:00' ),
			array( 'id' => '5', 'name' => 'Dynamic', 'type' => 'dynamic', 'deleted_at' => null ),
		);
	}

	/** Return standard and custom subscriber fields. */
	public function getSubscriberFields() {
		return array(
			array( 'id' => 'email', 'name' => 'Email', 'type' => 'text' ),
			array( 'id' => 'first_name', 'name' => 'First name', 'type' => 'text' ),
			array( 'id' => 'last_name', 'name' => 'Last name', 'type' => 'text' ),
			array( 'id' => 'cf_1', 'name' => 'Company', 'type' => 'text' ),
		);
	}

	/** Return a subscriber or MailPoet's documented not-found error. */
	public function getSubscriber( $email ) {
		if ( ! isset( $this->subscribers[ $email ] ) ) {
			throw new RuntimeException( 'Subscriber not found.', 4 );
		}

		return $this->subscribers[ $email ];
	}

	/** Add a new subscriber. */
	public function addSubscriber( $subscriber, $list_ids ) {
		$subscriber['id']     = (string) ( count( $this->subscribers ) + 10 );
		$subscriber['status'] = 'subscribed';
		$this->subscribers[ $subscriber['email'] ] = $subscriber;
		$this->memberships[ $subscriber['email'] ] = $list_ids;

		return $subscriber;
	}

	/** Update an existing subscriber. */
	public function updateSubscriber( $email, $subscriber ) {
		$this->subscribers[ $email ] = array_merge( $this->subscribers[ $email ], $subscriber );

		return $this->subscribers[ $email ];
	}

	/** Add an existing subscriber to lists. */
	public function subscribeToLists( $email, $list_ids ) {
		$this->memberships[ $email ] = $list_ids;

		return $this->subscribers[ $email ];
	}

	/** Append a tag to an existing subscriber. */
	public function tagSubscriber( $subscriber_id, $tag ) {
		foreach ( $this->subscribers as $email => $subscriber ) {
			if ( (string) $subscriber['id'] === (string) $subscriber_id ) {
				$this->tags[ $subscriber_id ][] = $tag;
				return $this->subscribers[ $email ];
			}
		}

		throw new RuntimeException( 'Subscriber not found.', 4 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-heysummit-mailpoet-gateway.php';
require_once dirname( __DIR__ ) . '/includes/class-heysummit-mailpoet-rest-controller.php';

/** Fail the test run when two values differ. */
function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$api        = new Fake_MailPoet_API();
$gateway    = new HeySummit_MailPoet_Gateway( $api );
$controller = new HeySummit_MailPoet_REST_Controller(
	static function () use ( $gateway ) {
		return $gateway;
	}
);

$controller->register_routes();
assert_same( 5, count( $GLOBALS['heysummit_test_routes'] ), 'All connector routes should be registered.' );
assert_same( true, $controller->permissions_check(), 'Administrators should be allowed.' );
assert_same( 'heysummit_sync_mailpoet', $GLOBALS['heysummit_test_capability'], 'Connector access should use the dedicated capability.' );
$GLOBALS['heysummit_test_can_manage'] = false;
assert_same( false, $controller->permissions_check(), 'Non-administrators should be denied.' );
$GLOBALS['heysummit_test_can_manage'] = true;

$lists_response = $controller->get_lists();
assert_same( array( array( 'id' => '3', 'name' => 'Newsletter' ) ), $lists_response->data['lists'], 'Only active standard lists should be exposed.' );

$create_response = $controller->sync_subscriber(
	new WP_REST_Request(
		array(
			'subscriber' => array(
				'email'      => 'taylor@example.com',
				'first_name' => 'Taylor',
				'cf_1'       => 'HeySummit',
				'ignored'    => 'not-a-mailpoet-field',
			),
			'list_ids'  => array( '3' ),
		)
	)
);
assert_same( true, $create_response->data['created'], 'The first sync should create a subscriber.' );
assert_same( '10', $create_response->data['subscriber']['id'], 'The created subscriber id should be returned.' );
assert_same( false, isset( $api->subscribers['taylor@example.com']['ignored'] ), 'Unknown subscriber fields should be removed.' );
assert_same( array( 3 ), $api->memberships['taylor@example.com'], 'The requested list should be assigned.' );

$update_response = $controller->sync_subscriber(
	new WP_REST_Request(
		array(
			'subscriber' => array(
				'email'      => 'taylor@example.com',
				'first_name' => 'Tay',
			),
			'list_ids'  => array( 3 ),
		)
	)
);
assert_same( false, $update_response->data['created'], 'A later sync should update the existing subscriber.' );
assert_same( 'Tay', $api->subscribers['taylor@example.com']['first_name'], 'Existing subscriber data should be updated.' );

$tag_response = $controller->tag_subscriber( new WP_REST_Request( array(), array( 'subscriber_id' => 10, 'tag' => 'Revenue Summit' ) ) );
assert_same( '10', $tag_response->data['subscriber']['id'], 'Tagging should return the subscriber id.' );
assert_same( array( 'Revenue Summit' ), $api->tags[10], 'Tags should be appended through MailPoet.' );

$invalid_response = $controller->sync_subscriber(
	new WP_REST_Request(
		array(
			'subscriber' => array( 'email' => 'not-an-email' ),
			'list_ids'  => array( 3 ),
		)
	)
);
assert_same( 'invalid_email', $invalid_response->code, 'Invalid email addresses should be rejected.' );

fwrite( STDOUT, "Connector contract tests passed.\n" );

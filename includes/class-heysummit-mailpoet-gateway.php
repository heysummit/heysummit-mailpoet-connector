<?php
/**
 * Adapt MailPoet's in-process PHP API for the HeySummit REST controller.
 *
 * @package HeySummit_MailPoet_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wrap the supported MailPoet v1 API behind a small connector contract.
 */
class HeySummit_MailPoet_Gateway {
	/**
	 * MailPoet API client.
	 *
	 * @var object
	 */
	private $api;

	/**
	 * Build a gateway around the supplied API client or the active MailPoet plugin.
	 *
	 * @param object|null $api MailPoet-compatible API client, primarily for tests.
	 *
	 * @throws RuntimeException When MailPoet is unavailable.
	 */
	public function __construct( $api = null ) {
		if ( null !== $api ) {
			$this->api = $api;
			return;
		}

		if ( ! class_exists( '\\MailPoet\\API\\API' ) ) {
			throw new RuntimeException( 'MailPoet must be installed and active.' );
		}

		$this->api = \MailPoet\API\API::MP( 'v1' );
	}

	/**
	 * Return whether MailPoet has completed its own setup.
	 *
	 * @return bool
	 */
	public function is_setup_complete() {
		return (bool) $this->api->isSetupComplete();
	}

	/**
	 * Return active MailPoet lists that accept direct subscriptions.
	 *
	 * @return array
	 */
	public function get_lists() {
		$lists = $this->api->getLists();

		return array_values(
			array_filter(
				$lists,
				static function ( $list ) {
					$is_active  = empty( $list['deleted_at'] );
					$is_standard = empty( $list['type'] ) || 'default' === $list['type'];

					return $is_active && $is_standard;
				}
			)
		);
	}

	/**
	 * Return the default and custom fields accepted by MailPoet subscribers.
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->api->getSubscriberFields();
	}

	/**
	 * Create or update a subscriber and ensure they belong to the requested lists.
	 *
	 * MailPoet's default confirmation and welcome-email options are intentionally
	 * retained so the customer's own consent settings remain authoritative.
	 *
	 * @param array $subscriber Subscriber fields accepted by MailPoet.
	 * @param array $list_ids   MailPoet list identifiers.
	 *
	 * @return array
	 *
	 * @throws Throwable When MailPoet rejects the operation.
	 */
	public function sync_subscriber( $subscriber, $list_ids ) {
		$email    = $subscriber['email'];
		$existing = $this->find_subscriber( $email );
		$created  = false;

		if ( $existing ) {
			$result = $this->api->updateSubscriber( $email, $subscriber );
			$this->api->subscribeToLists( $email, $list_ids );
		} else {
			try {
				$result  = $this->api->addSubscriber( $subscriber, $list_ids );
				$created = true;
			} catch ( Throwable $exception ) {
				if ( 12 !== (int) $exception->getCode() ) {
					throw $exception;
				}

				// Another request may have created the subscriber after our lookup.
				$result = $this->api->updateSubscriber( $email, $subscriber );
				$this->api->subscribeToLists( $email, $list_ids );
			}
		}

		return array(
			'created'    => $created,
			'subscriber' => $this->normalize_subscriber( $result, $email ),
		);
	}

	/**
	 * Append a MailPoet tag to a subscriber without replacing existing tags.
	 *
	 * @param int|string $subscriber_id MailPoet subscriber identifier.
	 * @param string     $tag           Tag name to append.
	 *
	 * @return array
	 */
	public function tag_subscriber( $subscriber_id, $tag ) {
		$result = $this->api->tagSubscriber( $subscriber_id, $tag );

		return $this->normalize_subscriber( $result );
	}

	/**
	 * Find a subscriber by email, returning null for MailPoet's not-found code.
	 *
	 * @param string $email Subscriber email address.
	 *
	 * @return array|null
	 *
	 * @throws Throwable When the lookup fails for another reason.
	 */
	private function find_subscriber( $email ) {
		try {
			return $this->api->getSubscriber( $email );
		} catch ( Throwable $exception ) {
			if ( 4 === (int) $exception->getCode() ) {
				return null;
			}

			throw $exception;
		}
	}

	/**
	 * Return only the subscriber details needed by HeySummit.
	 *
	 * @param array  $subscriber MailPoet subscriber response.
	 * @param string $email      Fallback email address.
	 *
	 * @return array
	 */
	private function normalize_subscriber( $subscriber, $email = '' ) {
		return array(
			'id'     => isset( $subscriber['id'] ) ? (string) $subscriber['id'] : '',
			'email'  => isset( $subscriber['email'] ) ? (string) $subscriber['email'] : $email,
			'status' => isset( $subscriber['status'] ) ? (string) $subscriber['status'] : '',
		);
	}
}

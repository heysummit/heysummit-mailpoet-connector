<?php
/**
 * Plugin Name:       HeySummit Connector for MailPoet
 * Plugin URI:        https://heysummit.com/
 * Description:       Securely sync HeySummit attendees into MailPoet lists.
 * Version:           0.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Requires Plugins:  mailpoet
 * Author:            HeySummit
 * Author URI:        https://heysummit.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       heysummit-mailpoet-connector
 */

defined( 'ABSPATH' ) || exit;

define( 'HEYSUMMIT_MAILPOET_CONNECTOR_VERSION', '0.1.0' );
define( 'HEYSUMMIT_MAILPOET_CONNECTOR_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-heysummit-mailpoet-gateway.php';
require_once __DIR__ . '/includes/class-heysummit-mailpoet-rest-controller.php';

/**
 * Register the least-privilege role and capability used by connector requests.
 */
function heysummit_mailpoet_connector_register_role() {
	$role = add_role(
		'heysummit_connector',
		'HeySummit Connector',
		array(
			'read'                         => true,
			'heysummit_sync_mailpoet'      => true,
		)
	);

	if ( ! $role ) {
		$role = get_role( 'heysummit_connector' );
	}

	if ( $role ) {
		$role->add_cap( 'heysummit_sync_mailpoet' );
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'heysummit_sync_mailpoet' );
	}
}

register_activation_hook( __FILE__, 'heysummit_mailpoet_connector_register_role' );

/**
 * Register the connector's REST API after WordPress and MailPoet are loaded.
 */
function heysummit_mailpoet_connector_register_routes() {
	$controller = new HeySummit_MailPoet_REST_Controller();
	$controller->register_routes();
}

add_action( 'rest_api_init', 'heysummit_mailpoet_connector_register_routes' );

=== HeySummit Connector for MailPoet ===
Contributors: heysummit
Tags: heysummit, mailpoet, events, subscribers, email marketing
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely sync HeySummit attendees into MailPoet lists.

== Description ==

HeySummit Connector for MailPoet provides the authenticated bridge used by HeySummit's MailPoet integration. It lets HeySummit retrieve list and field choices, create or update subscribers, assign subscribers to lists, and append optional tags.

The connector uses WordPress Application Passwords and adds a least-privilege HeySummit Connector role. It preserves the MailPoet site's confirmation and welcome-email settings.

== Installation ==

1. Install and configure MailPoet.
2. Upload and activate HeySummit Connector for MailPoet.
3. Create a dedicated WordPress user with the HeySummit Connector role.
4. Edit that user and create an Application Password for HeySummit.
5. Enter the WordPress site URL, connector username, and Application Password in HeySummit's MailPoet integration settings.
6. Select a MailPoet list and save the integration.

== Frequently Asked Questions ==

= Does the connector bypass MailPoet double opt-in? =

No. Subscriber creation and list assignment use MailPoet's default confirmation and welcome-email behaviour, so the MailPoet site's own settings remain authoritative.

= Does the connector expose other WordPress data? =

No. It only exposes the MailPoet operations required by HeySummit and requires an authenticated user with the dedicated HeySummit Connector capability.

== Changelog ==

= 0.1.0 =

* Initial release.

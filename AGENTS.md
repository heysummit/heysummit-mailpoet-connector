# AGENTS.md (HeySummit Connector for MailPoet)

## Purpose

- Keep this repository as a narrow WordPress bridge between HeySummit and MailPoet.
- Use MailPoet's supported `\\MailPoet\\API\\API::MP('v1')` interface. Do not query MailPoet database tables or depend on its internal classes.
- Keep attendee mapping, retries, list selection, sync logs, and product configuration in the main HeySummit application.

## Security and consent

- Authenticate remote requests with WordPress Application Passwords.
- Authorize connector routes with the dedicated `heysummit_sync_mailpoet` capability. Do not replace this with broad administrator capabilities.
- Preserve the least-privilege `HeySummit Connector` role and verify it cannot edit posts, users, or general site settings.
- Keep the REST surface limited to readiness, list discovery, field discovery, subscriber sync, and append-only tagging.
- Preserve MailPoet's default confirmation, notification, and welcome-email options. Do not bypass the site's double opt-in or resubscribe policy.
- Do not log Application Passwords or subscriber payloads.

## Compatibility

- Maintain PHP 7.4 compatibility until the plugin's `Requires PHP` header is intentionally raised.
- Keep the `Requires Plugins: mailpoet` header aligned with the WordPress.org MailPoet slug.
- In `.wp-env.json`, install `https://downloads.wordpress.org/plugin/mailpoet.zip` before the local plugin entry. Filename-derived slugs and reversed activation order break dependency activation.

## Verification

- Run PHP syntax checks and the connector contract tests on PHP 7.4 and a current supported PHP version.
- Start `wp-env` and confirm MailPoet and the connector activate together.
- Confirm all connector REST routes register, unauthenticated requests fail, the dedicated role can reach connector routes, and that role cannot create WordPress posts.
- Confirm the current MailPoet plugin still exposes every API method used by the gateway.
- Run WordPress Plugin Check against production files, excluding development-only `tests`, `.github`, `scripts`, `dist`, local environment files, and package metadata.

## Releases

- GitHub is the development source of truth. WordPress.org SVN is only a future distribution channel.
- Run `npm run build:zip` for the production archive. The ZIP must contain only the main plugin file, `includes/`, `readme.txt`, and `LICENSE`.
- Do not commit generated `dist/` archives or `node_modules/`.

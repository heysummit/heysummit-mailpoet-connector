# HeySummit Connector for MailPoet

This WordPress plugin exposes the narrow, authenticated REST contract required to sync HeySummit attendees into MailPoet.

## What it does

- Lists active MailPoet mailing lists.
- Lists MailPoet custom subscriber fields.
- Creates or updates a subscriber by email.
- Adds subscribers to the selected list while preserving MailPoet's confirmation and welcome-email settings.
- Appends tags without replacing a subscriber's existing tags.

The plugin does not store HeySummit credentials and does not expose general WordPress or MailPoet administration. Requests authenticate through WordPress Application Passwords and require the dedicated `heysummit_sync_mailpoet` capability. Activation adds a least-privilege **HeySummit Connector** role for the remote connection and also grants the capability to administrators.

## Local development

The repository includes a `wp-env` configuration that installs the current WordPress release, this plugin, and the current MailPoet release.

```bash
npm install
npm run env:start
```

The local WordPress dashboard is available at `http://localhost:8888/wp-admin` using the standard `wp-env` credentials.

Run the dependency-free connector contract tests with PHP 7.4 or later:

```bash
php tests/connector-test.php
```

Build the production ZIP without development files:

```bash
npm run build:zip
```

## Connecting HeySummit

1. Install and configure MailPoet.
2. Install and activate this connector.
3. Create a dedicated WordPress user with the **HeySummit Connector** role.
4. Edit that user and create an Application Password for HeySummit.
5. In HeySummit, enter the WordPress site URL, connector username, and generated Application Password.
6. Select the MailPoet list and optional field mappings or tag.

Application Passwords are revocable and should only be used over HTTPS.

## REST contract

All routes use the `heysummit-mailpoet/v1` namespace and require an authenticated user with the connector capability:

- `GET /wp-json/heysummit-mailpoet/v1/connector/status`
- `GET /wp-json/heysummit-mailpoet/v1/connector/lists`
- `GET /wp-json/heysummit-mailpoet/v1/connector/fields`
- `POST /wp-json/heysummit-mailpoet/v1/connector/subscribers/sync`
- `POST /wp-json/heysummit-mailpoet/v1/connector/subscribers/{id}/tags`

## License

GPL-2.0-or-later.

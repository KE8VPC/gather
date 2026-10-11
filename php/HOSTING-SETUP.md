# Gather hosting setup reference

This guide uses `example.com` as a placeholder. Replace it and `/gather` with the actual domain and installation path before entering values in hosting or provider consoles. Installation success and provider-console configuration must be verified on the target site. For the current walkthrough and troubleshooting, use [OAUTH-SETUP.md](OAUTH-SETUP.md).

## Upload and database

Upload the package's complete `gather` folder to the web directory serving `example.com`. Include hidden `.htaccess` files. Create an empty database and its user in your hosting panel, then open **https://example.com/gather/install/**. The browser installer checks folder protection, creates the tables, saves configuration and creates your first sign-in. Delete **`gather/install/`** when it finishes. See [INSTALL.md](INSTALL.md) for requirements and troubleshooting.

For an existing Gather database, preserve `config/local.php` and `storage/installed.lock`. Back up first and apply `php bin/upgrade.php` or `database/upgrade-1.2.sql` only if the 1.2 migration has not been applied; do not reimport the full schema or rerun the fresh installer. Version 1.3.0 adds no new schema migration.

The installer sets these values automatically in the private **hosted** `config/local.php` (manual installations should use the same values):

```php
'app_url' => 'https://example.com/gather',
'secure_cookies' => true,
'oauth_callback_url' => 'https://example.com/gather/public/oauth.php',
```

Keep the local development configuration pointed at localhost. Configure the database with your hosting provider's actual database hostname, database name and credentials.

## Values for the provider forms

After registering each application, enter its client ID and secret in **Administration → API settings**, at `/gather/public/admin.php`. See [ADMIN.md](ADMIN.md) for owner activation. This replaces manual editing of provider credentials; the field names below remain a reference for older/manual setups.

| Field | Value |
| --- | --- |
| App name | Gather (choose a unique name if Twitch requires it) |
| Homepage | `https://example.com/gather/` |
| Privacy policy | `https://example.com/gather/public/legal.php?page=privacy` |
| Terms of use | `https://example.com/gather/public/legal.php?page=terms` |
| Google authorized domain | `example.com` |
| Google authorized redirect URI | `https://example.com/gather/public/oauth.php?provider=youtube` |
| Twitch OAuth redirect URL | `https://example.com/gather/public/oauth.php?provider=twitch` |

Copy each callback exactly, including its query string. The `oauth_callback_url` configuration value above intentionally omits the query string; Gather adds the provider when it constructs the redirect.

## Google / YouTube

1. Open [Google Cloud Console](https://console.cloud.google.com/), choose/create the appropriate project, and enable **YouTube Data API v3**.
2. Configure Google Auth Platform/OAuth consent with Gather's name, the real support contact, the domain and page URLs above. Replace the operator/email placeholders on the legal pages before public launch or verification. Keep the actual contact address private until you intentionally publish it in the policy.
3. Request `https://www.googleapis.com/auth/youtube.readonly`. Create a **Web application** OAuth client and register the Google redirect URI above.
4. Add your own account as a test user while the project is in testing. Follow Google's required verification process before public rollout as applicable. Test and production projects should be separate.
5. Save the complete client ID and secret through Gather's **Administration → API settings**. Google only shows the full secret when it is created; see [OAUTH-SETUP.md](OAUTH-SETUP.md) for the current workflow.

The separate `youtube_api_key` is used for live-status checks; it does not replace the OAuth client ID and secret used for account linking. See [Google's web application guide](https://developers.google.com/identity/protocols/oauth2/web-server).

## Twitch

1. Open [Twitch Developer Console](https://dev.twitch.tv/console/apps). Verify your Twitch account's email and enable two-factor authentication if needed.
2. Register an application for Gather and add the exact Twitch redirect URL above. Use the confidential/server-side client type if prompted.
3. Save the client ID and secret through Gather's **Administration → API settings**. Gather requests `user:read:follows`; users grant it when connecting. These credentials also support Twitch live checks.

See [Twitch's application registration guide](https://dev.twitch.tv/docs/authentication/register-app/). Do not regenerate an existing application's secret unless you intend to replace it everywhere it is used.

## Encryption and scheduled maintenance

**If you used the browser installer, the encryption key already exists. Do not regenerate it.** It protects linked tokens and saved admin settings. The command below is only for an older manual installation that has no key or encrypted data yet:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

For that older manual setup only, save the result as `oauth_encryption_key` in `config/local.php`. Keep it stable across upgrades. Back up the original key together with private admin settings and the database; losing it makes encrypted data unreadable.

Schedule `php /YOUR/ACTUAL/WEB/DIRECTORY/gather/bin/maintain-connections.php` hourly through the hosting panel. Replace the placeholder with the host's real absolute path and PHP executable. This checks saved authorizations and removes stale/revoked connections; it does not import new follows automatically. Users choose **Sync now** for that.

## Verify before enabling public use

From the uploaded `gather` directory, run:

```sh
php bin/preflight.php
php bin/check-connections.php
php bin/check-web-access.php https://example.com/gather
```

`check-connections.php` reports missing fields without printing credentials or tokens. A configuration pass is not proof that the provider accepts the app or callbacks. Test actual consent, denial, first import, repeat sync, token refresh, disconnect and two separate Gather accounts in a regular browser. Follow the detailed acceptance list in [CONNECTIONS.md](CONNECTIONS.md).

Rumble and other new platform integrations remain deferred.

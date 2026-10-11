# YouTube and Twitch linked accounts

**Version 1.4.0:** Enter provider credentials in **Administration → API settings** instead of editing configuration. See [ADMIN.md](ADMIN.md) to activate your administrator account. The manual configuration instructions below remain available as a reference.

Gather 1.2 adds **Linked accounts** to the sidebar and import dialog. Users sign in to their existing Gather account, connect YouTube or Twitch, approve read access on the provider's own website, and return to an automatic first import. **Sync now** reads their current list again. This release links providers to Gather accounts; it does not add Google/Twitch as replacements for Gather's own sign-in.

## Upgrade an existing installation

1. Back up the database and private configuration outside the web root. Keep `config/local.php` and its existing values.
2. Upload the new package over the installation, including all hidden `.htaccess` files.
3. Run `php bin/upgrade.php`, or import `database/upgrade-1.2.sql` with phpMyAdmin. The migration adds two tables and can be rerun. Do not import the full schema into an existing database.
4. Add the new configuration keys below to the array in `config/local.php`. Do not replace your configuration with the example file. The rest of Gather works without provider credentials once the migration is applied.
5. Run `php bin/preflight.php` and repeat the private-folder HTTP access check in INSTALL.md.

Fresh installations should use `database/schema.sql`, which includes these tables.

For deployment configuration, use [HOSTING-SETUP.md](HOSTING-SETUP.md) as a template. Run `php bin/check-connections.php` after configuring credentials to identify missing setup without exposing secrets.

## Shared setup

Use HTTPS and secure cookies in production. Generate one encryption key on your own host:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Keep the result private; set `oauth_encryption_key` to it. Keep this key stable across updates and back it up separately from the database. Changing or losing it makes existing tokens unreadable; users must disconnect and reconnect. Tokens are stored using AES-256-GCM with an account-specific authenticated context. Do not put credentials or this key in browser code, uploaded ZIPs, public files, source control or chat messages.

Set `oauth_callback_url` to the exact URL of `public/oauth.php`, **without a query string**. For example:

```php
'oauth_callback_url' => 'https://example.com/tools/gather/public/oauth.php',
'oauth_encryption_key' => 'YOUR_GENERATED_BASE64_KEY',
'youtube_client_id' => '',
'youtube_client_secret' => '',
'twitch_client_id' => '',
'twitch_client_secret' => '',
```

Replace the domain and path with your installation. For a dedicated `public/` document root, the callback could instead be `https://gather.example.com/oauth.php`. The callback hostname and port must match `app_url`. Production callbacks must use HTTPS. Loopback HTTP is allowed only for local development. A PHP execution limit of at least 60 seconds is recommended for slow provider responses; imports are paginated across separate requests instead of one long request.

## Google / YouTube

1. Open [Google Cloud Console](https://console.cloud.google.com/). Use separate projects for testing and production. Enable **YouTube Data API v3**.
2. Configure the OAuth consent screen with the real application name, operator/contact details and your verified website domain. Publish the completed welcome, privacy and terms pages; replace the legal placeholders before verification/public launch.
3. Request only `https://www.googleapis.com/auth/youtube.readonly`. Create an OAuth client of type **Web application**.
4. Register this exact authorized redirect URI, changing the domain/path to yours:
   `https://example.com/tools/gather/public/oauth.php?provider=youtube`
5. Put the client ID and secret in `youtube_client_id` and `youtube_client_secret` in private configuration. These are separate from `youtube_api_key`, which still enables live-status checks for recent videos.
6. During testing, add allowed test users. Test-mode refresh tokens can expire after seven days for these permissions. Complete Google's applicable branding/scope verification before public rollout. Use a regular browser for Google authorization; embedded browsers may be rejected by Google.

The user must choose a Google/YouTube channel that supports the requested subscription access. Read access is requested with offline access for later user-triggered syncs. If Google omits a refresh token, the page asks the user to revoke Gather in Google connections and reconnect. One YouTube channel can be connected per Gather account, and the same provider identity cannot be assigned to two Gather accounts.

References: [Web server authorization](https://developers.google.com/identity/protocols/oauth2/web-server), [subscriptions API](https://developers.google.com/youtube/v3/docs/subscriptions/list), [OAuth policies](https://developers.google.com/identity/protocols/oauth2/policies), [YouTube developer policies](https://developers.google.com/youtube/terms/developer-policies).

## Twitch

1. Open the [Twitch developer console](https://dev.twitch.tv/console/apps) and register an application for your website. Follow Twitch's account/2FA requirements.
2. Use a confidential application with a client secret and register this exact OAuth redirect URL, changing the domain/path to yours:
   `https://example.com/tools/gather/public/oauth.php?provider=twitch`
3. Set `twitch_client_id` and `twitch_client_secret` in private configuration. Existing live-check credentials may be reused after the correct redirect URL is registered.
4. Gather requests only `user:read:follows` and imports **followed channels**, not a viewer's complete list of paid subscriptions. It validates the token's application, user and permissions before importing and refreshes expired access tokens when possible.

References: [Register an application](https://dev.twitch.tv/docs/authentication/register-app/), [authorization code flow](https://dev.twitch.tv/docs/authentication/getting-tokens-oauth/#authorization-code-grant-flow), [followed channels](https://dev.twitch.tv/docs/api/reference/#get-followed-channels), [token validation](https://dev.twitch.tv/docs/authentication/validate-tokens/).

## Sync, deletion and maintenance

- The first import starts on return from the provider. Keep the page open until complete. An interrupted job can be restarted with **Sync now**. Each page is staged in the user's PHP session; the library changes only when all pages complete successfully. Staging expires after 15 minutes for use; configure session-file garbage collection to remove inactive sessions promptly (no longer than 24 hours).
- Up to 1,000 unique creators fit in the combined library. Oversized provider lists or combined libraries return an explicit error without partially applying subscriptions. File imports remain limited to 100 links per operation.
- A complete sync adds new creators and removes previously imported creators that are no longer followed. Duplicates are skipped. Creators already present from a manual/file import are not claimed by the connection and survive disconnect. Removing an imported creator inside Gather alone may be undone by the next sync if it is still followed at the provider.
- Linked imports are excluded from all peer-recommendation inputs and counts. Existing recommendations continue to use follows added directly in Gather. Provider account names, identifiers and tokens remain private to their owner.
- **Disconnect & remove imports** deletes the provider connection and its imported subscriptions locally and attempts provider revocation. Failed revocation does not prevent local deletion; the user is told to remove access in the provider's settings too. Tokens never appear in API responses or browser storage. Do not log authorization query strings or response bodies: callbacks contain short-lived codes and state. Configure hosting logs accordingly.
- Run `php /absolute/path/to/gather/bin/maintain-connections.php` **hourly** through your hosting control panel's cron scheduler. This validates saved authorizations, removes revoked connections, removes connections/imports without a completed sync for 29 days, and purges public creator caches with no remaining subscribers. Monitor nonzero exits. A library/account page visit also removes that user's stale connections before displaying their data.
- Maintenance checks connection validity; it does not automatically sync new follows in the background. Users choose **Sync now**. The UI explains the 29-day retention window. Backup retention and restoration must honor deletion requests; run cleanup before exposing a restored database, and remove deleted user data from backups according to your documented policy.
- After a successful import, **Open and refresh your feed** loads recent content using the existing feed reader. Live checks still require the existing provider settings. Playback remains subject to provider restrictions.

## Acceptance before enabling publicly

Local automated checks use simulated provider responses and a real local MySQL database; they do **not** establish that a registered app works with Google or Twitch. On the actual host, test both providers with real consenting test accounts: allow/deny, correct account selection, multiple pages, duplicate re-sync, upstream unfollow, disconnected/revoked access, token refresh, two separate Gather users, returning from the provider at the exact nested path, imported content playback, and hourly maintenance. Check desktop/mobile and keyboard behavior. Google verification, host cron execution, provider quotas and live account authorization remain operator setup/acceptance steps.

The included policy text is an implementation-aligned starter draft, not a provider approval or legal review. Review the complete site's actual use, policies and media behavior for launch; filling these configuration keys alone does not certify compliance.

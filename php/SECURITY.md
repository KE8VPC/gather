# Security boundaries

## Browser installer (1.3.0)

Fresh setup requires HTTPS (except loopback PHP development), a session-bound CSRF token and the hosting database credentials. Run setup promptly after upload, then delete `/install/`. The wizard only accepts an empty database or its own interrupted attempt, never drops tables, and uses an exclusive file lock to serialize setup. Existing `config/local.php` or `storage/installed.lock` permanently disables setup; there is no web reset route.

Before database work, the installer verifies its own public probe and denies exposed private paths. Public self-checks reject internal IP destinations, pin the validated DNS address, retain certificate verification and disable redirects/proxies. Hosts must allow these HTTPS self-requests. The local development exception is restricted to the PHP development server and loopback request/host; a single-thread development server cannot service its own probes without a separate test proxy/worker.

Configuration is published from a fully written private temporary file without replacing an existing file. Account passwords are hashed; generated configuration, random encryption key and setup progress remain in private directories. Passwords are not echoed into error forms or saved in the setup session. Release packaging explicitly whitelists safe configuration files and excludes installation runtime files. These checks complement actual hosting access rules; production Apache/Nginx enforcement must still be verified on the host.

## Application

- The PHP session identifies the current user. Every API operation checks login; writes require a random session CSRF token. User IDs are never accepted from the browser as authority.
- PDO uses native prepared statements. User-scoped queries control subscription reads, removals and refreshes. Shared creator metadata contains public feed data; recommendations expose aggregate counts, not lists of other users.
- Passwords are stored using Argon2id where supported, otherwise PHP's PASSWORD_DEFAULT. Passwords are 12–72 bytes, login/registration attempts are rate-limited per IP and email, and sessions rotate after authentication. Sessions expire after two idle hours or 24 total hours. Password reset invalidates existing sessions.
- The application trusts only the configured Host value. Cookies are HttpOnly, SameSite=Lax and Secure in production, scoped to the application directory with a distinct name for each installation path. CSP restricts scripts to the site and frames to YouTube/Twitch. User/feed strings are output as escaped text, not executable HTML.
- Public feed requests use a fixed hostname allowlist, HTTPS only, no credentials or custom ports, no automatic redirects, public IPv4 validation and DNS pinning. Every redirect is revalidated. XML external entities/DTDs are rejected; LIBXML_NONET is used without entity substitution.
- Remote feeds, account registration and shared-host resource capacity still require operational monitoring. There is no WAF, moderation system, email ownership verification or self-service password recovery in this package.
- Subdirectory installs serve the root welcome page and public/ application. Private app/config/database/bin/tests/storage folders require their deny-all .htaccess files and an Apache configuration that honors them, or equivalent server-level rules. Verify private URLs return 403/404 before adding credentials. The alternative public/-only document root remains supported. Credentials and test configuration are excluded from release ZIPs. Never expose the bare PHP development server: it ignores .htaccess.
- This is implementation documentation and functional validation, not an independent security audit or a claim of regulatory certification.


## Linked accounts (1.2)

- Only an authenticated Gather session can link, sync or disconnect a provider. Connect/disconnect/sync POSTs require CSRF tokens; callbacks require a random, single-use, ten-minute state bound to the same Gather user/session and provider. Provider identity never authenticates another Gather account and email matching is not used for linking.
- Confidential server-side authorization-code flow, least-privilege read scopes, fixed provider endpoints, HTTPS, no redirects/proxies in token requests, response size/time bounds and generic provider errors. Tokens are encrypted with AES-256-GCM and bound to the user/provider/token field. The encryption key stays in private host configuration. Credentials and tokens are never returned to the browser or written to app logs. Configure hosting access logs to omit callback query strings.
- The connection page's CSP permits form redirects only to itself and the Google/Twitch authorization hosts. Provider strings and account names are escaped; scripts remain same-origin. Existing session/Host restrictions remain in force.
- Per-user database advisory locks serialize token refresh, connect, sync and disconnect across sessions. A connection generation rejects old sync jobs after reconnect. Imports are staged per session and applied transactionally only after complete pagination. Owner-derived user IDs control every query. One provider identity cannot replace a different user's connection.
- Provenance identifies newly imported subscriptions for deletion and excludes them from all peer-recommendation inputs. Manual existing subscriptions are preserved. Disconnect deletes local credentials and owned imports even if remote revocation fails. Required hourly maintenance checks grants and removes stale/revoked connections. See CONNECTIONS.md for retention, backup/session cleanup and acceptance obligations.
- These checks establish local implementation behavior with fixtures, not a live-provider certification or independent security audit.

Account-linking readiness also requires the callback scheme to match app_url and Secure cookies on HTTPS installations. The standalone check-connections CLI reports setup flags and valid callback URLs only; it does not print tokens, client secrets or encryption keys or send provider requests.

## API administration (1.4.0)

- A site administrator is assigned by a one-time ownership claim requiring an authenticated Gather user, CSRF token, current account password and the configured non-empty hosting database password. No first-registered-user assumption is made. The claim is serialized with a private file lock; after assignment, other accounts receive HTTP 403 for both GET and POST.
- Saving requires current administrator authorization, CSRF and password reauthentication, with per-user/IP throttling. Public use requires HTTPS and Secure cookies. Only the five supported provider credential fields can be overridden; database, session and encryption settings are excluded.
- Settings and administrator ID are encrypted and authenticated with AES-256-GCM under the existing installation key, written through a private temporary file and atomic replacement. Failed authentication/decryption fails closed. Secret fields are never populated with saved values or reflected after errors. Blank inputs preserve; explicit removal writes an empty override.
- Back up config/local.php and config/admin-settings.json together. Keep config web-denied and preserve the original encryption key. No web administrator-transfer/reset feature is provided. Package filters exclude all runtime settings and locks.

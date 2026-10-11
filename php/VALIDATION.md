# Validation — PHP/MySQL conversion

Verified locally on September 8, 2026 with PHP 8.4.25 and MySQL Community Server 8.4.11. These runtimes were provisioned in the ignored local work directory; they are not included in the upload package.

Passed:

- PHP syntax lint for every application, CLI, configuration-template and test PHP file.
- JavaScript syntax check for the standalone browser application.
- Fresh schema import into an empty real MySQL database.
- Configuration/database preflight, including required PHP extensions and all application tables.
- Unit tests: HTTPS/public-host policy, rejection of IP/credential/suffix tricks, canonical duplicate IDs, bounded XML archives with CDATA, unsafe media URLs, DTD/entity rejection, password hashing.
- HTTP integration tests through the PHP server with three distinct registered accounts: session-ID rotation, unauthorized read rejection, CSRF rejection, duplicate imports, separate user libraries, one- and two-peer recommendation counts, ownership-scoped removal and refresh, and logout.
- Real YouTube feed import: Marques Brownlee, 15 recent videos persisted.
- Real podcast import: NPR Planet Money, 30 recent episodes persisted with the bounded reader.
- Missing Twitch credentials reported as a connection note rather than a fabricated live status.
- Preview page and both CSS/JavaScript assets returned HTTP 200.
- Release ZIP inventory checked to include application/schema/docs/assets and exclude local configuration, secrets, database files and runtime binaries.

Not verified:

- Browser interaction, visual layout, keyboard/screen-reader behavior and media playback across browsers.
- Authenticated Twitch/YouTube live API requests: platform credentials were not supplied.
- The user's hosting account, Apache/Nginx configuration, HTTPS cookies, DNS, remote MySQL connectivity, backups/restore and multi-user behavior on that host.
- MariaDB compatibility through runtime execution.

The package is ready for upload and staging installation. Complete the acceptance checks in INSTALL.md before treating it as a production launch.

## September 10, 2026 — subdirectory update (1.1)

- Added a database-free root welcome page, served at an installation path such as `/gather/`.
- Verified the welcome page, relative links, CSS, favicon, sample preview and relative dashboard sign-in redirect under `/nested/gather/`.
- Reran the real MySQL three-account HTTP suite with the application mounted at `/nested/gather/public/`: registration, session rotation, cookie path, logout cookie deletion, CSRF, user libraries, duplicate imports, recommendations and real feed refresh passed.
- Unit checks passed for root, renamed and nested session cookie paths and distinct names across installations. All 16 application/CLI/test PHP files passed lint.
- Added deny-all .htaccess files in every private directory, root documentation/dotfile restrictions, and a command to check the installed server's HTTP access behavior.
- Local nested-route tests use a restricted PHP development router; they do NOT establish that Apache executes .htaccess rules. Run `php bin/check-web-access.php https://example.com/gather` on the target installation before adding production credentials. Target Apache/Nginx configuration and browser visual/playback QA remain unverified.
- No database migration is required. Existing installations keep their configuration and database and may require a fresh sign-in.

## Welcome registration count (1.1.1)

The welcome header now shows the total registered accounts from MySQL. The count is read on each page request, uses singular/plural labels, and exposes no account identities. If configuration or the database is unavailable, the badge is omitted and the welcome page stays accessible. PHP lint and an HTTP comparison against the real local MySQL count passed. The local count includes development test accounts; a fresh installed database starts at zero.

## Footer and legal pages (1.1.2)

Added automatic-year copyright, Privacy Policy and Terms of Use links to the welcome page, app/preview and sign-in/register page. Public policy pages use configurable operator/contact details with visible missing-value reminders. Seven changed/new PHP files passed syntax checks. Local HTTP checks passed for the nested welcome, preview, sign-in, registration and both legal pages; both policies set no session cookie, and invalid policy names or array inputs return 404. No database migration is required. Policy text is a starter draft requiring operator review before launch; hosted acceptance and browser visual QA were not performed for this change.

## Linked accounts (1.2.0) — September 11, 2026

- Added YouTube subscription / Twitch followed-channel linking for existing Gather users, automatic first import, paginated Sync now, reconnect and disconnect with imported-data removal. No Google/Twitch replacement sign-in or Rumble integration was added.
- The idempotent 1.2 migration was applied twice to the existing local MySQL database. Existing data remained available. The schema and upgrade scripts include both linked-account tables.
- Simulated-provider tests against real local MySQL passed: nested callback, read-only scopes, user-bound/expired/single-use state, authenticated token encryption, invalid callback host, cross-account identity protection, paging and duplicates, no partial writes on provider failure, oversized list rejection, repeated sync, private imports excluded from recommendations, upstream unfollow, manual-library preservation, access-token refresh, missing Twitch permissions, revocation failure/local cleanup, revoked refresh tokens and 29-day expiry.
- Local HTTP tests passed: nested routes; anonymous redirects/401; database-free settings preview; credentials-not-configured state; CSRF; wrong method; malformed/unsupported providers; forged callback; disconnect; and existing library access. The test account created by this suite was removed afterward.
- Existing three-account integration suite passed, including real public YouTube and NPR RSS fetches, user isolation, two-peer recommendations, duplicate imports, scoped removal and logout. These public feed fetches are not OAuth acceptance tests. The suite leaves its development accounts in the local database.
- All 25 distributable PHP source/test files passed syntax checks; both app scripts passed JavaScript syntax checks. Existing feed/parser/password and nested-cookie unit checks passed. Nested welcome/assets/preview redirects passed. Preflight sees both new tables and accurately reports both OAuth integrations as not configured. Maintenance completed with no saved real connections; fixture tests cover expired-data cleanup.
- Updated setup, privacy, security and upgrade documentation. The packaged archive excludes private local configuration, provider credentials, encryption keys and runtime/database files.
- **Still pending:** real Google/Twitch consent and denial, registered callback acceptance, provider-side refresh/revocation, Google verification as applicable, actual-host cron execution, Apache rule enforcement, browser visual/keyboard/mobile checks and provider playback acceptance. Local tests use a restricted development router, not Apache. No external deployment or independent security/legal audit was performed.

## Launch preparation (1.2.1) — September 11, 2026

- Added a deployment setup guide with generic `example.com` placeholders for the homepage, policy and provider callback values. Local development configuration was preserved; no credentials were created or entered and no external deployment was performed.
- Added the read-only `bin/check-connections.php` diagnostic. It reports missing client IDs/secrets, encryption key, callback/cookie readiness and database tables without displaying credentials or calling providers. In the current unconfigured environment it correctly returned NEEDS SETUP and a nonzero exit code.
- Tightened linking readiness to require matching application/callback schemes and Secure cookies for HTTPS. Three new regression cases passed (valid HTTPS nested setup, insecure cookies rejected, mismatched schemes rejected), along with the existing simulated-provider/real-MySQL suite. The three changed/new PHP files passed syntax checks.
- Actual in-app browser checks passed for the linked-account preview at the default desktop viewport and a 390x844 override, which yielded a 375px content width after the scrollbar. Linked-account and privacy pages reported no horizontal overflow. Verified mobile navigation, opening the import dialog, Escape dismissal and focus return to the invoking button, return-to-feed navigation and the privacy footer link. No console errors appeared in the inspected page logs. Viewport override was reset and the temporary browser tab closed afterward.
- These browser checks covered preview/unconfigured states, not connected provider actions, playback or authenticated account forms. A verified-TLS request to the intended hosted welcome page timed out; hosted reachability, Apache rules, real OAuth, cron, backups and production acceptance remain pending.
- The new archive includes the roadmap, host-specific guide and diagnostic; the previous 1.2.0 archive remains unchanged. No additional schema change is required beyond the existing 1.2 migration.

## Browser installer (1.3.0) — September 12, 2026

- Added `/install/` with automatic nested URL detection, server checks, database setup, generated private configuration/encryption key and first-account creation. Legal operator/contact placeholders remain configurable, and provider credentials are optional later setup. The welcome page offers installation only when unconfigured.
- All 30 distributable PHP files passed syntax checks. Existing parser/password unit checks passed. The dedicated `tests/install.php` suite passed 19 checks on a separate loopback MySQL 8.4 instance: URL/HTTPS/Host validation, internal-address probe rejection, password confirmation, DSN injection rejection, safe configuration serialization, seven tables, account hash, permanent locking, preservation of an occupied database and recovery from interrupted DDL. The user's existing database/configuration were not used for these installer mutations.
- HTTP tests against a disposable nested installation passed: setup form, invalid CSRF rejection, exposed-private-folder refusal before configuration, no password reflection, successful 303 redirect/completion reminder, locked repeat POST/configuration unchanged, disabled public probe, first-account sign-in, authenticated feed and welcome user count. Removing the fixture's installer folder returned 404 for setup while a fresh sign-in and authenticated feed still worked.
- Browser visual checks passed at the default desktop viewport and 390x844 mobile override. The mobile form reported no horizontal overflow; the locked state clearly displayed the deletion reminder and sign-in link. The temporary viewport was reset and the tab closed.
- HTTP access-denial tests used a loopback proxy and two PHP development workers, not Apache. Actual hosted HTTPS/self-request behavior, Apache/Nginx rules, file permissions/hard-link support, MariaDB compatibility, provider consent and production acceptance remain unverified. No site was deployed and no real Google/Twitch credentials were configured.
- Release 1.3.0 includes the deletable installer and revised four-step guide. Packaging whitelists safe configuration files and excludes local settings, keys, installation progress/probes and storage runtime files. Earlier release archives are preserved. Existing 1.2 sites require no additional schema migration for this release.

## API administration (1.4.0) — September 13, 2026

- User reported the 1.3 installer worked on their host. This is user-reported installation success; no independent hosted checks were performed in this release.
- Added an administrator-only API panel, one-time ownership verification for existing accounts, encrypted persistent settings and runtime overrides shared by application/CLI consumers. Original private configuration and existing data are preserved; there is no new SQL migration.
- All 33 distributable PHP files passed syntax checks and existing parser/password unit tests passed. Thirteen settings tests passed: ownership proof, empty-password rejection, exclusive claim, unauthorized write rejection, field allowlist, encrypted round-trip, blank preservation, explicit clearing, invalid field rejection and wrong-key/tamper failures.
- Eighteen HTTP checks passed against a separate disposable MySQL database and nested loopback app: anonymous redirect, owner activation, invalid CSRF/proof/password rejection, credential saving and non-disclosure, effective configuration/readiness, blank preservation, ordinary-user direct GET/POST denial, role-specific navigation and explicit secret removal disabling readiness. No external provider requests were made.
- In-app browser review confirmed administrator navigation, desktop API form and 390x844 mobile layout including lower save controls. The mobile document had equal viewport/content widths (375px after scrollbar), with no horizontal page overflow. Tests used synthetic credentials; real credentials were not entered in the browser.
- A full 1.4.0 package and an update-only package are provided. The latter excludes config, storage, database and installer directories so an existing 1.3.0 site can merge application files without rerunning setup. Hosted upgrade, real provider credential validity/OAuth, production access rules and independent security review remain unverified.

### Packaged update acceptance — September 14, 2026

The update ZIP was extracted with Windows Expand-Archive over the existing disposable installation. Configuration and encrypted settings hashes were unchanged, and the removed installer was not recreated. HTTP checks after extraction confirmed existing-account sign-in, administrator ownership, saved settings/readiness and an accessible authenticated feed. The setup URL remained 404. Temporary test services were stopped afterward. These are local package checks, not a hosted deployment.

## Connect button follow-up (1.4.1) — September 14, 2026

- Replaced disabled Connect controls with actionable setup/sign-in links. Administrators see missing-field, encryption-key or callback/cookie diagnostics without secret values. Ordinary users receive a contact-admin message.
- An authorized Connect POST now finishes on a same-origin continuation page. A separate script navigates to the fixed provider authorization URL, with a visible Continue link if automatic navigation fails. CSRF and single-use state remain required. This avoids depending on a form redirect chain across provider login hosts; it does not weaken the global CSP. Browser redirect differences are documented at https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/form-action.
- Eight CLI readiness checks and twelve local HTTP checks passed, including correct callback/scope/state, missing settings, anonymous/CSRF rejection, continuation markup, no secret disclosure and forged callback rejection. Changed PHP files and navigation JavaScript passed syntax checks.
- In-app browser confirmed the configured Twitch Connect button, missing YouTube-secret explanation and resulting continuation page. Automatic and manual continuation did not complete external navigation in this browser session; no console warning was captured. Cause of the user's hosted no-op and actual Twitch consent are therefore not confirmed. Use a regular browser for hosted acceptance.
- Package includes the researched OAuth setup guide. No schema migration, reinstallation or credential changes are required. Existing private configuration/settings are excluded from the update archive.

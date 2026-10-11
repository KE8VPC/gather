# Gather for PHP and MySQL

**Version 1.4.0:** Manage YouTube and Twitch credentials through **Administration**. See [ADMIN.md](ADMIN.md) for the update and one-time owner activation steps. Existing 1.3.0 sites need no database migration.

A standalone PHP conversion of the Gather content subscription hub. The server runs PHP; accounts and content are stored in MySQL. The browser uses plain JavaScript and CSS. No Node.js, React, Composer, build step, Cloudflare, or ChatGPT account is required on the host.

Use [HOSTING-SETUP.md](HOSTING-SETUP.md) as a deployment template; replace its `example.com` placeholders with your own domain and installation path.

See [ROADMAP.md](ROADMAP.md) for completed work, the next YouTube/Twitch launch checks, proposed improvements and deferred integrations.

## Easy installation (1.3.0)

Create an empty database in your hosting panel, upload the complete `gather` folder, and open **`/gather/install/`** over HTTPS. The installer creates the tables, writes configuration, generates the encryption key and creates your first account. Delete **`gather/install/`** after setup. YouTube/Twitch setup can wait until their applications are registered. See [INSTALL.md](INSTALL.md) for the four-step guide.

## Subdirectory installation

Open `/gather/` for the new welcome page and `/gather/public/` for the application. Folder names may be changed or nested. Upload every .htaccess file and follow the access checks in INSTALL.md. Existing installations must apply the safe 1.2 migration in CONNECTIONS.md before using this version. Keep existing configuration and database data.

## Included

- Email/password registration and login; secure sessions, CSRF protection, login rate limits, session rotation, server-side subscription ownership.
- Separate durable libraries, YouTube CSV imports, podcast OPML, Twitch URL lists and JSON, individual channel IDs and public feed links.
- Unified recent-content feed with platform filters, YouTube and Twitch embedded players, and podcast audio playback.
- Live section with two-minute refresh while visible and five-minute freshness cutoff.
- Recommendations ranked by distinct other users who share a manually added subscription and also follow the candidate creator. Linked-account imports are excluded from peer calculations.
- Database-free preview at `public/preview.php`; no account mutations or feed imports in preview mode.
- Linked accounts page with automatic first import, duplicate handling, Sync now, token refresh and disconnect; existing accounts use the safe 1.2 database upgrade.
- Configuration template, MySQL schema, preflight checks and account-management CLI commands.

See INSTALL.md to install and SECURITY.md for boundaries. Upload the complete `gather` folder into an existing Apache site, then open `/gather/` for the welcome page. The app stays under `/gather/public/`. The supplied private-folder `.htaccess` protections are required; run the web-access check in INSTALL.md before adding credentials. The separate `public/` document-root layout is also supported. The existing TypeScript Sites deployment is a separate application and is not changed by this package. Existing Sites accounts/data are not automatically migrated.

## Requirements

PHP 8.2+ (8.4 recommended), MySQL 8.0+, PDO MySQL, cURL with working CA certificates, mbstring, SimpleXML, OpenSSL, HTTPS. MySQL-compatible schema syntax is used, but MariaDB has not been runtime-tested. No package manager is needed.

## Limits and unfinished integrations

- YouTube and Twitch linked-account imports, Sync now and disconnect are implemented. Configure application credentials, callback URLs, encryption and hourly maintenance using CONNECTIONS.md. Live provider authorization/refresh still requires host acceptance.
- Twitch live checks need `twitch_client_id` and `twitch_client_secret`. YouTube live checks need `youtube_api_key` and cover only the recent RSS feed.
- No Spotify/Apple proprietary library imports, DRM/paywall bypass, email verification, self-service email password reset, billing, or moderation console.
- Supported public podcast hosts are listed in the import dialog and `app/Feeds.php`. Unknown hosts/redirects are rejected. Private/IP feed hosts and embedded credentials are rejected.
- Up to 100 links per import, 1,000 subscriptions per account, 30 recent entries parsed per feed, 60 retained per creator, 300 newest items returned per account. The reader stops after 30 complete items and caps downloaded decompressed data at roughly 2 MB.
- Manual feed refresh; live-only refresh runs while the Live now view is open. Linked-account maintenance runs hourly to check grants and purge stale data; new follows are synced when the user chooses Sync now. Each creator can refresh once per minute across users.
- Platform embeds may decline playback or require their own login. An original-platform link is always available.

## Validation commands

```
php bin/preflight.php
php tests/unit.php
node --check public/assets/app.js
```

`tests/http.mjs` is an optional developer integration test requiring Node.js, a configured disposable MySQL database and the PHP server at http://127.0.0.1:8088. It creates test accounts and imports real feeds; never run it against production. The host itself does not require Node.js. See VALIDATION.md for the checks actually performed on this release.



# Install Gather in a subdirectory

Already installed version 1.3.0? Use the **1.4.0 update ZIP** and [ADMIN.md](ADMIN.md) to add the API settings panel without rerunning installation.

## Easy browser setup (recommended for a new site)

1. In your hosting panel, create an **empty MySQL database** and a database user with access to it. Keep its hostname, database name, username and password handy.
2. Upload the package's complete `gather` folder, including hidden `.htaccess` files, into your existing website's web directory.
3. Open your installation's HTTPS address ending in `/install/` (for example, `https://example.com/gather/install/`; replace `example.com` with your own domain). Enter the database details, choose your first Gather sign-in, and click **Install Gather**.
4. When setup finishes, delete **`gather/install/`** using your hosting file manager or FTP app. Then sign in to Gather.

The installer checks PHP and folder permissions, verifies private folders are inaccessible from the web, creates the tables, generates the encryption key and saves `config/local.php`. It detects the website address and subdirectory automatically. No SQL import, configuration editing or command line is needed for a normal fresh installation. YouTube/Twitch credentials and the operator/contact details can be added later; blank legal details retain their visible reminders.

Use PHP 8.2+ with PDO MySQL, cURL, mbstring, SimpleXML, sessions and OpenSSL. MySQL 8.0+ is tested; MariaDB 10.6+ is accepted by the version check but has not been runtime-tested. PHP must be able to write to `config/` and `storage/`, and make an HTTPS request back to this website with working certificate verification. Apache must enforce the supplied `.htaccess` rules. Do not use permissions of 777.

The installer refuses non-empty databases and locks when configuration or the installed marker exists. If setup is interrupted, retry with the **same database and first-account email/password**. Existing rows are never erased. Removing `/install/` does not affect the application. Keep `config/local.php` and `storage/installed.lock` private and preserve them during updates.

**Already using Gather?** This is a fresh-install wizard, not an upgrade tool. Preserve your existing configuration and database. Apply the 1.2 migration only if you have not already done so; no new database migration is required for 1.3.0.

## Advanced/manual installation and hosting reference

The rest of this guide is for manual setup, troubleshooting and ongoing maintenance. Skip manual configuration and schema import if the browser installer has already completed.

Upload the complete `gather` folder into your existing site's document root. No new domain, subdomain or document-root change is required on Apache 2.4 hosting with `.htaccess` support.

For example:

```
public_html/
  existing-site-files...
  gather/
    index.php              <-- welcome page at /gather/
    .htaccess
    install/               <-- browser setup; delete after completion
    public/                <-- application at /gather/public/
      index.php
      auth.php
      api.php
      preview.php
      assets/
    app/                   <-- private: deny-all .htaccess
    config/                <-- private: deny-all .htaccess
    database/              <-- private: deny-all .htaccess
    bin/                   <-- private: deny-all .htaccess
    tests/                 <-- private: deny-all .htaccess
    storage/               <-- private: deny-all .htaccess
```

You may rename `gather` or nest it deeper, such as `/tools/gather/`. Links and assets are relative. The login cookie is scoped to the executing application directory, such as `/tools/gather/public/`, with a distinct name per path.

## 1. Upload and verify folder protection

Upload hidden `.htaccess` files in the root, `public` and every private folder. Apache must allow the supplied `Require`, `Options`, `DirectoryIndex` and `FilesMatch` directives (typically `AllowOverride All` for this directory). Parent-site rewrite rules must allow this real subdirectory through instead of routing it into a CMS.

**Before entering production database or platform credentials**, visit `https://example.com/gather/config/access-check.txt`. It must return HTTP 403 or 404, never the file's text. Then run, over SSH:

```
php bin/check-web-access.php https://example.com/gather
```

This checks the welcome page and CSS are accessible while all private folders, documentation and hidden files are denied. A 200 response for a private path is a failed installation. Ask the host to enable the access rules before configuring the app. Do not use PHP's bare development server for an exposed subdirectory installation; it ignores `.htaccess` files.

DreamHost: upload into a folder inside the existing site's web directory and use the MySQL hostname provided by the panel. There is no need to change the domain's web directory for this layout.

Nginx ignores `.htaccess`. Its administrator must configure equivalent denial for private directories, dotfiles and documentation before upload/configuration. If the host cannot enforce those rules, use the alternative `public/` document-root layout or move the private directories outside the web root with adjusted includes.

## 2. Create the database and configuration

Use PHP 8.2+ with PDO MySQL, cURL, mbstring, SimpleXML, sessions and OpenSSL, plus an empty MySQL 8.0+ database and dedicated database user. Enable HTTPS.

Copy `config/example.php` to `config/local.php` and set:

- `app_url`: the installation's exact public address, e.g. `https://example.com/gather` or `https://example.com/tools/gather`. The browser's host and port must match. Do not append `/index.php`.
- `db`: database host, port, database name, user and password. For a remote database requiring TLS, set `ssl_ca` to its CA file path.
- `secure_cookies`: true in production; false only for loopback HTTP development.
- `registration_enabled`: true for self-registration, false for owner-created accounts.
- `registration_code`: optional invitation code; empty means open registration when enabled.

Import `database/schema.sql` once into the empty database using phpMyAdmin or the MySQL client. **Existing installations: keep config/local.php and your existing database; do not reimport the schema.** Version 1.2 requires `php bin/upgrade.php` (or importing `database/upgrade-1.2.sql`) to add linked-account tables. Back up first; the migration preserves existing data and can be rerun. Existing users may need to sign in again because the session cookie is now scoped per installation.

Run `php bin/preflight.php`. Fix all failures. Configure a valid cURL CA trust bundle if necessary; never disable TLS certificate verification. Use normal hosting permissions (usually 755 directories, 644 files, and tighter access for config/local.php where supported). Never use 777.

## 3. Open Gather

- `https://example.com/gather/` — welcome page.
- `https://example.com/gather/public/index.php` — dashboard; prompts for sign-in when needed.
- `https://example.com/gather/public/auth.php` — login and account registration.
- `https://example.com/gather/public/preview.php` — database-free sample preview.

Click **Open your feed** on the welcome page to enter. New users can choose Create an account from the sign-in page when registration is enabled.

If registration is closed, set `GATHER_NEW_PASSWORD` in the current process environment and use `php bin/create-user.php email name`. Clear the variable afterward. For owner-assisted recovery, use `php bin/reset-password.php email`; it invalidates existing sessions. No verification/reset emails are sent.

## 4. Live platform connections

Put owner-managed credentials only in config/local.php:

- Twitch: `twitch_client_id` and `twitch_client_secret` enable server-side live checks.
- YouTube: `youtube_api_key` enables live checks for recent channel-feed videos.

Normal YouTube RSS and podcast imports require no platform authorization. Twitch URLs can be saved without credentials; live status shows a setup note until configured. YouTube and Twitch account linking is available after the additional setup in **CONNECTIONS.md**, including credentials, exact callback URLs and hourly maintenance.

## 5. Launch checks

Verify login/logout, two separate user libraries, imports/duplicates, feed refresh, shared-subscriber recommendations, media playback, mobile/keyboard use and known live/offline streams. Repeat `check-web-access.php` after uploading updates or changing server configuration. Back up and restore the database and private configuration outside the web root. Periodically delete expired auth_limits rows with `DELETE FROM auth_limits WHERE expires_at < UNIX_TIMESTAMP()`.

## Alternative: a dedicated public document root

The previous layout still works: point a dedicated site's document root at `gather/public/`, set app_url to that site's URL, and open the dashboard directly. Private directories then remain outside the web root; the new welcome page is optional and not served in that layout.

## Copyright and legal pages

The welcome page, app, preview and sign-in/register pages link to public Privacy Policy and Terms of Use pages. These work under the installation subdirectory and do not require an account or database connection.

Before launch, add `legal_operator` and `legal_contact_email` to the array in `config/local.php`, using the keys shown in `config/example.php`. Blank or missing values display explicit `[ADD SITE OPERATOR NAME]` and `[ADD PUBLIC CONTACT EMAIL]` reminders on both policy pages. Invalid email values also display the reminder. The footer uses Gather until an operator is supplied, and the copyright year updates automatically.

The policies in `public/legal.php` are starter drafts describing the bundled application. Review and customize them for your actual operator, hosting providers, retention practices, jurisdiction and any extensions before public launch. Update the revision date when changing policy text. The footer-only release added no database migration or registration consent checkbox. Version 1.2 adds the linked-account migration described above; the policy now covers linked-account data too.

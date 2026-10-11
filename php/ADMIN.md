# Administration and API credentials

For current Google/Twitch console instructions and error fixes, see [OAUTH-SETUP.md](OAUTH-SETUP.md), researched September 14, 2026.
## Update an installed Gather 1.3.0 site

1. Back up your existing Gather folder and database in your hosting panel.
2. Extract `gather-php-mysql-1.4.0-update.zip`. Upload the contents of its `gather` folder **into your existing `/gather/` folder**, replacing matching application files. Merge folders; do not delete or replace the entire existing folder. This update excludes `config/`, `storage/`, `database/` and `install/`.
3. Sign in and select **Administration**, or open **https://auglaize.co/gather/public/admin.php**.
4. Activate administration once using your hosting database password (the one used in installation) and your current Gather password. The signed-in account becomes the site's administrator. No SQL changes or configuration edits are needed.
5. Enter your Google/YouTube and Twitch application credentials, confirm your Gather password, and save.

Do not rerun the installer. This release needs no database migration for a working 1.3.0 site. Older versions still need the existing 1.2 linked-account migration. The full release ZIP is for fresh installations and includes `/install/`.

## Using the panel

- Google client ID and client secret enable YouTube account linking. The separate YouTube API key enables live checks. Twitch uses its client ID and client secret.
- Callback URLs are shown for copying into the provider consoles. The panel does not register provider applications or verify credentials with the provider. Complete those steps in Google/Twitch and test using **Linked accounts** afterward.
- A blank field keeps its current value. Use the explicit Remove checkbox to clear a credential. Removal overrides any original value in `local.php`.
- Saved credentials stay hidden. Replacing application credentials can require users to reconnect their accounts.
- The one-time ownership check requires the real, non-empty hosting database password. Later saves require only the administrator's Gather password. Ordinary accounts cannot access settings through the page, direct URLs or forged POSTs.

## Hosting and backup

The configuration directory must remain writable by PHP and blocked from web access. Public administration requires HTTPS and secure cookies. Credentials and the administrator ID are stored in authenticated encrypted `config/admin-settings.json`, using the existing `oauth_encryption_key` in `config/local.php`. Preserve both files and the original key together in private backups and future upgrades. Do not regenerate the key; linked accounts also depend on it.

The original `local.php` is unchanged. Application requests, linked-account actions, live checks and scheduled commands use the saved overrides through the shared configuration loader. Until settings are saved, the original configuration continues to work. Corrupt encrypted settings fail closed instead of silently reopening ownership registration.

This release supports one administrator. Use existing owner-assisted password recovery for that account if needed. Administrator transfer and general user management are not included. A manual installation without an encryption key needs the existing connection-key setup first; the 1.3 installer already generates that key.

## 1.4.1 connection-button update

Upload `gather-php-mysql-1.4.1-update.zip` by merging its gather contents into the existing folder and replacing matching application files. Keep the existing configuration and storage. Reload Linked accounts in a regular browser. If setup is incomplete, the administrator now sees the missing setting and a Set up link. Otherwise Connect opens the provider, with a visible Continue link as a fallback. No migration or installer rerun is needed.

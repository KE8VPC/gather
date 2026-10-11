# Gather: YouTube and Twitch setup, step by step

Checked against official documentation on September 14, 2026. Tailored to Gather 1.4.0 at https://auglaize.co/gather/. Provider-console labels can vary by account. These instructions were checked against Gather's source code; they are not confirmation of your current provider-console settings or successful live authorization.

## 1. Start here

**Your reported Google error: `400 redirect_uri_mismatch`.** Go directly to Google Auth Platform → Clients, select the web application whose client ID is saved in Gather, and edit **Authorized redirect URIs**. Add `https://auglaize.co/gather/public/oauth.php?provider=youtube`, save, and start a fresh connection from Gather's Linked accounts page. This value belongs in redirect URIs, not JavaScript origins. If it still fails, expand Google's error details and compare the `redirect_uri` value it received with this entry. Share only that callback value if you need help; do not share the complete authorization URL or any codes/secrets. Section 4D explains the web client setup.

Use a regular Chrome, Edge, Firefox or Safari window. Sign in to Gather with your existing Gather account. Open **Administration** at https://auglaize.co/gather/public/admin.php. If administration has not been activated, it will ask once for the database password used during installation and your Gather password.

Keep two tabs open: Gather Administration and the provider console. You will copy credentials from the console into Gather. Do not paste secrets into chat or upload downloaded credential JSON files to your public website.

There are three separate pieces to configure:

| Piece | What you enter in Gather | What it enables in this version |
|---|---|---|
| Google OAuth client | Google client ID + Google client secret | Users authorize importing their YouTube channel subscriptions |
| YouTube API key | YouTube API key (live checks) | Checks live status for YouTube videos already discovered in feeds |
| Twitch application | Twitch client ID + Twitch client secret | User-authorized Twitch follow imports and Twitch live checks |

These are application credentials shared by the site. Every user still grants access to their own provider account through **Linked accounts**. Gather currently uses its own email/password sign-in; linking a provider does not replace that sign-in. Twitch imports follows, not paid channel subscriptions. These distinctions come from Gather's implementation.

## 2. Exact addresses for your installation

| Console field | Value |
|---|---|
| App homepage | `https://auglaize.co/gather/` |
| Privacy policy | `https://auglaize.co/gather/public/legal.php?page=privacy` |
| Terms of use | `https://auglaize.co/gather/public/legal.php?page=terms` |
| Google authorized domain | `auglaize.co` |
| Google authorized redirect URI | `https://auglaize.co/gather/public/oauth.php?provider=youtube` |
| Twitch OAuth redirect URL | `https://auglaize.co/gather/public/oauth.php?provider=twitch` |

Compare these with the callback fields displayed in Gather Administration. If Gather displays localhost, a different domain, or a different directory, stop and correct the installation address before configuring the providers. Do not add `www`, remove `/public/`, omit the provider query, or add a trailing slash after `oauth.php`.

The callback is a return address for authorization. Start connections from **Linked accounts**, not by opening that callback address directly.

## 3. Set up Twitch

### Register the application

1. Open [Twitch Developer Console](https://dev.twitch.tv/console/apps) and sign in with the Twitch account that will own the application.
2. Verify that account's email and enable two-factor authentication. Refresh the developer console afterward.
3. Choose **Applications → Register Your Application**.
4. Enter a unique name, for example `Auglaize Gather` if available.
5. Paste the exact Twitch callback from section 2 into **OAuth Redirect URLs**. Click **Add** so it becomes a saved entry.
6. Choose the category that best describes your website. Complete the CAPTCHA and create the application.
7. Open **Manage** for that application. Copy its **Client ID**.
8. Choose **New Secret**, then copy and store the complete secret securely. Generating another secret invalidates the previous one.

Source: [Twitch application registration](https://dev.twitch.tv/docs/authentication/register-app/).

If a **Client Type** choice appears, use **Confidential** for Gather's PHP server. Gather uses authorization-code exchange for user connections and client credentials for live checks. It needs a client secret. Public/device-client instructions are for a different flow. This choice follows Gather's implementation and [Twitch's documented OAuth flows](https://dev.twitch.tv/docs/authentication/getting-tokens-oauth/).

### Save and connect

1. In Gather Administration, enter **Twitch client ID** and **Twitch client secret** from the same application.
2. Enter your Gather password under **Confirm changes** and click **Save API settings**.
3. Open **Linked accounts → Connect Twitch**.
4. Authorize the correct Twitch account and return to Gather. Gather requests `user:read:follows` automatically; you do not paste this scope into a credential field.
5. Wait for the first import. Check **Subscriptions**, then try **Sync now**.

Do not obtain tokens from a third-party token generator. Gather performs the token exchange itself. A successful follow import is the first milestone; an empty Live now section can simply mean none of those creators are broadcasting.

## 4. Set up Google / YouTube OAuth

### A. Select one project and enable YouTube

Open [Google Cloud Console](https://console.cloud.google.com/). Create or select a project, such as `Gather`. Keep that same project selected for every Google step below. Under **APIs & Services → Library**, find **YouTube Data API v3** and enable it. If it is already enabled, continue. Gather's current integration does not require YouTube Analytics or Reporting APIs.

Google requires a project with YouTube Data API enabled and appropriate authorization credentials. See [YouTube's getting-started guide](https://developers.google.com/youtube/v3/getting-started).

### B. Configure the current consent-screen interface

1. Open **Google Auth Platform → Branding**. If prompted, select **Get started**.
2. Enter the app name, a real support email and a monitored developer contact email.
3. Choose **External** for Gather's intended audience of users outside a single Workspace organization.
4. Complete Google's introductory setup, reviewing its policy agreement before accepting it.
5. Open **Audience**. Keep initial setup in **Testing** and add the exact Google email you will use to connect as a **Test user**.
6. Open **Data Access → Add or remove scopes**. Add `https://www.googleapis.com/auth/youtube.readonly` and save. If absent from the picker, confirm YouTube Data API v3 is enabled and use the manual scope entry if offered.

Older tutorials may call this simply “OAuth consent screen.” Google's current instructions use Branding, Audience and Data Access. [Official consent-screen setup](https://developers.google.com/workspace/guides/configure-oauth-consent).

### C. Enter branding links and your domain

In **Branding**, register `auglaize.co` as an authorized domain and use the homepage/privacy/terms addresses in section 2. The authorized-domain field takes a domain, not an HTTPS URL or `/gather` path. Use real contact information before public verification; your current legal-page placeholders need to be filled before that stage.

If Google requires domain ownership verification, use [Google Search Console](https://search.google.com/search-console/) for `auglaize.co` and follow its ownership instructions. Registering a Cloud project alone does not establish domain ownership. [Google branding and authorized-domain requirements](https://support.google.com/cloud/answer/15549049?hl=en).

### D. Create the correct OAuth client

1. Open **Google Auth Platform → Clients → Create client**.
2. Choose **Web application** and name it, for example `Gather website`.
3. Under **Authorized redirect URIs**, add the exact Google callback in section 2.
4. Gather's current server-side flow does not require an Authorized JavaScript origin. Leave that section empty. If you use it for some separate browser feature, an origin is only `https://auglaize.co`, without `/gather/`.
5. Create the client. It must be a web client, not a desktop client or service account.

The callback must match what Gather sends. This is why entering the homepage in the redirect field produces a mismatch. [Google web-server OAuth instructions](https://developers.google.com/identity/protocols/oauth2/web-server).

### E. Capture the complete secret immediately

Google now shows/downloads the full client secret at creation time only. Afterward it can show only the identifying last four characters. Those four characters are not a usable secret.

Copy the complete **Client ID** and **Client secret** into secure storage while the creation screen is open. In Gather Administration, enter them into **Google client ID** and **Google client secret**, confirm your Gather password and save. The client ID commonly ends in `.apps.googleusercontent.com`; it is not the Cloud project name or project number.

If the full secret is lost, use the client's **Add secret** option to create a replacement, save that complete value in Gather, test it, and retire the old secret once the replacement works. Do not repeatedly recreate the whole project. [Google client-secret visibility and rotation](https://support.google.com/cloud/answer/15549257?hl=en).

### F. Test YouTube linking before adding its API key

Open **Linked accounts → Connect YouTube**. Choose the Google account added under Test users, grant the requested permission, and return to Gather. The current Gather code also requires the selected account to have a YouTube channel. If you use multiple YouTube identities, choose the one whose subscriptions you want to import.

Wait for the import, inspect Subscriptions, and try Sync now. A separate API key is not needed for this OAuth import test. After linking works, proceed to live checks.

### G. Understand Testing versus public launch

Google's Testing status allows up to 100 listed test users. For this YouTube permission, test authorization and refresh tokens expire after seven days. Reconnecting a week later can therefore be normal during testing. Being a project owner does not replace adding the connecting account as a test user. [Google audience and testing behavior](https://support.google.com/cloud/answer/15549945?hl=en).

For wider public use, plan for production publishing and Google's applicable verification process. Publishing and verification are separate steps; clicking Publish does not automatically remove an unverified-app warning or grant unlimited access. Google documents exceptions for limited personal use and development, which should not be mistaken for public-launch approval. [Verification exceptions](https://support.google.com/cloud/answer/13464323?hl=en).

## 5. Add the YouTube API key for live checks

In the same Google project, open **APIs & Services → Credentials** and create an **API key**. Copy the actual key string, not its management ID. Enter it in Gather's **YouTube API key (live checks)** field and save with your Gather password. [Google API-key management](https://docs.cloud.google.com/docs/authentication/api-keys).

Restrict the key to **YouTube Data API v3**. For application restrictions, Gather makes these requests from PHP on your server, so use server **IP addresses** when your host provides stable outbound IP addresses. Ask your host which outbound IPs PHP uses; your home-computer IP and the website's incoming DNS address are not reliable substitutes. Browser HTTP-referrer restrictions are unsuitable for this server-side request path. If the host cannot provide stable outbound IPs, postpone the optional key while resolving that restriction choice; OAuth imports can still work. [Google API-key restrictions](https://docs.cloud.google.com/api-keys/docs/add-restrictions-api-keys).

Gather checks live status for YouTube videos discovered in a creator's recent feed. It does not perform an exhaustive search of all YouTube streams. A successful OAuth connection alone does not prove the API key is valid or guarantee a populated Live now section.

## 6. Troubleshoot by the point of failure

| What you see | What to check next |
|---|---|
| Gather says linking needs setup, before leaving the site | Save both client ID and complete secret for that provider. Check its callback display. The encryption key must be present; the installer already generated it. |
| Google `redirect_uri_mismatch` | Compare the registered web client's redirect with section 2 character for character, including `https`, `/public/` and `?provider=youtube`. Edit the client whose ID is actually saved in Gather. |
| Google `invalid_client` or failure after consent | Check that client ID and complete secret belong to the same client and that the secret is enabled. Do not paste the masked last four characters. |
| Google blocks a test account / access denied | Verify External/Testing and the exact Test user email in the selected project. A Workspace administrator can also restrict consent. |
| Google `org_internal` | The application is restricted to an organization. Check Audience and the account you selected. |
| Google `disallowed_useragent` | Start again in a regular browser instead of an embedded app browser. |
| Google asks you to reconnect after roughly seven days | Check whether the OAuth app is still in Testing. |
| Twitch will not register the app | Check verified email, 2FA, a unique app name, and that you clicked Add for the redirect entry. |
| Twitch redirect error | Match the Twitch callback exactly, using `?provider=twitch`, then start again from Gather. |
| Twitch has no usable secret / wrong client type | Use a confidential application compatible with authorization-code and client-credentials flows. |
| Twitch stopped working after New Secret | Update Gather with the new secret; Twitch invalidates the previous secret. |
| Gather reports expired/invalid connection state | Return to Linked accounts and start again in the same browser session. Avoid opening the callback directly, reusing old callback tabs, or switching between `www` and the bare domain. Gather's pending authorization lasts ten minutes and is single-use. |
| Gather asks for a Google account with a YouTube channel | Select an account/YouTube identity that has a channel. This is a requirement of Gather's current identity lookup. |
| Connection succeeds but the expected subscriptions are absent | Confirm the selected provider account/channel owns those subscriptions or follows. Import completion and feed-content refresh are separate steps. |
| Import works but Live now is empty | Check actual live activity and the separate live credentials. For YouTube, only discovered feed videos are checked. |
| Database-update message or server-side 500 | This may be a Gather/hosting issue rather than a provider-console choice. Report the message and point of failure. |

Google error descriptions: [web-server OAuth troubleshooting](https://developers.google.com/identity/protocols/oauth2/web-server). Twitch registration/secret behavior: [registration guide](https://dev.twitch.tv/docs/authentication/register-app/). Gather-specific diagnoses above are based on local implementation review; they are possibilities, not a diagnosis of your current error.

## 7. What a successful setup looks like

Administration showing “Required linking settings are present” only means required values and local URL/key checks passed. It is not a provider credential test.

Verify these separately: (1) provider consent opens, (2) return to Gather succeeds, (3) first import completes, (4) Sync now succeeds, and (5) live checks work when a followed creator is actually broadcasting. Test with another authorized account before public rollout.

Do not regenerate Gather's installation encryption key as an OAuth troubleshooting step. Version 1.3's installer created it, and version 1.4 uses it for encrypted admin settings as well as linked-account tokens. Replacing it can make saved settings unreadable.

If something still fails, share the provider, the step number in this guide, the exact error text and whether the error appears on the provider's page or after returning to Gather. Omit secrets, API keys, authorization codes, tokens and the full callback URL after consent. That information is enough to narrow the next check without exposing credentials.

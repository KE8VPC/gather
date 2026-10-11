# Gather roadmap

Updated September 16, 2026. This roadmap covers the PHP/MySQL application and its subdirectory installation. The older TypeScript Sites application is separate.

**Deployment:** No production hostname is embedded in this roadmap. Use [HOSTING-SETUP.md](HOSTING-SETUP.md) as a template and substitute the actual installation address when configuring provider applications.

**Current priority:** finish live YouTube/Twitch setup and validate the hosted experience before adding more platforms. Future items below are planning proposals, not authorization to implement or deploy them. No delivery dates are committed.

## Built — local validation complete

| Area | Current implementation |
| --- | --- |
| Multi-user accounts | Email/password registration and login, separate personal libraries, protected account actions. |
| Unified feed | YouTube videos, Twitch streams and podcast episodes, platform filters, embedded playback and original-content links. |
| Imports | Creator URLs, YouTube CSV, podcast OPML, text and JSON URL lists. |
| Live now | Provider-backed live checks when credentials are configured; refresh while the live view is open. |
| Recommendations | Shared-subscriber recommendations from follows added directly in Gather; linked-account imports are excluded. |
| Linked accounts | YouTube subscriptions and Twitch follows, automatic first import, Sync now, token refresh, reconnect and disconnect with imported-data removal. |
| Installation | Browser wizard at `/install/`, automatic tables/configuration/encryption key/first account, permanent setup lock, removable installer folder, nested-folder support and existing-site upgrade script. |
| Public information | Total registered-user count, automatic copyright year, Privacy Policy and Terms of Use with configurable operator/contact placeholders. |

“Built” does not mean production accepted. Linked-account checks used simulated provider responses and real local MySQL. Public feed fetching was tested separately. See [VALIDATION.md](VALIDATION.md) for evidence and limitations.

## 1. Next — activate and validate YouTube and Twitch

- [ ] Register/configure the Google and Twitch applications and the exact hosted callback URLs.
- [ ] Configure private credentials and a stable token-encryption key.
- [ ] Complete Google consent-screen/verification steps applicable to the public application.
- [ ] Apply the 1.2 database upgrade on the intended host, preserving existing accounts and configuration.
- [ ] Verify real authorization, cancellation, account selection, multi-page import, repeat sync, token refresh, revocation and disconnect for both providers.
- [ ] Confirm two real Gather users cannot access or alter each other's libraries or connections.
- [ ] Test upstream unfollows, interrupted imports and preservation of manually added creators.
- [ ] Verify recent-content playback and known live/offline streams with real provider settings.
- [ ] Enable hourly connection maintenance and confirm cleanup and failure reporting on the host.

**Completion means:** both providers work through the actual hosted subdirectory, with real consenting test accounts and a demonstrated disconnect/recovery path. Setup guide: [CONNECTIONS.md](CONNECTIONS.md).

## 2. Before public launch — hosting and usability acceptance

- [ ] Display the current Gather project version in the footer text, using one shared version value so the displayed version stays consistent across pages and releases. Requested September 16, 2026.
- [ ] Verify Apache/private-directory protections, HTTPS and session-cookie behavior on the actual host.
- [ ] Test phone/tablet layouts, keyboard navigation, readable errors and embedded players in regular browsers.
- [ ] Replace operator/contact placeholders and review policy text against the installation's actual practices.
- [ ] Exercise database/configuration backup and restoration, including the encryption key and deletion/retention handling.
- [ ] Confirm registration settings, provider quotas and scheduled-task monitoring are appropriate for the expected audience.
- [ ] Validate upgrades from an existing installation as well as a fresh install.

**Completion means:** installation, account use, imports, playback and recovery have been checked on the target host. Local checks alone do not close these items.

## 3. Proposed — easier account and site management

These are candidates for the next development phase, after live integrations are accepted:

- Owner administration page for registration controls, connection readiness, maintenance status and legal/site details. Define and enforce an owner role before exposing administrative controls.
- Email verification and self-service password reset, with configured email delivery and recovery tests.
- Account export and account deletion, including linked-provider cleanup.
- Optional “Sign in with Google” and “Sign in with Twitch.” Current linking starts from an existing Gather login; social sign-in would require its own account creation/linking and recovery rules.

## 4. Proposed — improve daily viewing

- [ ] **Latest video from each followed Twitch creator:** add a separate “Latest from your creators” section alongside Live now, with one video card per creator showing thumbnail, title, date and a Watch action. Prefer the latest saved broadcast, with highlights/uploads as a fallback. Cache results to avoid fetching every followed creator on each page visit, and show a clear empty state when no video is available. Requested September 16, 2026; planned, not implemented.
- Search and sort the personal library; organize creators into folders or tags.
- Watch later, watched/unwatched state and playback progress where the provider supports it.
- Optional scheduled follow-list sync and clearer reconnect reminders. Current hourly maintenance checks grants and retention; it does not import new follows automatically.
- Per-user notification preferences for new content or live streams, with opt-in delivery.
- Evaluate larger libraries and more feed history after measuring shared-host capacity and provider limits.

Keep linked-account data private and preserve existing manual imports when designing these features. Do not add linked YouTube data to peer recommendations without resolving the relevant provider requirements.

## 5. Deferred — Rumble and other integrations

The user explicitly deferred Rumble and other platform work until later.

- **Rumble:** research supported channel/video feeds and playback first. Account linking and automatic follow-list import remain unconfirmed; do not present a Connect button that implies they work.
- **Other video/podcast services:** assess each requested platform separately for supported imports, authorization, playback, rate limits and operating cost before selecting an approach.
- Keep existing RSS/OPML and URL/file imports available as alternatives.

**Completion criteria for any new integration:** use a verified supported access method, disclose its limitations, prove user separation and disconnect behavior, and test it against real provider accounts or feeds before marking it ready.

## Release tracking

| Milestone | Status |
| --- | --- |
| 1.1 — subdirectory welcome page | Implemented; target-host acceptance pending. |
| 1.1.1 — registered-user count | Implemented and locally checked. |
| 1.1.2 — legal footer and policy pages | Implemented; operator placeholders and policy review remain. |
| 1.2.0 — YouTube/Twitch linking | Packaged and locally tested; credentials and live acceptance pending. |
| Public launch | Pending phases 1 and 2. |
| Further features/integrations | Proposed or explicitly deferred; no assigned version or date. |

## Latest local progress — 1.2.1

- [x] Browser checks for the linked-account preview on desktop and a phone-sized viewport, mobile navigation, import dialog Escape/focus return, privacy-page navigation and horizontal overflow. No console errors were observed on the checked pages.
- [x] Enforce matching callback/application schemes and secure cookies for HTTPS account linking; regression checks added.
- [x] Add a secret-free setup diagnostic and deployment/provider setup template.
- [ ] Register provider applications, upload/verify the hosted installation, and complete live acceptance. A read-only request to the intended hosted welcome page timed out; no hosted pass is claimed.

## September 13, 2026 — Administration delivered (1.4.0)

Administrator-only YouTube/Twitch credential entry, one-time owner activation for existing accounts, encrypted saved settings, callback display and an update-only upload package are implemented and locally tested. Real provider application registration and hosted linking acceptance remain the next launch steps. General user management and additional platform integrations remain outside this release.

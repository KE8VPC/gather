# PHP/MySQL version

The standalone conversion is in **[php/](php/README.md)**. Start with **[php/INSTALL.md](php/INSTALL.md)**. It includes native PHP accounts, MySQL persistence and plain JavaScript/CSS; no Node.js server is required. The original TypeScript implementation below remains available separately.

# Gather — Content Curator

A multi-user video and podcast library built with TypeScript, React, Vinext, Cloudflare Workers and D1.

## Included

- ChatGPT sign-in with server-side ownership checks for each library operation.
- YouTube subscriptions.csv import, Twitch channel URL lists/JSON, podcast OPML, and individual public RSS links.
- Durable subscriptions, recent content, duplicate protection, platform filters, in-page YouTube/Twitch players and podcast audio playback.
- Live Twitch checks and recent-feed YouTube live checks when the owner configures platform credentials.
- Recommendations ranked by distinct users who share at least one of the current user's subscriptions, excluding already-followed creators. No fabricated recommendation data or publicly exposed user libraries.
- Refresh errors, empty states, explicit sample feed, mobile layouts, accessible primitive controls.

## Platform setup

Copy `.env.example` to `.env` for local configuration. Configure hosted values as Sites runtime secrets, never as browser variables or committed files.

- `TWITCH_CLIENT_ID` and `TWITCH_CLIENT_SECRET`: an owner-registered Twitch application. Used server-side to obtain a client-credentials token and call Get Streams. The app does not need users' Twitch passwords or tokens.
- `YOUTUBE_API_KEY`: enables live checks using videos.list for videos present in each imported channel's recent RSS feed. RSS content retrieval itself does not require this key.

Direct OAuth account subscription sync is not implemented in this TypeScript version. The separate PHP/MySQL version now supports YouTube and Twitch linking; see php/CONNECTIONS.md. YouTube users import Google Takeout subscriptions.csv; Twitch users import channel URL lists or JSON; podcasts use public feeds/OPML. Spotify/Apple proprietary library imports and paywalled content are not connected. Public podcast hosting domains are explicitly allowed; unknown hosts/redirect targets require a code change. Embedded playback remains subject to provider restrictions. YouTube live detection is limited to the recent RSS feed, not an exhaustive live search.

References: https://developers.google.com/youtube/v3/docs/subscriptions/list and https://dev.twitch.tv/docs/api/reference/#get-streams.

## Local development

`pnpm install`, then `pnpm dev`. The starter provides a local-only sign-in identity at `/signin-with-chatgpt?return_to=/`. This development identity does not ship as production authentication. Hosted identity comes from the Sites trusted dispatcher; do not expose the raw Worker outside that trusted boundary.

Generate schema changes with `pnpm db:generate`. Apply the initial migration locally using:

`pnpm exec wrangler d1 execute DB --local --config .openai/local-db.json --persist-to .wrangler/state --file drizzle/0000_adorable_stardust.sql`

Production migrations are packaged and applied by Sites. Do not run table-creation SQL from request handlers.

## Validation

- `node --experimental-strip-types tests/unit.mts`
- `python tests/database.py`
- With dev server running: `node tests/api.mjs`
- With outbound internet and dev server: `node tests/feeds-live.mjs` (temporarily imports two feeds into the local test identity, then removes those subscriptions).
- `pnpm exec tsc --noEmit`
- `pnpm build`

Browser interaction and multi-user hosted sign-in must be checked before a broad public release. The local development identity is singular; recommendation and ownership isolation are additionally verified against SQLite fixtures representing multiple users.

## Operational limits

100 sources per import, 1,000 subscriptions per user, 6 creators per refresh request, 2 MB per fetched feed, 30 items parsed per feed, 60 retained per creator and 300 newest returned per user. Live status refreshes every two minutes while its view is open; status older than five minutes is hidden. Refresh has a per-creator 60-second shared lease. There is no scheduled background ingestion, billing, moderation console or public registration management. The initial deployment remains private to its owner; granting additional visitors requires configuring site access.

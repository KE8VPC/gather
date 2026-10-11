# Efficient YouTube refresh update — September 16, 2026

## Install on an existing Gather 1.4.1 site

Extract `gather-youtube-efficient-refresh-update.zip` and merge its `gather` contents into your existing Gather folder, replacing matching application files. Upload all six application files together:

- `app/YouTube.php` (new)
- `app/Feeds.php`
- `app/Library.php`
- `public/api.php`
- `public/admin.php`
- `public/assets/app.js`

Then hard-refresh the browser with Ctrl+F5. Open Administration to see the YouTube live-check counter, then open Live now. The first pass gradually checks cached videos; subsequent passes reuse those results.

No installer rerun, manual SQL import, key replacement or private configuration changes are needed. Two additive cache tables (`youtube_video_checks` and `youtube_api_budget`) are created automatically on first use. The database account needs CREATE and REFERENCES privileges, as it did for installation. Existing application tables, users and subscriptions are preserved. Keep your normal database/application backup before uploading.

This combined patch includes the previous Live now fixes and YouTube/Twitch popup autoplay. It replaces the previous live-now and autoplay-only patches; do not upload an older patch over it.

## What now gets checked

| Work | Schedule and limits |
|---|---|
| Discover channel videos through RSS | At most once per channel per hour, shared across users |
| Automatic discovery | At most 20 overdue feeds per refresh pass, oldest checks first |
| Manual Refresh feed | Processes overdue feeds; does not bypass the hourly cache |
| Known live YouTube videos | Eligible every two minutes |
| Upcoming YouTube streams | Eligible every five minutes |
| Ordinary or unavailable videos | Eligible once per day |
| Completed broadcasts | Eligible once per week |
| Provider requests | Up to 50 video IDs per request, across different channels |
| Initial queue processing | At most 20 provider batches per browser refresh pass; live/upcoming videos take priority |

Checks run while a user keeps Live now open, or requests a refresh. These are minimum cache intervals, not an always-on scheduler or a guarantee of detection within that time. A large initial queue warms gradually across refresh passes. New broadcasts can take an hour or longer to appear, and discovery is still limited to public RSS entries plus videos already known to Gather.

Routine Live now updates use a smaller response containing live results and cache status. They do not retransmit the complete 300-item feed or recommendation list. The server requests only the YouTube API fields needed for live status, rather than full video descriptions.

## Shared quota protection

Gather reserves up to **8,000 YouTube live-check requests per Pacific calendar day for this installation**, shared by all users and tabs. Reservations happen before provider requests, so failed calls count too; a worker that stops after reservation can overcount slightly rather than exceed the cap. The counter is persisted in MySQL and does not reset when you reopen the browser or upload files.

This is a local safety budget, not a measurement of Google's actual remaining quota. OAuth subscription imports, other Google API use, and other installations sharing your Google project are outside this counter. Administration links to Google's quota console for the authoritative total. The budget leaves headroom within the usual 10,000-unit quota for these endpoints, but cannot guarantee capacity if other applications use that same allocation.

Provider failures pause live checks across the site for 15 minutes, then 30 minutes, then up to one hour after repeated failures. A reported daily quota exhaustion pauses them until midnight Pacific time. Successful recovery clears the warning. Live-state failures do not mark streams offline or extend their verification timestamp; results older than five minutes remain hidden with the pause/error explanation available.

## Example and validation

An isolated test with **336 channels and 15 cached videos each** checked 5,040 IDs in **101 provider requests** during initial cache population. At the next two-minute interval, it checked only the single known live video. A second user following those channels reused the same cache with no additional provider request. Those are synthetic test results, not measurements of the hosted account.

Further checks covered hourly feed caching, cache sharing, unauthorized subscriptions, upcoming streams, missing keys, budget enforcement, Pacific-day reset, in-flight coordination, provider backoff, quota exhaustion, failure freshness, cache cleanup and recovery. Existing Twitch batching and user isolation tests passed. JavaScript checks covered the 336-channel cache, request bounds, automatic 20-feed limit, lightweight snapshots, progress/errors and tab return. Nested-path HTTP checks verified login/CSRF protection, the enforced budget, admin usage display, retained autoplay and unchanged private configuration. Syntax checks and existing parser/settings/readiness tests passed.

## Push notifications

YouTube's documented PubSubHubbub/WebSub notifications cover new uploads and title/description updates. They can trigger checks of affected videos, but are not a documented guaranteed start/end event for every live stream. A future integration can combine authenticated push callbacks with these cached checks and occasional discovery to recover missed events. **Push subscriptions and callbacks are not implemented in this update.**

References:

- https://developers.google.com/youtube/v3/docs/videos/list
- https://developers.google.com/youtube/v3/getting-started#quota-usage
- https://developers.google.com/youtube/v3/guides/push_notifications

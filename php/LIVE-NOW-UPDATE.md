# Live now update — September 16, 2026

For an existing Gather 1.4.1 installation, extract `gather-live-now-update.zip` and merge its `gather` folder into the existing installation, replacing these three files together:

- `app/Library.php`
- `public/api.php`
- `public/assets/app.js`

The archive also includes this guide. No installer rerun, database migration or credential change is required. Existing private configuration and saved API settings are not included. Hard-refresh Gather with Ctrl+F5 after uploading, open Live now, and wait for the checks to finish. This patch includes both YouTube and Twitch popup autoplay changes, and supersedes the earlier autoplay-only patches.

## What changed

- Live streams have a separate, user-scoped query. The newest-300-items feed limit can no longer exclude an older stream that is still broadcasting.
- Twitch follows are checked in batches of up to 100, with the API result limit explicitly set to 100. A library of 89 Twitch follows now needs one token request and one streams request per uncached batch, instead of 178 provider requests.
- Twitch results appear before the remaining YouTube/podcast checks finish. Other results load progressively. During a long Live now refresh, Twitch batches are revisited approximately every two minutes between other checks.
- Returning to a visible Live now browser tab triggers another refresh. Choosing Live now while the initial library loads also triggers a check once subscriptions arrive.
- Automatic checks show progress and failure counts; detailed provider errors remain on Subscriptions. Successful offline checks remove Twitch live status, while failed Twitch checks preserve the previous result until its original five-minute freshness window expires.
- Freshness uses the server clock, avoiding false expiry when a viewer's computer clock is wrong. Successfully checked content is timestamped after provider requests complete.
- YouTube continues checking previously detected live streams even after they leave the recent RSS feed. Active broadcasts are retained ahead of ordinary uploads in each creator's 60-item cache.

## YouTube coverage and troubleshooting

A saved YouTube API key is required in addition to OAuth client credentials. Saved means the setting exists; it does not prove the provider accepts it. If the update still misses a stream, look at that creator's error note in Subscriptions after refreshing. API restrictions, permissions, quota or provider availability can prevent live verification.

YouTube discovery still starts from recent public feed entries and previously detected streams. This update does not search every channel for undiscovered broadcasts. The official search endpoint has a separate limited daily quota, so polling it for every subscription every two minutes is unsuitable without additional quota management.

On September 16, the City of Wapakoneta public channel's `/live` page indicated that `KHTVAXzWAt4` was live; its RSS feed contained that video with a July 13 publication date. This is consistent with the recent-300-items exclusion, but the hosted Gather database and API response were not inspected.

## Validation

- PHP syntax and JavaScript syntax checks passed.
- Eighteen isolated MySQL regression checks passed with synthetic provider responses: 89-channel batching, more than 20 live results, 300-item feed cutoff, old Twitch and YouTube streams, user isolation, invalid/unauthorized batches, cache reuse, stale expiry, provider failure preservation, successful offline transitions and recovery.
- JavaScript behavior checks passed for progressive results, error visibility during automatic refresh, concurrent-refresh prevention, clock skew, freshness expiry and tab-return refresh.
- Existing parser/password tests passed. The ZIP is checked for exact intended contents and source-byte equality.
- These checks do not establish successful playback or provider checks on the hosted site. Confirm the live channels after upload.

References: https://dev.twitch.tv/docs/api/reference/#get-streams and https://developers.google.com/youtube/v3/docs/search/list

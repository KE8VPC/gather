import { env } from 'cloudflare:workers';
import { database } from '@/db/store';
import { identity, failure, body } from '@/lib/api';
import { readFeed, entries, key } from '@/lib/feeds';
const config = () =>
  env as unknown as {
    TWITCH_CLIENT_ID?: string;
    TWITCH_CLIENT_SECRET?: string;
    YOUTUBE_API_KEY?: string;
  };
let twitchToken = { value: '', expires: 0 };
async function twitchHeaders() {
  const e = config();
  if (!e.TWITCH_CLIENT_ID || !e.TWITCH_CLIENT_SECRET)
    throw new Error(
      'Twitch live checks need the site owner to connect a Twitch API app.',
    );
  if (twitchToken.expires < Date.now()) {
    const r = await fetch('https://id.twitch.tv/oauth2/token', {
      method: 'POST',
      body: new URLSearchParams({
        client_id: e.TWITCH_CLIENT_ID,
        client_secret: e.TWITCH_CLIENT_SECRET,
        grant_type: 'client_credentials',
      }),
      signal: AbortSignal.timeout(12000),
    });
    if (!r.ok) throw new Error('Twitch connection needs attention.');
    const t: any = await r.json();
    if (!t.access_token)
      throw new Error('Twitch did not provide an access token.');
    twitchToken = {
      value: t.access_token,
      expires: Date.now() + (t.expires_in - 60) * 1000,
    };
  }
  return {
    'Client-ID': e.TWITCH_CLIENT_ID,
    Authorization: 'Bearer ' + twitchToken.value,
  };
}
export async function POST(request: Request) {
  try {
    const user = await identity(request);
    const input = await body(request);
    if (
      !Array.isArray(input.ids) ||
      input.ids.length > 6 ||
      input.ids.some((v: any) => typeof v !== 'string')
    )
      throw new Error('Refresh up to 6 subscriptions at a time.');
    const db = database();
    const results = [];
    for (const id of input.ids) {
      const c: any = await db
        .prepare(
          'SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND c.id=?',
        )
        .bind(user, id)
        .first();
      if (!c) {
        results.push({ id, error: 'Subscription not found.' });
        continue;
      }
      // Atomic shared creator lease bounds external requests across concurrent users.
      const now = Date.now();
      const claim = await db
        .prepare('UPDATE creators SET synced_at=? WHERE id=? AND synced_at<?')
        .bind(now, id, now - 60000)
        .run();
      if (!claim.meta.changes) {
        results.push({ id, cached: true, error: c.error });
        continue;
      }
      try {
        if (c.platform === 'Twitch') {
          const headers = await twitchHeaders();
          const login = new URL(c.url).pathname.slice(1);
          const r = await fetch(
            'https://api.twitch.tv/helix/streams?user_login=' +
              encodeURIComponent(login),
            { headers, signal: AbortSignal.timeout(12000) },
          );
          if (!r.ok)
            throw new Error('Twitch live check failed. Try again later.');
          const data: any = await r.json();
          const stream = data.data?.[0];
          const writes = [
            db
              .prepare(
                'UPDATE items SET live=0,checked_at=? WHERE creator_id=?',
              )
              .bind(now, id),
          ];
          if (stream)
            writes.push(
              db
                .prepare(
                  'INSERT INTO items(id,creator_id,title,url,image,published,live,checked_at) VALUES(?,?,?,?,?,?,1,?) ON CONFLICT(id) DO UPDATE SET title=excluded.title,image=excluded.image,published=excluded.published,live=1,checked_at=excluded.checked_at',
                )
                .bind(
                  await key(c.url + '/live'),
                  id,
                  String(stream.title).slice(0, 500),
                  c.url,
                  String(stream.thumbnail_url)
                    .replace('{width}', '640')
                    .replace('{height}', '360'),
                  Date.parse(stream.started_at) || now,
                  now,
                ),
            );
          writes.push(
            db
              .prepare('UPDATE creators SET name=?,error=NULL WHERE id=?')
              .bind(stream?.user_name ?? c.name, id),
          );
          await db.batch(writes);
        } else {
          const parsed = entries(await readFeed(c.feed), c);
          const liveIds = new Set<string>();
          let warning: string | null = null;
          if (c.platform === 'YouTube') {
            const apiKey = config().YOUTUBE_API_KEY;
            if (apiKey && parsed.rows.length) {
              const ids = parsed.rows
                .map((e) => e.video)
                .filter(Boolean)
                .join(',');
              const u = new URL('https://www.googleapis.com/youtube/v3/videos');
              u.search = new URLSearchParams({
                part: 'liveStreamingDetails',
                id: ids,
                key: apiKey,
              }).toString();
              const r = await fetch(u, { signal: AbortSignal.timeout(12000) });
              if (r.ok) {
                const json: any = await r.json();
                for (const i of json.items ?? [])
                  if (
                    i.liveStreamingDetails?.actualStartTime &&
                    !i.liveStreamingDetails?.actualEndTime
                  )
                    liveIds.add(i.id);
              } else
                warning =
                  'Videos updated; YouTube live checks need a valid API key.';
            } else
              warning =
                'Videos updated. YouTube live checks need the site owner to connect a YouTube API key.';
          }
          const writes = [
            db
              .prepare(
                'UPDATE items SET live=0,checked_at=? WHERE creator_id=?',
              )
              .bind(now, id),
          ];
          for (const e of parsed.rows)
            writes.push(
              db
                .prepare(
                  'INSERT INTO items(id,creator_id,title,url,image,media,published,live,checked_at) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET title=excluded.title,image=excluded.image,media=excluded.media,published=excluded.published,live=excluded.live,checked_at=excluded.checked_at',
                )
                .bind(
                  await key(id + e.url),
                  id,
                  e.title,
                  e.url,
                  e.image || null,
                  e.media || null,
                  e.published,
                  liveIds.has(e.video) ? 1 : 0,
                  now,
                ),
            );
          writes.push(
            db
              .prepare('UPDATE creators SET name=?,error=? WHERE id=?')
              .bind(parsed.name, warning, id),
          );
          // Keep bounded recent history per creator.
          writes.push(
            db
              .prepare(
                'DELETE FROM items WHERE creator_id=? AND id NOT IN(SELECT id FROM items WHERE creator_id=? ORDER BY published DESC LIMIT 60)',
              )
              .bind(id, id),
          );
          await db.batch(writes);
        }
        results.push({ id, updated: true });
      } catch (e) {
        const message = e instanceof Error ? e.message : 'Refresh failed.';
        await db
          .prepare('UPDATE creators SET error=? WHERE id=?')
          .bind(message, id)
          .run();
        results.push({ id, error: message });
      }
    }
    return Response.json({ results });
  } catch (e) {
    return failure(e);
  }
}

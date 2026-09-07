import { database, recommendationsSQL } from '@/db/store';
import { identity, failure, body } from '@/lib/api';
import { source } from '@/lib/feeds';
export async function GET() {
  try {
    const user = await identity();
    const db = database();
    const [subscriptions, items, recommendations] = await Promise.all([
      db
        .prepare(
          'SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? ORDER BY c.name',
        )
        .bind(user)
        .all(),
      db
        .prepare(
          'SELECT i.*,c.name AS creator,c.platform,c.url AS creator_url FROM items i JOIN creators c ON c.id=i.creator_id JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? ORDER BY i.published DESC LIMIT 300',
        )
        .bind(user)
        .all(),
      db.prepare(recommendationsSQL).bind(user, user, user).all(),
    ]);
    return Response.json(
      {
        subscriptions: subscriptions.results,
        items: items.results,
        recommendations: recommendations.results,
      },
      { headers: { 'Cache-Control': 'private, no-store' } },
    );
  } catch (e) {
    return failure(e);
  }
}
export async function POST(request: Request) {
  try {
    const user = await identity(request);
    const data = await body(request);
    if (
      !Array.isArray(data.sources) ||
      !data.sources.length ||
      data.sources.length > 100 ||
      data.sources.some((s: any) => typeof s !== 'string' || s.length > 2000)
    )
      throw new Error('Import between 1 and 100 creator or feed URLs.');
    const db = database();
    const count = await db
      .prepare('SELECT COUNT(*) AS n FROM subscriptions WHERE user_id=?')
      .bind(user)
      .first<{ n: number }>();
    const parsed = [],
      errors = [];
    for (const raw of data.sources) {
      try {
        parsed.push(await source(raw));
      } catch (e) {
        errors.push({
          url: raw,
          error: e instanceof Error ? e.message : 'Invalid URL',
        });
      }
    }
    if ((count?.n ?? 0) + parsed.length > 1000)
      throw new Error('Your library can hold up to 1,000 subscriptions.');
    let added = 0;
    for (let i = 0; i < parsed.length; i += 25) {
      const batch = parsed
        .slice(i, i + 25)
        .flatMap((c) => [
          db
            .prepare(
              'INSERT INTO creators(id,name,platform,url,feed) VALUES(?,?,?,?,?) ON CONFLICT(id) DO NOTHING',
            )
            .bind(c.id, c.name, c.platform, c.url, c.feed),
          db
            .prepare(
              'INSERT INTO subscriptions(user_id,creator_id,created_at) VALUES(?,?,?) ON CONFLICT(user_id,creator_id) DO NOTHING',
            )
            .bind(user, c.id, Date.now()),
        ]);
      const results = await db.batch(batch);
      added += results
        .filter((_, n) => n % 2 === 1)
        .reduce((n, r) => n + (r.meta.changes ?? 0), 0);
    }
    return Response.json({ added, errors, ids: parsed.map((c) => c.id) });
  } catch (e) {
    return failure(e);
  }
}
export async function DELETE(request: Request) {
  try {
    const user = await identity(request);
    const data = await body(request);
    if (typeof data.id !== 'string') throw new Error('Choose a subscription.');
    await database()
      .prepare('DELETE FROM subscriptions WHERE user_id=? AND creator_id=?')
      .bind(user, data.id)
      .run();
    return Response.json({ ok: true });
  } catch (e) {
    return failure(e);
  }
}

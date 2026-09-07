import { env } from 'cloudflare:workers';
export function database(): D1Database {
  const db = (env as unknown as { DB: D1Database }).DB;
  if (!db) throw new Error('Storage is unavailable. Please try again later.');
  return db;
}
export const recommendationsSQL = `WITH peers AS (
 SELECT DISTINCT other.user_id FROM subscriptions mine
 JOIN subscriptions other ON other.creator_id=mine.creator_id
 WHERE mine.user_id=? AND other.user_id<>?
) SELECT c.*,COUNT(DISTINCT s.user_id) AS shared_users
 FROM peers JOIN subscriptions s ON s.user_id=peers.user_id
 JOIN creators c ON c.id=s.creator_id
 WHERE NOT EXISTS(SELECT 1 FROM subscriptions own WHERE own.user_id=? AND own.creator_id=c.id)
 GROUP BY c.id ORDER BY shared_users DESC,c.name ASC LIMIT 24`;

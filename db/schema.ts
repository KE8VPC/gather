import {
  sqliteTable,
  text,
  integer,
  primaryKey,
  index,
} from 'drizzle-orm/sqlite-core';
export const creators = sqliteTable('creators', {
  id: text('id').primaryKey(),
  name: text('name').notNull(),
  platform: text('platform').notNull(),
  url: text('url').notNull(),
  feed: text('feed').notNull(),
  syncedAt: integer('synced_at').notNull().default(0),
  error: text('error'),
});
export const subscriptions = sqliteTable(
  'subscriptions',
  {
    userId: text('user_id').notNull(),
    creatorId: text('creator_id')
      .notNull()
      .references(() => creators.id),
    createdAt: integer('created_at').notNull(),
  },
  (t) => [
    primaryKey({ columns: [t.userId, t.creatorId] }),
    index('idx_subscriptions_creator').on(t.creatorId),
  ],
);
export const items = sqliteTable(
  'items',
  {
    id: text('id').primaryKey(),
    creatorId: text('creator_id')
      .notNull()
      .references(() => creators.id),
    title: text('title').notNull(),
    url: text('url').notNull(),
    image: text('image'),
    media: text('media'),
    published: integer('published').notNull(),
    live: integer('live').notNull().default(0),
    checkedAt: integer('checked_at').notNull().default(0),
  },
  (t) => [index('idx_items_creator_published').on(t.creatorId, t.published)],
);

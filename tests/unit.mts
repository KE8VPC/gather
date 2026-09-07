import assert from 'node:assert/strict';
import { csvRows, csvSources } from '../lib/imports.ts';
import { feedAllowed, source, entries } from '../lib/feeds.ts';
assert.deepEqual(
  csvRows(
    'Channel ID,Channel URL,Channel title\r\nUC123,"https://www.youtube.com/channel/UC123","Name, with comma"',
  ),
  [
    ['Channel ID', 'Channel URL', 'Channel title'],
    ['UC123', 'https://www.youtube.com/channel/UC123', 'Name, with comma'],
  ],
);
assert.equal(
  csvSources(
    'Channel Id,Channel Url,Channel Title\nUC123,https://www.youtube.com/channel/UC123,Creator',
  ).length,
  1,
);
assert.throws(() => csvRows('"unfinished'));
for (const url of [
  'http://feeds.simplecast.com/a',
  'https://127.0.0.1/feed',
  'https://localhost/rss',
  'https://feeds.simplecast.com.evil.test/a',
  'https://user:secret@feeds.simplecast.com/a',
  'https://feeds.simplecast.com:444/a',
  'https://www.youtube.com/watch?v=abc',
])
  assert.equal(feedAllowed(url), false, url);
assert.equal(feedAllowed('https://feeds.simplecast.com/54nAGcIl'), true);
const a = await source('https://twitch.tv/SomeCreator/'),
  b = await source('https://www.twitch.tv/somecreator');
assert.equal(a.id, b.id);
await assert.rejects(source('https://youtube.com/@creator'));
const rss = entries(
  '<rss><channel><title>Podcast</title><item><title>Episode</title><link>javascript:alert(1)</link><enclosure url="https://cdn.example.com/audio.mp3" type="audio/mpeg"/><pubDate>invalid</pubDate></item></channel></rss>',
  { name: 'Fallback' },
);
assert.equal(rss.name, 'Podcast');
assert.equal(rss.rows[0].url, 'https://cdn.example.com/audio.mp3');
assert.equal(rss.rows[0].published, 0);
assert.throws(() => entries('<!DOCTYPE rss><rss/>', {}));
console.log(
  'PASS: CSV parsing, duplicate canonicalization, feed host policy, unsafe media links and XML entity rejection',
);
import { trimFeed } from '../lib/feeds.ts';
const big =
  '<rss><channel><title>Archive</title>' +
  Array.from(
    { length: 80 },
    (_, i) =>
      '<item><title>Episode ' +
      i +
      '</title><description><![CDATA[This text contains </item> and should not count.]]></description><link>https://example.com/' +
      i +
      '</link></item>',
  ).join('') +
  '</channel></rss>';
const trimmed = trimFeed(big);
assert.ok(trimmed);
assert.equal(entries(trimmed, { name: 'Archive' }).rows.length, 30);
assert.ok(!trimmed.includes('Episode 30'));
console.log(
  'PASS: bounded archive reader preserves 30 entries and ignores closing tags in CDATA',
);

import { XMLParser, XMLValidator } from 'fast-xml-parser';
export const parser = new XMLParser({
  ignoreAttributes: false,
  attributeNamePrefix: '@_',
  processEntities: false,
  htmlEntities: false,
  ignoreDeclaration: true,
});
export function array<T>(value: T | T[] | undefined): T[] {
  return value === undefined ? [] : Array.isArray(value) ? value : [value];
}
export function text(value: any): string {
  return typeof value === 'string'
    ? value
    : typeof value === 'number'
      ? String(value)
      : typeof value?.['#text'] === 'string'
        ? value['#text']
        : '';
}
export function clean(value: any): string {
  return text(value)
    .replace(/<[^>]*>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .slice(0, 500);
}
export function https(value: any): string {
  try {
    const u = new URL(text(value));
    return u.protocol === 'https:' && !u.username && !u.password ? u.href : '';
  } catch {
    return '';
  }
}
const hosts = [
  'feeds.simplecast.com',
  'feeds.megaphone.fm',
  'feeds.transistor.fm',
  'anchor.fm',
  'feeds.buzzsprout.com',
  'rss.art19.com',
  'feeds.acast.com',
  'feeds.npr.org',
  'feeds.twit.tv',
  'feeds.feedburner.com',
  'feeds.libsyn.com',
  'rss.libsyn.com',
  'feeds.captivate.fm',
  'feeds.redcircle.com',
  'feeds.soundcloud.com',
  'feeds.audioboom.com',
  'audioboom.com',
  'www.omnycontent.com',
  'omnycontent.com',
  'podcastfeeds.nbcnews.com',
  'feeds.fireside.fm',
  'feeds.podbean.com',
  'rss.wbur.org',
  'www.pbs.org',
  'feed.podbean.com',
  'feeds.99percentinvisible.org',
];
export function feedAllowed(value: string) {
  try {
    const u = new URL(value);
    return (
      u.protocol === 'https:' &&
      !u.username &&
      !u.password &&
      !u.port &&
      (hosts.includes(u.hostname) ||
        (u.hostname === 'www.youtube.com' &&
          u.pathname === '/feeds/videos.xml' &&
          /^UC[\w-]{22}$/.test(u.searchParams.get('channel_id') ?? '')))
    );
  } catch {
    return false;
  }
}
export async function readFeed(url: string): Promise<string> {
  for (let redirects = 0; redirects < 4; redirects++) {
    if (!feedAllowed(url))
      throw new Error(
        'Feed host is not supported. Use a public feed from a supported podcast host.',
      );
    const r = await fetch(url, {
      redirect: 'manual',
      signal: AbortSignal.timeout(12000),
      headers: {
        Accept:
          'application/rss+xml, application/atom+xml, application/xml, text/xml',
      },
    });
    if (r.status >= 300 && r.status < 400) {
      url = new URL(r.headers.get('location') ?? '', url).href;
      continue;
    }
    if (!r.ok) throw new Error(`Feed could not be read (${r.status}).`);
    const reader = r.body?.getReader();
    if (!reader) throw new Error('Empty feed.');
    let size = 0,
      xml = '';
    const decoder = new TextDecoder();
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      size += value.length;
      xml += decoder.decode(value, { stream: true });
      const recent = trimFeed(xml);
      if (recent) {
        await reader.cancel();
        return recent;
      }
      if (size > 2000000) {
        await reader.cancel();
        throw new Error('Recent feed content is larger than 2 MB.');
      }
    }
    return xml + decoder.decode();
  }
  throw new Error('Feed redirected too many times.');
}
export async function key(value: string) {
  return Array.from(
    new Uint8Array(
      await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value)),
    ),
    (x) => x.toString(16).padStart(2, '0'),
  ).join('');
}
export async function source(raw: string) {
  const value = raw.trim();
  let u: URL;
  try {
    u = new URL(value);
  } catch {
    if (/^UC[\w-]{22}$/.test(value))
      u = new URL('https://www.youtube.com/channel/' + value);
    else
      throw new Error(
        'Use a full creator URL, YouTube channel ID, or podcast feed URL.',
      );
  }
  if (u.protocol !== 'https:' || u.username || u.password || u.port)
    throw new Error('Use a public HTTPS URL without credentials.');
  let platform = 'Podcasts',
    name = '',
    url = u.href,
    feed = u.href;
  if (['youtube.com', 'www.youtube.com'].includes(u.hostname)) {
    const id =
      u.pathname.match(/^\/channel\/(UC[\w-]{22})\/?$/)?.[1] ??
      (u.pathname === '/feeds/videos.xml'
        ? u.searchParams.get('channel_id')
        : null);
    if (!id || !/^UC[\w-]{22}$/.test(id))
      throw new Error(
        'Use a YouTube /channel/ URL or import your YouTube subscriptions.csv file.',
      );
    platform = 'YouTube';
    url = 'https://www.youtube.com/channel/' + id;
    feed = 'https://www.youtube.com/feeds/videos.xml?channel_id=' + id;
    name = 'YouTube channel ' + id;
  } else if (['twitch.tv', 'www.twitch.tv'].includes(u.hostname)) {
    const login = u.pathname
      .match(/^\/([a-zA-Z0-9_]{3,25})\/?$/)?.[1]
      ?.toLowerCase();
    if (
      !login ||
      [
        'directory',
        'downloads',
        'settings',
        'subscriptions',
        'videos',
      ].includes(login)
    )
      throw new Error('Use a Twitch creator channel URL.');
    platform = 'Twitch';
    name = login;
    url = 'https://www.twitch.tv/' + login;
    feed = url;
  } else {
    if (!feedAllowed(feed))
      throw new Error(
        'Unsupported feed host. Supported: Simplecast, Megaphone, Transistor, Anchor, Buzzsprout, ART19, Acast, NPR, TWiT, FeedBurner, Libsyn, Captivate, RedCircle, SoundCloud, Audioboom, Omny, Fireside, Podbean, WBUR and PBS.',
      );
    name = u.hostname;
  }
  return { id: await key(url), name, platform, url, feed };
}
export function entries(xml: string, creator: any) {
  if (/<!DOCTYPE|<!ENTITY/i.test(xml))
    throw new Error('Feeds with document entities are not supported.');
  const doc = parser.parse(xml);
  const root = doc.feed ?? doc.rss?.channel;
  if (!root) throw new Error('This link is not an RSS or Atom feed.');
  const name = clean(root.title) || creator.name;
  const rows = array<any>(root.entry ?? root.item)
    .slice(0, 30)
    .map((e) => {
      const video = text(e['yt:videoId']);
      const links = array<any>(e.link);
      const link = links.find((l) => l['@_rel'] === 'alternate') ?? links[0];
      const url =
        video && /^[\w-]{11}$/.test(video)
          ? 'https://www.youtube.com/watch?v=' + video
          : https(typeof link === 'string' ? link : link?.['@_href']);
      const enclosure =
        array<any>(e.enclosure).find((x) =>
          String(x['@_type']).startsWith('audio/'),
        ) ??
        array<any>(e.link).find(
          (x) =>
            x['@_rel'] === 'enclosure' &&
            String(x['@_type']).startsWith('audio/'),
        );
      const media = https(enclosure?.['@_url'] ?? enclosure?.['@_href']);
      const image = video
        ? 'https://i.ytimg.com/vi/' + video + '/hqdefault.jpg'
        : https(
            e['itunes:image']?.['@_href'] ??
              root['itunes:image']?.['@_href'] ??
              root.image?.url,
          );
      const published = Date.parse(text(e.published ?? e.pubDate ?? e.updated));
      return {
        title: clean(e.title) || 'Untitled episode',
        url: url || media,
        image,
        media,
        published: Number.isFinite(published) ? published : 0,
        video,
      };
    })
    .filter((e) => e.url);
  return { name, rows };
}

// Keep original XML through the 30th complete entry; validate before closing the
// envelope so markers inside CDATA/comments never produce malformed fragments.
export function trimFeed(xml: string): string | null {
  if (/<!DOCTYPE|<!ENTITY/i.test(xml))
    throw new Error('Feeds with document entities are not supported.');
  let count = 0;
  const tokens =
    /<!\[CDATA\[[\s\S]*?\]\]>|<!--[\s\S]*?-->|<\/(item|entry)\s*>/g;
  for (const match of xml.matchAll(tokens)) {
    if (!match[1]) continue;
    if (++count === 30) {
      const tail = match[1] === 'item' ? '</channel></rss>' : '</feed>';
      const candidate = xml.slice(0, match.index! + match[0].length) + tail;
      return XMLValidator.validate(candidate) === true ? candidate : null;
    }
  }
  return null;
}

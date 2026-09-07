'use client';
import { useEffect, useState, useCallback, useRef } from 'react';
import {
  Radio,
  Layers3,
  SquarePlay,
  Headphones,
  MessageSquare,
  Plus,
  Compass,
  ArrowUpRight,
  RefreshCw,
  Play,
  Users,
  Link as LinkIcon,
  Upload,
  Check,
  Trash2,
  ExternalLink,
} from 'lucide-react';
import {
  SidebarProvider,
  Sidebar,
  SidebarHeader,
  SidebarContent,
  SidebarFooter,
  SidebarInset,
  SidebarTrigger,
} from '@/components/ui/sidebar';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { csvSources } from '@/lib/imports';
type Creator = {
  id: string;
  name: string;
  url: string;
  feed: string;
  platform: string;
  synced_at: number;
  error: string | null;
  shared_users?: number;
};
type Item = {
  id: string;
  creator_id: string;
  title: string;
  url: string;
  image: string | null;
  media: string | null;
  published: number;
  live: number;
  checked_at: number;
  creator: string;
  platform: string;
};
type Library = {
  subscriptions: Creator[];
  items: Item[];
  recommendations: Creator[];
};
const blank: Library = { subscriptions: [], items: [], recommendations: [] };
const examples: Item[] = [
  [
    'dtp6b76pMak',
    'Using Apple Vision Pro: What It’s Actually Like!',
    'Marques Brownlee',
  ],
  ['DxL2HoqLbyA', 'The Most Misunderstood Concept in Physics', 'Veritasium'],
  [
    '_1MIAMxixag',
    'Graphic Design Tutorial: Typography Design & Art Direction pt. 1',
    'The Futur Academy',
  ],
  [
    'u4PmMeMFXMY',
    'Hiking 75 miles Alone on the Alta Via 1 Dolomites',
    'Kraig Adams',
  ],
].map(([id, title, creator]) => ({
  id,
  creator_id: '',
  title,
  creator,
  url: 'https://www.youtube.com/watch?v=' + id,
  image: 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg',
  media: null,
  published: 0,
  live: 0,
  checked_at: 0,
  platform: 'YouTube',
}));
function PlatformIcon({ platform }: { platform: string }) {
  return platform === 'YouTube' ? (
    <SquarePlay size={16} />
  ) : platform === 'Twitch' ? (
    <MessageSquare size={16} />
  ) : (
    <Headphones size={16} />
  );
}
async function api(path: string, method = 'GET', data?: unknown) {
  const r = await fetch('/api/' + path, {
    method,
    headers: data ? { 'Content-Type': 'application/json' } : undefined,
    body: data ? JSON.stringify(data) : undefined,
  });
  const json: any = await r.json();
  if (!r.ok) throw new Error(json.error ?? 'Unable to complete this request.');
  return json;
}
export default function Hub({ name }: { name: string | null }) {
  const [view, setView] = useState('Your feed'),
    [platform, setPlatform] = useState('All platforms'),
    [open, setOpen] = useState(false),
    [library, setLibrary] = useState<Library>(blank),
    [loading, setLoading] = useState(!!name),
    [error, setError] = useState(''),
    [notice, setNotice] = useState(''),
    [busy, setBusy] = useState(false),
    [progress, setProgress] = useState(''),
    [input, setInput] = useState(''),
    [importMode, setImportMode] = useState('link'),
    [importError, setImportError] = useState(''),
    [importResult, setImportResult] = useState(''),
    [player, setPlayer] = useState<Item | null>(null),
    [hostname, setHostname] = useState(''),
    [demo, setDemo] = useState(false);
  const syncLock = useRef(false);
  const libraryRef = useRef(library);
  libraryRef.current = library;
  const load = useCallback(async () => {
    if (!name) return blank;
    try {
      const data: Library = await api('library');
      setLibrary(data);
      setError('');
      return data;
    } catch (e) {
      setError((e as Error).message);
      return null;
    } finally {
      setLoading(false);
    }
  }, [name]);
  useEffect(() => {
    setHostname(window.location.hostname);
    void load();
  }, [load]);
  const refresh = useCallback(
    async (creators: Creator[], silent = false) => {
      if (syncLock.current || !creators.length) return;
      syncLock.current = true;
      setBusy(true);
      setError('');
      let failed = 0;
      try {
        for (let i = 0; i < creators.length; i += 6) {
          setProgress(
            `Refreshing ${Math.min(i + 6, creators.length)} of ${creators.length}`,
          );
          const result = await api('sync', 'POST', {
            ids: creators.slice(i, i + 6).map((c) => c.id),
          });
          failed += result.results.filter((r: any) => r.error).length;
        }
        await load();
        if (!silent)
          setNotice(
            failed
              ? `Refresh finished. ${failed} subscription${failed === 1 ? ' needs' : 's need'} attention in Subscriptions.`
              : 'Your feed is up to date.',
          );
      } catch (e) {
        setError((e as Error).message);
      } finally {
        setBusy(false);
        setProgress('');
        syncLock.current = false;
      }
    },
    [load],
  );
  useEffect(() => {
    if (view !== 'Live now' || !name) return;
    const update = () => {
      const list = libraryRef.current.subscriptions.filter(
        (c) => c.platform !== 'Podcasts',
      );
      void refresh(list, true);
    };
    update();
    const timer = setInterval(update, 120000);
    return () => clearInterval(timer);
  }, [view, name, refresh]);
  async function importSources(sources: string[]) {
    if (!name) {
      setImportError('Sign in first to save subscriptions to your account.');
      return;
    }
    setBusy(true);
    setImportError('');
    setImportResult('');
    try {
      const unique = [...new Set(sources.map((s) => s.trim()).filter(Boolean))];
      if (!unique.length)
        throw new Error('Add a creator URL or choose a subscription file.');
      if (unique.length > 100)
        throw new Error('Import up to 100 subscriptions at a time.');
      const r = await api('library', 'POST', { sources: unique });
      setImportResult(
        `${r.added} subscription${r.added === 1 ? '' : 's'} added. Existing subscriptions are skipped.`,
      );
      if (r.errors.length)
        setImportError(
          r.errors.map((e: any) => `${e.url}: ${e.error}`).join('\n'),
        );
      const updated = await load();
      setDemo(false);
      if (updated && r.added) {
        setBusy(false);
        await refresh(
          updated.subscriptions.filter((c) => r.ids.includes(c.id)),
        );
      }
    } catch (e) {
      setImportError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function readFile(file: File | undefined) {
    if (!file) return;
    setImportError('');
    try {
      if (file.size > 1000000)
        throw new Error('Choose a file smaller than 1 MB.');
      const content = await file.text();
      let sources: string[] = [];
      if (/\.(opml|xml)$/i.test(file.name)) {
        const xml = new DOMParser().parseFromString(content, 'application/xml');
        if (xml.querySelector('parsererror'))
          throw new Error('This is not a valid OPML file.');
        sources = [...xml.querySelectorAll('outline[xmlUrl]')].map(
          (x) => x.getAttribute('xmlUrl') ?? '',
        );
      } else if (/\.json$/i.test(file.name)) {
        const data = JSON.parse(content);
        const list = Array.isArray(data)
          ? data
          : (data.subscriptions ?? data.follows ?? data.data);
        if (!Array.isArray(list))
          throw new Error(
            'JSON must contain a list of creator URLs or subscriptions.',
          );
        sources = list
          .map((x: any) =>
            typeof x === 'string'
              ? x
              : (x.url ??
                x.channel_url ??
                (x.broadcaster_login
                  ? 'https://www.twitch.tv/' + x.broadcaster_login
                  : '')),
          )
          .filter(Boolean);
      } else sources = csvSources(content);
      if (!sources.length)
        throw new Error(
          'No subscription URLs found. Use YouTube subscriptions.csv, podcast OPML, or a list of HTTPS creator URLs.',
        );
      setInput([...new Set(sources)].join('\n'));
      setImportResult(
        `${sources.length} links ready to import. Review them below.`,
      );
    } catch (e) {
      setImportError((e as Error).message);
    }
  }
  async function remove(c: Creator) {
    setBusy(true);
    try {
      await api('library', 'DELETE', { id: c.id });
      await load();
      setNotice(`${c.name} removed from your subscriptions.`);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  const live = library.items.filter(
    (i) => i.live === 1 && Date.now() - i.checked_at < 300000,
  );
  const selected = (
    demo ? examples : view === 'Live now' ? live : library.items
  ).filter((i) => platform === 'All platforms' || i.platform === platform);
  const creators = (
    view === 'Discover' ? library.recommendations : library.subscriptions
  ).filter((c) => platform === 'All platforms' || c.platform === platform);
  const noSubs = library.subscriptions.length === 0;
  function chooseView(v: string) {
    setView(v);
    setDemo(false);
    setPlatform('All platforms');
    setNotice('');
  }
  const playerVideo =
    player?.platform === 'YouTube'
      ? new URL(player.url).searchParams.get('v')
      : null;
  return (
    <SidebarProvider>
      <Sidebar className="hub-sidebar">
        <SidebarHeader>
          <a href="/" className="brand">
            <span className="brand-mark">
              <Layers3 />
            </span>
            Gather<span className="brand-dot">.</span>
          </a>
        </SidebarHeader>
        <SidebarContent>
          <p className="nav-label">YOUR SPACE</p>
          <nav>
            {[
              { icon: Layers3, label: 'Your feed' },
              { icon: Radio, label: 'Live now' },
              { icon: Compass, label: 'Discover' },
              { icon: Users, label: 'Subscriptions' },
            ].map(({ icon: Icon, label }) => (
              <button
                className={'nav-item ' + (view === label ? 'active' : '')}
                key={label}
                onClick={() => chooseView(label)}
                aria-current={view === label ? 'page' : undefined}
              >
                <Icon size={19} />
                {label}
                {label === 'Live now' && live.length > 0 && (
                  <span className="nav-count">{live.length}</span>
                )}
                {label === 'Subscriptions' && (
                  <span className="nav-count">
                    {library.subscriptions.length}
                  </span>
                )}
              </button>
            ))}
          </nav>
          <div className="sidebar-note">
            <div className="tiny-stack">
              <SquarePlay />
              <MessageSquare />
              <Headphones />
            </div>
            <h3>
              All your favorites.
              <br />
              One good place.
            </h3>
            <p>
              Bring your subscriptions together and make room for something new.
            </p>
            <button onClick={() => setOpen(true)}>
              Import subscriptions <Plus size={16} />
            </button>
          </div>
        </SidebarContent>
        <SidebarFooter>
          <div className="profile">
            <span className="avatar">{name ? name[0].toUpperCase() : 'G'}</span>
            <div>
              <strong title={name ?? ''}>
                {name ?? 'Your personal space'}
              </strong>
              <small>
                {name ? 'Your private library' : 'Sign in to save your feed'}
              </small>
            </div>
          </div>
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="topbar">
          <div>
            <SidebarTrigger />
            <span>MY LIBRARY</span>
          </div>
          <a
            href={
              name
                ? '/signout-with-chatgpt?return_to=/'
                : '/signin-with-chatgpt?return_to=/'
            }
            target="_top"
          >
            {name ? 'Sign out' : 'Sign in'} <ArrowUpRight size={15} />
          </a>
        </header>
        <main className="workspace">
          <div className="page-heading">
            <div>
              <div className="eyebrow">YOUR CREATORS. YOUR FEED.</div>
              <h1>{view}</h1>
              <p>
                {view === 'Discover'
                  ? 'Find your next favorite through people with shared interests.'
                  : view === 'Live now'
                    ? 'Be there while it happens.'
                    : view === 'Subscriptions'
                      ? 'The creators that make your feed yours.'
                      : 'Your corner of the internet, brought together.'}
              </p>
            </div>
            <button className="primary" onClick={() => setOpen(true)}>
              <Plus size={17} /> Import subscriptions
            </button>
          </div>
          {error && (
            <div className="message error" role="alert">
              {error} <button onClick={() => void load()}>Try again</button>
            </div>
          )}
          {notice && (
            <div className="message" role="status">
              <Check size={16} />
              {notice}
            </div>
          )}
          {noSubs && view === 'Your feed' && !demo && (
            <section className="welcome-panel">
              <div>
                <span className="pill">START WITH YOUR FAVORITES</span>
                <h2>Make yourself at home.</h2>
                <p>
                  Add a YouTube channel, Twitch creator, or podcast feed.
                  <br />
                  Your next watch starts right here.
                </p>
                <button className="light-button" onClick={() => setOpen(true)}>
                  Build your feed <ArrowUpRight size={17} />
                </button>
              </div>
              <div className="platform-stack" aria-hidden="true">
                <div>
                  <SquarePlay />
                  <span>YouTube</span>
                </div>
                <div>
                  <MessageSquare />
                  <span>Twitch</span>
                </div>
                <div>
                  <Headphones />
                  <span>Podcasts</span>
                </div>
              </div>
            </section>
          )}
          <div className="section-heading">
            <h2>
              {view === 'Your feed'
                ? demo
                  ? 'A look around Gather'
                  : 'The latest from your world'
                : view === 'Discover'
                  ? 'Recommended by shared subscribers'
                  : view === 'Live now'
                    ? `${live.length} live ${live.length === 1 ? 'stream' : 'streams'}`
                    : `${library.subscriptions.length} subscriptions`}
            </h2>
            {!noSubs && (
              <button
                className="quiet refresh-button"
                onClick={() => void refresh(library.subscriptions)}
                disabled={busy}
              >
                <RefreshCw size={14} className={busy ? 'spin' : ''} />
                {progress || 'Refresh feed'}
              </button>
            )}
          </div>
          <Tabs value={platform} onValueChange={(v) => setPlatform(String(v))}>
            <TabsList className="platform-tabs" aria-label="Filter by platform">
              {['All platforms', 'YouTube', 'Twitch', 'Podcasts'].map((p) => (
                <TabsTrigger value={p} key={p}>
                  {p}
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
          {view === 'Live now' && (
            <p className="section-hint">
              Live status refreshes every 2 minutes while this view is open.
              Checks older than 5 minutes are hidden. YouTube checks cover
              videos in the channel’s recent feed.
            </p>
          )}
          {view === 'Discover' && (
            <p className="section-hint">
              Ranked by the number of other users who follow a creator and share
              at least one of your subscriptions. Individual users’ libraries
              stay private.
            </p>
          )}
          {demo && (
            <div className="message sample-message">
              Sample videos · These are real examples, not your subscriptions or
              current recommendations.
              <button onClick={() => setDemo(false)}>Exit sample feed</button>
            </div>
          )}
          {loading ? (
            <div className="empty-feed" role="status">
              <RefreshCw className="spin" />
              <p>Loading your library…</p>
            </div>
          ) : view === 'Subscriptions' || view === 'Discover' ? (
            creators.length ? (
              <div className="creator-grid">
                {creators.map((c) => (
                  <article className="creator-card" key={c.id}>
                    <div
                      className={'creator-symbol ' + c.platform.toLowerCase()}
                    >
                      <PlatformIcon platform={c.platform} />
                    </div>
                    <div className="creator-info">
                      <span className="platform-name">{c.platform}</span>
                      <h3>{c.name}</h3>
                      {view === 'Discover' ? (
                        <p>
                          <Users size={14} />
                          {c.shared_users} shared subscriber
                          {c.shared_users === 1 ? '' : 's'}
                        </p>
                      ) : (
                        <p>
                          {c.synced_at
                            ? 'Checked ' +
                              new Date(c.synced_at).toLocaleString()
                            : 'Ready to refresh'}
                        </p>
                      )}
                      <a href={c.url} target="_blank" rel="noopener noreferrer">
                        Visit creator <ExternalLink size={13} />
                      </a>
                    </div>
                    {view === 'Discover' ? (
                      <button
                        className="outline-button"
                        disabled={busy}
                        onClick={() => void importSources([c.url])}
                      >
                        <Plus size={15} /> Follow
                      </button>
                    ) : (
                      <button
                        className="remove-button"
                        title={'Remove ' + c.name}
                        aria-label={'Remove ' + c.name}
                        disabled={busy}
                        onClick={() => void remove(c)}
                      >
                        <Trash2 size={17} />
                      </button>
                    )}
                    {c.error && view === 'Subscriptions' && (
                      <p className="creator-error">{c.error}</p>
                    )}
                  </article>
                ))}
              </div>
            ) : (
              <Empty
                icon={view === 'Discover' ? <Compass /> : <Users />}
                title={
                  view === 'Discover'
                    ? 'Your next favorite is on its way'
                    : 'Start your collection'
                }
                description={
                  view === 'Discover'
                    ? 'As other users join and share your interests, new creator recommendations will appear here.'
                    : 'Add creators from the platforms you already love.'
                }
                action={noSubs ? () => setOpen(true) : undefined}
              />
            )
          ) : selected.length ? (
            <div className="content-grid">
              {selected.map((item) => (
                <article className="content-card" key={item.id}>
                  <button
                    className="thumbnail"
                    onClick={() => setPlayer(item)}
                    aria-label={'Play ' + item.title}
                  >
                    {item.image ? (
                      <img
                        src={item.image}
                        alt=""
                        loading="lazy"
                        onError={(e) => {
                          e.currentTarget.style.display = 'none';
                        }}
                      />
                    ) : (
                      <Headphones size={48} />
                    )}
                    <span className="play-overlay">
                      <Play size={24} fill="currentColor" />
                    </span>
                    {item.live === 1 &&
                      Date.now() - item.checked_at < 300000 && (
                        <span className="live-badge">● LIVE</span>
                      )}
                    <span className="media-label">
                      <PlatformIcon platform={item.platform} />
                      {item.platform}
                    </span>
                  </button>
                  <div className="card-copy">
                    <p className="creator-name">{item.creator}</p>
                    <button
                      onClick={() => setPlayer(item)}
                      className="content-title"
                    >
                      {item.title}
                    </button>
                    <p className="content-meta">
                      {demo
                        ? 'Sample video'
                        : item.published
                          ? new Date(item.published).toLocaleDateString(
                              undefined,
                              {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                              },
                            )
                          : 'Publication date unavailable'}
                      {item.media ? ' · Podcast episode' : ''}
                    </p>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <Empty
              icon={view === 'Live now' ? <Radio /> : <Play />}
              title={
                view === 'Live now'
                  ? 'No verified live streams right now'
                  : noSubs
                    ? 'Your feed starts with your favorites'
                    : 'Nothing here yet'
              }
              description={
                view === 'Live now'
                  ? 'Check Subscriptions for any connection issues, or come back for the next stream.'
                  : noSubs
                    ? 'Import your subscriptions to see videos and episodes here.'
                    : 'Refresh your feed or choose another platform. Any feed errors are shown in Subscriptions.'
              }
              action={noSubs ? () => setOpen(true) : undefined}
            />
          )}
          {noSubs && view === 'Your feed' && !demo && (
            <button className="sample-link" onClick={() => setDemo(true)}>
              See how a filled feed looks <ArrowUpRight size={15} />
            </button>
          )}
          {view === 'Your feed' && (
            <div className="community-note">
              <Users size={21} />
              <div>
                <strong>A little discovery, powered by people.</strong>
                <p>
                  Shared subscriptions help you find creators through people
                  with similar taste.
                </p>
              </div>
              <button
                onClick={() => chooseView('Discover')}
                aria-label="Explore recommendations"
              >
                <ArrowUpRight size={21} />
              </button>
            </div>
          )}
        </main>
        <footer className="page-footer">
          <span>Gather · Content Curator</span>
          <span>Your creators. Your feed.</span>
        </footer>
      </SidebarInset>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="import-dialog">
          <DialogTitle>Bring your subscriptions</DialogTitle>
          <DialogDescription>
            Add public creator links, or import a subscription file. Following
            here only changes your Gather library.
          </DialogDescription>
          {!name ? (
            <a
              href="/signin-with-chatgpt?return_to=/"
              target="_top"
              className="primary"
            >
              Sign in to import <ArrowUpRight size={16} />
            </a>
          ) : (
            <>
              <Tabs
                value={importMode}
                onValueChange={(v) => setImportMode(String(v))}
              >
                <TabsList className="platform-tabs">
                  <TabsTrigger value="link">
                    <LinkIcon size={15} /> Paste links
                  </TabsTrigger>
                  <TabsTrigger value="file">
                    <Upload size={15} /> Import a file
                  </TabsTrigger>
                </TabsList>
              </Tabs>
              {importMode === 'file' && (
                <label className="file-drop">
                  <Upload />
                  <strong>Choose a subscription file</strong>
                  <span>
                    YouTube CSV · Podcast OPML · URL lists (TXT / JSON)
                  </span>
                  <input
                    type="file"
                    accept=".csv,.opml,.xml,.txt,.json"
                    onChange={(e) => void readFile(e.target.files?.[0])}
                  />
                </label>
              )}
              <label className="field-label" htmlFor="sources">
                Creator or feed links{' '}
                <span>One per line · Up to 100 at a time</span>
              </label>
              <textarea
                id="sources"
                rows={5}
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder={
                  'https://www.youtube.com/channel/UC…\nhttps://www.twitch.tv/creator\nhttps://feeds.simplecast.com/…'
                }
              />
              <p className="import-help">
                YouTube: use channel IDs or export subscriptions.csv through
                Google Takeout. Twitch: paste channel URLs or import a URL list.
                Podcasts: use public RSS links or an OPML export.
              </p>
              <details className="supported-hosts">
                <summary>Supported podcast hosts and live connections</summary>
                <p>
                  Simplecast, Megaphone, Transistor, Anchor, Buzzsprout, ART19,
                  Acast, NPR, TWiT, FeedBurner, Libsyn, Captivate, RedCircle,
                  SoundCloud, Audioboom, Omny, Fireside, Podbean, WBUR and PBS.
                  Some redirect destinations may need support added.
                </p>
                <p>
                  Video and podcast feeds work without platform account access.
                  Twitch live status needs a Twitch API app; YouTube live status
                  needs a YouTube API key configured by the site owner.
                  Automatic account subscription syncing is not connected.
                </p>
              </details>
              <button
                className="primary"
                disabled={busy || !input.trim()}
                onClick={() => void importSources(input.split(/\r?\n/))}
              >
                {busy ? (
                  <RefreshCw size={16} className="spin" />
                ) : (
                  <Plus size={16} />
                )}{' '}
                {busy ? progress || 'Importing…' : 'Import subscriptions'}
              </button>
            </>
          )}
          {importResult && (
            <div className="message" role="status">
              {importResult}
            </div>
          )}
          {importError && (
            <div className="message error import-errors" role="alert">
              {importError}
            </div>
          )}
        </DialogContent>
      </Dialog>
      <Dialog
        open={!!player}
        onOpenChange={(o) => {
          if (!o) setPlayer(null);
        }}
      >
        <DialogContent className="player-dialog">
          <DialogTitle>{player?.title}</DialogTitle>
          <DialogDescription>
            {player?.creator} · {player?.platform}
          </DialogDescription>
          {player && (
            <>
              {playerVideo && /^[\w-]{11}$/.test(playerVideo) ? (
                <iframe
                  title={player.title}
                  src={'https://www.youtube-nocookie.com/embed/' + playerVideo}
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowFullScreen
                />
              ) : player.platform === 'Twitch' && hostname ? (
                <iframe
                  title={player.title}
                  src={
                    'https://player.twitch.tv/?channel=' +
                    encodeURIComponent(new URL(player.url).pathname.slice(1)) +
                    '&parent=' +
                    encodeURIComponent(hostname) +
                    '&autoplay=false'
                  }
                  allow="autoplay; fullscreen"
                  allowFullScreen
                />
              ) : player.media ? (
                <audio controls src={player.media} preload="none" />
              ) : (
                <div className="import-option">
                  This item is available on its original platform.
                </div>
              )}
              <p className="import-help">
                Playback follows the creator’s platform settings. If playback is
                unavailable here, open the original.
              </p>
              <a
                className="outline-button"
                href={player.url}
                target="_blank"
                rel="noopener noreferrer"
              >
                Open on{' '}
                {player.platform === 'Podcasts'
                  ? 'original site'
                  : player.platform}{' '}
                <ExternalLink size={15} />
              </a>
            </>
          )}
        </DialogContent>
      </Dialog>
    </SidebarProvider>
  );
}
function Empty({
  icon,
  title,
  description,
  action,
}: {
  icon: React.ReactNode;
  title: string;
  description: string;
  action?: () => void;
}) {
  return (
    <div className="empty-feed">
      <span className="empty-icon">{icon}</span>
      <h3>{title}</h3>
      <p>{description}</p>
      {action && (
        <button className="outline-button" onClick={action}>
          <Plus size={16} /> Add your first subscriptions
        </button>
      )}
    </div>
  );
}

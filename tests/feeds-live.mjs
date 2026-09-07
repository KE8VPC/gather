import assert from 'node:assert/strict';
const base = 'http://localhost:3000';
const signin = await fetch(base + '/signin-with-chatgpt?return_to=/', {
  redirect: 'manual',
});
const cookie = signin.headers.get('set-cookie').split(';')[0];
async function api(path, data, method = 'POST') {
  const r = await fetch(base + '/api/' + path, {
    method,
    headers: { cookie, Origin: base, 'Content-Type': 'application/json' },
    body: data ? JSON.stringify(data) : undefined,
  });
  const j = await r.json();
  assert.equal(r.status, 200, JSON.stringify(j));
  return j;
}
const imported = await api('library', {
  sources: [
    'https://www.youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ',
    'https://feeds.npr.org/510289/podcast.xml',
  ],
});
try {
  const result = await api('sync', { ids: imported.ids });
  const library = await api('library', null, 'GET');
  for (const id of imported.ids) {
    const c = library.subscriptions.find((c) => c.id === id);
    const count = library.items.filter((i) => i.creator_id === id).length;
    console.log(
      c.platform,
      JSON.stringify({ name: c.name, count, status: c.error ?? 'updated' }),
    );
    assert.ok(count > 0, JSON.stringify(result));
  }
} finally {
  for (const id of imported.ids) await api('library', { id }, 'DELETE');
}
console.log(
  'PASS: real YouTube and NPR RSS import, fetch, parse and durable content storage',
);

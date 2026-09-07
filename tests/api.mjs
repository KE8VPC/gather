import assert from 'node:assert/strict';
const base = 'http://localhost:3000';
const sign = await fetch(base + '/signin-with-chatgpt?return_to=/', {
  redirect: 'manual',
});
assert.equal(sign.status, 302);
const cookie = sign.headers.get('set-cookie').split(';')[0];
async function req(
  path,
  method = 'GET',
  data,
  authorized = true,
  origin = base,
) {
  const r = await fetch(base + path, {
    method,
    headers: {
      ...(authorized ? { cookie } : {}),
      ...(method === 'GET'
        ? {}
        : { Origin: origin, 'Content-Type': 'application/json' }),
    },
    body: data ? JSON.stringify(data) : undefined,
  });
  const raw = await r.text();
  let body;
  try {
    body = JSON.parse(raw);
  } catch {
    body = { error: raw };
  }
  return { status: r.status, body };
}
assert.equal((await req('/api/library', 'GET', null, false)).status, 401);
assert.equal(
  (
    await req(
      '/api/library',
      'POST',
      { sources: ['https://twitch.tv/gather_test_channel'] },
      true,
      'https://evil.example',
    )
  ).status,
  403,
);
const imported = await req('/api/library', 'POST', {
  sources: ['https://twitch.tv/gather_test_channel', 'https://127.0.0.1/feed'],
});
assert.equal(imported.status, 200, JSON.stringify(imported));
assert.equal(imported.body.errors.length, 1);
const id = imported.body.ids[0];
const duplicate = await req('/api/library', 'POST', {
  sources: ['https://www.twitch.tv/gather_test_channel'],
});
assert.equal(duplicate.body.added, 0);
const library = await req('/api/library');
assert.equal(library.status, 200, JSON.stringify(library));
assert.equal(library.body.subscriptions.filter((c) => c.id === id).length, 1);
const sync = await req('/api/sync', 'POST', { ids: [id, 'not-owned'] });
assert.equal(sync.status, 200, JSON.stringify(sync));
assert.equal(sync.body.results[1].error, 'Subscription not found.');
assert.ok(sync.body.results[0].error?.includes('Twitch'));
assert.equal((await req('/api/library', 'DELETE', { id })).status, 200);
assert.ok(
  !(await req('/api/library')).body.subscriptions.some((c) => c.id === id),
);
console.log(
  'PASS: local sign-in, unauthorized read, cross-origin write rejection, import, duplicate, user-owned sync, missing Twitch credentials and removal',
);

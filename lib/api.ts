import { getChatGPTUser } from '../app/chatgpt-auth';
export async function identity(request?: Request) {
  const u = await getChatGPTUser();
  if (!u)
    throw new Response(
      JSON.stringify({ error: 'Sign in to use your library.' }),
      { status: 401, headers: { 'content-type': 'application/json' } },
    );
  if (request && request.method !== 'GET') {
    const origin = request.headers.get('origin');
    if (!origin || origin !== new URL(request.url).origin)
      throw new Response(
        JSON.stringify({ error: 'Request origin is not allowed.' }),
        { status: 403 },
      );
  }
  return u.userId;
}
export function failure(e: unknown) {
  if (e instanceof Response) return e;
  return Response.json(
    {
      error:
        e instanceof Error ? e.message : 'Unable to complete this request.',
    },
    { status: 400 },
  );
}
export async function body(request: Request) {
  if (Number(request.headers.get('content-length') ?? 0) > 100000)
    throw new Error('Import is too large. Maximum 100 KB.');
  const reader = request.body?.getReader();
  if (!reader) throw new Error('Missing request.');
  let data = '',
    size = 0;
  const decoder = new TextDecoder();
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    size += value.length;
    if (size > 100000) {
      await reader.cancel();
      throw new Error('Import is too large. Maximum 100 KB.');
    }
    data += decoder.decode(value, { stream: true });
  }
  return JSON.parse(data + decoder.decode());
}

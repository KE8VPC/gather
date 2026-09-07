import Hub from './hub';
import { getChatGPTUser } from './chatgpt-auth';
export const dynamic = 'force-dynamic';
export default async function Page() {
  const user = await getChatGPTUser();
  return <Hub name={user?.displayName ?? null} />;
}

import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const base=process.env.GATHER_TEST_URL;
const php=process.env.GATHER_TEST_PHP;
if(!base || !php || !['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Provide a loopback GATHER_TEST_URL and GATHER_TEST_PHP for a disposable database.');
const email='linked-http-'+randomUUID()+'@example.test';
let cookie='',csrf='';
async function request(path,options={}){const r=await fetch(base+'/'+path,{redirect:'manual',...options,headers:{cookie,...options.headers}});if(r.headers.get('set-cookie'))cookie=r.headers.get('set-cookie').split(';')[0];return {status:r.status,headers:r.headers,body:await r.text()};}
async function action(data,token=csrf){return request('connections-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify(data)});}
try{
 assert.equal((await request('connections.php')).status,302);
 assert.equal((await action({provider:'youtube',action:'start'})).status,401);
 let r=await request('connections.php?preview=1');assert.equal(r.status,200);assert.match(r.body,/This is a preview/);assert.match(r.body,/Sign in to connect/);
 r=await request('auth.php?mode=register');csrf=r.body.match(/name="csrf" value="([^"]+)"/)[1];
 r=await request('auth.php?mode=register',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf,email,name:'Linked HTTP test',password:'unique-connections-test-2026'})});assert.equal(r.status,302,r.body);
 r=await request('connections.php');assert.equal(r.status,200);csrf=r.body.match(/name="csrf-token" content="([^"]+)"/)[1];assert.match(r.body,/Not enabled yet/);assert.doesNotMatch(r.body,/fake-access|fake-refresh|client_secret|encryption_key/);
 assert.equal((await request('connections-api.php')).status,405);
 assert.equal((await action({provider:'youtube',action:'disconnect'},'bad')).status,403);
 assert.equal((await action({provider:'rumble',action:'start'})).status,400);
 assert.equal((await action({provider:['youtube'],action:'start'})).status,400);
 assert.equal((await action({provider:'youtube',action:'start'})).status,503);
 assert.equal((await request('oauth.php?provider=youtube&state=forged&code=secret')).status,403);
 assert.equal((await request('oauth.php?provider=twitch',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'connect',csrf:'bad'})})).status,403);
 assert.equal((await action({provider:'youtube',action:'disconnect'})).status,200);
 r=await request('index.php');assert.match(r.body,/Linked accounts/);assert.match(r.body,/connections.php/);
 assert.equal((await request('api.php?action=library')).status,200);
 console.log('PASS: nested routes, anonymous access, preview, missing credentials, CSRF, malformed providers, callback state, disconnect and existing library access.');
}finally{
 const bootstrap=fileURLToPath(new URL('../app/bootstrap.php',import.meta.url));
 const result=spawnSync(php,['-r',"require $argv[1]; query('DELETE FROM users WHERE email=?',[$argv[2]]);",bootstrap,email],{encoding:'utf8'});
 if(result.status!==0)throw new Error('Test-account cleanup failed.');
}

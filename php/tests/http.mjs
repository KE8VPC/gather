import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const base=process.env.GATHER_TEST_URL||'http://127.0.0.1:8088';
const suffix=randomUUID().slice(0,8);
class Browser {
 cookie=''; token='';
 async request(path,options={}){const r=await fetch(base+'/'+path,{redirect:'manual',...options,headers:{cookie:this.cookie,...options.headers}});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];const body=await r.text();return {status:r.status,body,headers:r.headers};}
 async register(name){let r=await this.request('auth.php?mode=register');assert.ok((r.headers.get('set-cookie')||'').includes('path='+new URL(base+'/').pathname),'cookie is scoped to application path');this.token=r.body.match(/name="csrf" value="([^"]+)"/)[1];const old=this.cookie;r=await this.request('auth.php?mode=register',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:this.token,email:name+suffix+'@example.test',name,password:'a-unique-test-password-2026'})});assert.equal(r.status,302,r.body);assert.notEqual(this.cookie,old,'session rotates');r=await this.request('index.php');this.token=r.body.match(/name="csrf-token" content="([^"]+)"/)[1];}
 async api(action='library',method='GET',data,token=this.token){const r=await this.request('api.php?action='+action,{method,headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:data?JSON.stringify(data):undefined});return {...r,json:JSON.parse(r.body)};}
}
const anon=new Browser();assert.equal((await anon.api()).status,401);
const a=new Browser(),b=new Browser(),c=new Browser();await a.register('Alice');await b.register('Bob');await c.register('Carol');
assert.equal((await a.api('library','POST',{sources:['https://twitch.tv/test']},'wrong')).status,403);
const shared='https://www.youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ',candidate='https://twitch.tv/gather_recommend_'+suffix;
let r=await a.api('library','POST',{sources:[shared,'https://127.0.0.1/private']});assert.equal(r.status,200,r.body);assert.equal(r.json.added,1);assert.equal(r.json.errors.length,1);const sharedId=r.json.ids[0];
assert.equal((await a.api('library','POST',{sources:[shared]})).json.added,0);
await b.api('library','POST',{sources:[shared,candidate]});const candidateId=(await c.api('library','POST',{sources:[candidate]})).json.ids[0];
r=await a.api();assert.equal(r.json.subscriptions.length,1);assert.equal(r.json.recommendations.find(x=>x.id===candidateId).shared_users,1,'Non-peer excluded');
await c.api('library','POST',{sources:[shared]});r=await a.api();assert.equal(r.json.recommendations.find(x=>x.id===candidateId).shared_users,2,'Two distinct peers');
await a.api('library','DELETE',{id:candidateId});assert.ok((await b.api()).json.subscriptions.some(x=>x.id===candidateId));assert.equal((await a.api('sync','POST',{id:candidateId})).status,404);
r=await a.api('sync','POST',{id:sharedId});assert.equal(r.status,200,r.body);assert.ok((await a.api()).json.items.length>0,r.body);
const podcast=(await a.api('library','POST',{sources:['https://feeds.npr.org/510289/podcast.xml']})).json.ids[0];r=await a.api('sync','POST',{id:podcast});assert.equal(r.status,200,r.body);assert.ok((await a.api()).json.items.some(i=>i.creator_id===podcast),r.body);
r=await b.api('sync','POST',{id:candidateId});assert.match(r.json.error,/Twitch/);
const loggedOut=await a.request('auth.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:a.token,action:'logout'})});assert.equal(loggedOut.status,302);assert.ok(loggedOut.headers.get('set-cookie').includes('path='+new URL(base+'/').pathname),'logout clears the same cookie path');assert.equal((await a.api()).status,401);
console.log('PASS: registration, session rotation, CSRF, per-user libraries, duplicates, two-peer recommendations, scoped removal/sync, real YouTube + podcast imports, credential warnings, logout.');



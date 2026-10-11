import assert from 'node:assert/strict';
const base=process.env.GATHER_TEST_MOUNT||'http://127.0.0.1:8088/nested/gather/';
const response=await fetch(base);assert.equal(response.status,200);const html=await response.text();
for(const link of ['public/index.php','public/auth.php','public/preview.php','public/assets/site.css','public/assets/welcome.css','public/assets/favicon.svg'])assert.ok(html.includes('"'+link+'"'),link);
for(const asset of ['public/assets/site.css','public/assets/welcome.css','public/assets/favicon.svg','public/preview.php']){const r=await fetch(new URL(asset,base));await r.arrayBuffer();assert.equal(r.status,200,asset);}
const auth=await fetch(new URL('public/index.php',base),{redirect:'manual'});await auth.text();assert.equal(auth.status,302);assert.equal(auth.headers.get('location'),'auth.php');
console.log('PASS: nested welcome, relative links, styles, preview, and relative dashboard sign-in redirect.');

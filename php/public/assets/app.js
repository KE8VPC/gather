'use strict';
const $=s=>document.querySelector(s);
const preview=document.body.dataset.preview==='true';
let library={subscriptions:[],items:[],live_items:[],recommendations:[]},view='Your feed',platform='All platforms',sample=preview,busy=false,loading=!preview,serverClockOffset=0;
const examples=[['dtp6b76pMak','Using Apple Vision Pro: What It’s Actually Like!','Marques Brownlee'],['DxL2HoqLbyA','The Most Misunderstood Concept in Physics','Veritasium'],['_1MIAMxixag','Graphic Design Tutorial: Typography Design & Art Direction pt. 1','The Futur Academy'],['u4PmMeMFXMY','Hiking 75 miles Alone on the Alta Via 1 Dolomites','Kraig Adams']].map(([id,title,creator])=>({id,title,creator,url:'https://www.youtube.com/watch?v='+id,image:'https://i.ytimg.com/vi/'+id+'/hqdefault.jpg',platform:'YouTube',live:0,published:0}));
function node(tag,attrs={},...children){const el=document.createElement(tag);for(const [k,v] of Object.entries(attrs)){if(k.startsWith('on'))el.addEventListener(k.slice(2),v);else if(k==='class')el.className=v;else el.setAttribute(k,String(v));}for(const child of children.flat())if(child!==null&&child!==undefined)el.append(child instanceof Node?child:document.createTextNode(String(child)));return el;}
function message(text,error=false,target='#status'){const box=$(target);box.replaceChildren();if(text)box.append(node('div',{class:'message'+(error?' error':''),role:error?'alert':'status'},text));}
function safeURL(url){try{const u=new URL(url);return u.protocol==='https:'&&!u.username&&!u.password?u.href:'';}catch{return '';}}
async function api(action,method='GET',data){if(preview)throw new Error('This is a safe preview. Install Gather and sign in to save your own subscriptions.');const response=await fetch('api.php?action='+encodeURIComponent(action),{method,headers:{'Content-Type':'application/json','X-CSRF-Token':$('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});const json=await response.json();if(!response.ok)throw new Error(json.error||'Unable to complete this request.');return json;}
async function load(liveOnly=false){try{
 const result=await api(liveOnly?'live':'library');library=liveOnly?{...library,...result}:result;
 if(liveOnly&&result.youtube_feeds){const states=new Map(result.youtube_feeds.map(c=>[c.id,c]));library.subscriptions=library.subscriptions.map(c=>({...c,...(states.get(c.id)||{})}));}
 serverClockOffset=Number(library.server_time||Date.now())-Date.now();
}catch(e){message(e.message,true);}finally{loading=false;render();}}
function isLive(item){return Number(item.live)===1&&Date.now()+serverClockOffset-Number(item.checked_at)<300000;}
function setBusy(value){busy=value;$('#refresh').disabled=value;$('#import-submit').disabled=value;}
async function refresh(ids=library.subscriptions.map(c=>c.id),silent=false){
 if(busy||!ids.length)return;setBusy(true);
 let failures=0,done=0,lastLoad=Date.now(),lastTwitch=Date.now(),youtubeRequestsLeft=20,youtubePending=0,youtubeError='';
 const selected=new Set(ids),creators=library.subscriptions.filter(c=>selected.has(c.id));
 const twitchIds=creators.filter(c=>c.platform==='Twitch').map(c=>c.id);
 const youtube=creators.filter(c=>c.platform==='YouTube');
 const dueFeeds=youtube.filter(c=>!Number(c.synced_at)||Date.now()+serverClockOffset-Number(c.synced_at)>=3600000).sort((a,b)=>Number(a.synced_at)-Number(b.synced_at));
 const dueYouTube=(silent?dueFeeds.slice(0,20):dueFeeds).map(c=>c.id);
 const otherIds=[...dueYouTube,...creators.filter(c=>c.platform==='Podcasts').map(c=>c.id)];
 const progress=()=>message(otherIds.length?`Refreshing ${done} of ${otherIds.length} due feeds. YouTube feeds are cached for one hour.`:'Checking live streams using cached videos…');
 const checkYouTube=async(maxRequests)=>{
  if(!youtube.length||youtubeError)return;
  for(let i=0;i<maxRequests&&youtubeRequestsLeft>0;i++){
   const result=await api('youtube-live','POST',{});youtubeRequestsLeft--;youtubePending=Number(result.pending)||0;
   if(result.error){youtubeError=result.error;break;}
   if(!result.checked||!youtubePending)break;
   if(i%5===4)await load(view==='Live now');
  }
 };
 const checkTwitch=async()=>{
  for(let i=0;i<twitchIds.length;i+=100){const result=await api('sync-twitch','POST',{ids:twitchIds.slice(i,i+100)});failures+=result.results.filter(r=>r.error).length;await load(view==='Live now');}
  lastTwitch=Date.now();
 };
 try{
  progress();await checkTwitch();await checkYouTube(5);await load(view==='Live now');
  for(const id of otherIds){
   if(view==='Live now'&&Date.now()-lastTwitch>=120000){await checkTwitch();await checkYouTube(2);await load(view==='Live now');}
   const result=await api('sync','POST',{id});if(result.error)failures++;done++;progress();
   if(Date.now()-lastLoad>=10000){await load(view==='Live now');lastLoad=Date.now();}
  }
  if(dueYouTube.length||youtubePending){await checkYouTube(20);await load(view==='Live now');}else if(otherIds.length)await load(view==='Live now');
  message(youtubeError|| (failures?`Refresh finished with ${failures} feed notes. Open Subscriptions for details.`:youtubePending?`${youtubePending} cached videos await their first or next check. Checks continue while Live now is open.`:dueFeeds.length>dueYouTube.length?`${dueFeeds.length-dueYouTube.length} channel feeds are queued for gradual discovery. Cached live checks continue while this page is open.`:silent?'Live checks finished. Recent results and channel feeds were reused.':'Your feed is up to date. YouTube channel feeds are cached for one hour.'),!!youtubeError||failures>0);
 }catch(e){message(e.message,true);}finally{setBusy(false);render();}
}
function empty(title,text){return node('div',{class:'empty-feed'},node('span',{class:'empty-icon'},view==='Live now'?'◉':'▷'),node('h3',{},title),node('p',{},text),!library.subscriptions.length?node('button',{class:'outline-button',onclick:openImport},'＋ Add your first subscriptions'):null);}
function render(){
 $('#view-title').textContent=view;$('#subscription-count').textContent=library.subscriptions.length;
 $('#view-description').textContent=view==='Discover'?'Find your next favorite through people with shared interests.':view==='Live now'?'Be there while it happens.':view==='Subscriptions'?'The creators that make your feed yours.':'Your corner of the internet, brought together.';
 $('[data-view].active')?.classList.remove('active');document.querySelectorAll('nav [data-view]').forEach(b=>{b.classList.toggle('active',b.dataset.view===view);if(b.dataset.view===view)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current');});
 $('#welcome').hidden=view!=='Your feed'||library.subscriptions.length>0||sample;
 $('#refresh').hidden=!library.subscriptions.length||sample;
 $('#sample-toggle').hidden=view!=='Your feed'||library.subscriptions.length>0;
 $('#sample-toggle').textContent=sample?'Exit sample feed':'See how a filled feed looks ↗';
 const live=(library.live_items||library.items).filter(isLive);
 $('#section-title').textContent=view==='Your feed'?(sample?'A look around Gather':'The latest from your world'):view==='Discover'?'Recommended by shared subscribers':view==='Live now'?`${live.length} live streams`:`${library.subscriptions.length} subscriptions`;
 $('#section-hint').textContent=sample?'Sample videos — these are real examples, not your subscriptions or current recommendations.':view==='Discover'?'Ranked by distinct users who share at least one of your subscriptions and also follow this creator. Individual libraries remain private.':view==='Live now'?'Channel feeds are cached for one hour. Known YouTube live videos are checked about every two minutes; upcoming streams every five minutes. Checks are shared across users. New streams can take up to an hour or longer to appear. Checks older than five minutes are hidden.':'';
 if(view==='Live now'&&library.youtube_status?.error)$('#section-hint').textContent+=' '+library.youtube_status.error;
 document.querySelectorAll('[data-platform]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.platform===platform)));
 const content=$('#content');content.setAttribute('aria-busy',String(loading));content.replaceChildren();
 if(loading){content.append(empty('Loading your library…',''));return;}
 if(view==='Subscriptions'||view==='Discover'){
  const creators=(view==='Discover'?library.recommendations:library.subscriptions).filter(c=>platform==='All platforms'||c.platform===platform);
  if(!creators.length){content.append(empty(view==='Discover'?'Your next favorite is on its way':'Start your collection',view==='Discover'?'Recommendations will appear as other users join and share your interests.':'Add creators from the platforms you already love.'));return;}
  const grid=node('div',{class:'creator-grid'});
  for(const c of creators){const card=node('article',{class:'creator-card'},node('div',{class:'creator-symbol '+c.platform.toLowerCase()},c.platform==='Podcasts'?'◉':'▷'),node('div',{class:'creator-info'},node('span',{class:'platform-name'},c.platform),node('h3',{},c.name),node('p',{},view==='Discover'?`${c.shared_users} shared subscribers`:c.synced_at?`Checked ${new Date(Number(c.synced_at)).toLocaleString()}`:'Ready to refresh'),node('a',{href:safeURL(c.url)||'#',target:'_blank',rel:'noopener noreferrer'},'Visit creator ↗')),node('button',{class:view==='Discover'?'outline-button':'remove-button','aria-label':(view==='Discover'?'Follow ':'Remove ')+c.name,onclick:()=>view==='Discover'?importSources([c.url]):remove(c)},view==='Discover'?'＋ Follow':'Remove'));
   if(c.error&&view==='Subscriptions')card.append(node('p',{class:'creator-error'},c.error));grid.append(card);
  }content.append(grid);return;
 }
 const items=(sample?examples:view==='Live now'?live:library.items).filter(i=>platform==='All platforms'||i.platform===platform);
 if(!items.length){content.append(empty(view==='Live now'?(busy?'Checking live streams…':'No verified live streams right now'):library.subscriptions.length?'Nothing here yet':'Your feed starts with your favorites',view==='Live now'?'Check Subscriptions for connection notes, or come back for the next stream.':'Import subscriptions or refresh your feed to see videos and episodes here.'));return;}
 const grid=node('div',{class:'content-grid'});
 for(const item of items){const thumb=node('button',{class:'thumbnail',onclick:()=>play(item),'aria-label':'Play '+item.title});if(safeURL(item.image)){const img=node('img',{src:item.image,alt:'',loading:'lazy'});img.addEventListener('error',()=>img.remove());thumb.append(img);}else thumb.append(node('span',{},'◉'));
  thumb.append(node('span',{class:'play-overlay'},'▶'),node('span',{class:'media-label'},item.platform));if(isLive(item))thumb.append(node('span',{class:'live-badge'},'● LIVE'));
  grid.append(node('article',{class:'content-card'},thumb,node('div',{class:'card-copy'},node('p',{class:'creator-name'},item.creator),node('button',{class:'content-title',onclick:()=>play(item)},item.title),node('p',{class:'content-meta'},sample?'Sample video':Number(item.published)?new Date(Number(item.published)).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'}):'Publication date unavailable'))));
 }content.append(grid);
}
function play(item){$('#player-title').textContent=item.title;$('#player-creator').textContent=item.creator+' · '+item.platform;const media=$('#player-media');media.replaceChildren();const url=safeURL(item.url);if(!url){message('This item has no valid original URL.',true);return;}const parsed=new URL(url),video=parsed.searchParams.get('v');
 if(item.platform==='YouTube'&&/^[\w-]{11}$/.test(video||''))media.append(node('iframe',{title:item.title,src:'https://www.youtube-nocookie.com/embed/'+video+'?autoplay=1&playsinline=1',allow:'autoplay; encrypted-media; picture-in-picture; fullscreen',allowfullscreen:''}));
 else if(item.platform==='Twitch'&&/^\/[\w]{3,25}\/?$/.test(parsed.pathname))media.append(node('iframe',{title:item.title,src:'https://player.twitch.tv/?channel='+encodeURIComponent(parsed.pathname.slice(1))+'&parent='+encodeURIComponent(location.hostname)+'&autoplay=true',allow:'autoplay; fullscreen',allowfullscreen:''}));
 else if(safeURL(item.media))media.append(node('audio',{src:item.media,controls:'',preload:'none'}));
 else media.append(node('p',{class:'import-help'},'This item is available on its original platform.'));
 $('#original-link').href=url;$('#player-dialog').showModal();
}
function openImport(){$('#import-dialog').showModal();if(preview)message('Preview only. Imports become available after installation and sign-in.',false,'#import-status');}
async function importSources(sources){if(busy)return;const unique=[...new Set(sources.map(s=>s.trim()).filter(Boolean))];setBusy(true);try{if(!unique.length||unique.length>100)throw new Error('Import between 1 and 100 links at a time.');const r=await api('library','POST',{sources:unique});sample=false;const text=`${r.added} subscriptions added. Existing subscriptions are skipped.`;message(text,false,'#import-status');message(text);if(r.errors.length)message(text+'\n'+r.errors.map(e=>e.url+': '+e.error).join('\n'),true,'#import-status');await load();setBusy(false);if(r.ids.length)await refresh(r.ids);}catch(e){message(e.message,true,'#import-status');if(!$('#import-dialog').open)message(e.message,true);}finally{setBusy(false);}}
async function remove(c){if(busy)return;setBusy(true);try{await api('library','DELETE',{id:c.id});await load();message(c.name+' removed from your subscriptions.');}catch(e){message(e.message,true);}finally{setBusy(false);}}
function csvRows(text){const rows=[];let row=[],cell='',quoted=false;for(let i=0;i<text.length;i++){const c=text[i];if(c==='"'){if(quoted&&text[i+1]==='"'){cell+='"';i++;}else quoted=!quoted;}else if(c===','&&!quoted){row.push(cell);cell='';}else if((c==='\n'||c==='\r')&&!quoted){if(c==='\r'&&text[i+1]==='\n')i++;row.push(cell);if(row.some(Boolean))rows.push(row);row=[];cell='';}else cell+=c;}if(quoted)throw new Error('The CSV contains an unclosed quoted field.');row.push(cell);if(row.some(Boolean))rows.push(row);return rows;}
async function readFile(file){if(!file)return;try{if(file.size>1000000)throw new Error('Choose a file smaller than 1 MB.');const text=await file.text();let sources=[];if(/\.(xml|opml)$/i.test(file.name)){const xml=new DOMParser().parseFromString(text,'application/xml');if(xml.querySelector('parsererror'))throw new Error('Invalid OPML file.');sources=[...xml.querySelectorAll('outline[xmlUrl]')].map(n=>n.getAttribute('xmlUrl'));}else if(/\.json$/i.test(file.name)){const data=JSON.parse(text);const list=Array.isArray(data)?data:data.subscriptions||data.follows||data.data;if(!Array.isArray(list))throw new Error('JSON must contain a list of creator URLs.');sources=list.map(x=>typeof x==='string'?x:x.url||x.channel_url||(x.broadcaster_login?'https://www.twitch.tv/'+x.broadcaster_login:'')).filter(Boolean);}else{const rows=csvRows(text.replace(/^\uFEFF/,''));const headers=(rows[0]||[]).map(c=>c.trim().toLowerCase());let col=headers.findIndex(c=>['channel url','channel_url','url','feed url','feed_url'].includes(c));if(col<0)col=headers.indexOf('channel id');sources=(col>=0?rows.slice(1).map(r=>r[col]):rows.flat()).filter(v=>v&&/^(https:\/\/|UC[\w-]{22}$)/.test(v.trim()));}if(!sources.length)throw new Error('No subscription links found.');$('#sources').value=[...new Set(sources)].join('\n');message(`${sources.length} links ready. Review them, then select Import subscriptions.`,false,'#import-status');}catch(e){message(e.message,true,'#import-status');}}
document.querySelectorAll('[data-import]').forEach(b=>b.addEventListener('click',openImport));
document.querySelectorAll('[data-view]').forEach(b=>b.addEventListener('click',()=>{view=b.dataset.view;platform='All platforms';sample=false;message('');render();$('.site-sidebar').classList.remove('open');$('.mobile-menu').setAttribute('aria-expanded','false');if(view==='Live now'&&!preview)refresh(library.subscriptions.filter(c=>c.platform!=='Podcasts').map(c=>c.id),true);else if(!preview)load();}));
document.querySelectorAll('[data-platform]').forEach(b=>b.addEventListener('click',()=>{platform=b.dataset.platform;render();}));
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
$('#player-dialog').addEventListener('close',()=>$('#player-media').replaceChildren());
$('#refresh').addEventListener('click',()=>refresh(view==='Live now'?library.subscriptions.filter(c=>c.platform!=='Podcasts').map(c=>c.id):undefined));$('#import-submit').addEventListener('click',()=>importSources($('#sources').value.split(/\r?\n/)));$('#import-file').addEventListener('change',e=>readFile(e.target.files[0]));$('#sample-toggle').addEventListener('click',()=>{sample=!sample;render();});$('.mobile-menu').addEventListener('click',()=>{const open=$('.site-sidebar').classList.toggle('open');$('.mobile-menu').setAttribute('aria-expanded',String(open));});
setInterval(()=>{if(view==='Live now'&&!preview&&!document.hidden)refresh(library.subscriptions.filter(c=>c.platform!=='Podcasts').map(c=>c.id),true);},120000);
if(preview){loading=false;render();message('Safe preview · Sample content only. No database or account actions are performed.');}else load().then(()=>{if(new URLSearchParams(location.search).get('refresh')==='1'){history.replaceState(null,'',location.pathname);refresh();}else if(view==='Live now')refresh(library.subscriptions.filter(c=>c.platform!=='Podcasts').map(c=>c.id),true);});

document.addEventListener('visibilitychange',()=>{if(!document.hidden&&view==='Live now'&&!preview)refresh(library.subscriptions.filter(c=>c.platform!=='Podcasts').map(c=>c.id),true);});

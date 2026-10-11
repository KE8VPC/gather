'use strict';
const token=document.querySelector('meta[name="csrf-token"]').content;
const status=document.querySelector('#connection-status');
let busy=false, disconnectProvider='';
function announce(text,error=false){const p=document.createElement('p');p.className='message'+(error?' error':'');p.textContent=text;status.replaceChildren(p);}
function lock(value){busy=value;document.querySelectorAll('.connection-actions button').forEach(b=>{if(value){b.dataset.previouslyDisabled=String(b.disabled);b.disabled=true;}else{b.disabled=b.dataset.previouslyDisabled==='true';}});}
async function request(provider,action,job){const response=await fetch('connections-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({provider,action,job})});let data;try{data=await response.json();}catch{throw new Error('Unable to reach Gather. Please reload and try again.');}if(!response.ok)throw new Error(data.error||'The connection could not be updated.');return data;}
async function sync(provider){
 if(busy)return;lock(true);
 const card=document.querySelector(`[data-provider="${provider}"]`),progress=card.querySelector('[data-progress]');
 try{
  announce('Reading your followed creators. Keep this page open until the import finishes.');
  let result=await request(provider,'start');const job=result.job;
  while(!result.done){result=await request(provider,'step',job);progress.textContent=`${result.scanned} creators read…`;}
  const message=`Sync complete: ${result.added} added, ${result.existing} already in your library, ${result.removed} no longer followed and removed.`;
  announce(message);progress.textContent=message;
  const link=document.createElement('a');link.href='index.php?refresh=1';link.className='outline-button';link.textContent='Open and refresh your feed ↗';status.append(link);
  card.querySelector('[data-sync-time]').textContent='Last synced '+new Date().toLocaleString();
 }catch(error){announce(error.message,true);progress.textContent='Sync did not finish. You can try again or reconnect.';}
 finally{lock(false);}
}
document.querySelectorAll('[data-sync]').forEach(button=>button.addEventListener('click',()=>sync(button.dataset.sync)));
const dialog=document.querySelector('#disconnect-dialog');
document.querySelectorAll('[data-disconnect]').forEach(button=>button.addEventListener('click',()=>{if(!busy){disconnectProvider=button.dataset.disconnect;dialog.showModal();}}));
document.querySelector('#cancel-disconnect').addEventListener('click',()=>dialog.close());
document.querySelector('#confirm-disconnect').addEventListener('click',async()=>{if(busy)return;dialog.close();lock(true);try{await request(disconnectProvider,'disconnect');location.replace('connections.php');}catch(error){announce(error.message,true);lock(false);}});
const auto=document.body.dataset.autoSync;if(['youtube','twitch'].includes(auto))sync(auto);

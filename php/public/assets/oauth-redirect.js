'use strict';
const continuation=document.getElementById('provider-continue');
if(continuation){
 const destination=new URL(continuation.href);
 if(destination.protocol==='https:' && ['accounts.google.com','id.twitch.tv'].includes(destination.hostname))location.replace(destination.href);
}

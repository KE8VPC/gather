<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/AdminSettings.php';
$root=sys_get_temp_dir().'/gather-admin-'.bin2hex(random_bytes(10));mkdir($root,0700);
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name.PHP_EOL;}
function rejected(Closure $fn,string $name):void{try{$fn();}catch(Throwable){check(true,$name);return;}throw new RuntimeException('FAIL '.$name);}
try{
 $key=base64_encode(random_bytes(32));$service=new AdminSettings($root,$key);
 check($service->read()['admin_user_id']===0,'fresh site has no administrator');
 rejected(fn()=>$service->claim(1,'wrong','hosting-password'),'ownership proof required');
 rejected(fn()=>$service->claim(1,'',''),'empty database password cannot grant administration');
 $service->claim(12,'hosting-password','hosting-password');check($service->read()['admin_user_id']===12,'ownership claim saved');
 rejected(fn()=>$service->claim(14,'hosting-password','hosting-password'),'second ownership claim rejected');
 rejected(fn()=>$service->save(14,['youtube_client_secret'=>'intruder']),'other user cannot save');
 $service->save(12,['youtube_client_id'=>'fake-client','youtube_client_secret'=>'fake-secret','db'=>'ignored']);
 $state=$service->read();check($state['values']['youtube_client_secret']==='fake-secret' && !isset($state['values']['db']),'allowed settings round-trip only');
 check(!str_contains(file_get_contents($root.'/admin-settings.json'),'fake-secret'),'secrets encrypted at rest');
 $service->save(12,['youtube_client_secret'=>'','twitch_client_id'=>'fake-twitch']);
 check($service->read()['values']['youtube_client_secret']==='fake-secret','blank preserves existing secret');
 $service->save(12,['clear_youtube_client_secret'=>'1']);check($service->read()['values']['youtube_client_secret']==='','explicit removal persists empty override');
 rejected(fn()=>$service->save(12,['youtube_api_key'=>"bad\nvalue"]),'multiline credential rejected');
 rejected(fn()=>(new AdminSettings($root,base64_encode(random_bytes(32))))->read(),'wrong encryption key fails closed');
 $envelope=json_decode(file_get_contents($root.'/admin-settings.json'),true);$blob=base64_decode($envelope['sealed']);$blob[30]=chr(ord($blob[30])^1);file_put_contents($root.'/admin-settings.json',json_encode(['sealed'=>base64_encode($blob)]));
 rejected(fn()=>$service->read(),'tampering fails closed');
}finally{foreach(['admin-settings.json','admin-settings.lock'] as $name)if(is_file($root.'/'.$name))unlink($root.'/'.$name);rmdir($root);}

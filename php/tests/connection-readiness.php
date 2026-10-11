<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Connections.php';
$c=['app_url'=>'https://example.com/gather','oauth_callback_url'=>'https://example.com/gather/public/oauth.php','secure_cookies'=>true,'oauth_encryption_key'=>base64_encode(random_bytes(32)),'twitch_client_id'=>'fixture-id','twitch_client_secret'=>'fixture-secret'];
function verify(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
verify((new Connections($c))->ready('twitch'),'configured provider ready');
foreach(['twitch_client_id','twitch_client_secret','oauth_encryption_key','oauth_callback_url'] as $field){$bad=$c;$bad[$field]='';$service=new Connections($bad);verify(!$service->ready('twitch') && count($service->setupIssues('twitch'))===1,'missing '.$field.' diagnosed');}
$bad=$c;$bad['secure_cookies']=false;verify(!(new Connections($bad))->ready('twitch'),'insecure HTTPS cookies rejected');
$session=[];$url=(new Connections($c))->authorize(1,'twitch',$session);parse_str(parse_url($url,PHP_URL_QUERY),$q);
verify($q['redirect_uri']==='https://example.com/gather/public/oauth.php?provider=twitch' && $q['scope']==='user:read:follows','authorization callback and scope retained');
verify(!str_contains(json_encode((new Connections($c))->setupIssues('twitch')),'fixture-secret'),'diagnostics do not reveal secrets');

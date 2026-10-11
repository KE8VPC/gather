<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/Connections.php';
require dirname(__DIR__).'/app/Library.php';
if (getenv('GATHER_CONNECTION_TESTS')!=='disposable-database') { fwrite(STDERR,"Set GATHER_CONNECTION_TESTS=disposable-database only for a local test database.\n"); exit(1); }
function check(bool $ok,string $label):void { if (!$ok) throw new RuntimeException('FAIL '.$label); echo 'PASS '.$label.PHP_EOL; }
function rejects(Closure $fn,string $label):void { try {$fn();}catch(Throwable){check(true,$label);return;} throw new RuntimeException('FAIL '.$label); }
$settings=config()+['youtube_client_id'=>'test-google','youtube_client_secret'=>'test-secret','oauth_callback_url'=>'http://127.0.0.1:8088/nested/gather/public/oauth.php','oauth_encryption_key'=>base64_encode(random_bytes(32))];
$settings['twitch_client_id']='test-twitch';$settings['twitch_client_secret']='test-secret';
$settings['youtube_client_id']='test-google';$settings['youtube_client_secret']='test-secret';
$settings['oauth_callback_url']='http://127.0.0.1:8088/nested/gather/public/oauth.php';
$settings['oauth_encryption_key']=base64_encode(random_bytes(32));
$ids=[];$creatorIds=[];$calls=[];$mode='two-pages';$refreshes=0;
$channel='UC'.str_repeat('x',22);$other='UC'.str_repeat('z',22);$identity='UC'.str_repeat('a',22);
$transport=function(string $url,array $headers,?array $post)use(&$calls,&$mode,&$refreshes,$channel,$other,$identity):array {
    $calls[]=$url;
    if (str_contains($url,'/revoke')) { if($mode==='revoke-fails')throw new ProviderFailure(503);return []; }
    if (str_ends_with($url,'/token')) {
        if(($post['grant_type']??'')==='refresh_token'){$refreshes++;if($mode==='revoked')throw new ProviderFailure(400,'invalid_grant');}
        return ['access_token'=>'fake-access','refresh_token'=>'fake-refresh','expires_in'=>3600];
    }
    if (str_contains($url,'/channels?')) return ['items'=>[['id'=>$identity,'snippet'=>['title'=>'Test linked account']]]];
    if (str_ends_with($url,'/validate')) return ['client_id'=>'test-twitch','user_id'=>'12345','login'=>'sample-user','scopes'=>$mode==='bad-scope'?[]:['user:read:follows']];
    if (str_contains($url,'/subscriptions?')) {
        if ($mode==='fail-page' && str_contains($url,'pageToken=')) throw new ProviderFailure(503);
        if ($mode==='empty') return ['items'=>[]];
        if ($mode==='oversized') return ['items'=>array_map(fn($n)=>['snippet'=>['resourceId'=>['channelId'=>'UC'.str_pad((string)$n,22,'0',STR_PAD_LEFT)]]],range(1,1001))];
        if(str_contains($url,'pageToken='))return ['items'=>[['snippet'=>['resourceId'=>['channelId'=>$other]]],['snippet'=>['resourceId'=>['channelId'=>$channel]]]]];
        return ['items'=>[['snippet'=>['resourceId'=>['channelId'=>$channel]]]],'nextPageToken'=>'page-two'];
    }
    if(str_contains($url,'/channels/followed?'))return ['data'=>[['broadcaster_login'=>'gather_test_creator']],'pagination'=>[]];
    throw new RuntimeException('Unexpected fixture request');
};
$service=new Connections($settings,$transport);
try {
    foreach(['a','b'] as $name){query('INSERT INTO users(email,name,password_hash,created_at) VALUES(?,?,?,?)',['connections-'.$name.'-'.bin2hex(random_bytes(6)).'@example.test','Connection test','not-a-login-hash',now_ms()]);$ids[]=(int)db()->lastInsertId();}
    [$a,$b]=$ids;$session=[];
    $url=$service->authorize($a,'youtube',$session);parse_str(parse_url($url,PHP_URL_QUERY),$args);
    check($args['redirect_uri']==='http://127.0.0.1:8088/nested/gather/public/oauth.php?provider=youtube','nested callback uses configured exact URL');
    check($args['scope']===Connections::SCOPES['youtube'] && $args['response_type']==='code','read-only authorization code flow');
    $wrong=$session;rejects(fn()=>Connections::consumeState($b,'youtube',$args['state'],$wrong),'state bound to Gather user');
    $wrong=$session;rejects(fn()=>Connections::consumeState($a,'youtube','bad',$wrong),'invalid state rejected');
    $old=$session;$old['oauth_pending']['youtube']['expires']=time()-1;rejects(fn()=>Connections::consumeState($a,'youtube',$args['state'],$old),'expired state rejected');
    $pending=Connections::consumeState($a,'youtube',$args['state'],$session);
    rejects(fn()=>Connections::consumeState($a,'youtube',$args['state'],$session),'callback state is single use');
    $cipher=$service->seal('private-token','user:a');check(!str_contains($cipher,'private-token') && $service->unseal($cipher,'user:a')==='private-token','tokens encrypted at rest');
    rejects(fn()=>$service->unseal($cipher,'user:b'),'tokens cannot be swapped between users');
    $badSettings=$settings;$badSettings['oauth_callback_url']='https://evil.example/oauth.php';check(!(new Connections($badSettings))->ready('youtube'),'external callback host rejected');
    $production=$settings;$production['app_url']='https://example.com/gather';$production['oauth_callback_url']='https://example.com/gather/public/oauth.php';$production['secure_cookies']=true;
    check((new Connections($production))->ready('youtube'),'HTTPS subdirectory setup accepted with secure cookies');
    $production['secure_cookies']=false;check(!(new Connections($production))->ready('youtube'),'HTTPS account linking requires secure cookies');
    $mismatch=$settings;$mismatch['oauth_callback_url']='https://127.0.0.1:8088/nested/gather/public/oauth.php';$mismatch['secure_cookies']=true;
    check(!(new Connections($mismatch))->ready('youtube'),'callback and application URL schemes must match');
    $service->locked($a,fn()=>$service->connect($a,'youtube','fake-code',$pending));
    $row=$service->account($a,'youtube');check(!str_contains($row['access_token'],'fake-access'),'database contains ciphertext');
    rejects(fn()=>$service->locked($b,fn()=>$service->connect($b,'youtube','fake-code',$pending)),'provider identity cannot overwrite another user');
    $job=$service->beginSync($a,'youtube',$session);
    $part=$service->step($a,'youtube',$job['job'],$session);
    check(!$part['done'] && $part['scanned']===1 && !query('SELECT 1 FROM subscriptions WHERE user_id=?',[$a])->fetchColumn(),'first page staged without partial library writes');
    rejects(fn()=>$service->step($b,'youtube',$job['job'],$session),'sync cannot cross users');
    $result=$service->step($a,'youtube',$job['job'],$session);
    check($result['done'] && $result['added']===2 && $result['scanned']===2,'pagination and duplicate channels handled');
    foreach([$channel,$other] as $id)$creatorIds[]=Feeds::source($id)['id'];
    $job=$service->beginSync($a,'youtube',$session);$mode='fail-page';
    $service->step($a,'youtube',$job['job'],$session);
    rejects(fn()=>$service->step($a,'youtube',$job['job'],$session),'provider failure on later page is reported');
    check((int)query('SELECT COUNT(*) FROM subscriptions WHERE user_id=?',[$a])->fetchColumn()===2,'failed sync preserves completed library');
    $mode='oversized';$job=$service->beginSync($a,'youtube',$session);rejects(fn()=>$service->step($a,'youtube',$job['job'],$session),'oversized provider list leaves library unchanged');
    $mode='two-pages';$job=$service->beginSync($a,'youtube',$session);$service->step($a,'youtube',$job['job'],$session);$again=$service->step($a,'youtube',$job['job'],$session);check($again['added']===0,'repeat sync is idempotent');
    Library::add($b,[$channel]);
    check(query(Library::RECOMMENDATIONS,[$b,$b,$b])->fetchAll()===[],'linked imports excluded from peer recommendations');
    $manual='https://www.twitch.tv/manual_test_creator';Library::add($a,[$manual]);$creatorIds[]=Feeds::source($manual)['id'];
    $mode='empty';$job=$service->beginSync($a,'youtube',$session);$result=$service->step($a,'youtube',$job['job'],$session);
    check($result['removed']===2 && (int)query('SELECT COUNT(*) FROM subscriptions WHERE user_id=?',[$a])->fetchColumn()===1,'unfollow removes imported entries and preserves manual follows');
    query('UPDATE linked_accounts SET expires_at=0 WHERE user_id=?',[$a]);$mode='two-pages';$job=$service->beginSync($a,'youtube',$session);$service->step($a,'youtube',$job['job'],$session);check($refreshes===1,'expired access token refreshed');
    $service->authorize($a,'twitch',$session);$tp=$session['oauth_pending']['twitch'];
    $mode='bad-scope';rejects(fn()=>$service->connect($a,'twitch','fake',$tp),'missing Twitch follows permission rejected');
    $mode='two-pages';$service->connect($a,'twitch','fake',$tp);$job=$service->beginSync($a,'twitch',$session);$result=$service->step($a,'twitch',$job['job'],$session);$creatorIds[]=Feeds::source('https://www.twitch.tv/gather_test_creator')['id'];
    check($result['added']===1,'Twitch followed channels imported');
    $mode='revoke-fails';check(!$service->disconnect($a,'twitch'),'provider revocation failure reported');
    check(!query('SELECT 1 FROM linked_accounts WHERE user_id=? AND provider=?',[$a,'twitch'])->fetchColumn(),'local tokens removed even when provider is unavailable');
    check(!query('SELECT 1 FROM subscriptions WHERE user_id=? AND creator_id=?',[$a,end($creatorIds)])->fetchColumn(),'disconnect removes its imported subscriptions');
    query('UPDATE linked_accounts SET expires_at=0 WHERE user_id=?',[$a]);$mode='revoked';rejects(fn()=>$service->verify($a,'youtube',$service->account($a,'youtube')),'revoked refresh token reported');
    $service->forget($a,'youtube');check((int)query('SELECT COUNT(*) FROM subscriptions WHERE user_id=?',[$a])->fetchColumn()===1,'disconnect preserves manual library');
    $mode='two-pages';$service->connect($a,'youtube','fake-code',$pending);$job=$service->beginSync($a,'youtube',$session);$service->step($a,'youtube',$job['job'],$session);$service->step($a,'youtube',$job['job'],$session);
    query('UPDATE linked_accounts SET connected_at=1,last_sync_at=1 WHERE user_id=?',[$a]);
    check(Connections::expire($a)===1 && (int)query('SELECT COUNT(*) FROM subscriptions WHERE user_id=?',[$a])->fetchColumn()===1,'stale connection and imports expire while manual follows remain');
    echo "All simulated-provider / real-MySQL connection checks passed. No live provider authorization was performed.\n";
} finally {
    foreach($ids as $id)query('DELETE FROM users WHERE id=?',[$id]);
    foreach($creatorIds as $id)query('DELETE FROM creators WHERE id=? AND NOT EXISTS(SELECT 1 FROM subscriptions WHERE creator_id=?)',[$id,$id]);
}

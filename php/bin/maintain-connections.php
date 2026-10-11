<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/Connections.php';
// Run hourly: validate stored grants, remove revoked/old authorized data.
$service=new Connections(config()); $checked=0; $removed=0; $failed=0;
foreach(query('SELECT user_id,provider FROM linked_accounts')->fetchAll() as $link) {
    $uid=(int)$link['user_id']; $p=$link['provider'];
    try {
        $service->locked($uid,function()use($service,$uid,$p,&$checked,&$removed){
            $row=query('SELECT * FROM linked_accounts WHERE user_id=? AND provider=?',[$uid,$p])->fetch();
            if (!$row) return;
            if (max((int)$row['connected_at'],(int)$row['last_sync_at'])<time()-29*86400) { $service->disconnect($uid,$p); $removed++; return; }
            try { $service->verify($uid,$p,$row); $checked++; }
            catch (ProviderFailure $e) { if (!$e->revoked()) throw $e; $service->forget($uid,$p); $removed++; }
        });
    } catch (Throwable) { $failed++; }
}
// Public content with no remaining subscribers has no purpose in the cache.
query('DELETE c FROM creators c LEFT JOIN subscriptions s ON s.creator_id=c.id WHERE s.creator_id IS NULL');
echo "Connections checked: $checked; removed: $removed; temporary failures: $failed\n";
exit($failed?1:0);

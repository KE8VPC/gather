<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/Connections.php';
$failed=false;
function connection_check(string $label,bool $ok): void {
    global $failed;
    echo ($ok?'PASS ':'NEEDS SETUP ').$label.PHP_EOL;
    if (!$ok) $failed=true;
}
try {
    $c=config(); $service=new Connections($c);
    echo "Gather linked-account setup (no secrets or provider requests)\n";
    $key=base64_decode((string)($c['oauth_encryption_key']??''),true);
    connection_check('Private encryption key contains 32 bytes',$key!==false && strlen($key)===32);
    foreach(Connections::PROVIDERS as $p=>$label) {
        connection_check($label.' client ID is set',trim((string)($c[$p.'_client_id']??''))!=='');
        connection_check($label.' client secret is set',trim((string)($c[$p.'_client_secret']??''))!=='');
        try { $callback=$service->redirect($p); connection_check($label.' callback matches the configured site and cookie security',true); echo 'Register this callback: '.$callback.PHP_EOL; }
        catch(Throwable) { connection_check($label.' callback matches the configured site and cookie security',false); }
    }
    try { foreach(['linked_accounts','linked_imports'] as $table)query('SELECT 1 FROM '.$table.' LIMIT 1'); connection_check('Linked-account database tables are available',true); }
    catch(Throwable) { connection_check('Linked-account database tables are available; apply bin/upgrade.php if needed',false); }
    echo "This checks local configuration only. Provider registration, consent, verification and hosted cron execution must be tested separately.\n";
} catch(Throwable) { connection_check('Readable config/local.php',false); }
exit($failed?1:0);

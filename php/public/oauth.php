<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Connections.php';
try {
    boot(); $u=user();
    if (!$u) { header('Location: auth.php'); exit; }
    $uid=(int)$u['id']; $p=Connections::provider($_GET['provider']??null); $service=new Connections(config());
    header('Referrer-Policy: no-referrer');
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();
        if (($_POST['action']??'')!=='connect') throw new InvalidArgumentException('Unsupported request.',400);
        throttle('link:'.$uid,20);
        // Fail before leaving the site if the database upgrade was not applied.
        query('SELECT 1 FROM linked_accounts LIMIT 1');
        $url=$service->authorize($uid,$p,$_SESSION);
        // End the form submission on our own origin. A fresh navigation avoids
        // browser form-action restrictions on the provider's login redirects.
        header('Content-Type: text/html; charset=utf-8');
        $label=Connections::PROVIDERS[$p];
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connect '.escape($label).' · Gather</title><link rel="stylesheet" href="assets/site.css"><link rel="stylesheet" href="assets/oauth.css"><script src="assets/oauth-redirect.js" defer></script></head><body class="auth-page"><main class="auth-card"><h1>Opening '.escape($label).'…</h1><p>If you are not taken there automatically, use the button below.</p><a id="provider-continue" class="primary" rel="noreferrer" href="'.escape($url).'">Continue to '.escape($label).'</a><p><a href="connections.php">Back to linked accounts</a></p></main></body></html>';exit;
    }
    if ($_SERVER['REQUEST_METHOD']!=='GET') throw new InvalidArgumentException('Unsupported request.',405);
    $pending=Connections::consumeState($uid,$p,$_GET['state']??null,$_SESSION);
    if (isset($_GET['error'])) throw new RuntimeException('Connection cancelled or permission denied. Your existing library has not changed.');
    $code=$_GET['code']??null;
    if (!is_string($code) || $code==='' || strlen($code)>4000) throw new InvalidArgumentException('The provider did not return a valid connection code.',400);
    $service->locked($uid,fn()=>$service->connect($uid,$p,$code,$pending));
    unset($_SESSION['linked_sync'][$p]);
    $_SESSION['connection_notice']=Connections::PROVIDERS[$p].' connected. Importing your creators…';
    $_SESSION['connection_auto_sync']=$p;
} catch (InvalidArgumentException $e) {
    http_response_code(in_array($e->getCode(),[400,403,405,409,429],true)?$e->getCode():400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>'.escape($e->getMessage()).'</p><p><a href="connections.php">Back to linked accounts</a></p>'; exit;
} catch (Throwable $e) {
    if (session_status()!==PHP_SESSION_ACTIVE) { http_response_code(503); exit('Account linking is temporarily unavailable.'); }
    $_SESSION['connection_notice']=$e instanceof PDOException?'Account linking needs a database update or is temporarily unavailable.':$e->getMessage();
}
// Strip provider codes and state from the visible URL immediately after handling.
header('Location: connections.php',true,303);


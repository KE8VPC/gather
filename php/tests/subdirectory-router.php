<?php
// Test harness only: PHP's built-in server does not enforce .htaccess.
// This router verifies nested URL behavior, NOT Apache access-rule execution.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$mount='/nested/gather';
$relative=str_starts_with($path,$mount.'/')?substr($path,strlen($mount)+1):null;
$entries=['public/admin.php'=>'public/admin.php',''=>'index.php','index.php'=>'index.php','public/index.php'=>'public/index.php','public/auth.php'=>'public/auth.php','public/api.php'=>'public/api.php','public/preview.php'=>'public/preview.php','public/legal.php'=>'public/legal.php','public/connections.php'=>'public/connections.php','public/oauth.php'=>'public/oauth.php','public/connections-api.php'=>'public/connections-api.php'];
if (isset($entries[$relative??'!'])) {
    $_SERVER['SCRIPT_NAME']=$mount.'/'.($relative?:'index.php');
    require $root.'/'.$entries[$relative];return;
}
if (in_array($relative,['install/','install/index.php','install/probe.php'],true)) {
    $entry=$relative==='install/'?'install/index.php':$relative;
    if(!is_file($root.'/'.$entry)){http_response_code(404);exit;}
    $_SERVER['SCRIPT_NAME']=$mount.'/'.$entry;require $root.'/'.$entry;return;
}
if ($relative==='install/style.css' && is_file($root.'/'.$relative)) {header('Content-Type: text/css');readfile($root.'/'.$relative);return;}
$assets=['public/assets/oauth-redirect.js'=>'application/javascript','public/assets/oauth.css'=>'text/css','public/assets/admin.css'=>'text/css','public/assets/site.css'=>'text/css','public/assets/welcome.css'=>'text/css','public/assets/favicon.svg'=>'image/svg+xml','public/assets/app.js'=>'application/javascript','public/assets/connections.js'=>'application/javascript'];
if (isset($assets[$relative??'!'])) {header('Content-Type: '.$assets[$relative]);readfile($root.'/'.$relative);return;}
http_response_code(404);echo 'Not found';


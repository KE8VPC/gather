<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$root=dirname(__DIR__);
if (is_file($root.'/config/local.php') || is_file($root.'/storage/installed.lock') || !is_file($root.'/config/install-probe.php')) { http_response_code(404); exit; }
$token=require $root.'/config/install-probe.php';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) { http_response_code(404);exit; }
echo $token;

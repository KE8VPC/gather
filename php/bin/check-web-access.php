<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = rtrim($argv[1] ?? '', '/');
$parts = parse_url($base);
if (!$parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !(($parts['scheme'] ?? '') === 'https' || (($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['localhost','127.0.0.1'], true)))) {
    fwrite(STDERR, "Usage: php bin/check-web-access.php https://example.com/gather\n"); exit(1);
}
if (!extension_loaded('curl')) { fwrite(STDERR,"PHP cURL is required.\n"); exit(1); }
$failed = false;
$checks = ['/' => [200], '/public/assets/site.css' => [200], '/config/access-check.txt' => [403,404], '/app/bootstrap.php' => [403,404], '/database/schema.sql' => [403,404], '/bin/preflight.php' => [403,404], '/tests/unit.php' => [403,404], '/storage/.gitkeep' => [403,404], '/README.md' => [403,404], '/.htaccess' => [403,404]];
foreach ($checks as $path => $expected) {
    $curl = curl_init($base.$path);
    curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static fn($curl,$data)=>strlen($data)]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $pass = $ok !== false && in_array($status,$expected,true);
    $failed = $failed || !$pass;
    echo ($pass?'PASS':'FAIL').' '.$path.' HTTP '.$status.PHP_EOL;
}
if ($failed) fwrite(STDERR,"Do not add production credentials until public/private access checks pass. Confirm the server honors .htaccess (or equivalent Nginx rules).\n");
exit($failed?1:0);

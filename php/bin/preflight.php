<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Connections.php';
$failed=false;
function report(string $label,bool $ok):void{global $failed;echo ($ok?'PASS':'FAIL').' '.$label.PHP_EOL;if(!$ok)$failed=true;}
report('PHP 8.2 or newer',PHP_VERSION_ID>=80200);
foreach(['pdo_mysql','curl','mbstring','simplexml','session','openssl'] as $extension)report('Extension '.$extension,extension_loaded($extension));
try{
 $c=config();$url=parse_url($c['app_url']);
 report('Configured site URL',isset($url['host'])&&!in_array($url['host'],['example.com','gather.example.com'],true));
 report('Secure production URL/cookies or loopback development',(($url['scheme']??'')==='https'&&$c['secure_cookies'])||in_array($url['host']??'',['127.0.0.1','localhost'],true));
 report('Database connection',db()->query('SELECT 1')->fetchColumn()==1);
 foreach(['users','creators','subscriptions','items','auth_limits','linked_accounts','linked_imports'] as $table){query('SELECT 1 FROM '.$table.' LIMIT 1');report('Table '.$table,true);}
 echo 'Twitch live checks: '.(!empty($c['twitch_client_id'])&&!empty($c['twitch_client_secret'])?'configured; test against Twitch':'not configured').PHP_EOL;
 echo 'YouTube live checks: '.(!empty($c['youtube_api_key'])?'configured; test against YouTube':'not configured').PHP_EOL;
 $connections=new Connections($c);
 foreach(Connections::PROVIDERS as $provider=>$label) echo $label.' account linking: '.($connections->ready($provider)?'configured; live authorization still needs testing':'not configured').PHP_EOL;
 echo 'Registration: '.($c['registration_enabled']?($c['registration_code']?'invitation code required':'open'):'closed').PHP_EOL;
}catch(Throwable $e){report('Configuration/database readiness',false);echo "Review config/local.php and database setup.\n";}
exit($failed?1:0);


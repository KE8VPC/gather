<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Connections.php';
try {boot();$u=user();}catch(Throwable){http_response_code(503);exit('Administration is unavailable. Check the private configuration and settings backup.');}
if(!$u){header('Location: auth.php');exit;}
$c=config();$uid=(int)$u['id'];$owner=(int)$c['admin_user_id'];
if($owner!==0 && $owner!==$uid){http_response_code(403);exit('Administrator access required.');}
$local=PHP_SAPI==='cli-server' && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true) && in_array(parse_url($c['app_url'],PHP_URL_HOST),['localhost','127.0.0.1','[::1]'],true);
if(!$local && (empty($c['secure_cookies']) || !in_array($_SERVER['HTTPS']??'',['on','1'],true))){http_response_code(403);exit('Open administration over HTTPS with secure cookies enabled.');}
$settings=new AdminSettings(dirname(__DIR__).'/config',(string)($c['oauth_encryption_key']??''));
$error='';$notice=$_SESSION['admin_notice']??'';unset($_SESSION['admin_notice']);
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        csrf();throttle('admin-user:'.$uid,15);throttle('admin-ip:'.($_SERVER['REMOTE_ADDR']??''),30);
        $password=$_POST['current_password']??'';
        $hash=query('SELECT password_hash FROM users WHERE id=?',[$uid])->fetchColumn();
        if(!is_string($password) || strlen($password)>72 || !password_verify($password,(string)$hash))throw new InvalidArgumentException('Your Gather password is incorrect.',403);
        if($owner===0){
            $proof=$_POST['database_password']??'';
            if(!is_string($proof))throw new InvalidArgumentException('Enter the hosting database password.');
            $settings->claim($uid,$proof,(string)$c['db']['password']);
            $_SESSION['admin_notice']='Administration is now enabled for your account.';
        }else{
            $settings->save($uid,$_POST);
            $_SESSION['admin_notice']='Credentials saved. Users can connect when the provider application is configured correctly.';
        }
        header('Location: admin.php',true,303);exit;
    }catch(InvalidArgumentException $e){http_response_code(in_array($e->getCode(),[403,429],true)?$e->getCode():400);$error=$e->getMessage();}
    catch(Throwable){http_response_code(500);$error='Settings could not be saved. Check the site encryption key, config folder permissions and available disk space.';}
}
$service=new Connections($c);
require_once dirname(__DIR__).'/app/YouTube.php';
$youtubeUsage=null;
if($owner===$uid){try{$youtubeUsage=(new YouTube($c))->status();}catch(Throwable){$youtubeUsage=['error'=>'YouTube cache setup is unavailable. The database account needs permission to create the two cache tables.'];}}
$fields=['youtube_client_id'=>'Google client ID','youtube_client_secret'=>'Google client secret','youtube_api_key'=>'YouTube API key (live checks)','twitch_client_id'=>'Twitch client ID','twitch_client_secret'=>'Twitch client secret'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administration · Gather</title><link rel="stylesheet" href="assets/site.css"><link rel="stylesheet" href="assets/admin.css"><link rel="icon" href="assets/favicon.svg"></head>
<body class="legal-page"><header class="legal-header"><a class="brand" href="index.php"><span class="brand-mark">G</span>Gather.</a><a href="index.php">← Back to your feed</a></header><main class="admin-main">
<p class="eyebrow">SITE ADMINISTRATION</p><h1><?=$owner===0?'Enable administration':'API settings'?></h1>
<?php if($error):?><p class="message error" role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($notice):?><p class="message" role="status"><?=escape($notice)?></p><?php endif;?>
<?php if($owner===0):?>
<p>Confirm that you manage this website. This one-time step makes your signed-in account the administrator.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><section class="admin-card"><h2>Verify site ownership</h2><p>Use the database password from your hosting panel—the same one used during installation. It is checked locally and is not saved again.</p><label>Hosting database password<input type="password" name="database_password" required autocomplete="off" maxlength="2000"></label><label>Your Gather password<input type="password" name="current_password" required autocomplete="current-password" maxlength="72"></label><button class="primary">Enable administration</button></section></form>
<?php else:?>
<p>Save the application credentials from Google and Twitch. Saved values stay hidden; leave a field blank to keep its current value.</p>
<p>These settings apply to the whole site. Replacing an application’s credentials may require users to reconnect their accounts.</p>
<?php if($youtubeUsage):?><section class="admin-card"><h2>YouTube live-check usage</h2>
<?php if(isset($youtubeUsage['used'])):?><p><strong><?=number_format($youtubeUsage['used'])?> of <?=number_format($youtubeUsage['limit'])?></strong> live-check requests reserved today across this Gather installation. The counter resets at midnight Pacific time.</p><?php endif;?>
<p>Channel feeds refresh at most once an hour. Video checks combine up to 50 IDs across channels. Known live videos are checked about every two minutes while Live now is open; upcoming streams every five minutes. Ordinary videos are rechecked at most daily. These intervals are minimums and may be longer when checks are queued.</p>
<p>This counter covers Gather’s live-check requests, including failed attempts. Google account imports and other applications using the same project consume additional quota. <a href="https://console.cloud.google.com/apis/api/youtube.googleapis.com/quotas" target="_blank" rel="noopener noreferrer">View Google’s actual quota usage ↗</a></p>
<?php if($youtubeUsage['error']??null):?><p class="message error"><?=escape($youtubeUsage['error'])?></p><?php endif;?></section><?php endif;?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>">
<?php foreach(['youtube'=>'YouTube','twitch'=>'Twitch'] as $provider=>$label):?>
<section class="admin-card"><h2><?=escape($label)?></h2><p class="admin-status"><?=$service->ready($provider)?'Required linking settings are present.':'Account linking still needs setup.'?> This does not verify the credentials with the provider.</p>
<p><a href="<?=$provider==='youtube'?'https://console.cloud.google.com/apis/credentials':'https://dev.twitch.tv/console/apps'?>" target="_blank" rel="noopener noreferrer">Open <?=$provider==='youtube'?'Google':'Twitch'?> developer console ↗</a></p>
<label>Callback URL to register with <?=$provider==='youtube'?'Google':'Twitch'?><input readonly value="<?=escape((string)($c['oauth_callback_url']??'').'?provider='.$provider)?>"></label>
<?php foreach($fields as $field=>$label):if(!str_starts_with($field,$provider.'_'))continue;?>
<label><?=escape($label)?> <span class="admin-status"><?=empty($c[$field])?'Not saved':'Saved'?></span><input type="password" name="<?=escape($field)?>" maxlength="4096" autocomplete="new-password" placeholder="<?=empty($c[$field])?'Enter value':'Leave blank to keep saved value'?>"></label>
<?php if(!empty($c[$field])):?><label class="admin-clear"><input type="checkbox" name="clear_<?=escape($field)?>" value="1"> Remove saved <?=escape($label)?></label><?php endif;?>
<?php endforeach;?></section><?php endforeach;?>
<section class="admin-card"><h2>Confirm changes</h2><label>Your Gather password<input type="password" name="current_password" required autocomplete="current-password" maxlength="72"></label><button class="primary">Save API settings</button></section></form>
<p><a href="connections.php">Go to linked accounts →</a></p>
<?php endif;?></main></body></html>

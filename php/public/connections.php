<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/legal.php';
require_once dirname(__DIR__).'/app/Connections.php';
$preview=($_GET['preview']??'')==='1'; $u=null; $rows=[]; $csrf=''; $notice=''; $setupError=false;
if (!$preview) {
    try { boot(); $u=user(); } catch (Throwable) { http_response_code(503); exit('Gather needs configuration. Follow INSTALL.md.'); }
    if (!$u) { header('Location: auth.php'); exit; }
    $csrf=$_SESSION['csrf'];
    $notice=$_SESSION['connection_notice']??''; unset($_SESSION['connection_notice']);
    try {
        Connections::expire((int)$u['id']);
        foreach(query('SELECT provider,display_name,connected_at,last_sync_at FROM linked_accounts WHERE user_id=?',[$u['id']])->fetchAll() as $row) $rows[$row['provider']]=$row;
    } catch (Throwable) { $setupError=true; }
}
$service=new Connections($preview?[]:config());
$auto=$preview?'':($_SESSION['connection_auto_sync']??''); unset($_SESSION['connection_auto_sync']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Permit the two authorization destinations when browsers enforce form-action
// through the POST -> 303 redirect chain. No arbitrary external form targets.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self' https://accounts.google.com https://id.twitch.tv; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=escape($csrf)?>"><title>Linked accounts · Gather</title><link rel="stylesheet" href="assets/site.css"><link rel="icon" href="assets/favicon.svg"><script src="assets/connections.js" defer></script></head>
<body class="legal-page connections-page" data-auto-sync="<?=escape($auto)?>">
<header class="legal-header"><a class="brand" href="<?=$preview?'preview.php':'index.php'?>"><span class="brand-mark">G</span>Gather.</a><a href="<?=$preview?'preview.php':'index.php'?>">← Back to your feed</a></header>
<main class="connections-main">
  <p class="eyebrow">YOUR LIBRARY</p><h1>Linked accounts</h1>
  <p class="connections-intro">Connect once to bring your YouTube subscriptions and Twitch follows into Gather.</p>
  <?php if($preview):?><p class="message">This is a preview. Sign in to Gather to connect your accounts.</p><?php endif;?>
  <?php if($setupError):?><p class="message error">Account linking is unavailable. The site owner should check the database connection and apply the linked-account database update.</p><?php endif;?>
  <div id="connection-status" role="status" aria-live="polite"><?php if($notice):?><p class="message"><?=escape($notice)?></p><?php endif;?></div>
  <div class="connection-grid">
  <?php foreach(Connections::PROVIDERS as $p=>$label): $row=$rows[$p]??null; $ready=!$preview && !$setupError && $service->ready($p); ?>
    <section class="connection-card" aria-labelledby="title-<?=$p?>" data-provider="<?=$p?>">
      <div class="connection-card-heading"><span class="connection-symbol <?=$p?>" aria-hidden="true"><?=$p==='youtube'?'▷':'◈'?></span><h2 id="title-<?=$p?>"><?=$label?></h2></div>
      <p><?=$p==='youtube'?'Import the channels you subscribe to on YouTube.':'Import the creators you follow on Twitch. Paid channel subscriptions are separate.'?></p>
      <?php if($row):?>
        <p class="connection-account"><strong>Connected as <?=escape($row['display_name'])?></strong><br><span data-sync-time><?=$row['last_sync_at']?'Last synced '.escape(gmdate('M j, Y H:i',(int)$row['last_sync_at'])).' UTC':'Ready for your first import'?></span></p>
      <?php else:?><p class="connection-account"><strong><?=$ready?'Not connected':($preview?'Connect after signing in':'Not enabled yet')?></strong><br><?=$ready?'You choose which account to use.':($preview?'Your account connections will appear here.':'The site owner needs to finish this connection’s setup.')?></p><?php endif;?>
      <div class="connection-actions">
      <?php if($row):?>
        <button class="primary" data-sync="<?=$p?>" <?=$ready?'':'disabled'?>>Sync now</button>
      <?php endif;?>
      <?php if($ready):?><form action="oauth.php?provider=<?=$p?>" method="post"><input type="hidden" name="csrf" value="<?=escape($csrf)?>"><input type="hidden" name="action" value="connect"><button type="submit" class="<?=$row?'outline-button':'primary'?>"><?=$row?'Reconnect':'Connect '.$label?></button></form>
      <?php elseif($preview):?><a class="outline-button" href="auth.php">Sign in to connect</a>
      <?php elseif(in_array((int)(config()['admin_user_id']??0),[0,(int)$u['id']],true)):?><a class="outline-button" href="admin.php">Set up <?=escape($label)?></a>
      <?php else:?><p>Contact the site administrator to enable <?=escape($label)?>.</p><?php endif;?>
      <?php if($row):?><button class="quiet" data-disconnect="<?=$p?>">Disconnect &amp; remove imports</button><?php endif;?>
      </div>
      <?php if(!$preview && !$ready && in_array((int)(config()['admin_user_id']??0),[0,(int)$u['id']],true)):?><p class="message"><?=escape($setupError?'Account-linking database checks failed.':implode(' ',$service->setupIssues($p)))?></p><?php endif;?>
      <p class="connection-progress" data-progress role="status"></p>
    </section>
  <?php endforeach;?>
  </div>
  <section class="connection-help"><h2>What happens when you connect?</h2>
    <ul><li>You sign in with the provider and allow Gather to read your subscriptions or follows. Gather never receives your provider password.</li>
    <li>Your first import starts automatically. Use <strong>Sync now</strong> after changing who you follow. Complete syncs update imported creators, including removing creators you no longer follow. Creators already added manually stay in your library.</li>
    <li>Gather does not follow, unfollow, subscribe, post or make purchases on your provider account. Up to 1,000 creators can fit in your combined library.</li>
    <li>Linked-account imports are private and excluded from shared-subscriber recommendations. Disconnecting removes the connection and its imported subscriptions from Gather. It does not change your provider account.</li>
    <li>Sync at least once every 29 days to retain a connection’s imported data. Older connections are removed. You can reconnect whenever you like.</li></ul>
    <p>By connecting, you ask Gather to read and store this information for your feed as described in the <a href="legal.php?page=privacy">Privacy Policy</a>. YouTube use is also subject to the <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">YouTube Terms of Service</a>.</p>
    <p>Prefer files or links? <a href="<?=$preview?'preview.php':'index.php'?>">Return to your feed</a> and choose Import subscriptions.</p>
  </section>
</main>
<?php legal_footer('', 'legal-page-footer'); ?>
<dialog id="disconnect-dialog" class="import-dialog" aria-labelledby="disconnect-title"><h2 id="disconnect-title">Disconnect this account?</h2><p>This removes the linked account and subscriptions imported through it from Gather. Creators you added manually stay. Nothing changes on YouTube or Twitch.</p><div class="connection-actions"><button id="confirm-disconnect" class="primary">Disconnect &amp; remove imports</button><button id="cancel-disconnect" class="outline-button">Keep connected</button></div></dialog>
</body></html>

<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Installer.php';
function e(mixed $text):string { return htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$installer=new Installer(dirname(__DIR__));$error='';$saved=false;$location=null;
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
try {
    $location=Installer::location($_SERVER);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('gather_install_'.substr(hash('sha256',$location['path']),0,12));
    session_set_cookie_params(['httponly'=>true,'secure'=>$location['secure'],'samesite'=>'Strict','path'=>$location['path'].'/install/']);
    session_start();$_SESSION['install_csrf']??=bin2hex(random_bytes(32));
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if ($installer->locked()) { http_response_code(403);throw new RuntimeException('Gather is already configured. Setup cannot be run again.'); }
        $csrf=$_POST['csrf']??null;
        if (!is_string($csrf) || !hash_equals($_SESSION['install_csrf'],$csrf)) { http_response_code(403);throw new RuntimeException('Your setup session changed. Reload this page and try again.'); }
        if(in_array(false,$installer->checks(),true)) throw new RuntimeException('Resolve the server checks below before continuing.');
        $input=Installer::input($_POST,$location['url']);
        // No passwords are kept in the session or re-rendered into HTML.
        if (($_SESSION['install_last_attempt']??0)>time()-5) throw new RuntimeException('Please wait a few seconds before trying setup again.');
        $_SESSION['install_last_attempt']=time();
        $installer->verifyProtection($location['url']);
        $installer->install($input);
        $_SESSION=['install_complete'=>true];session_regenerate_id(true);
        header('Location: index.php',true,303);exit;
    }
    $saved=!empty($_SESSION['install_complete']);
} catch(Throwable $failure) {
    $error=($failure instanceof RuntimeException || $failure instanceof InvalidArgumentException) && !$failure instanceof PDOException ? $failure->getMessage() : 'Setup could not finish. Check server permissions and available disk space, then try again.';
}
$locked=$installer->locked();$checks=$installer->checks();
$value=static fn(string $name,string $default=''):string=>is_string($_POST[$name]??null)?$_POST[$name]:$default;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install Gather</title><link rel="stylesheet" href="../public/assets/site.css"><link rel="stylesheet" href="style.css"><link rel="icon" href="../public/assets/favicon.svg"></head>
<body class="legal-page"><header class="legal-header"><a class="brand" href="../"><span class="brand-mark">G</span>Gather.</a><span>Website setup</span></header>
<main class="install-main">
<?php if($locked):?>
  <p class="eyebrow">SETUP LOCKED</p><h1><?=$saved?'Gather is ready.':'Gather is already configured.'?></h1>
  <p><?=$saved?'Your database tables, settings, encryption key and first account have been created.':'The installer cannot overwrite your existing configuration or accounts.'?></p>
  <?php if($error):?><p class="message error" role="alert"><?=e($error)?></p><?php endif;?>
  <section class="install-reminder"><h2>Delete the install folder</h2><p>In your hosting file manager or FTP app, delete <strong>gather/install/</strong>. Your site will keep working after this folder is removed.</p></section>
  <a class="primary" href="../public/auth.php">Sign in to Gather ↗</a>
  <p class="install-help">YouTube and Twitch can be connected after their application credentials are configured. Your feed also supports creator links and subscription files.</p>
<?php else:?>
  <p class="eyebrow">WELCOME TO GATHER</p><h1>Let’s set up your site.</h1><p>Have your hosting database details ready. Gather will create its tables and save the settings automatically.</p>
  <?php if($error):?><p class="message error" role="alert"><?=e($error)?></p><?php endif;?>
  <details class="install-checks" <?=in_array(false,$checks,true)?'open':''?>><summary><?=in_array(false,$checks,true)?'Some server checks need attention':'Server checks passed'?></summary><ul><?php foreach($checks as $label=>$ok):?><li><?= $ok?'✓':'✕' ?> <?=e($label)?></li><?php endforeach;?></ul></details>
  <?php if($location):?><p class="install-url">Installing at <strong><?=e($location['url'])?>/</strong></p><?php endif;?>
  <?php if($location && !in_array(false,$checks,true)):?>
  <form method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?=e($_SESSION['install_csrf']??'')?>">
    <fieldset><legend>1. Connect your database</legend><p>Create an empty MySQL database and its user in your hosting panel first. Existing databases with tables will not be overwritten.</p>
      <label>Database hostname<input name="db_host" required maxlength="254" value="<?=e($value('db_host','localhost'))?>" autocomplete="off"><small>Use the hostname supplied by your host. On DreamHost this is usually not localhost.</small></label>
      <div class="install-columns"><label>Database name<input name="db_name" required maxlength="254" value="<?=e($value('db_name'))?>" autocomplete="off"></label><label>Port<input name="db_port" required inputmode="numeric" pattern="[0-9]+" maxlength="5" value="<?=e($value('db_port','3306'))?>"></label></div>
      <label>Database username<input name="db_user" required maxlength="254" value="<?=e($value('db_user'))?>" autocomplete="off"></label>
      <label>Database password<input type="password" name="db_password" maxlength="2000" autocomplete="off"></label>
    </fieldset>
    <fieldset><legend>2. Create your first account</legend><p>This is your Gather sign-in, separate from the database login.</p>
      <label>Your name<input name="name" required maxlength="80" value="<?=e($value('name'))?>" autocomplete="name"></label>
      <label>Your email<input type="email" name="email" required maxlength="254" value="<?=e($value('email'))?>" autocomplete="email"></label>
      <label>Choose a password<input type="password" name="password" required minlength="12" autocomplete="new-password"><small>Use 12–72 bytes. Choose a unique password.</small></label>
      <label>Confirm password<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label>
    </fieldset>
    <fieldset><legend>3. Choose site settings</legend>
      <label>New account registration<select name="registration"><option value="open" <?=$value('registration','open')==='open'?'selected':''?>>Allow people to create accounts</option><option value="closed" <?=$value('registration')==='closed'?'selected':''?>>Keep registration closed for now</option></select></label>
      <label>Site operator name <span>(optional)</span><input name="legal_operator" maxlength="500" value="<?=e($value('legal_operator'))?>"></label>
      <label>Public contact email <span>(optional)</span><input type="email" name="legal_contact_email" maxlength="254" value="<?=e($value('legal_contact_email'))?>"><small>These details appear on your policy pages. Leave them blank to keep the reminders for later.</small></label>
    </fieldset>
    <p class="install-help">Before saving, Gather will check that private folders are protected. YouTube and Twitch setup is optional and can be done later.</p>
    <button class="primary" type="submit">Install Gather</button><p class="install-help">This may take a moment. Keep this page open until setup finishes.</p>
  </form>
  <?php endif;?>
<?php endif;?>
</main></body></html>

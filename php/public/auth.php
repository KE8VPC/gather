<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/legal.php';
require_once dirname(__DIR__).'/app/bootstrap.php';
try {boot();} catch(Throwable $e) {http_response_code(503);exit('Gather needs configuration. Follow INSTALL.md to connect MySQL.');}
$mode=($_GET['mode']??'login')==='register'?'register':'login';$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        csrf();
        if (($_POST['action']??'')==='logout') {
            $_SESSION=[];session_destroy();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>session_cookie_path(),'secure'=>(bool)config()['secure_cookies'],'httponly'=>true,'samesite'=>'Lax']);header('Location: auth.php');exit;
        }
        $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
        throttle('ip:'.($_SERVER['REMOTE_ADDR']??'unknown'),30);
        throttle('email:'.$email,10);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254) throw new InvalidArgumentException('Enter a valid email address.');
        if ($mode==='register') {
            if (!config()['registration_enabled']) throw new InvalidArgumentException('Registration is closed. Contact the site owner.');
            $code=(string)config()['registration_code'];
            if ($code!=='' && !hash_equals($code,(string)($_POST['invitation']??''))) throw new InvalidArgumentException('Enter a valid invitation code.');
            valid_password($password);$name=trim((string)($_POST['name']??''));
            if (mb_strlen($name)<1 || mb_strlen($name)>80) throw new InvalidArgumentException('Enter a name of up to 80 characters.');
            try {query('INSERT INTO users(email,name,password_hash,created_at) VALUES(?,?,?,?)',[$email,$name,password_digest($password),now_ms()]);}
            catch(PDOException $e){if ($e->getCode()==='23000') throw new InvalidArgumentException('Unable to register that address. Try signing in or contact the site owner.');throw $e;}
        }
        $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
        // Use a valid dummy hash to keep missing-user failures on the hash verification path.
        $valid=password_verify($password,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$u || !$valid || strlen($password)>72) throw new InvalidArgumentException('Email or password is incorrect.');
        authenticate($u);header('Location: index.php');exit;
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Gather auth: '.$e->getMessage());$error='Unable to sign in right now. Please try again later.';}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $mode==='register'?'Create an account':'Sign in' ?> · Gather</title><link rel="stylesheet" href="assets/site.css"><link rel="icon" href="assets/favicon.svg"></head><body class="auth-page"><main class="auth-card"><a class="brand" href="index.php"><span class="brand-mark">G</span>Gather.</a><h1><?= $mode==='register'?'Make yourself at home.':'Welcome back.' ?></h1><p>Your creators. Your feed. All together.</p><?php if($error):?><div class="message error" role="alert"><?=escape($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><?php if($mode==='register'):?><label>Your name<input name="name" maxlength="80" required autocomplete="name" value="<?=escape($_POST['name']??'')?>"></label><?php endif;?><label>Email address<input type="email" name="email" maxlength="254" required autocomplete="email" value="<?=escape($_POST['email']??'')?>"></label><label>Password<input type="password" name="password" required autocomplete="<?=$mode==='register'?'new-password':'current-password'?>" <?= $mode==='register'?'minlength="12"':'' ?>></label><?php if($mode==='register'):?><p class="import-help">Use 12–72 bytes. Your email identifies your account; email verification is not enabled.</p><?php if(config()['registration_code']!==''):?><label>Invitation code<input name="invitation" required autocomplete="off"></label><?php endif;endif;?><button class="primary" type="submit"><?= $mode==='register'?'Create account':'Sign in' ?></button></form><p><?php if($mode==='login'):?><?php if(config()['registration_enabled']):?>New here? <a href="auth.php?mode=register">Create an account</a>.<?php endif;?><br><small>For a password reset, contact the site owner.</small><?php else:?>Already have an account? <a href="auth.php">Sign in</a>.<?php endif;?></p></main><?php legal_footer('', 'auth-footer'); ?></body></html>


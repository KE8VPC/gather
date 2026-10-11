<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try{$email=strtolower(trim($argv[1]??''));$password=getenv('GATHER_NEW_PASSWORD')?:'';if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException();valid_password($password);$r=query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE email=?',[password_digest($password),$email]);if(!$r->rowCount())throw new RuntimeException();echo "Password updated. Existing sessions are invalidated.\n";}catch(Throwable){fwrite(STDERR,"Usage: php bin/reset-password.php email\nSet GATHER_NEW_PASSWORD in the process environment. Confirm the account exists.\n");exit(1);}

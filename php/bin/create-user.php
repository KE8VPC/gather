<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$email=strtolower(trim($argv[1]??''));$name=trim($argv[2]??'');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!$name||mb_strlen($name)>80){fwrite(STDERR,"Usage: php bin/create-user.php email name\nSet GATHER_NEW_PASSWORD in the current process environment, not as a command-line argument.\n");exit(1);}
try{$password=getenv('GATHER_NEW_PASSWORD')?:'';valid_password($password);query('INSERT INTO users(email,name,password_hash,created_at) VALUES(?,?,?,?)',[$email,$name,password_digest($password),now_ms()]);echo "Account created.\n";}catch(Throwable $e){fwrite(STDERR,"Could not create account. Check password length, duplicate email, and database configuration.\n");exit(1);}

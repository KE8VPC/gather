<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('GATHER_INSTALL_TESTS')!=='isolated-local-database') { fwrite(STDERR,"Installer tests require an isolated local MySQL server on port 33080 and GATHER_INSTALL_TESTS=isolated-local-database.\n");exit(1); }
require dirname(__DIR__).'/app/Installer.php';
function verify(bool $value,string $message):void {if(!$value)throw new RuntimeException('FAIL '.$message);echo 'PASS '.$message.PHP_EOL;}
function fails(Closure $call,string $message):void {try{$call();}catch(Throwable){verify(true,$message);return;}throw new RuntimeException('FAIL '.$message);}
$admin=new PDO('mysql:host=127.0.0.1;port=33080;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$suffix=bin2hex(random_bytes(5));$schemas=[];$roots=[];
function fixture(string $suffix,string $case):array {
    global $admin,$schemas,$roots;
    $db='gather_install_'.$suffix.'_'.$case;
    if(!preg_match('/^gather_install_[a-f0-9]{10}_[a-z]+$/D',$db))throw new LogicException('Invalid test database name.');
    $admin->exec('CREATE DATABASE `'.$db.'`');$schemas[]=$db;
    $root=sys_get_temp_dir().'/gather-install-'.$suffix.'-'.$case;$roots[]=$root;
    foreach(['config','storage','database'] as $dir)mkdir($root.'/'.$dir,0700,true);
    copy(dirname(__DIR__).'/database/schema.sql',$root.'/database/schema.sql');
    return [$root,$db];
}
$data=['db_host'=>'127.0.0.1','db_port'=>'33080','db_user'=>'root','db_password'=>'','name'=>"O'Reilly",'email'=>'owner@example.test','password'=>'test-unique-password-2026','password_confirm'=>'test-unique-password-2026','registration'=>'closed','legal_operator'=>"O'Reilly's \n Test",'legal_contact_email'=>''];
try {
    $location=Installer::location(['HTTP_HOST'=>'auglaize.co','HTTPS'=>'on','SCRIPT_NAME'=>'/gather/install/index.php']);
    verify($location['url']==='https://auglaize.co/gather','subdirectory URL detection');
    fails(fn()=>Installer::location(['HTTP_HOST'=>'auglaize.co','SCRIPT_NAME'=>'/gather/install/index.php']),'public HTTP refused');
    fails(fn()=>Installer::location(['HTTP_HOST'=>"evil\r\nInjected: yes",'HTTPS'=>'on','SCRIPT_NAME'=>'/gather/install/index.php']),'invalid Host refused');
    [$root,$db]=fixture($suffix,'fresh');$data['db_name']=$db;
    fails(fn()=>(new Installer($root))->verifyProtection('https://127.0.0.1'),'public protection probe cannot target internal addresses');
    $bad=$data;$bad['password_confirm']='wrong';fails(fn()=>Installer::input($bad,$location['url']),'password confirmation enforced');
    $bad=$data;$bad['db_host']='localhost;dbname=other';fails(fn()=>Installer::input($bad,$location['url']),'database DSN injection refused');
    $input=Installer::input($data,$location['url']);$installer=new Installer($root);$installer->install($input);
    $saved=require $root.'/config/local.php';
    verify($saved['app_url']===$location['url'] && $saved['oauth_callback_url']===$location['url'].'/public/oauth.php','configuration and provider callback saved');
    verify($saved['legal_operator']===$data['legal_operator'],'quotes and newlines round-trip safely in PHP configuration');
    verify(strlen(base64_decode($saved['oauth_encryption_key'],true))===32 && $saved['secure_cookies'] && !$saved['registration_enabled'],'encryption key and selected settings saved');
    $admin->exec('USE `'.$db.'`');verify(count($admin->query('SHOW TABLES')->fetchAll())===7,'all seven tables created');
    $user=$admin->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);verify(password_verify($data['password'],$user['password_hash']),'first account has a valid password hash');
    $hash=hash_file('sha256',$root.'/config/local.php');fails(fn()=>$installer->install($input),'second installation is blocked');verify(hash_file('sha256',$root.'/config/local.php')===$hash,'existing configuration unchanged');
    unlink($root.'/config/local.php');verify($installer->locked(),'completion marker still locks setup if configuration is removed');
    [$root,$db]=fixture($suffix,'occupied');$admin->exec('USE `'.$db.'`');$admin->exec('CREATE TABLE unrelated(id INT)');$data['db_name']=$db;
    fails(fn()=>(new Installer($root))->install(Installer::input($data,$location['url'])),'non-empty database refused');verify(count($admin->query('SHOW TABLES')->fetchAll())===1,'unrelated database tables preserved');
    [$root,$db]=fixture($suffix,'resume');$data['db_name']=$db;$installer=new Installer($root);
    $schema=file_get_contents($root.'/database/schema.sql');$first=explode(';',$schema)[0].';';file_put_contents($root.'/database/schema.sql',$first.' BROKEN SQL;');
    fails(fn()=>$installer->install(Installer::input($data,$location['url'])),'interrupted schema creation reported');verify(is_file($root.'/config/install-progress.php') && !is_file($root.'/config/local.php'),'retry marker saved without publishing incomplete settings');
    file_put_contents($root.'/database/schema.sql',$schema);$installer->install(Installer::input($data,$location['url']));verify($installer->locked(),'partial fresh installation can resume');
    echo "Installer database tests passed on the isolated local server.\n";
} finally {
    foreach($schemas as $db)$admin->exec('DROP DATABASE `'.$db.'`');
    // Only remove directories generated in this run, inside the actual temp root.
    $base=realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR;
    foreach($roots as $root){$resolved=realpath($root);if(!$resolved || !str_starts_with($resolved,$base.'gather-install-'.$suffix.'-'))throw new RuntimeException('Refusing cleanup outside the test directory.');
        $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($items as $item){if($item->isLink() || $item->isFile())unlink($item->getPathname());else rmdir($item->getPathname());}rmdir($resolved);
    }
}

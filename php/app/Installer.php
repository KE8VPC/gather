<?php
declare(strict_types=1);

final class Installer {
    public function __construct(private string $root) {}
    public function locked(): bool {
        return file_exists($this->root.'/config/local.php') || file_exists($this->root.'/storage/installed.lock');
    }
    public static function location(array $server): array {
        $host=(string)($server['HTTP_HOST']??'');
        if (!preg_match('/^(?:[a-zA-Z0-9.-]+|\[::1\])(?::[0-9]{1,5})?$/D',$host)) throw new RuntimeException('Open the installer using your website’s normal address.');
        $secure=($server['HTTPS']??'')==='on' || ($server['HTTPS']??'')==='1';
        $local=PHP_SAPI==='cli-server' && in_array($server['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
            && in_array(parse_url('http://'.$host,PHP_URL_HOST),['localhost','127.0.0.1','[::1]'],true);
        if (!$secure && !$local) throw new RuntimeException('Open this page with https:// before entering database or account passwords. If HTTPS is terminated by your host’s proxy, ask the host to configure PHP’s HTTPS indicator.');
        $script=(string)($server['SCRIPT_NAME']??'');
        if (!str_ends_with($script,'/install/index.php') || preg_match('/[\x00-\x20?#]/',$script)) throw new RuntimeException('Open the installer at /gather/install/ on your website.');
        $path=substr($script,0,-strlen('/install/index.php'));
        return ['url'=>($secure?'https':'http').'://'.$host.$path,'path'=>$path,'secure'=>$secure];
    }
    public function checks(): array {
        $checks=['PHP 8.2 or newer'=>PHP_VERSION_ID>=80200];
        foreach(['pdo_mysql','curl','mbstring','simplexml','session','openssl'] as $ext) $checks['PHP '.$ext.' support']=extension_loaded($ext);
        foreach(['config','storage'] as $dir) $checks['Can save '.$dir.' files']=is_dir($this->root.'/'.$dir) && is_writable($this->root.'/'.$dir);
        foreach(['app','config','database','bin','tests','storage'] as $dir) $checks['Protection file for '.$dir]=is_file($this->root.'/'.$dir.'/.htaccess');
        $checks['Database schema is available']=is_readable($this->root.'/database/schema.sql');
        return $checks;
    }
    private function writeNew(string $path,string $contents): void {
        $temporary=dirname($path).'/.install-'.bin2hex(random_bytes(12)).'.php';
        $handle=@fopen($temporary,'x');
        if (!$handle) throw new RuntimeException('Unable to create the settings file. Check folder permissions and try again. Existing settings will never be overwritten.');
        @chmod($temporary,0600);
        $ok=fwrite($handle,$contents)===strlen($contents) && fflush($handle);
        if ($ok && function_exists('fsync')) $ok=fsync($handle);
        fclose($handle);
        // Link publishes a fully written file atomically and refuses an existing
        // destination. A failed/crashed write never leaves a partial local.php.
        if (!$ok || !@link($temporary,$path)) { @unlink($temporary); throw new RuntimeException('The server could not safely save settings. Check disk space and folder permissions. Existing settings were not overwritten.'); }
        @unlink($temporary);
    }
    public function probeToken(): string {
        $path=$this->root.'/config/install-probe.php';
        if (!is_file($path)) {
            try { $this->writeNew($path,"<?php return ".var_export(bin2hex(random_bytes(32)),true).";\n"); }
            catch (RuntimeException $e) { if (!is_file($path)) throw $e; }
        }
        $value=require $path;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D',$value)) throw new RuntimeException('The setup protection check could not start. Contact the site owner.');
        return $value;
    }
    public function verifyProtection(string $url): void {
        // Complete this before accepting a database connection or writing secrets.
        $host=(string)parse_url($url,PHP_URL_HOST);$port=(int)(parse_url($url,PHP_URL_PORT)?:443);
        $local=PHP_SAPI==='cli-server' && in_array($host,['localhost','127.0.0.1','[::1]'],true);
        $resolve=[];
        if (!$local) {
            if (parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException('The installation URL must use HTTPS.');
            $addresses=filter_var($host,FILTER_VALIDATE_IP)?[$host]:gethostbynamel($host);
            if (!$addresses) throw new RuntimeException('The website hostname could not be resolved. Check its DNS settings before setup.');
            foreach($addresses as $address) if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('The website hostname must resolve to a public address for the setup protection check.');
            // Pin the checked address so a changed DNS answer cannot target an internal service.
            $resolve=[$host.':'.$port.':'.$addresses[0]];
        }
        $token=$this->probeToken();
        foreach(['/install/probe.php'=>200,'/config/access-check.txt'=>403,'/app/Installer.php'=>403,'/database/schema.sql'=>403,'/storage/.gitkeep'=>403,'/bin/preflight.php'=>403,'/tests/unit.php'=>403] as $path=>$expected) {
            $body=''; $curl=curl_init($url.$path);
            curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,
                CURLOPT_RESOLVE=>$resolve,
                CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$body){$body.=$chunk;return strlen($body)>16000?0:strlen($chunk);}]);
            $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
            if ($expected===200) {
                if ($ok===false || $status!==200 || !hash_equals($token,trim($body))) throw new RuntimeException('The server could not verify this installation over its public URL. Ask your host to allow HTTPS requests from PHP to this website and check the cURL certificate bundle. No database changes were made.');
            } elseif ($ok===false || !in_array($status,[403,404],true)) {
                throw new RuntimeException('Private folder protection failed for '.$path.'. Upload the hidden .htaccess files and ask your host to enable them (or equivalent Nginx rules). No database changes were made.');
            }
        }
    }
    public static function input(array $data,string $url): array {
        $get=static function(string $key,int $max=254)use($data):string {
            $value=$data[$key]??'';
            if (!is_string($value) || strlen($value)>$max || str_contains($value,"\0")) throw new InvalidArgumentException('Please check the '.$key.' field.');
            return trim($value);
        };
        $host=$get('db_host');$name=$get('db_name');$user=$get('db_user');$port=$get('db_port',5);
        if ($host==='' || preg_match('/[;\x00-\x20]/',$host) || !preg_match('/^[a-zA-Z0-9_-]+$/D',$name) || $user==='' || !ctype_digit($port) || (int)$port<1 || (int)$port>65535) throw new InvalidArgumentException('Enter the database hostname, database name, username and a port between 1 and 65535.');
        $dbPassword=$data['db_password']??'';$password=$data['password']??'';
        if (!is_string($dbPassword) || strlen($dbPassword)>2000 || str_contains($dbPassword,"\0")) throw new InvalidArgumentException('Check the database password.');
        if (!is_string($password) || strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Choose an account password between 12 and 72 bytes.');
        if (!is_string($data['password_confirm']??null) || !hash_equals($password,$data['password_confirm'])) throw new InvalidArgumentException('The account passwords do not match.');
        $email=strtolower($get('email'));$accountName=$get('name',240);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $accountName==='' || mb_strlen($accountName)>80) throw new InvalidArgumentException('Enter your name (up to 80 characters) and a valid email address.');
        $contact=$get('legal_contact_email');
        if ($contact!=='' && !filter_var($contact,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid public contact email, or leave it blank for now.');
        $config=[
            'app_url'=>$url,'db'=>['host'=>$host,'port'=>(int)$port,'name'=>$name,'user'=>$user,'password'=>$dbPassword,'ssl_ca'=>null],
            'secure_cookies'=>str_starts_with($url,'https://'),'registration_enabled'=>($data['registration']??'')==='open','registration_code'=>'',
            'legal_operator'=>$get('legal_operator',500),'legal_contact_email'=>$contact,
            'youtube_api_key'=>'','youtube_client_id'=>'','youtube_client_secret'=>'','twitch_client_id'=>'','twitch_client_secret'=>'',
            'oauth_callback_url'=>$url.'/public/oauth.php','oauth_encryption_key'=>base64_encode(random_bytes(32)),
        ];
        return ['config'=>$config,'email'=>$email,'name'=>$accountName,'password'=>$password];
    }
    public function install(array $input): void {
        $lock=@fopen($this->root.'/storage/install-running.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { if($lock)fclose($lock);throw new RuntimeException('Another setup request is running. Wait a moment and try again.'); }
        try {
            if ($this->locked()) throw new RuntimeException('Gather is already configured. The installer is locked.');
            $c=$input['config'];$d=$c['db'];
            try {
                $pdo=new PDO('mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['password'],[
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>8]);
            } catch (PDOException) { throw new RuntimeException('Could not connect to the database. Check the hostname, database name, username, password and whether your host has granted this user access to the database.'); }
            $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
            if (stripos($version,'MariaDB')!==false) $supported=(bool)preg_match('/(?:^|-)10\.(?:[6-9]|[1-9][0-9])\.|(?:^|-)1[1-9]\./',$version);
            else $supported=version_compare($version,'8.0','>=');
            if (!$supported) throw new RuntimeException('Gather needs MySQL 8.0 or newer, or MariaDB 10.6 or newer.');
            $fingerprint=hash('sha256',json_encode([$d['host'],$d['port'],$d['name'],$d['user']],JSON_THROW_ON_ERROR));
            $progressPath=$this->root.'/config/install-progress.php';
            $progress=is_file($progressPath)?require $progressPath:null;
            if ($progress!==null && $progress!==$fingerprint) throw new RuntimeException('An earlier setup attempt used a different database. Finish that attempt first, or ask the site owner to review config/install-progress.php.');
            $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($progress===null) {
                if ($tables) throw new RuntimeException('This database already contains tables. Choose an empty database. For an existing Gather site, use the upgrade instructions instead. Nothing was changed.');
                $this->writeNew($progressPath,"<?php return ".var_export($fingerprint,true).";\n");
            } elseif (array_diff($tables,['users','creators','subscriptions','items','auth_limits','linked_accounts','linked_imports'])) {
                throw new RuntimeException('Unexpected tables were found in this setup database. Ask the site owner to review it before continuing.');
            }
            $sql=(string)file_get_contents($this->root.'/database/schema.sql');
            $sql=preg_replace('/^--.*$/m','',$sql);
            $sql=preg_replace('/CREATE TABLE (?!IF NOT EXISTS)/','CREATE TABLE IF NOT EXISTS ',$sql);
            try { foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $pdo->exec($statement); }
            catch(PDOException) { throw new RuntimeException('The database connected, but its tables could not be created. Check CREATE and REFERENCES permissions. You can retry with the same database; existing rows are not erased.'); }
            // MySQL DDL is not transactional. Keep a private database fingerprint so
            // a failed fresh install can resume, without adopting unrelated tables.
            $rows=$pdo->query('SELECT email,password_hash FROM users LIMIT 2')->fetchAll();
            if ($rows) {
                if(count($rows)!==1 || $rows[0]['email']!==$input['email'] || !password_verify($input['password'],$rows[0]['password_hash'])) throw new RuntimeException('This setup database already has an account. Retry using the same first-account email and password as the previous attempt.');
            } else {
                $q=$pdo->prepare('INSERT INTO users(email,name,password_hash,created_at) VALUES(?,?,?,?)');
                $q->execute([$input['email'],$input['name'],password_hash($input['password'],defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT),(int)floor(microtime(true)*1000)]);
            }
            $this->writeNew($this->root.'/config/local.php',"<?php\n// Generated by the Gather installer. Keep this file private.\nreturn ".var_export($c,true).";\n");
            // local.php is itself a permanent lock, even if writing the marker fails.
            @file_put_contents($this->root.'/storage/installed.lock',gmdate(DATE_ATOM)."\n",LOCK_EX);
            @chmod($this->root.'/storage/installed.lock',0600);
            @unlink($progressPath);@unlink($this->root.'/config/install-probe.php');
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
}

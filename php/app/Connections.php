<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Feeds.php';

final class ProviderFailure extends RuntimeException {
    public function __construct(public readonly int $status, public readonly string $reason = '') {
        parent::__construct(in_array($status, [400,401], true) && in_array($reason, ['invalid_grant','invalid_token','unauthorized'], true)
            ? 'This connection has expired or been revoked. Connect the account again.'
            : ($status === 403 ? 'Access was denied. Check the permissions granted to Gather and the provider quota.'
            : ($status === 429 ? 'The provider is busy. Please try syncing again later.' : 'The provider could not complete the request. Please try again.')));
    }
    public function revoked(): bool { return $this->status === 401 || ($this->status === 400 && $this->reason === 'invalid_grant'); }
}

final class Connections {
    public const PROVIDERS = ['youtube'=>'YouTube', 'twitch'=>'Twitch'];
    public const SCOPES = ['youtube'=>'https://www.googleapis.com/auth/youtube.readonly', 'twitch'=>'user:read:follows'];
    // A callable transport is injected only by CLI tests, never from request/config data.
    public function __construct(private array $settings, private ?Closure $transport = null) {}
    public static function provider(mixed $value): string {
        if (!is_string($value) || !isset(self::PROVIDERS[$value])) throw new InvalidArgumentException('Choose YouTube or Twitch.',400);
        return $value;
    }
    private function key(): string {
        $key = base64_decode((string)($this->settings['oauth_encryption_key'] ?? ''), true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('Account linking needs an encryption key configured by the site owner.');
        return $key;
    }
    public function ready(string $provider): bool {
        return $this->setupIssues($provider)===[];
    }
    public function setupIssues(string $provider): array {
        self::provider($provider);$issues=[];
        if(trim((string)($this->settings[$provider.'_client_id']??''))==='')$issues[]='Client ID is missing.';
        if(trim((string)($this->settings[$provider.'_client_secret']??''))==='')$issues[]='Client secret is missing.';
        try {
            $this->key();
        } catch (Throwable) { $issues[]='The site encryption key needs attention.'; }
        try { $this->redirect($provider); }
        catch(Throwable){$issues[]='The callback address or HTTPS cookie settings need attention.';}
        return $issues;
    }
    public function redirect(string $provider): string {
        self::provider($provider);
        // Explicit callback URL supports both nested installs and public-only document roots.
        $url = (string)($this->settings['oauth_callback_url'] ?? '');
        $p = parse_url($url); $app = parse_url((string)($this->settings['app_url'] ?? ''));
        if (!$p || !$app || !isset($p['host'], $p['scheme'], $p['path']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])
            || preg_match('/[\x00-\x20]/', $url) || !str_ends_with($p['path'], '/oauth.php')
            || strtolower($p['host']) !== strtolower($app['host'] ?? '') || ($p['port'] ?? null) !== ($app['port'] ?? null)
            || $p['scheme'] !== ($app['scheme'] ?? '') || ($p['scheme']==='https' && empty($this->settings['secure_cookies']))
            || ($p['scheme'] !== 'https' && !($p['scheme'] === 'http' && in_array($p['host'], ['localhost','127.0.0.1'], true)))) {
            throw new RuntimeException('Account linking needs a matching callback URL and secure HTTPS cookies configured by the site owner.');
        }
        return $url . '?provider=' . $provider;
    }
    private function cryptContext(int $uid, string $p, string $field): string { return 'Gather:'.$uid.':'.$p.':'.$field; }
    public function seal(string $value, string $context): string {
        $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag,$context);
        if ($cipher === false) throw new RuntimeException('Unable to protect account connection.');
        return base64_encode($iv.$tag.$cipher);
    }
    public function unseal(string $value, string $context): string {
        $raw=base64_decode($value,true);
        if ($raw===false || strlen($raw)<28) throw new RuntimeException('Unable to read account connection. Disconnect and reconnect it.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
        if ($plain===false) throw new RuntimeException('Unable to read account connection. Disconnect and reconnect it.');
        return $plain;
    }
    private function request(string $url, array $headers=[], ?array $post=null): array {
        $p=parse_url($url);
        if (!$p || ($p['scheme']??'')!=='https' || !in_array($p['host']??'', ['oauth2.googleapis.com','www.googleapis.com','id.twitch.tv','api.twitch.tv'],true)
            || isset($p['user']) || isset($p['pass']) || isset($p['port'])) throw new LogicException('Invalid provider endpoint.');
        if ($this->transport) return ($this->transport)($url,$headers,$post);
        $body=''; $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json'],$headers),CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$body){$body.=$chunk;return strlen($body)>2000000?0:strlen($chunk);}]);
        if ($post!==null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
        $ok=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
        $data=json_decode($body,true);
        if ($ok===false || $status<200 || $status>=300) {
            $reason=is_string($data['error']??null)?$data['error']:'';
            // Never expose provider bodies, codes, tokens or credential-bearing URLs.
            throw new ProviderFailure($status,$reason);
        }
        if ($body==='') return [];
        if (!is_array($data)) throw new ProviderFailure(502);
        return $data;
    }
    public function authorize(int $uid,string $p,array &$session): string {
        self::provider($p);
        if (!$this->ready($p)) throw new RuntimeException('The site owner has not enabled this connection yet.');
        $state=bin2hex(random_bytes(32));
        $session['oauth_pending'][$p]=['state'=>$state,'user'=>$uid,'expires'=>time()+600,'redirect'=>$this->redirect($p)];
        $args=['client_id'=>$this->settings[$p.'_client_id'],'redirect_uri'=>$this->redirect($p),'response_type'=>'code','scope'=>self::SCOPES[$p],'state'=>$state];
        if ($p==='youtube') $args+=['access_type'=>'offline','prompt'=>'consent select_account'];
        else $args+=['force_verify'=>'true'];
        return ($p==='youtube'?'https://accounts.google.com/o/oauth2/v2/auth':'https://id.twitch.tv/oauth2/authorize').'?'.http_build_query($args);
    }
    public static function consumeState(int $uid,string $p,mixed $state,array &$session): array {
        $pending=$session['oauth_pending'][$p]??null;
        unset($session['oauth_pending'][$p]);
        if (!is_array($pending) || $pending['user']!==$uid || $pending['expires']<time() || !is_string($state) || !hash_equals($pending['state'],$state)) {
            throw new InvalidArgumentException('The connection request expired or belongs to another session. Please start again.',403);
        }
        return $pending;
    }
    private function tokenEndpoint(string $p): string { return $p==='youtube'?'https://oauth2.googleapis.com/token':'https://id.twitch.tv/oauth2/token'; }
    private function identity(string $p,string $token): array {
        if ($p==='youtube') {
            $data=$this->request('https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true',['Authorization: Bearer '.$token]);
            $channel=$data['items'][0]??[];
            if (!is_string($channel['id']??null) || !preg_match('/^UC[\w-]{22}$/D',$channel['id'])) throw new RuntimeException('Choose a Google account with a YouTube channel, then reconnect.');
            return ['id'=>$channel['id'],'name'=>mb_substr((string)($channel['snippet']['title']??'YouTube account'),0,200)];
        }
        $data=$this->request('https://id.twitch.tv/oauth2/validate',['Authorization: OAuth '.$token]);
        if (($data['client_id']??'')!==$this->settings['twitch_client_id'] || !is_string($data['user_id']??null) || !ctype_digit($data['user_id'])
            || !in_array(self::SCOPES['twitch'],$data['scopes']??[],true)) throw new ProviderFailure(401,'unauthorized');
        return ['id'=>$data['user_id'],'name'=>mb_substr((string)($data['login']??'Twitch account'),0,200)];
    }
    private function checkToken(array $t,string $p): void {
        if (!is_string($t['access_token']??null) || $t['access_token']==='' || strlen($t['access_token'])>10000 || preg_match('/[\x00-\x20]/',$t['access_token'])
            || !is_numeric($t['expires_in']??null) || (int)$t['expires_in']<1) throw new ProviderFailure(502);
        if (isset($t['scope'])) {
            $scopes=is_array($t['scope'])?$t['scope']:explode(' ',(string)$t['scope']);
            if (!in_array(self::SCOPES[$p],$scopes,true)) throw new ProviderFailure(401,'unauthorized');
        }
    }
    public function locked(int $uid,Closure $operation): mixed {
        $name='gather-links-'.substr(hash('sha256',($this->settings['db']['name']??'').':'.$uid),0,40);
        if ((int)query('SELECT GET_LOCK(?,5)',[$name])->fetchColumn()!==1) throw new InvalidArgumentException('Another account connection is busy. Please try again.',409);
        try { return $operation(); } finally { query('SELECT RELEASE_LOCK(?)',[$name]); }
    }
    public function connect(int $uid,string $p,string $code,array $pending): void {
        if (!$this->ready($p) || $pending['redirect']!==$this->redirect($p)) throw new RuntimeException('Connection settings changed. Please start again.');
        $t=$this->request($this->tokenEndpoint($p),[],['client_id'=>$this->settings[$p.'_client_id'],'client_secret'=>$this->settings[$p.'_client_secret'],'grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$pending['redirect']]);
        $this->checkToken($t,$p); $identity=$this->identity($p,$t['access_token']);
        $existing=query('SELECT external_id FROM linked_accounts WHERE user_id=? AND provider=?',[$uid,$p])->fetchColumn();
        if ($existing!==false && $existing!==$identity['id']) throw new RuntimeException('Disconnect the current account before linking a different one.');
        $refresh=$t['refresh_token']??'';
        if (!is_string($refresh) || $refresh==='') throw new RuntimeException('The provider did not grant lasting access. Revoke Gather in the provider settings, then reconnect.');
        $access=$this->seal($t['access_token'],$this->cryptContext($uid,$p,'access'));
        $refresh=$this->seal($refresh,$this->cryptContext($uid,$p,'refresh'));
        try {
            query('INSERT INTO linked_accounts(user_id,provider,external_id,display_name,access_token,refresh_token,expires_at,generation,connected_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=IF(user_id=VALUES(user_id),VALUES(display_name),display_name),access_token=IF(user_id=VALUES(user_id),VALUES(access_token),access_token),refresh_token=IF(user_id=VALUES(user_id),VALUES(refresh_token),refresh_token),expires_at=IF(user_id=VALUES(user_id),VALUES(expires_at),expires_at),generation=IF(user_id=VALUES(user_id),VALUES(generation),generation)',
                [$uid,$p,$identity['id'],$identity['name'],$access,$refresh,time()+(int)$t['expires_in'],bin2hex(random_bytes(16)),time()]);
            if (!query('SELECT 1 FROM linked_accounts WHERE user_id=? AND provider=? AND external_id=?',[$uid,$p,$identity['id']])->fetchColumn()) throw new RuntimeException('That provider account is already connected to another Gather account.');
        } catch (PDOException $e) { throw new RuntimeException('Unable to save the account connection.'); }
    }
    public function account(int $uid,string $p): array {
        $row=query('SELECT * FROM linked_accounts WHERE user_id=? AND provider=?',[$uid,$p])->fetch();
        if (!$row) throw new InvalidArgumentException('Connect this account before syncing.',404);
        return $row;
    }
    private function access(int $uid,string $p,array $row,bool $force=false): string {
        $token=$this->unseal($row['access_token'],$this->cryptContext($uid,$p,'access'));
        if (!$force && (int)$row['expires_at']>time()+120) return $token;
        $refresh=$this->unseal($row['refresh_token'],$this->cryptContext($uid,$p,'refresh'));
        $t=$this->request($this->tokenEndpoint($p),[],['client_id'=>$this->settings[$p.'_client_id'],'client_secret'=>$this->settings[$p.'_client_secret'],'grant_type'=>'refresh_token','refresh_token'=>$refresh]);
        $this->checkToken($t,$p);
        $newRefresh=$t['refresh_token']??$refresh;
        if (!is_string($newRefresh) || $newRefresh==='') throw new ProviderFailure(502);
        query('UPDATE linked_accounts SET access_token=?,refresh_token=?,expires_at=? WHERE user_id=? AND provider=? AND generation=?',[
            $this->seal($t['access_token'],$this->cryptContext($uid,$p,'access')),$this->seal($newRefresh,$this->cryptContext($uid,$p,'refresh')),time()+(int)$t['expires_in'],$uid,$p,$row['generation']]);
        return $t['access_token'];
    }
    public function verify(int $uid,string $p,array $row): string {
        $token=$this->access($uid,$p,$row);
        try { $identity=$this->identity($p,$token); }
        catch (ProviderFailure $e) {
            if ($e->status!==401) throw $e;
            $token=$this->access($uid,$p,$this->account($uid,$p),true); $identity=$this->identity($p,$token);
        }
        if ($identity['id']!==$row['external_id']) throw new ProviderFailure(401,'unauthorized');
        query('UPDATE linked_accounts SET display_name=? WHERE user_id=? AND provider=? AND generation=?',[$identity['name'],$uid,$p,$row['generation']]);
        return $token;
    }
    public function beginSync(int $uid,string $p,array &$session): array {
        $row=$this->account($uid,$p);
        $job=['id'=>bin2hex(random_bytes(16)),'user'=>$uid,'generation'=>$row['generation'],'started'=>time(),'cursor'=>'','sources'=>[],'pages'=>0];
        $session['linked_sync'][$p]=$job;
        return ['job'=>$job['id'],'done'=>false,'scanned'=>0];
    }
    public function step(int $uid,string $p,string $jobId,array &$session): array {
        $job=$session['linked_sync'][$p]??null;
        if (!$job || $job['user']!==$uid || !hash_equals($job['id'],$jobId) || $job['started']<time()-900) throw new InvalidArgumentException('This sync expired. Select Sync now to start again.',409);
        $row=$this->account($uid,$p);
        if ($job['generation']!==$row['generation']) throw new InvalidArgumentException('The connection changed. Start a new sync.',409);
        // Each request reads one provider page, avoiding shared-host request timeouts.
        $token=$this->verify($uid,$p,$row);
        $headers=['Authorization: Bearer '.$token];
        if ($p==='youtube') {
            $url='https://www.googleapis.com/youtube/v3/subscriptions?'.http_build_query(['part'=>'snippet','mine'=>'true','maxResults'=>50]+($job['cursor']!==''?['pageToken'=>$job['cursor']]:[]));
        } else {
            $headers[]='Client-ID: '.$this->settings['twitch_client_id'];
            $url='https://api.twitch.tv/helix/channels/followed?'.http_build_query(['user_id'=>$row['external_id'],'first'=>100]+($job['cursor']!==''?['after'=>$job['cursor']]:[]));
        }
        $data=$this->request($url,$headers);
        $entries=$data[$p==='youtube'?'items':'data']??null;
        if (!is_array($entries)) throw new ProviderFailure(502);
        foreach ($entries as $entry) {
            $id=$p==='youtube'?($entry['snippet']['resourceId']['channelId']??''):($entry['broadcaster_login']??'');
            if (!is_string($id) || !preg_match($p==='youtube'?'/^UC[\w-]{22}$/D':'/^[a-zA-Z0-9_]{3,25}$/D',$id)) throw new ProviderFailure(502);
            $source=Feeds::source(($p==='youtube'?'https://www.youtube.com/channel/':'https://www.twitch.tv/').$id);
            // Keep provider-derived display names in the user's connection only;
            // creator names are populated by the existing public feed reader.
            $job['sources'][$source['id']]=$source;
        }
        $cursor=$p==='youtube'?($data['nextPageToken']??''):($data['pagination']['cursor']??'');
        if (!is_string($cursor) || strlen($cursor)>4000 || ($cursor!=='' && $cursor===$job['cursor'])) throw new ProviderFailure(502);
        if (count($job['sources'])>1000 || ++$job['pages']>40) throw new RuntimeException('This account exceeds the 1,000-creator import limit. Use a smaller file import instead. Your existing library has not changed.');
        if ($cursor!=='') {
            $job['cursor']=$cursor; $session['linked_sync'][$p]=$job;
            return ['done'=>false,'scanned'=>count($job['sources']),'job'=>$job['id']];
        }
        $result=$this->apply($uid,$p,$job['sources']);
        unset($session['linked_sync'][$p]);
        return ['done'=>true,'scanned'=>count($job['sources'])]+$result;
    }
    private function apply(int $uid,string $p,array $sources): array {
        $pdo=db(); $pdo->beginTransaction();
        try {
            query('SELECT id FROM users WHERE id=? FOR UPDATE',[$uid]);
            $existing=query('SELECT creator_id FROM subscriptions WHERE user_id=?',[$uid])->fetchAll(PDO::FETCH_COLUMN);
            $owned=query('SELECT creator_id FROM linked_imports WHERE user_id=? AND provider=?',[$uid,$p])->fetchAll(PDO::FETCH_COLUMN);
            $removed=array_diff($owned,array_keys($sources)); $added=array_diff(array_keys($sources),$existing);
            if (count($existing)-count($removed)+count($added)>1000) throw new RuntimeException('Your combined library would exceed 1,000 subscriptions. Remove some creators and sync again. Your existing library has not changed.');
            foreach ($removed as $id) query('DELETE FROM subscriptions WHERE user_id=? AND creator_id=?',[$uid,$id]);
            foreach ($added as $id) {
                $c=$sources[$id];
                query('INSERT INTO creators(id,name,platform,url,feed) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE id=creators.id',array_values($c));
                query('INSERT INTO subscriptions(user_id,creator_id,created_at) VALUES(?,?,?)',[$uid,$id,now_ms()]);
                query('INSERT INTO linked_imports(user_id,provider,creator_id) VALUES(?,?,?)',[$uid,$p,$id]);
            }
            query('UPDATE linked_accounts SET last_sync_at=? WHERE user_id=? AND provider=?',[time(),$uid,$p]);
            $pdo->commit();
            return ['added'=>count($added),'removed'=>count($removed),'existing'=>count($sources)-count($added)];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public function forget(int $uid,string $p): void {
        $pdo=db(); $pdo->beginTransaction();
        try {
            query('SELECT id FROM users WHERE id=? FOR UPDATE',[$uid]);
            query('DELETE s FROM subscriptions s JOIN linked_imports i ON i.user_id=s.user_id AND i.creator_id=s.creator_id WHERE i.user_id=? AND i.provider=?',[$uid,$p]);
            query('DELETE FROM linked_accounts WHERE user_id=? AND provider=?',[$uid,$p]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public function disconnect(int $uid,string $p): bool {
        $row=query('SELECT * FROM linked_accounts WHERE user_id=? AND provider=?',[$uid,$p])->fetch();
        if (!$row) return true;
        $revoked=true;
        try {
            if ($p==='youtube') $this->request('https://oauth2.googleapis.com/revoke',[],['token'=>$this->unseal($row['refresh_token'],$this->cryptContext($uid,$p,'refresh'))]);
            else $this->request('https://id.twitch.tv/oauth2/revoke',[],['client_id'=>$this->settings['twitch_client_id'],'token'=>$this->unseal($row['access_token'],$this->cryptContext($uid,$p,'access'))]);
        } catch (Throwable) { $revoked=false; }
        // Always delete locally, even when the provider is unreachable or keys changed.
        $this->forget($uid,$p);
        return $revoked;
    }
    public static function expire(?int $uid=null): int {
        $cutoff=time()-29*86400;
        $stale=query('SELECT user_id,provider FROM linked_accounts WHERE GREATEST(connected_at,last_sync_at)<?'.($uid!==null?' AND user_id=?':''),$uid!==null?[$cutoff,$uid]:[$cutoff])->fetchAll();
        $service=new self(config()); $count=0;
        foreach ($stale as $row) $service->locked((int)$row['user_id'],function()use($service,$row,$cutoff,&$count){
            $current=query('SELECT 1 FROM linked_accounts WHERE user_id=? AND provider=? AND GREATEST(connected_at,last_sync_at)<?',[$row['user_id'],$row['provider'],$cutoff])->fetchColumn();
            if ($current) { $service->forget((int)$row['user_id'],$row['provider']); $count++; }
        });
        return $count;
    }
}

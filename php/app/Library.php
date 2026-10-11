<?php
declare(strict_types=1);
require_once __DIR__.'/Feeds.php';
require_once __DIR__.'/Connections.php';
require_once __DIR__.'/YouTube.php';
final class Library {
    public const RECOMMENDATIONS = 'WITH public_subscriptions AS (SELECT s.* FROM subscriptions s WHERE NOT EXISTS (SELECT 1 FROM linked_imports i WHERE i.user_id=s.user_id AND i.creator_id=s.creator_id)) SELECT c.*,ranked.shared_users FROM creators c JOIN (
        SELECT s.creator_id,COUNT(DISTINCT s.user_id) AS shared_users FROM public_subscriptions s
        JOIN (SELECT DISTINCT other.user_id FROM public_subscriptions mine
              JOIN public_subscriptions other ON other.creator_id=mine.creator_id
              WHERE mine.user_id=? AND other.user_id<>?) peers ON peers.user_id=s.user_id
        WHERE NOT EXISTS(SELECT 1 FROM subscriptions own WHERE own.user_id=? AND own.creator_id=s.creator_id)
        GROUP BY s.creator_id) ranked ON ranked.creator_id=c.id
        ORDER BY ranked.shared_users DESC,c.name ASC LIMIT 24';
    public static function read(int $user): array {
        return array_merge(self::live($user),[
            'subscriptions'=>query('SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? ORDER BY c.name',[$user])->fetchAll(),
            'items'=>query('SELECT i.*,c.name AS creator,c.platform FROM items i JOIN creators c ON c.id=i.creator_id JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? ORDER BY i.published DESC LIMIT 300',[$user])->fetchAll(),
            'recommendations'=>query(self::RECOMMENDATIONS,[$user,$user,$user])->fetchAll()
        ]);
    }
    public static function live(int $user):array {
        Connections::expire($user);
        try{$youtubeStatus=(new YouTube(config()))->status();}
        catch(Throwable){$youtubeStatus=['error'=>'YouTube live-cache setup is unavailable. Ask the site administrator to check database permissions and storage.'];}
        return [
            'server_time'=>now_ms(),
            'youtube_status'=>$youtubeStatus,
            'youtube_feeds'=>query("SELECT c.id,c.synced_at,c.error FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND c.platform='YouTube'",[$user])->fetchAll(),
            // Live streams must not be truncated by the recent-upload feed limit.
            'live_items'=>query('SELECT i.*,c.name AS creator,c.platform FROM items i JOIN creators c ON c.id=i.creator_id JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND i.live=1 AND i.checked_at>? ORDER BY i.published DESC',[$user,now_ms()-300000])->fetchAll(),
        ];
    }
    public static function add(int $user,array $sources): array {
        if (count($sources)<1 || count($sources)>100) throw new InvalidArgumentException('Import 1–100 links at a time.');
        $parsed=[];$errors=[];
        foreach ($sources as $raw) {
            if (!is_string($raw) || strlen($raw)>2000) throw new InvalidArgumentException('Invalid source link.');
            try {$source=Feeds::source($raw);$parsed[$source['id']]=$source;}
            catch(InvalidArgumentException $e){$errors[]=['url'=>$raw,'error'=>$e->getMessage()];}
        }
        $pdo=db();$added=0;$pdo->beginTransaction();
        try {
            query('SELECT id FROM users WHERE id=? FOR UPDATE',[$user]);
            $existing=query('SELECT creator_id FROM subscriptions WHERE user_id=?',[$user])->fetchAll(PDO::FETCH_COLUMN);
            $new=array_diff(array_keys($parsed),$existing);
            if (count($existing)+count($new)>1000) throw new InvalidArgumentException('Your library can hold up to 1,000 subscriptions.');
            foreach ($parsed as $c) {
                query('INSERT INTO creators(id,name,platform,url,feed) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE id=creators.id',array_values($c));
                if (!in_array($c['id'],$existing,true)) {
                    query('INSERT INTO subscriptions(user_id,creator_id,created_at) VALUES(?,?,?)',[$user,$c['id'],now_ms()]);$added++;
                }
            }
            $pdo->commit();
        }catch(Throwable $e){$pdo->rollBack();throw $e;}
        return ['added'=>$added,'ids'=>array_keys($parsed),'errors'=>$errors];
    }
    public static function remove(int $user,string $id): void {
        query('DELETE FROM subscriptions WHERE user_id=? AND creator_id=?',[$user,$id]);
    }
    public static function syncTwitch(int $user,array $ids,?Closure $transport=null): array {
        if (!$ids || count($ids)>100 || array_filter($ids,fn($id)=>!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D',$id))) throw new InvalidArgumentException('Choose 1–100 Twitch subscriptions.');
        $ids=array_values(array_unique($ids));$marks=implode(',',array_fill(0,count($ids),'?'));
        $creators=query("SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND c.platform='Twitch' AND c.id IN ($marks)",[$user,...$ids])->fetchAll();
        if (count($creators)!==count($ids)) throw new InvalidArgumentException('Subscription not found.',404);
        $pending=[];$results=[];$now=now_ms();
        foreach ($creators as $c) {
            if (query('UPDATE creators SET synced_at=? WHERE id=? AND synced_at<?',[$now,$c['id'],$now-60000])->rowCount()) $pending[]=$c;
            else $results[]=['id'=>$c['id'],'cached'=>true,'error'=>$c['error']];
        }
        if (!$pending) return ['results'=>$results];
        try {
            $settings=config();
            if (!$settings['twitch_client_id'] || !$settings['twitch_client_secret']) throw new RuntimeException('Twitch live checks need API credentials configured by the site owner.');
            $request=$transport??[Feeds::class,'json'];
            $token=$request('https://id.twitch.tv/oauth2/token',[],['client_id'=>$settings['twitch_client_id'],'client_secret'=>$settings['twitch_client_secret'],'grant_type'=>'client_credentials']);
            if (empty($token['access_token'])) throw new RuntimeException('Twitch authorization did not return an access token.');
            $logins=array_map(fn($c)=>trim((string)parse_url($c['url'],PHP_URL_PATH),'/'),$pending);
            $url='https://api.twitch.tv/helix/streams?first=100&'.implode('&',array_map(fn($login)=>'user_login='.rawurlencode($login),$logins));
            $response=$request($url,['Client-ID: '.$settings['twitch_client_id'],'Authorization: Bearer '.$token['access_token']],null);
            if (!isset($response['data']) || !is_array($response['data'])) throw new RuntimeException('Twitch returned an invalid live-status response.');
            $streams=[];foreach ($response['data'] as $s) $streams[strtolower($s['user_login'])]=$s;
            $checked=now_ms();db()->beginTransaction();
            foreach ($pending as $c) {
                $id=$c['id'];$login=strtolower(trim((string)parse_url($c['url'],PHP_URL_PATH),'/'));
                query('UPDATE items SET live=0,checked_at=? WHERE creator_id=?',[$checked,$id]);
                $s=$streams[$login]??null;
                if ($s) query('INSERT INTO items(id,creator_id,title,url,image,media,published,live,checked_at) VALUES(?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE title=VALUES(title),image=VALUES(image),published=VALUES(published),live=1,checked_at=VALUES(checked_at)',[hash('sha256',$id.$c['url']),$id,mb_substr($s['title'],0,500),$c['url'],Feeds::https(str_replace(['{width}','{height}'],['640','360'],$s['thumbnail_url']))?:null,null,(strtotime($s['started_at'])?:time())*1000,$checked]);
                query('UPDATE creators SET name=?,error=NULL WHERE id=?',[$s?mb_substr($s['user_name'],0,500):$c['name'],$id]);
            }
            db()->commit();
            foreach ($pending as $c) $results[]=['id'=>$c['id'],'updated'=>true,'error'=>null];
        }catch(Throwable $e){
            if (db()->inTransaction()) db()->rollBack();
            $error=$e instanceof PDOException?'Unable to save refreshed content.':mb_substr($e->getMessage(),0,500);
            foreach ($pending as $c) {query('UPDATE creators SET error=? WHERE id=?',[$error,$c['id']]);$results[]=['id'=>$c['id'],'error'=>$error];}
        }
        return ['results'=>$results];
    }
    public static function sync(int $user,string $id): array {
        $c=query('SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND c.id=?',[$user,$id])->fetch();
        if (!$c) throw new InvalidArgumentException('Subscription not found.',404);
        if ($c['platform']==='YouTube') return (new YouTube(config()))->discover($user,$id);
        $now=now_ms();
        $lease=query('UPDATE creators SET synced_at=? WHERE id=? AND synced_at<?',[$now,$id,$now-60000]);
        if (!$lease->rowCount()) return ['id'=>$id,'cached'=>true,'error'=>$c['error']];
        try {
            $rows=[];$warning=null;$name=$c['name'];$settings=config();
            if ($c['platform']==='Twitch') {
                if (!$settings['twitch_client_id'] || !$settings['twitch_client_secret']) throw new RuntimeException('Twitch live checks need API credentials configured by the site owner.');
                $token=Feeds::json('https://id.twitch.tv/oauth2/token',[],['client_id'=>$settings['twitch_client_id'],'client_secret'=>$settings['twitch_client_secret'],'grant_type'=>'client_credentials']);
                $login=ltrim(parse_url($c['url'],PHP_URL_PATH),'/');
                $streams=Feeds::json('https://api.twitch.tv/helix/streams?user_login='.rawurlencode($login),['Client-ID: '.$settings['twitch_client_id'],'Authorization: Bearer '.$token['access_token']]);
                if (!empty($streams['data'][0])) {
                    $s=$streams['data'][0];$name=mb_substr($s['user_name'],0,500);
                    $rows[]=['title'=>mb_substr($s['title'],0,500),'url'=>$c['url'],'image'=>Feeds::https(str_replace(['{width}','{height}'],['640','360'],$s['thumbnail_url'])),'media'=>'','published'=>(strtotime($s['started_at'])?:time())*1000,'live'=>1];
                }
            }else {
                $parsed=Feeds::parse(Feeds::fetch($c['feed']),$c['name']);$rows=$parsed['rows'];$name=$parsed['name'];$live=[];
                foreach ($rows as &$row) $row['live']=in_array($row['video'],$live,true)?1:0;
                unset($row);
            }
            db()->beginTransaction();
            $now=now_ms();
            query('UPDATE items SET live=0,checked_at=? WHERE creator_id=?',[$now,$id]);
            foreach ($rows as $row) query('INSERT INTO items(id,creator_id,title,url,image,media,published,live,checked_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),image=VALUES(image),media=VALUES(media),published=VALUES(published),live=VALUES(live),checked_at=VALUES(checked_at)',[hash('sha256',$id.$row['url']),$id,$row['title'],$row['url'],$row['image']?:null,$row['media']?:null,$row['published'],$row['live'],$now]);
            query('UPDATE creators SET name=?,error=? WHERE id=?',[$name,$warning,$id]);
            $old=query('SELECT id FROM items WHERE creator_id=? ORDER BY live DESC,published DESC,id DESC LIMIT 1000 OFFSET 60',[$id])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($old as $oldId) query('DELETE FROM items WHERE id=? AND creator_id=?',[$oldId,$id]);
            db()->commit();return ['id'=>$id,'updated'=>true,'error'=>$warning];
        }catch(Throwable $e){
            if (db()->inTransaction()) db()->rollBack();
            $message=$e instanceof PDOException?'Unable to save refreshed content.':mb_substr($e->getMessage(),0,500);
            query('UPDATE creators SET error=? WHERE id=?',[$message,$id]);
            return ['id'=>$id,'error'=>$message];
        }
    }
}

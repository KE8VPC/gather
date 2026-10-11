<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/Feeds.php';

final class YouTube {
    public const FEED_INTERVAL=3600000;
    public const DAILY_BUDGET=8000;
    private Closure $request;
    private Closure $fetch;
    private Closure $clock;
    public function __construct(private array $settings,?Closure $request=null,?Closure $fetch=null,?Closure $clock=null) {
        $this->request=$request??Closure::fromCallable([Feeds::class,'json']);
        $this->fetch=$fetch??Closure::fromCallable([Feeds::class,'fetch']);
        $this->clock=$clock??Closure::fromCallable('now_ms');
    }
    public static function schema():void {
        static $ready=false;if($ready)return;
        // Additive, idempotent upgrade, always outside content transactions.
        query("CREATE TABLE IF NOT EXISTS youtube_video_checks (
            item_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            video_id VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            state VARCHAR(12) NOT NULL DEFAULT 'unknown',
            next_check_at BIGINT NOT NULL DEFAULT 0,
            KEY idx_youtube_due(next_check_at),
            CONSTRAINT fk_youtube_check_item FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        query("CREATE TABLE IF NOT EXISTS youtube_api_budget (
            id TINYINT PRIMARY KEY, quota_day CHAR(10) NOT NULL,
            used_requests INT UNSIGNED NOT NULL DEFAULT 0,
            blocked_until BIGINT NOT NULL DEFAULT 0,
            failures INT UNSIGNED NOT NULL DEFAULT 0,
            lease_until BIGINT NOT NULL DEFAULT 0,
            lease_token CHAR(32) NOT NULL DEFAULT '',
            last_error VARCHAR(500) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        query("INSERT IGNORE INTO youtube_api_budget(id,quota_day) VALUES(1,'')");
        $ready=true;
    }
    private function day(int $now):string {
        return (new DateTimeImmutable('@'.intdiv($now,1000)))->setTimezone(new DateTimeZone('America/Los_Angeles'))->format('Y-m-d');
    }
    private function resetAt(int $now):int {
        return (new DateTimeImmutable('@'.intdiv($now,1000)))->setTimezone(new DateTimeZone('America/Los_Angeles'))->modify('tomorrow')->setTime(0,0)->getTimestamp()*1000;
    }
    public function status():array {
        self::schema();$now=($this->clock)();$row=query('SELECT * FROM youtube_api_budget WHERE id=1')->fetch();
        $used=$row['quota_day']===$this->day($now)?(int)$row['used_requests']:0;
        $error=empty($this->settings['youtube_api_key'])?'YouTube live checks need an API key in Administration.':null;
        if((int)$row['blocked_until']>$now)$error=$row['last_error'];
        elseif($used>=self::DAILY_BUDGET)$error='YouTube live checks have reached Gather’s daily budget and will resume after midnight Pacific time.';
        return ['used'=>$used,'limit'=>self::DAILY_BUDGET,'reset_at'=>$this->resetAt($now),'paused_until'=>max((int)$row['blocked_until'],$used>=self::DAILY_BUDGET?$this->resetAt($now):0),'error'=>$error];
    }
    public function discover(int $user,string $id):array {
        self::schema();
        $c=query("SELECT c.* FROM creators c JOIN subscriptions s ON s.creator_id=c.id WHERE s.user_id=? AND c.id=? AND c.platform='YouTube'",[$user,$id])->fetch();
        if(!$c)throw new InvalidArgumentException('Subscription not found.',404);
        $now=($this->clock)();
        if(!query('UPDATE creators SET synced_at=? WHERE id=? AND synced_at<?',[$now,$id,$now-self::FEED_INTERVAL])->rowCount())return ['id'=>$id,'cached'=>true,'error'=>$c['error']];
        try {
            $parsed=Feeds::parse(($this->fetch)($c['feed']),$c['name']);
            db()->beginTransaction();
            foreach($parsed['rows'] as $row) {
                // Feed discovery never marks a stream offline or renews live verification.
                query('INSERT INTO items(id,creator_id,title,url,image,media,published,live,checked_at) VALUES(?,?,?,?,?,?,?,0,0) ON DUPLICATE KEY UPDATE title=VALUES(title),image=VALUES(image),media=VALUES(media),published=VALUES(published)',[hash('sha256',$id.$row['url']),$id,$row['title'],$row['url'],$row['image']?:null,$row['media']?:null,$row['published']]);
            }
            query('UPDATE creators SET name=?,error=NULL WHERE id=?',[$parsed['name'],$id]);
            $old=query("SELECT i.id FROM items i LEFT JOIN youtube_video_checks y ON y.item_id=i.id WHERE i.creator_id=? ORDER BY i.live DESC,(y.state='upcoming') DESC,i.published DESC,i.id DESC LIMIT 1000 OFFSET 60",[$id])->fetchAll(PDO::FETCH_COLUMN);
            foreach($old as $oldId)query('DELETE FROM items WHERE id=? AND creator_id=?',[$oldId,$id]);
            db()->commit();return ['id'=>$id,'updated'=>true,'error'=>null];
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            $error=$e instanceof PDOException?'Unable to save refreshed content.':mb_substr($e->getMessage(),0,500);
            query('UPDATE creators SET error=? WHERE id=?',[$error,$id]);return ['id'=>$id,'error'=>$error];
        }
    }
    private function seed(int $user):void {
        // Existing installations get a one-time check of cached videos without downloading feeds.
        query("INSERT IGNORE INTO youtube_video_checks(item_id,video_id,state)
            SELECT i.id,RIGHT(i.url,11),IF(i.live=1,'live','unknown') FROM items i
            JOIN creators c ON c.id=i.creator_id JOIN subscriptions s ON s.creator_id=c.id
            LEFT JOIN youtube_video_checks existing ON existing.item_id=i.id
            WHERE s.user_id=? AND existing.item_id IS NULL AND c.platform='YouTube' AND i.url REGEXP '^https://www[.]youtube[.]com/watch[?]v=[A-Za-z0-9_-]{11}$'",[$user]);
    }
    private function pending(int $user,int $now):int {
        return (int)query('SELECT COUNT(*) FROM youtube_video_checks y JOIN items i ON i.id=y.item_id JOIN subscriptions s ON s.creator_id=i.creator_id WHERE s.user_id=? AND y.next_check_at<=?',[$user,$now])->fetchColumn();
    }
    public function check(int $user):array {
        self::schema();$now=($this->clock)();$this->seed($user);
        if(empty($this->settings['youtube_api_key']))return ['checked'=>0,'pending'=>0,'error'=>$this->status()['error']];
        $token=bin2hex(random_bytes(16));$rows=[];
        db()->beginTransaction();
        try {
            $budget=query('SELECT * FROM youtube_api_budget WHERE id=1 FOR UPDATE')->fetch();
            if($budget['quota_day']!==$this->day($now)) {
                query('UPDATE youtube_api_budget SET quota_day=?,used_requests=0 WHERE id=1',[$this->day($now)]);$budget['used_requests']=0;
            }
            if((int)$budget['lease_until']>$now || (int)$budget['blocked_until']>$now || (int)$budget['used_requests']>=self::DAILY_BUDGET){
                db()->commit();return ['checked'=>0,'pending'=>0,'deferred'=>true,'error'=>$this->status()['error']];
            }
            $rows=query("SELECT y.*,i.creator_id FROM youtube_video_checks y JOIN items i ON i.id=y.item_id JOIN subscriptions s ON s.creator_id=i.creator_id
                WHERE s.user_id=? AND y.next_check_at<=? ORDER BY CASE y.state WHEN 'live' THEN 0 WHEN 'upcoming' THEN 1 WHEN 'unknown' THEN 2 ELSE 3 END,y.next_check_at,y.item_id LIMIT 50 FOR UPDATE",[$user,$now])->fetchAll();
            if(!$rows){db()->commit();return ['checked'=>0,'pending'=>0,'error'=>null];}
            foreach($rows as $row)query('UPDATE youtube_video_checks SET next_check_at=? WHERE item_id=?',[$now+120000,$row['item_id']]);
            // Reserve before sending: failed requests count too, and concurrent users share the cap.
            query('UPDATE youtube_api_budget SET used_requests=used_requests+1,lease_until=?,lease_token=? WHERE id=1',[$now+60000,$token]);
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        try {
            $response=($this->request)('https://www.googleapis.com/youtube/v3/videos?'.http_build_query([
                'part'=>'snippet,liveStreamingDetails',
                'fields'=>'items(id,snippet/liveBroadcastContent,liveStreamingDetails(actualEndTime,scheduledStartTime))',
                'id'=>implode(',',array_unique(array_column($rows,'video_id'))),'key'=>$this->settings['youtube_api_key']]),[],null);
            if(!isset($response['items']) || !is_array($response['items']))throw new RuntimeException('Invalid YouTube response.');
            $videos=[];foreach($response['items'] as $v)if(is_array($v)&&is_string($v['id']??null))$videos[$v['id']]=$v;
            $now=($this->clock)();db()->beginTransaction();
            // Ignore a response if its lease was reclaimed after a stalled worker.
            $owner=query('SELECT lease_token FROM youtube_api_budget WHERE id=1 FOR UPDATE')->fetchColumn();
            if($owner!==$token){db()->commit();return ['checked'=>0,'pending'=>0,'deferred'=>true,'error'=>null];}
            foreach($rows as $row) {
                $v=$videos[$row['video_id']]??[];$kind=$v['snippet']['liveBroadcastContent']??'none';
                $ended=!empty($v['liveStreamingDetails']['actualEndTime']);
                $state=$ended?'ended':($kind==='live'?'live':($kind==='upcoming'?'upcoming':'inactive'));
                $interval=match($state){'live'=>120000,'upcoming'=>300000,'ended'=>604800000,default=>86400000};
                query('UPDATE youtube_video_checks SET state=?,next_check_at=? WHERE item_id=?',[$state,$now+$interval,$row['item_id']]);
                query('UPDATE items SET live=?,checked_at=? WHERE id=?',[$state==='live'?1:0,$now,$row['item_id']]);
            }
            query("UPDATE youtube_api_budget SET failures=0,blocked_until=0,last_error=NULL,lease_until=0,lease_token='' WHERE id=1");
            db()->commit();return ['checked'=>count($rows),'pending'=>$this->pending($user,$now),'error'=>null];
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            $now=($this->clock)();db()->beginTransaction();
            try {
                $budget=query('SELECT * FROM youtube_api_budget WHERE id=1 FOR UPDATE')->fetch();
                if($budget['lease_token']!==$token){db()->commit();return ['checked'=>0,'pending'=>0,'deferred'=>true,'error'=>null];}
                $quota=$e instanceof PlatformRequestFailure && in_array($e->reason,['quotaExceeded','dailyLimitExceeded'],true);
                $delay=min(3600000,900000*(2**min(2,(int)$budget['failures'])));
                $until=$quota?$this->resetAt($now):$now+$delay;
                $error=$quota?'YouTube reports its quota is exhausted. Live checks are paused until midnight Pacific time.':'YouTube live checks failed. Checks are paused temporarily; verify the API key, restrictions and quota in Administration.';
                query("UPDATE youtube_api_budget SET failures=failures+1,blocked_until=?,last_error=?,lease_until=0,lease_token='' WHERE id=1",[$until,$error]);
                foreach($rows as $row)query('UPDATE youtube_video_checks SET next_check_at=? WHERE item_id=?',[$until,$row['item_id']]);
                db()->commit();return ['checked'=>0,'pending'=>0,'error'=>$error];
            }catch(Throwable $failure){if(db()->inTransaction())db()->rollBack();throw $failure;}
        }
    }
}

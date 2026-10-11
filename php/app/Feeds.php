<?php
declare(strict_types=1);
final class PlatformRequestFailure extends RuntimeException {
    public function __construct(public readonly int $status,public readonly string $reason='') {
        parent::__construct('Platform connection failed. Check the configured API credentials and quota.');
    }
}
final class Feeds {
    public const HOSTS = ['feeds.simplecast.com','feeds.megaphone.fm','feeds.transistor.fm','anchor.fm','feeds.buzzsprout.com','rss.art19.com','feeds.acast.com','feeds.npr.org','feeds.twit.tv','feeds.feedburner.com','feeds.libsyn.com','rss.libsyn.com','feeds.captivate.fm','feeds.redcircle.com','feeds.soundcloud.com','feeds.audioboom.com','audioboom.com','www.omnycontent.com','omnycontent.com','podcastfeeds.nbcnews.com','feeds.fireside.fm','feeds.podbean.com','rss.wbur.org','www.pbs.org','feed.podbean.com','feeds.99percentinvisible.org'];
    public static function https(string $url): string {
        $p = parse_url($url);
        return $p && ($p['scheme']??'')==='https' && !isset($p['user'],$p['pass']) && !isset($p['user']) && !isset($p['pass']) && isset($p['host']) && !preg_match('/[\x00-\x20]/', $url) ? $url : '';
    }
    public static function allowed(string $url): bool {
        if (!self::https($url)) return false;
        $p=parse_url($url);
        if (isset($p['port']) && $p['port']!==443) return false;
        if (in_array(strtolower($p['host']),self::HOSTS,true)) return true;
        parse_str($p['query']??'', $q);
        return strtolower($p['host'])==='www.youtube.com' && ($p['path']??'')==='/feeds/videos.xml' && is_string($q['channel_id']??null) && (bool)preg_match('/^UC[\w-]{22}$/D',$q['channel_id']);
    }
    public static function source(string $raw): array {
        $raw=trim($raw);
        if (preg_match('/^UC[\w-]{22}$/D',$raw)) $raw='https://www.youtube.com/channel/'.$raw;
        if (!self::https($raw)) throw new InvalidArgumentException('Use a public HTTPS creator or feed URL.');
        $p=parse_url($raw); $host=strtolower($p['host']);
        if (isset($p['port']) && $p['port']!==443) throw new InvalidArgumentException('Custom ports are not supported.');
        $platform='Podcasts'; $name=$host; $url=$raw; $feed=$raw;
        if (in_array($host,['youtube.com','www.youtube.com'],true)) {
            parse_str($p['query']??'', $q);
            preg_match('~^/channel/(UC[\w-]{22})/?$~D',$p['path']??'', $m);
            $id=$m[1]??(($p['path']??'')==='/feeds/videos.xml'?($q['channel_id']??''):'');
            if (!is_string($id) || !preg_match('/^UC[\w-]{22}$/D',$id)) throw new InvalidArgumentException('Use a YouTube /channel/ URL, channel ID, or subscriptions.csv export.');
            $platform='YouTube'; $name='YouTube channel '.$id;
            $url='https://www.youtube.com/channel/'.$id;
            $feed='https://www.youtube.com/feeds/videos.xml?channel_id='.$id;
        } elseif (in_array($host,['twitch.tv','www.twitch.tv'],true)) {
            if (!preg_match('~^/([a-zA-Z0-9_]{3,25})/?$~D',$p['path']??'', $m) || in_array(strtolower($m[1]),['directory','downloads','settings','subscriptions','videos'],true)) throw new InvalidArgumentException('Use a Twitch creator channel URL.');
            $platform='Twitch'; $name=strtolower($m[1]); $url='https://www.twitch.tv/'.$name; $feed=$url;
        } elseif (!self::allowed($feed)) throw new InvalidArgumentException('Unsupported podcast host. See the import help for supported public feed hosts.');
        return ['id'=>hash('sha256',$url),'name'=>$name,'platform'=>$platform,'url'=>$url,'feed'=>$feed];
    }
    private static function resolved(string $url): array {
        $host=parse_url($url,PHP_URL_HOST);
        $records=dns_get_record($host,DNS_A);
        $ips=[];
        foreach ($records ?: [] as $record) if (isset($record['ip'])) {
            if (!filter_var($record['ip'],FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('Feed resolved to a non-public address.');
            $ips[]=$record['ip'];
        }
        if (!$ips) throw new RuntimeException('Unable to resolve the feed host.');
        return [$host.':443:'.$ips[0]];
    }
    public static function trim(string $xml): ?string {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new RuntimeException('Document entities are not supported.');
        preg_match_all('~<!\[CDATA\[[\s\S]*?\]\]>|<!--[\s\S]*?-->|</(item|entry)\s*>~',$xml,$matches,PREG_OFFSET_CAPTURE);
        $count=0;
        foreach ($matches[0] as $i=>$match) {
            $kind=$matches[1][$i][0]??'';
            if ($kind && ++$count===30) {
                $candidate=substr($xml,0,$match[1]+strlen($match[0])).($kind==='item'?'</channel></rss>':'</feed>');
                libxml_use_internal_errors(true);
                $doc=simplexml_load_string($candidate,SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOCDATA);
                libxml_clear_errors();
                return $doc ? $candidate : null;
            }
        }
        return null;
    }
    public static function fetch(string $url): string {
        for ($redirect=0;$redirect<4;$redirect++) {
            if (!self::allowed($url)) throw new RuntimeException('Unsupported feed or redirect host.');
            $data=''; $location=''; $complete=false; $failure='';
            $curl=curl_init($url);
            curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>self::resolved($url),CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Gather/1.0 RSS reader',CURLOPT_HEADERFUNCTION=>static function($c,$line) use (&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);},CURLOPT_WRITEFUNCTION=>static function($c,$chunk) use (&$data,&$complete,&$failure){
                $data.=$chunk;
                try {$recent=self::trim($data);}catch(Throwable $e){$failure=$e->getMessage();return 0;}
                if ($recent!==null){$data=$recent;$complete=true;return 0;}
                if (strlen($data)>2000000){$failure='Recent feed content is larger than 2 MB.';return 0;}
                return strlen($chunk);
            }]);
            $ok=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
            if ($failure) throw new RuntimeException($failure);
            if ($status>=300 && $status<400 && $location) {
                if (str_starts_with($location,'//')) $url='https:'.$location;
                elseif (str_starts_with($location,'/')) $url='https://'.parse_url($url,PHP_URL_HOST).$location;
                elseif (str_starts_with($location,'https://')) $url=$location;
                else throw new RuntimeException('Unsupported feed redirect.');
                continue;
            }
            if (($ok===false && !$complete) || $status!==200) throw new RuntimeException('Unable to read feed. Check the link and try again.');
            return $data;
        }
        throw new RuntimeException('Feed redirected too many times.');
    }
    private static function first(SimpleXMLElement $node,string $path): string {
        $found=$node->xpath($path);
        return isset($found[0]) ? (string)$found[0] : '';
    }
    private static function clean(string $text): string {return mb_substr(html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_XML1,'UTF-8'),0,500);}
    public static function parse(string $xml,string $fallback): array {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new RuntimeException('Document entities are not supported.');
        libxml_use_internal_errors(true);
        $root=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOCDATA);
        libxml_clear_errors();
        if (!$root) throw new RuntimeException('This link is not valid XML.');
        $channels=$root->xpath('/*[local-name()="feed"] | /*[local-name()="rss"]/*[local-name()="channel"]');
        if (!$channels) throw new RuntimeException('Use an RSS or Atom feed.');
        $channel=$channels[0]; $name=self::clean(self::first($channel,'./*[local-name()="title"]'))?:$fallback;
        $cover=self::first($channel,'./*[local-name()="image"]/@href') ?: self::first($channel,'./*[local-name()="image"]/*[local-name()="url"]');
        $entries=$channel->xpath('./*[local-name()="item" or local-name()="entry"]'); $rows=[];
        foreach (array_slice($entries ?: [],0,30) as $entry) {
            $video=self::first($entry,'./*[local-name()="videoId"]');
            $video=preg_match('/^[\w-]{11}$/D',$video)?$video:'';
            $url=self::first($entry,'./*[local-name()="link"][@rel="alternate"]/@href') ?: self::first($entry,'./*[local-name()="link"]/@href') ?: self::first($entry,'./*[local-name()="link"]');
            $media=self::first($entry,'./*[local-name()="enclosure"][starts-with(@type,"audio/")]/@url') ?: self::first($entry,'./*[local-name()="link"][@rel="enclosure"][starts-with(@type,"audio/")]/@href');
            $image=self::first($entry,'./*[local-name()="image"]/@href')?:$cover;
            if ($video){$url='https://www.youtube.com/watch?v='.$video;$image='https://i.ytimg.com/vi/'.$video.'/hqdefault.jpg';}
            $media=self::https($media);$url=self::https($url)?:$media;
            if (!$url) continue;
            $date=self::first($entry,'./*[local-name()="published" or local-name()="pubDate" or local-name()="updated"]');
            $rows[]=['title'=>self::clean(self::first($entry,'./*[local-name()="title"]'))?:'Untitled episode','url'=>$url,'image'=>self::https($image),'media'=>$media,'published'=>($date ? (strtotime($date)?:0) : 0)*1000,'video'=>$video];
        }
        return ['name'=>$name,'rows'=>$rows];
    }
    public static function json(string $url,array $headers=[],?array $post=null): array {
        $allowed=['id.twitch.tv','api.twitch.tv','www.googleapis.com'];
        if (!self::https($url) || !in_array(parse_url($url,PHP_URL_HOST),$allowed,true)) throw new RuntimeException('Invalid platform endpoint.');
        $curl=curl_init($url); $data='';
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers,CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$data){$data.=$chunk;return strlen($data)>2000000?0:strlen($chunk);}]);
        if ($post!==null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if ($ok===false || $status!==200) {
            $failure=json_decode($data,true);$reason=$failure['error']['errors'][0]['reason']??'';
            throw new PlatformRequestFailure((int)$status,is_string($reason)?substr($reason,0,100):'');
        }
        return json_decode($data,true,512,JSON_THROW_ON_ERROR);
    }
}

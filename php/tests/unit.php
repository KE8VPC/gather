<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Feeds.php';
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
check(!Feeds::allowed('https://127.0.0.1/rss'),'Private IP rejected');
check(!Feeds::allowed('https://feeds.simplecast.com.evil.test/a'),'Host suffix rejected');
check(!Feeds::allowed('https://user:password@feeds.simplecast.com/a'),'Credentials rejected');
check(!Feeds::allowed('http://feeds.simplecast.com/a'),'Plain HTTP rejected');
check(Feeds::source('https://twitch.tv/TestCreator/')['id']===Feeds::source('https://www.twitch.tv/testcreator')['id'],'Canonical duplicates');
$xml='<rss><channel><title>Podcast</title>';
for($i=0;$i<80;$i++)$xml.='<item><title>Episode '.$i.'</title><description><![CDATA[Keep </item> inside this text.]]></description><link>https://example.com/'.$i.'</link></item>';
$xml.='</channel></rss>';
$trimmed=Feeds::trim($xml);check($trimmed!==null,'Archive truncated');$parsed=Feeds::parse($trimmed,'Fallback');check(count($parsed['rows'])===30,'Exactly 30 recent entries');
$bad=Feeds::parse('<rss><channel><title>Test</title><item><title>One</title><link>javascript:alert(1)</link><enclosure url="https://example.com/audio.mp3" type="audio/mpeg"/></item></channel></rss>','Test');
check($bad['rows'][0]['url']==='https://example.com/audio.mp3','Unsafe link rejected');
try{Feeds::parse('<!DOCTYPE rss><rss/>','Test');throw new LogicException('DTD was accepted');}catch(RuntimeException $e){check(!($e instanceof LogicException),'DTD rejection');}
$hash=password_digest('testing-a-long-secret');check(password_verify('testing-a-long-secret',$hash),'Password verifies');check(!password_verify('wrong',$hash),'Wrong password rejected');
echo "PASS: URL policy, canonicalization, bounded XML, CDATA, unsafe links, DTD rejection and password hashing.\n";

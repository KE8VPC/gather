<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Library.php';
header('Content-Type: application/json; charset=utf-8');
try {
    boot();$u=user();
    if (!$u) throw new InvalidArgumentException('Sign in to use your library.',401);
    $method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'library';$data=[];
    if ($method!=='GET') {
        csrf();
        $raw=file_get_contents('php://input',false,null,0,100001);
        if (strlen($raw)>100000) throw new InvalidArgumentException('Request exceeds 100 KB.');
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new InvalidArgumentException('Invalid request.');
    }
    $uid=(int)$u['id'];session_write_close();
    if ($action==='library' && $method==='GET') $result=Library::read($uid);
    elseif ($action==='live' && $method==='GET') $result=Library::live($uid);
    elseif ($action==='library' && $method==='POST' && is_array($data['sources']??null)) $result=Library::add($uid,$data['sources']);
    elseif ($action==='library' && $method==='DELETE' && is_string($data['id']??null)) {Library::remove($uid,$data['id']);$result=['ok'=>true];}
    elseif ($action==='sync' && $method==='POST' && is_string($data['id']??null)) $result=Library::sync($uid,$data['id']);
    elseif ($action==='sync-twitch' && $method==='POST' && is_array($data['ids']??null)) $result=Library::syncTwitch($uid,$data['ids']);
    elseif ($action==='youtube-live' && $method==='POST') $result=(new YouTube(config()))->check($uid);
    else throw new InvalidArgumentException('Unsupported request.',400);
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(InvalidArgumentException $e){$code=$e->getCode();http_response_code(in_array($code,[400,401,403,404,429],true)?$code:400);echo json_encode(['error'=>$e->getMessage()]);}
catch(JsonException){http_response_code(400);echo json_encode(['error'=>'Invalid JSON request.']);}
catch(Throwable $e){error_log('Gather API: '.$e->getMessage());http_response_code(503);echo json_encode(['error'=>'The service is temporarily unavailable. Please try again later.']);}

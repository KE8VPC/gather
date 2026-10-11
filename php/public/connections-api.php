<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Connections.php';
header('Content-Type: application/json; charset=utf-8');
try {
    boot(); $u=user();
    if (!$u) throw new InvalidArgumentException('Sign in to manage linked accounts.',401);
    if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('Use POST for account actions.',405);
    csrf(); $raw=file_get_contents('php://input',false,null,0,5001);
    if (strlen($raw)>5000) throw new InvalidArgumentException('Request is too large.',400);
    $data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    $p=Connections::provider($data['provider']??null); $uid=(int)$u['id']; $service=new Connections(config());
    $result=$service->locked($uid,function()use($service,$uid,$p,$data){
        $action=$data['action']??'';
        if ($action==='disconnect') {
            $revoked=$service->disconnect($uid,$p);
            unset($_SESSION['linked_sync'][$p],$_SESSION['oauth_pending'][$p]);
            $message=$revoked?'Disconnected. Imported subscriptions were removed from Gather.':'Disconnected locally. Also remove Gather from your provider’s connected-app settings because its revocation service could not be reached.';
            $_SESSION['connection_notice']=$message;
            return ['done'=>true,'revoked'=>$revoked,'message'=>$message];
        }
        if (!$service->ready($p)) throw new RuntimeException('The site owner needs to finish this connection’s setup.');
        try {
            if ($action==='start') { throttle('linked-sync:'.$uid,20); return $service->beginSync($uid,$p,$_SESSION); }
            if ($action==='step' && is_string($data['job']??null)) return $service->step($uid,$p,$data['job'],$_SESSION);
            throw new InvalidArgumentException('Unsupported account action.',400);
        } catch (ProviderFailure $e) {
            unset($_SESSION['linked_sync'][$p]);
            if ($e->revoked()) $service->forget($uid,$p);
            throw $e;
        }
    });
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) {
    http_response_code(in_array($e->getCode(),[400,401,403,404,405,409,429],true)?$e->getCode():400); echo json_encode(['error'=>$e->getMessage()]);
} catch (JsonException) { http_response_code(400); echo json_encode(['error'=>'Invalid request.']); }
catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>$e instanceof PDOException?'Account linking is temporarily unavailable. Contact the site owner.':$e->getMessage()]); }

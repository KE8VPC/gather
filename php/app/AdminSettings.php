<?php
declare(strict_types=1);

final class AdminSettings {
    public const FIELDS=['youtube_client_id','youtube_client_secret','youtube_api_key','twitch_client_id','twitch_client_secret'];
    public function __construct(private string $directory,private string $encodedKey) {}
    private function key():string {
        $key=base64_decode($this->encodedKey,true);
        if($key===false || strlen($key)!==32)throw new RuntimeException('The site encryption key is missing. Contact the hosting administrator.');
        return $key;
    }
    public function read():array {
        $path=$this->directory.'/admin-settings.json';
        if(!is_file($path))return ['admin_user_id'=>0,'values'=>[]];
        $envelope=json_decode((string)file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
        $blob=base64_decode($envelope['sealed']??'',true);
        if($blob===false || strlen($blob)<29)throw new RuntimeException('Admin settings could not be read. Restore the private settings backup.');
        $plain=openssl_decrypt(substr($blob,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($blob,0,12),substr($blob,12,16),'Gather admin settings v1');
        if($plain===false)throw new RuntimeException('Admin settings could not be unlocked. Check the original site encryption key.');
        $state=json_decode($plain,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($state) || !is_int($state['admin_user_id']??null) || $state['admin_user_id']<1 || !is_array($state['values']??null))throw new RuntimeException('Invalid admin settings.');
        foreach($state['values'] as $field=>$value)if(!in_array($field,self::FIELDS,true) || !is_string($value))throw new RuntimeException('Invalid admin settings.');
        return $state;
    }
    private function change(Closure $update):void {
        $lock=@fopen($this->directory.'/admin-settings.lock','c');
        if(!$lock)throw new RuntimeException('The config folder must be writable to save admin settings.');
        $temporary=null;
        try {
            if(!flock($lock,LOCK_EX))throw new RuntimeException('Settings are busy. Try again.');
            $state=$update($this->read());
            $iv=random_bytes(12);$tag='';
            $cipher=openssl_encrypt(json_encode($state,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag,'Gather admin settings v1');
            if($cipher===false)throw new RuntimeException('Could not encrypt settings.');
            $temporary=$this->directory.'/.admin-'.bin2hex(random_bytes(12)).'.tmp';
            $handle=@fopen($temporary,'x');
            if(!$handle)throw new RuntimeException('The config folder must be writable to save admin settings.');
            @chmod($temporary,0600);
            $json=json_encode(['sealed'=>base64_encode($iv.$tag.$cipher)],JSON_THROW_ON_ERROR);
            try {$ok=fwrite($handle,$json)===strlen($json) && fflush($handle);if($ok && function_exists('fsync'))$ok=fsync($handle);}finally{fclose($handle);}
            if(!$ok || !@rename($temporary,$this->directory.'/admin-settings.json'))throw new RuntimeException('Settings were not saved. Check disk space and config folder permissions.');
            $temporary=null;
        } finally {if($temporary!==null)@unlink($temporary);flock($lock,LOCK_UN);fclose($lock);}
    }
    public function claim(int $uid,string $providedPassword,string $databasePassword):void {
        if($uid<1 || $databasePassword==='' || !hash_equals($databasePassword,$providedPassword))throw new InvalidArgumentException('The hosting database password is incorrect.',403);
        $this->change(static function(array $state)use($uid):array {
            if($state['admin_user_id']!==0)throw new InvalidArgumentException('Administration has already been activated.',403);
            $state['admin_user_id']=$uid;return $state;
        });
    }
    public function save(int $uid,array $input):void {
        $this->change(static function(array $state)use($uid,$input):array {
            if($uid<1 || $state['admin_user_id']!==$uid)throw new InvalidArgumentException('Administrator access required.',403);
            foreach(self::FIELDS as $field) {
                $value=$input[$field]??'';
                if(!is_string($value) || strlen($value)>4096 || preg_match('/[\x00-\x1f\x7f]/',$value))throw new InvalidArgumentException('Check the credential fields. Each must be a single line.');
                $value=trim($value);
                if(($input['clear_'.$field]??'')==='1')$state['values'][$field]='';
                elseif($value!=='')$state['values'][$field]=$value;
            }
            return $state;
        });
    }
}

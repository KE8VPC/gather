<?php
declare(strict_types=1);
function config(): array {
    static $config;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config/local.php';
        if (!is_file($path)) throw new RuntimeException('Site setup is incomplete. Configure config/local.php.');
        $base = require $path;
        if (!is_array($base)) throw new RuntimeException('Invalid site configuration.');
        require_once __DIR__.'/AdminSettings.php';
        $settings=new AdminSettings(dirname(__DIR__).'/config',(string)($base['oauth_encryption_key']??''));
        $state=$settings->read();
        $config=array_replace($base,$state['values']);
        $config['admin_user_id']=$state['admin_user_id'];
    }
    return $config;
}
function db(): PDO {
    static $db;
    if (!$db) {
        $c = config()['db'];
        foreach (['host','name'] as $key) if (preg_match('/[;\x00-\x1f]/', $c[$key])) throw new RuntimeException('Invalid database configuration.');
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        if (!empty($c['ssl_ca'])) $options[PDO::MYSQL_ATTR_SSL_CA] = $c['ssl_ca'];
        $db = new PDO('mysql:host='.$c['host'].';port='.(int)$c['port'].';dbname='.$c['name'].';charset=utf8mb4', $c['user'], $c['password'], $options);
    }
    return $db;
}
function query(string $sql, array $values = []): PDOStatement {
    $statement = db()->prepare($sql);
    $statement->execute($values);
    return $statement;
}
function now_ms(): int { return (int) floor(microtime(true)*1000); }
function escape(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function session_cookie_path(?string $scriptName = null): string {
    // Scope cookies to the executing public entrypoints, including a renamed
    // or nested installation. Do not depend on a fixed /gather/ folder name.
    $scriptName ??= $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $directory = str_replace('\\', '/', dirname($scriptName));
    if (!str_starts_with($directory, '/') || preg_match('/[;\x00-\x20]/', $directory)) return '/';
    return rtrim($directory, '/') . '/';
}
function session_cookie_name(?string $path = null): string {
    return 'gather_' . substr(hash('sha256', $path ?? session_cookie_path()), 0, 12);
}
function boot(): void {
    $c = config();
    $origin = parse_url($c['app_url']);
    if (!$origin || !isset($origin['host']) || !in_array($origin['scheme'] ?? '', ['https','http'], true)) throw new RuntimeException('Invalid app_url.');
    $expected = strtolower($origin['host'].(isset($origin['port']) ? ':'.$origin['port'] : ''));
    if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== $expected) { http_response_code(421); exit('This host is not configured for Gather.'); }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https: data:; media-src https:; frame-src https://www.youtube-nocookie.com https://player.twitch.tv; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Cache-Control: private, no-store');
    if ($c['secure_cookies']) header('Strict-Transport-Security: max-age=31536000');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(session_cookie_name());
    session_set_cookie_params(['httponly'=>true, 'secure'=>(bool)$c['secure_cookies'], 'samesite'=>'Lax', 'path'=>session_cookie_path()]);
    session_start();
    if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen'] > 7200) $_SESSION = [];
    if (isset($_SESSION['started']) && time()-$_SESSION['started'] > 86400) $_SESSION = [];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $u = query('SELECT id,email,name,session_version FROM users WHERE id=?', [$_SESSION['user_id']])->fetch();
    if (!$u || (int)$u['session_version'] !== ($_SESSION['session_version'] ?? 0)) { unset($_SESSION['user_id']); return null; }
    return $u;
}
function csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) throw new InvalidArgumentException('Your session changed. Reload the page and try again.', 403);
}
function authenticate(array $u): void {
    session_regenerate_id(true);
    $_SESSION = ['user_id'=>(int)$u['id'], 'session_version'=>(int)$u['session_version'], 'csrf'=>bin2hex(random_bytes(32)), 'started'=>time(), 'last_seen'=>time()];
}
function throttle(string $key, int $maximum): void {
    $bucket = hash('sha256', $key);
    query('INSERT INTO auth_limits(bucket,attempts,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<?,1,attempts+1), expires_at=IF(expires_at<?,VALUES(expires_at),expires_at)', [$bucket,time()+900,time(),time()]);
    $n = query('SELECT attempts FROM auth_limits WHERE bucket=?',[$bucket])->fetchColumn();
    if ((int)$n > $maximum) throw new InvalidArgumentException('Too many attempts. Please try again in 15 minutes.',429);
}
function valid_password(string $password): void {
    if (strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
}
function password_digest(string $password): string {
    return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}


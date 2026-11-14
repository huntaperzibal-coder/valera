<?php
declare(strict_types=1);

define('FOXY_ADMIN_SESSION', 7 * 86400);

require_once dirname(__DIR__, 2) . '/lib/foxy_private.php';
require_once dirname(__DIR__, 2) . '/lib/foxy_compat.php';
require_once dirname(__DIR__, 2) . '/api/_memory.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

if (PHP_SAPI !== 'cli-server' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    exit('Админка доступна только по HTTPS.');
}

$config = foxyLoadConfig();
$configReady = is_array($config)
    && !empty($config['api_key'])
    && !empty($config['folder_id'])
    && !empty($config['parent_password_hash']);

$sessionLifetime = FOXY_ADMIN_SESSION;
$privateDir = foxyPrivateDir();
$sessionDir = $privateDir . '/sessions';
if ((is_dir($sessionDir) || @mkdir($sessionDir, 0700, true) || is_dir($sessionDir)) && is_writable($sessionDir)) {
    session_name('foxy_admin');
    ini_set('session.use_strict_mode', '1');
    session_save_path($sessionDir);
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    foxySessionCookieParams($sessionLifetime, '/admin');
    session_start();
} else {
    http_response_code(503);
    exit('Сессии админки недоступны. Проверь каталог valera-private/sessions.');
}

if (!isset($_SESSION['foxy_admin_csrf'])) {
    $_SESSION['foxy_admin_csrf'] = bin2hex(random_bytes(16));
}

function foxyAdminToken(): string {
    return '<input type="hidden" name="csrf" value="' . foxyEsc($_SESSION['foxy_admin_csrf']) . '">';
}

function foxyAdminCsrfOk(): bool {
    return hash_equals((string) ($_SESSION['foxy_admin_csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
}

function foxyAdminLoggedIn(): bool {
    return !empty($_SESSION['foxy_admin'])
        && time() < (int) ($_SESSION['foxy_admin_until'] ?? 0);
}

function foxyAdminRequireLogin(): void {
    if (!foxyAdminLoggedIn()) {
        http_response_code(403);
        exit('Требуется вход в админку.');
    }
}

function foxyAdminGo(string $to = './'): void {
    header('Location: ' . $to, true, 303);
    exit;
}

function foxyAdminLogin(string $password, array $config): bool {
    if (strlen($password) > 256 || strlen($password) < 12) {
        return false;
    }
    $rateDir = foxyPrivateDir() . '/rate';
    if (!is_dir($rateDir)) {
        @mkdir($rateDir, 0700, true);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = $rateDir . '/admin-' . hash_hmac('sha256', $ip, (string) $config['parent_password_hash']) . '.json';
    $now = time();
    $rate = is_file($rateFile) ? json_decode((string) file_get_contents($rateFile), true) : null;
    if (!is_array($rate) || ($rate['since'] ?? 0) < $now - 900) {
        $rate = ['since' => $now, 'attempts' => 0];
    }
    if ($rate['attempts'] >= 6) {
        throw new RuntimeException('Слишком много попыток. Подожди 15 минут.');
    }
    if (!password_verify($password, (string) $config['parent_password_hash'])) {
        $rate['attempts']++;
        @file_put_contents($rateFile, json_encode($rate), LOCK_EX);
        return false;
    }
    @unlink($rateFile);
    session_regenerate_id(true);
    $_SESSION['foxy_admin'] = true;
    $_SESSION['foxy_admin_until'] = $now + FOXY_ADMIN_SESSION;
    $_SESSION['foxy_admin_csrf'] = bin2hex(random_bytes(16));
    return true;
}

function foxyUpdateParentPasswordHash(array $config, string $newPassword): void {
    if (strlen($newPassword) < 12 || strlen($newPassword) > 256) {
        throw new RuntimeException('Пароль должен быть от 12 до 256 символов.');
    }
    $config['parent_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $export = var_export($config, true);
    $body = "<?php\nreturn " . $export . ";\n";
    $path = foxyConfigPath();
    $dir = dirname($path);
    $tmp = tempnam($dir, '.config-');
    if ($tmp === false) {
        throw new RuntimeException('Не удалось обновить конфиг.');
    }
    try {
        if (!chmod($tmp, 0600) || file_put_contents($tmp, $body, LOCK_EX) === false || !rename($tmp, $path)) {
            throw new RuntimeException('Не удалось записать config.php.');
        }
        @chmod($path, 0600);
    } finally {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
}

date_default_timezone_set('Asia/Novosibirsk');
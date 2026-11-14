<?php
declare(strict_types=1);

/** PHP 7.2-safe session cookie (samesite array form is 7.3+). */
function foxySessionCookieParams(int $lifetime, string $path): void
{
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        return;
    }
    session_set_cookie_params($lifetime, $path, '', true, true);
}

/** Copy auto-memory SQLite for backup without touching the live file. */
function foxySqliteBackupCopy(string $livePath, string $copyPath): void
{
    if (!is_file($livePath)) {
        return;
    }
    $pdo = new PDO('sqlite:' . $livePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $pdo->exec('VACUUM INTO ' . $pdo->quote($copyPath));
    } catch (Throwable $e) {
        if (!@copy($livePath, $copyPath)) {
            throw new RuntimeException('Не удалось скопировать SQLite: ' . $e->getMessage(), 0, $e);
        }
    }
    @chmod($copyPath, 0600);
}
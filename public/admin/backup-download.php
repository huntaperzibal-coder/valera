<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');
if (function_exists('set_time_limit')) {
    @set_time_limit(300);
}

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/backup.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && isset($_GET['download'])) {
    if (!foxyAdminLoggedIn()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Сессия истекла. Войди в админку снова.');
    }
    $download = $_SESSION['local_backup'] ?? null;
    $token = (string) $_GET['download'];
    if (!$download || !hash_equals((string) ($download['token'] ?? ''), $token)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Архив недоступен. Подготовь новую копию.');
    }
    unset($_SESSION['local_backup']);
    session_write_close();
    $archive = (string) ($download['path'] ?? '');
    $file = is_file($archive) ? fopen($archive, 'rb') : false;
    if ($file === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Архив недоступен.');
    }
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($download['name'] ?? 'foxy-valera.zip'));
    if ($name === '') {
        $name = 'foxy-valera.zip';
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string) fstat($file)['size']);
    fpassthru($file);
    fclose($file);
    if (is_file($archive)) {
        @unlink($archive);
    }
    $stage = (string) ($download['stage'] ?? '');
    if ($stage !== '' && is_dir($stage)) {
        foxyRemoveBackupStage($stage);
    }
    exit;
}

if ($method === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'backup_download') {
        foxyBackupJson(['error' => 'Неизвестное действие.'], 400);
    }
    if (!foxyAdminLoggedIn()) {
        foxyBackupJson(['error' => 'Сессия истекла. Войди в админку снова.'], 401);
    }
    if (!foxyAdminCsrfOk()) {
        foxyBackupJson(['error' => 'Обнови страницу и повтори.'], 403);
    }
    try {
        $built = foxyBuildLocalBackupZip();
        $token = bin2hex(random_bytes(24));
        $_SESSION['local_backup'] = [
            'token' => $token,
            'path' => $built['archive'],
            'stage' => $built['stage'],
            'name' => $built['name'],
        ];
        foxyBackupJson([
            'ok' => true,
            'url' => 'backup-download.php?download=' . rawurlencode($token),
        ]);
    } catch (Throwable $e) {
        $message = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось подготовить резервную копию.';
        foxyBackupJson(['error' => $message], 500);
    }
}

foxyBackupJson(['error' => 'Метод не поддерживается.'], 405);
<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/backup.php';
require __DIR__ . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/foxy_compat.php';

$allowedSections = ['home', 'memory', 'ai', 'voice', 'github', 'backup', 'security'];
$section = (string) ($_GET['section'] ?? 'home');
if (!in_array($section, $allowedSections, true)) {
    $section = 'home';
}

if (isset($_GET['backup_download']) && $_SERVER['REQUEST_METHOD'] === 'GET' && foxyAdminLoggedIn()) {
    $download = $_SESSION['local_backup'] ?? null;
    if (!$download || !hash_equals($download['token'], (string) $_GET['backup_download'])) {
        http_response_code(404);
        exit('Архив недоступен. Подготовь новую копию.');
    }
    unset($_SESSION['local_backup']);
    session_write_close();
    $archive = $download['path'];
    $file = fopen($archive, 'rb');
    if ($file === false) {
        http_response_code(404);
        exit('Архив недоступен.');
    }
    if (is_file($archive)) {
        unlink($archive);
    }
    if (is_dir($download['stage'] ?? '')) {
        foxyRemoveBackupStage($download['stage']);
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $download['name'] . '"');
    header('Content-Length: ' . (string) fstat($file)['size']);
    fpassthru($file);
    fclose($file);
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        if (!$configReady) {
            $loginError = 'Сначала создай valera-private/config.php на сервере.';
        } else {
            try {
                $password = (string) ($_POST['password'] ?? '');
                if (foxyAdminLogin($password, $config)) {
                    foxyAdminGo('?section=home');
                }
                $loginError = 'Неверный пароль.';
            } catch (RuntimeException $e) {
                $loginError = $e->getMessage();
            }
        }
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        foxyAdminGo('./');
    } elseif (!foxyAdminCsrfOk()) {
        http_response_code(403);
        exit('Обнови страницу.');
    } elseif (!foxyAdminLoggedIn()) {
        http_response_code(403);
        exit('Требуется вход.');
    } else {
        try {
            if ($action === 'memory_parent_save') {
                $notes = trim((string) ($_POST['parent_notes'] ?? ''));
                if (mb_strlen($notes, 'UTF-8') > 800) {
                    throw new RuntimeException('Заметки длиннее 800 символов.');
                }
                $memoryFile = foxyPrivateDir() . '/memory.json';
                if ($notes === '') {
                    if (is_file($memoryFile)) {
                        @unlink($memoryFile);
                    }
                } else {
                    $payload = json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if ($payload === false || @file_put_contents($memoryFile, $payload, LOCK_EX) === false) {
                        throw new RuntimeException('Не удалось сохранить заметки.');
                    }
                    @chmod($memoryFile, 0600);
                }
                $_SESSION['flash'] = 'Родительские заметки сохранены.';
                foxyAdminGo('?section=memory');
            }
            if ($action === 'memory_parent_clear') {
                $memoryFile = foxyPrivateDir() . '/memory.json';
                if (is_file($memoryFile)) {
                    @unlink($memoryFile);
                }
                $_SESSION['flash'] = 'Заметки очищены.';
                foxyAdminGo('?section=memory');
            }
            if ($action === 'memory_delete') {
                $db = foxyMemoryDb();
                if (!$db) {
                    throw new RuntimeException('SQLite недоступен.');
                }
                $id = filter_var($_POST['memory_id'] ?? null, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    throw new RuntimeException('Неверная запись.');
                }
                $db->prepare('DELETE FROM memories WHERE id = ?')->execute([$id]);
                $_SESSION['flash'] = 'Запись удалена.';
                foxyAdminGo('?section=memory');
            }
            if ($action === 'memory_clear_auto') {
                $db = foxyMemoryDb();
                if (!$db) {
                    throw new RuntimeException('SQLite недоступен.');
                }
                $db->exec('DELETE FROM memories');
                $_SESSION['flash'] = 'Автоматическая память очищена.';
                foxyAdminGo('?section=memory');
            }
            if ($action === 'memory_update') {
                $db = foxyMemoryDb();
                if (!$db) {
                    throw new RuntimeException('SQLite недоступен.');
                }
                $id = filter_var($_POST['memory_id'] ?? null, FILTER_VALIDATE_INT);
                $content = trim((string) ($_POST['memory_content'] ?? ''));
                $kind = (string) ($_POST['memory_kind'] ?? '');
                if ($id === false || $id < 1 || !in_array($kind, ['interest', 'preference', 'story'], true)) {
                    throw new RuntimeException('Неверные данные.');
                }
                if (!foxyMemoryIsSafe($content)) {
                    throw new RuntimeException('Текст не прошёл проверку безопасности памяти.');
                }
                $db->prepare('UPDATE memories SET kind = ?, content = ?, updated_at = ? WHERE id = ?')
                    ->execute([$kind, $content, time(), $id]);
                $_SESSION['flash'] = 'Запись обновлена.';
                foxyAdminGo('?section=memory');
            }
            if ($action === 'ai_save' || $action === 'voice_save') {
                $settings = foxyLoadSettings();
                if ($action === 'ai_save') {
                    $model = trim((string) ($_POST['model'] ?? ''));
                    $settings['model'] = $model === '' ? null : $model;
                    $settings['chat_hourly_limit'] = (int) ($_POST['chat_hourly_limit'] ?? 30);
                    $settings['chat_min_interval'] = (int) ($_POST['chat_min_interval'] ?? 5);
                    $settings['web_search'] = isset($_POST['web_search']);
                } else {
                    $settings['speech'] = [
                        'voice' => trim((string) ($_POST['speech_voice'] ?? 'marina')),
                        'role' => trim((string) ($_POST['speech_role'] ?? 'friendly')),
                        'speed' => trim((string) ($_POST['speech_speed'] ?? '0.99')),
                        'pitchShift' => trim((string) ($_POST['speech_pitch'] ?? '-100')),
                    ];
                }
                foxySaveSettings($settings);
                $_SESSION['flash'] = 'Настройки сохранены.';
                foxyAdminGo('?section=' . ($action === 'ai_save' ? 'ai' : 'voice'));
            }
            if ($action === 'password_change') {
                if (!$config) {
                    throw new RuntimeException('Конфиг недоступен.');
                }
                $a = (string) ($_POST['new_password'] ?? '');
                $b = (string) ($_POST['new_password_repeat'] ?? '');
                if ($a !== $b) {
                    throw new RuntimeException('Пароли не совпадают.');
                }
                foxyUpdateParentPasswordHash($config, $a);
                $_SESSION['flash'] = 'Пароль обновлён. Войди снова на сайте и в админке.';
                $_SESSION = [];
                session_destroy();
                foxyAdminGo('./');
            }
            if ($action === 'backup_download') {
                if (!class_exists(ZipArchive::class)) {
                    throw new RuntimeException('На сервере нужно расширение PHP zip.');
                }
                $stage = foxyPrivateDir() . '/backups/.stage-' . bin2hex(random_bytes(12));
                if (!is_dir($stage) && !mkdir($stage, 0700, true)) {
                    throw new RuntimeException('Не удалось создать временную папку.');
                }
                $archive = $stage . '/foxy-valera.zip';
                $zip = new ZipArchive();
                if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                    throw new RuntimeException('Не удалось создать ZIP.');
                }
                try {
                    $siteRoot = dirname(__DIR__);
                    foxyAddBackupEntry($zip, $siteRoot, 'public_html');
                    $private = foxyPrivateDir();
                    $sqliteLive = $private . '/foxy-memory.sqlite';
                    if (is_file($sqliteLive)) {
                        $sqliteCopy = $stage . '/foxy-memory.sqlite';
                        foxySqliteBackupCopy($sqliteLive, $sqliteCopy);
                        foxyAddBackupEntry($zip, $sqliteCopy, 'valera-private/foxy-memory.sqlite');
                    }
                    foreach (['config.php', 'admin-settings.json', 'memory.json', 'memory-status.json', 'usage.jsonl'] as $file) {
                        $path = $private . '/' . $file;
                        if (is_file($path)) {
                            foxyAddBackupEntry($zip, $path, 'valera-private/' . $file);
                        }
                    }
                    $repoRoot = dirname($siteRoot, 2) . '/valera-repo';
                    if (is_dir($repoRoot . '/tools')) {
                        foxyAddBackupEntry($zip, $repoRoot . '/tools', 'valera-repo/tools');
                    }
                    if (!$zip->close()) {
                        throw new RuntimeException('Не удалось записать ZIP.');
                    }
                } catch (Throwable $e) {
                    $zip->close();
                    if (is_file($archive)) {
                        unlink($archive);
                    }
                    throw $e;
                }
                $token = bin2hex(random_bytes(24));
                $date = new DateTimeImmutable('now', new DateTimeZone('Asia/Novosibirsk'));
                $_SESSION['local_backup'] = [
                    'token' => $token,
                    'path' => $archive,
                    'stage' => $stage,
                    'name' => 'foxy-valera-' . $date->format('Y-m-d_H-i-s') . '.zip',
                ];
                header('Content-Type: application/json; charset=utf-8');
                $payload = json_encode(['url' => '?section=backup&backup_download=' . $token]);
                if ($payload === false) {
                    throw new RuntimeException('Не удалось подготовить ответ.');
                }
                echo $payload;
                exit;
            }
        } catch (Throwable $e) {
            if ($action === 'backup_download') {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                $message = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось подготовить резервную копию.';
                echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['flash'] = $e instanceof RuntimeException ? $e->getMessage() : 'Ошибка операции.';
            foxyAdminGo('?section=' . $section);
        }
    }
}

if (!foxyAdminLoggedIn()) {
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Вход — админка Фокси</title><link rel="stylesheet" href="admin.css?v=1"></head><body>';
    echo '<main class="login-wrap">';
    echo '<h1>Админка Фокси</h1><p>Отдельный вход для взрослого. Используется тот же пароль, что и для включения ИИ на сайте.</p>';
    if ($loginError !== '') {
        echo '<p class="orda-notice error">' . foxyEsc($loginError) . '</p>';
    }
    if (!$configReady) {
        echo '<p class="orda-notice error">config.php на сервере ещё не настроен — админка покажет ограниченные данные после входа.</p>';
    }
    echo '<form method="post"><input type="hidden" name="action" value="login">';
    echo '<label>Пароль<input type="password" name="password" required minlength="12" autocomplete="current-password"></label>';
    echo '<div class="orda-buttons start"><button class="btn-main" type="submit">Войти</button></div></form>';
    echo '<p><a href="../">← На сайт</a></p></main></body></html>';
    exit;
}

$titles = [
    'home' => ['Главная', 'Состояние'],
    'memory' => ['Память Фокси', 'Память'],
    'ai' => ['Настройки ИИ', 'ИИ'],
    'voice' => ['Голос Фокси', 'Голос'],
    'github' => ['GitHub и сервер', 'GitHub'],
    'backup' => ['Резервное копирование', 'Резервные копии'],
    'security' => ['Безопасность', 'Доступ'],
];
[$pageTitle, $crumb] = $titles[$section];
foxyAdminPageStart($section, $pageTitle, $crumb);
if (!empty($_SESSION['flash'])) {
    echo '<p class="orda-notice" role="status">' . foxyEsc((string) $_SESSION['flash']) . '</p>';
    unset($_SESSION['flash']);
}
require __DIR__ . '/sections/' . $section . '.php';
if ($section !== 'github' && $section !== 'backup') {
    echo '<script defer src="admin.js?v=1"></script>';
}
foxyAdminPageEnd();
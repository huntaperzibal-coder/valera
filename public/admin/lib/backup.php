<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/foxy_compat.php';

function foxyBackupCommand(array $command, string $directory, ?array $env = null): string
{
    $log = tmpfile();
    if (!$log) {
        throw new RuntimeException('Не удалось открыть журнал операции.');
    }
    try {
        $process = proc_open($command, [['pipe', 'r'], $log, ['file', '/dev/null', 'w']], $pipes, $directory, $env);
        if (is_resource($process)) {
            fclose($pipes[0]);
        }
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Команда завершилась с ошибкой.');
        }
        rewind($log);
        return (string) stream_get_contents($log);
    } finally {
        fclose($log);
    }
}

function foxyRemoveBackupStage(string $dir): void
{
    foreach (new FilesystemIterator($dir) as $entry) {
        $path = $entry->getPathname();
        if ($entry->isDir() && !$entry->isDot()) {
            foxyRemoveBackupStage($path);
        } elseif ($entry->isFile()) {
            unlink($path);
        }
    }
    rmdir($dir);
}

function foxyAddBackupEntry(ZipArchive $zip, string $path, string $name, bool $store = false): void
{
    if (is_link($path)) {
        return;
    }
    if (is_dir($path)) {
        if (!$zip->addEmptyDir($name)) {
            throw new RuntimeException('Не удалось добавить папку в ZIP.');
        }
        foreach (new FilesystemIterator($path) as $entry) {
            foxyAddBackupEntry($zip, $entry->getPathname(), $name . '/' . $entry->getFilename(), $store);
        }
        return;
    }
    if (!is_file($path) || !$zip->addFile($path, $name)) {
        throw new RuntimeException('Не удалось добавить файл в ZIP.');
    }
    if ($store && !$zip->setCompressionName($name, ZipArchive::CM_STORE)) {
        throw new RuntimeException('Не удалось настроить ZIP.');
    }
}

/** Read-only: existing server credentials (selectel.json). Never written from админки. */
function foxyBackupCredentialsPath(): string
{
    return foxyPrivateDir() . '/selectel.json';
}

function foxyLoadBackupCredentials(): array
{
    $path = foxyBackupCredentialsPath();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/** Use env exactly as on server — no hardcoded repository override. */
function foxyResticEnv(array $credentials): array
{
    return $credentials;
}

function foxyBackupCredentialsReady(array $credentials): bool
{
    foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'RESTIC_PASSWORD', 'RESTIC_REPOSITORY'] as $key) {
        if (empty($credentials[$key])) {
            return false;
        }
    }
    return true;
}

/** Optional status file written by server cron (read-only). */
function foxyBackupCronStatus(): ?array
{
    $path = foxyPrivateDir() . '/backup-last.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** List recent restic snapshots (read-only, existing restic + selectel.json). */
function foxyBackupSnapshots(array $credentials): array
{
    if (!foxyBackupCredentialsReady($credentials)) {
        return ['ok' => false, 'error' => 'На сервере нет полного selectel.json (ключи и RESTIC_REPOSITORY).'];
    }
    $restic = foxyPrivateDir() . '/restic';
    if (!is_executable($restic)) {
        return ['ok' => false, 'error' => 'restic не найден в valera-private/restic'];
    }
    try {
        $log = foxyBackupCommand([$restic, '--no-cache', 'snapshots', '--json'], foxyPrivateDir(), foxyResticEnv($credentials));
        $decoded = json_decode($log, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Не удалось прочитать список снимков'];
        }
        return ['ok' => true, 'snapshots' => array_slice($decoded, -14)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function foxySqliteIntegrity(string $sqlitePath): ?string
{
    if (!is_file($sqlitePath)) {
        return 'Файл базы не найден.';
    }
    try {
        $db = new PDO('sqlite:' . $sqlitePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = $db->query('PRAGMA integrity_check')->fetchColumn();
        return $result === 'ok' ? null : (string) $result;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}
<?php
declare(strict_types=1);

function foxyBackupCommand(array $command, string $directory, ?array $env = null): string {
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
            throw new RuntimeException('Команда завершилась с ошибкой. Проверь настройки и доступ к хранилищу.');
        }
        rewind($log);
        return (string) stream_get_contents($log);
    } finally {
        fclose($log);
    }
}

function foxyRemoveBackupStage(string $dir): void {
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

function foxyAddBackupEntry(ZipArchive $zip, string $path, string $name, bool $store = false): void {
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

function foxyBackupCredentialsPath(): string {
    return foxyPrivateDir() . '/selectel.json';
}

function foxyLoadBackupCredentials(): array {
    $path = foxyBackupCredentialsPath();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function foxySaveBackupCredentials(array $credentials): void {
    $dir = foxyPrivateDir();
    if (count($credentials) !== 3 || in_array('', $credentials, true)) {
        throw new RuntimeException('Заполни реквизиты Selectel.');
    }
    $tmp = tempnam($dir, '.selectel-');
    if ($tmp === false) {
        throw new RuntimeException('Не удалось создать файл реквизитов.');
    }
    try {
        $payload = json_encode($credentials, JSON_THROW_ON_ERROR);
        if (!chmod($tmp, 0600) || file_put_contents($tmp, $payload, LOCK_EX) === false || !rename($tmp, foxyBackupCredentialsPath())) {
            throw new RuntimeException('Не удалось сохранить реквизиты.');
        }
        @chmod(foxyBackupCredentialsPath(), 0600);
    } finally {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
}

function foxyResticEnv(array $credentials): array {
    return $credentials + [
        'AWS_DEFAULT_REGION' => 'ru-3',
        'RESTIC_REPOSITORY' => 's3:https://s3.ru-3.storage.selcloud.ru/hunta-backups/valera/',
    ];
}

/** List recent restic snapshots (read-only). */
function foxyBackupSnapshots(array $credentials): array {
    $restic = foxyPrivateDir() . '/restic';
    if (!is_executable($restic)) {
        return ['ok' => false, 'error' => 'restic не найден в valera-private/restic'];
    }
    try {
        $log = foxyBackupCommand([$restic, 'snapshots', '--json'], foxyPrivateDir(), foxyResticEnv($credentials));
        $decoded = json_decode($log, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Не удалось прочитать список снимков'];
        }
        return ['ok' => true, 'snapshots' => array_slice($decoded, -14)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function foxySqliteIntegrity(string $sqlitePath): ?string {
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
<?php
declare(strict_types=1);

$credentials = foxyLoadBackupCredentials();
$credReady = foxyBackupCredentialsReady($credentials);
$snapshots = $credReady ? foxyBackupSnapshots($credentials) : ['ok' => false, 'error' => 'Ежедневные копии и restic уже настроены на сервере; реквизиты не отображаются и из админки не меняются.'];
$cronStatus = foxyBackupCronStatus();
$sqlitePath = foxyPrivateDir() . '/foxy-memory.sqlite';
$integrity = is_file($sqlitePath) ? foxySqliteIntegrity($sqlitePath) : null;

echo '<div class="columns" id="backup">';
echo '<section class="card"><h2>Selectel (только просмотр)</h2>';
echo '<p>На Masterhost уже настроено резервное копирование в Selectel через restic. Админка <strong>не создаёт</strong> второй репозиторий, <strong>не записывает</strong> <code>selectel.json</code> и <strong>не запускает</strong> полный restic-backup — только показывает снимки по существующим настройкам сервера.</p>';
if (is_array($cronStatus)) {
    $when = isset($cronStatus['at']) ? (string) $cronStatus['at'] : '—';
    $msg = isset($cronStatus['message']) ? (string) $cronStatus['message'] : (isset($cronStatus['status']) ? (string) $cronStatus['status'] : 'Выполнено');
    echo '<p class="orda-notice" role="status">Последний cron: ' . foxyEsc($when) . ' — ' . foxyEsc($msg) . '</p>';
}
if (!empty($snapshots['ok']) && !empty($snapshots['snapshots'])) {
    echo '<h3>Последние снимки</h3><table class="data"><thead><tr><th>ID</th><th>Время</th><th>Теги</th></tr></thead><tbody>';
    foreach (array_reverse($snapshots['snapshots']) as $snap) {
        $time = isset($snap['time']) ? gmdate('Y-m-d H:i', strtotime((string) $snap['time'])) . ' UTC' : '—';
        $tags = isset($snap['tags']) && is_array($snap['tags']) ? implode(', ', $snap['tags']) : '';
        echo '<tr><td><code>' . foxyEsc(substr((string) ($snap['id'] ?? ''), 0, 8)) . '</code></td><td>' . foxyEsc($time) . '</td><td>' . foxyEsc($tags) . '</td></tr>';
    }
    echo '</tbody></table>';
} elseif (!empty($snapshots['error'])) {
    echo '<p class="orda-notice">' . foxyEsc((string) $snapshots['error']) . '</p>';
}
echo '</section>';

echo '<section class="card"><h2>Резервная копия на компьютер</h2>';
echo '<p>Локальный ZIP: каталог сайта и копия <code>valera-private</code> (SQLite через VACUUM INTO или безопасное копирование, без перезаписи живой базы).</p>';
echo '<form method="post" data-backup-download action="backup-download.php">' . foxyAdminToken();
echo '<input type="hidden" name="action" value="backup_download">';
echo '<div class="orda-buttons start"><button class="btn-secondary" type="submit">Скачать резервную копию</button></div>';
echo '<p role="status" hidden></p></form>';
echo '<h3>Проверка SQLite</h3>';
if (!is_file($sqlitePath)) {
    echo '<p>Пока нет файла автопамяти — это нормально до первой записи.</p>';
} elseif ($integrity === null) {
    echo '<p class="orda-notice" role="status">integrity_check: ok</p>';
} else {
    echo '<p class="orda-notice error">' . foxyEsc($integrity) . '</p>';
}
echo '<p>Восстановление на сервер — вручную после проверки архива; автоматическое восстановление из админки отключено.</p></section>';
echo '</div><script defer src="admin.js?v=2"></script>';
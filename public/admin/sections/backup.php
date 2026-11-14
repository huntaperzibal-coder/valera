<?php
declare(strict_types=1);

$credentials = foxyLoadBackupCredentials();
$snapshots = $credentials !== [] ? foxyBackupSnapshots($credentials) : ['ok' => false, 'error' => 'Реквизиты Selectel не сохранены'];
$status = $_SESSION['backup_status'] ?? '';
$statusOk = !empty($_SESSION['backup_ok']);
unset($_SESSION['backup_status'], $_SESSION['backup_ok']);
$sqlitePath = foxyPrivateDir() . '/foxy-memory.sqlite';
$integrity = is_file($sqlitePath) ? foxySqliteIntegrity($sqlitePath) : 'Файл базы не найден (это нормально до первой записи).';

echo '<div class="columns" id="backup">';
echo '<section class="card"><h2>Синхронизация с Selectel</h2>';
if ($status !== '') {
    $class = $statusOk ? '' : ' error';
    echo '<p class="orda-notice' . $class . '" role="status">' . foxyEsc($status) . '</p>';
}
if ($credentials !== []) {
    echo '<form method="post">' . foxyAdminToken() . '<div class="orda-buttons start"><button class="btn-main" name="action" value="backup_selectel" type="submit">Сохранить в Selectel сейчас</button></div></form>';
}
echo '<details' . ($credentials === [] ? ' open' : '') . '><summary>Реквизиты Selectel</summary>';
echo '<form method="post">' . foxyAdminToken();
echo '<label>Ключ доступа Selectel<input type="password" name="access_key" required autocomplete="off"></label>';
echo '<label>Секретный ключ Selectel<input type="password" name="secret_key" required autocomplete="off"></label>';
echo '<label>Пароль restic<input type="password" name="backup_password" required autocomplete="off"></label>';
echo '<label class="check"><input type="checkbox" name="initialize_repository" value="1"> Создать репозиторий при первой настройке</label>';
echo '<div class="orda-buttons start"><button class="btn-main" name="action" value="backup_selectel_save" type="submit">Сохранить в Selectel и запомнить реквизиты</button></div></form></details>';
if (!empty($snapshots['ok']) && !empty($snapshots['snapshots'])) {
    echo '<h3>Последние снимки</h3><table class="data"><thead><tr><th>ID</th><th>Время</th><th>Теги</th></tr></thead><tbody>';
    foreach (array_reverse($snapshots['snapshots']) as $snap) {
        $time = isset($snap['time']) ? gmdate('Y-m-d H:i', strtotime((string) $snap['time'])) . ' UTC' : '—';
        echo '<tr><td><code>' . foxyEsc(substr((string) ($snap['id'] ?? ''), 0, 8)) . '</code></td><td>' . foxyEsc($time) . '</td><td>' . foxyEsc(implode(', ', $snap['tags'] ?? [])) . '</td></tr>';
    }
    echo '</tbody></table>';
} elseif (!empty($snapshots['error'])) {
    echo '<p class="orda-notice">' . foxyEsc((string) $snapshots['error']) . '</p>';
}
echo '</section>';

echo '<section class="card"><h2>Резервная копия на компьютер</h2>';
echo '<p>Каталог сайта, valera-private (конфиг, память, SQLite-копия), без перезаписи живой базы.</p>';
echo '<form method="post" data-backup-download action="">' . foxyAdminToken();
echo '<input type="hidden" name="action" value="backup_download">';
echo '<div class="orda-buttons start"><button class="btn-secondary" type="submit">Скачать резервную копию</button></div>';
echo '<p role="status" hidden></p></form>';
echo '<h3>Проверка SQLite</h3>';
if ($integrity === null) {
    echo '<p class="orda-notice" role="status">integrity_check: ok</p>';
} else {
    echo '<p class="orda-notice error">' . foxyEsc($integrity) . '</p>';
}
echo '<p>Восстановление: распакуй архив вручную, сравни файлы и только после проверки замени на сервере. Автоматическое восстановление из админки не выполняется.</p></section>';
echo '</div><script defer src="admin.js?v=1"></script>';
<?php
declare(strict_types=1);

$memoryFile = foxyPrivateDir() . '/memory.json';
$parentNotes = '';
if (is_file($memoryFile)) {
    $parsed = json_decode((string) file_get_contents($memoryFile), true);
    if (is_array($parsed) && is_string($parsed['notes'] ?? null)) {
        $parentNotes = $parsed['notes'];
    }
}

$db = foxyMemoryDb();
$memories = $db ? foxyMemoryRows($db, 50) : [];

echo '<form method="post" class="card">';
echo '<h2>Родительские заметки</h2><p>Те же данные, что в диалоге «Для взрослых» на сайте (до 800 символов).</p>';
echo foxyAdminToken();
echo '<label>Заметки<textarea name="parent_notes" maxlength="800">' . foxyEsc($parentNotes) . '</textarea></label>';
echo '<div class="orda-buttons start"><button class="btn-main" type="submit" name="action" value="memory_parent_save">Сохранить заметки</button>';
echo '<button class="btn-secondary" type="submit" name="action" value="memory_parent_clear" formnovalidate onclick="return confirm(\'Очистить родительские заметки?\');">Очистить</button></div></form>';

echo '<section class="card"><h2>Автоматическая память (SQLite)</h2>';
if (!$db) {
    echo '<p class="orda-notice error">База недоступна. Проверь pdo_sqlite и каталог valera-private.</p></section>';
    return;
}
echo '<div class="orda-buttons start"><button class="btn-secondary" type="submit" form="memory-clear-auto" name="action" value="memory_clear_auto">Удалить все авто-воспоминания</button></div>';
echo '<form id="memory-clear-auto" method="post" onsubmit="return confirm(\'Удалить все автоматические записи?\');">' . foxyAdminToken() . '</form>';

if ($memories === []) {
    echo '<p>Пока записей нет.</p>';
} else {
    echo '<table class="data"><thead><tr><th>ID</th><th>Тип</th><th>Содержание</th><th>Обновлено</th><th></th></tr></thead><tbody>';
    foreach ($memories as $row) {
        $updated = isset($row['updated_at']) ? gmdate('Y-m-d H:i', (int) $row['updated_at']) . ' UTC' : '—';
        echo '<tr><td>' . (int) $row['id'] . '</td><td>' . foxyEsc((string) $row['kind']) . '</td>';
        echo '<td><form method="post" class="mem-edit">';
        echo foxyAdminToken();
        echo '<input type="hidden" name="memory_id" value="' . (int) $row['id'] . '">';
        echo '<input type="text" name="memory_content" maxlength="160" value="' . foxyEsc((string) $row['content']) . '">';
        echo '<select name="memory_kind"><option value="interest"' . (($row['kind'] ?? '') === 'interest' ? ' selected' : '') . '>interest</option>';
        echo '<option value="preference"' . (($row['kind'] ?? '') === 'preference' ? ' selected' : '') . '>preference</option>';
        echo '<option value="story"' . (($row['kind'] ?? '') === 'story' ? ' selected' : '') . '>story</option></select>';
        echo '<button class="btn-main" type="submit" name="action" value="memory_update">Сохранить</button>';
        echo '</form></td><td>' . foxyEsc($updated) . '</td><td>';
        echo '<form method="post" onsubmit="return confirm(\'Удалить запись?\');">' . foxyAdminToken();
        echo '<input type="hidden" name="memory_id" value="' . (int) $row['id'] . '">';
        echo '<button class="btn-secondary" type="submit" name="action" value="memory_delete">Удалить</button></form></td></tr>';
    }
    echo '</tbody></table>';
}
echo '</section>';
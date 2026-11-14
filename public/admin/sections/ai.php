<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/foxy_usage.php';

$settings = foxyLoadSettings();
$model = $config ? foxyEffectiveModel($config, $settings) : '';
$usage = foxyUsageSummary();

echo '<form method="post" class="card">';
echo '<h2>Параметры модели</h2>';
echo '<p>Секреты (API-ключ, folder_id) остаются только в <code>valera-private/config.php</code> на сервере.</p>';
echo foxyAdminToken();
echo '<label>Модель (пусто = из config.php)<input type="text" name="model" value="' . foxyEsc((string) ($settings['model'] ?? '')) . '" placeholder="' . foxyEsc((string) ($config['model'] ?? 'qwen3.6-35b-a3b')) . '"></label>';
echo '<label>Лимит сообщений в час<input type="number" name="chat_hourly_limit" min="1" max="120" value="' . (int) $settings['chat_hourly_limit'] . '"></label>';
echo '<label>Пауза между сообщениями (сек)<input type="number" name="chat_min_interval" min="1" max="60" value="' . (int) $settings['chat_min_interval'] . '"></label>';
$checked = !empty($settings['web_search']) ? ' checked' : '';
echo '<label class="check"><input type="checkbox" name="web_search" value="1"' . $checked . '> Разрешить интернет-поиск по кнопке на сайте</label>';
echo '<div class="orda-buttons start"><button class="btn-main" type="submit" name="action" value="ai_save">Сохранить</button></div></form>';

echo '<section class="card"><h2>Статистика использования</h2><p>Учитываются только токены и оценка стоимости, без текста диалогов.</p>';
echo '<table class="data"><thead><tr><th>Период</th><th>Запросы</th><th>Вход</th><th>Выход</th><th>Оценка, ₽</th></tr></thead><tbody>';
foreach (['today' => 'Сегодня (UTC)', 'month' => 'Месяц', 'all' => 'Всего'] as $key => $label) {
    $row = $usage['periods'][$key] ?? [];
    echo '<tr><td>' . foxyEsc($label) . '</td><td>' . (int) ($row['requests'] ?? 0) . '</td><td>' . (int) ($row['input'] ?? 0) . '</td>';
    echo '<td>' . (int) ($row['output'] ?? 0) . '</td><td>' . foxyEsc(number_format((float) ($row['rub'] ?? 0), 4, '.', ' ')) . '</td></tr>';
}
echo '</tbody></table>';
if ($usage['last']) {
    echo '<p>Последняя модель: <code>' . foxyEsc((string) ($usage['last']['model'] ?? $model)) . '</code></p>';
}
echo '</section>';
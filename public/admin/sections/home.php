<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/foxy_usage.php';

$settings = foxyLoadSettings();
$model = $config ? foxyEffectiveModel($config, $settings) : '—';
$usage = foxyUsageSummary();
$sqlitePath = foxyPrivateDir() . '/foxy-memory.sqlite';
$sqliteOk = foxySqliteIntegrity(is_file($sqlitePath) ? $sqlitePath : '') === null && is_file($sqlitePath);
$memDiagnostic = is_file(foxyPrivateDir() . '/memory-status.json')
    ? json_decode((string) file_get_contents(foxyPrivateDir() . '/memory-status.json'), true)
    : null;
$aiReady = $configReady;
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

echo '<div class="stat-grid">';
echo '<div class="stat"><strong>ИИ и конфиг</strong><span>' . ($aiReady ? 'Готово' : 'Нужен config.php') . '</span></div>';
echo '<div class="stat"><strong>HTTPS</strong><span>' . ($https ? 'Да' : 'Нет') . '</span></div>';
echo '<div class="stat"><strong>SQLite память</strong><span>' . ($sqliteOk ? 'OK' : (is_file($sqlitePath) ? 'Проверка' : 'Нет файла')) . '</span></div>';
echo '<div class="stat"><strong>Модель</strong><span>' . foxyEsc($model) . '</span></div>';
echo '<div class="stat"><strong>Запросов сегодня</strong><span>' . (int) ($usage['periods']['today']['requests'] ?? 0) . '</span></div>';
echo '<div class="stat"><strong>Оценка руб. за месяц</strong><span>' . foxyEsc(number_format((float) ($usage['periods']['month']['rub'] ?? 0), 4, '.', ' ')) . '</span></div>';
echo '</div>';

echo '<section class="card"><h2>Соединения</h2>';
echo '<p>Публичный сайт: <a href="../">открыть интерфейс Фокси</a>. API чата: <code>/api/chat.php</code> (только POST, после родительского входа).</p>';
if (is_array($memDiagnostic) && !empty($memDiagnostic['status'])) {
    echo '<p class="orda-notice" role="status">Автопамять: ' . foxyEsc((string) $memDiagnostic['status']) . '</p>';
}
if ($usage['last']) {
    $last = $usage['last'];
    echo '<p>Последний учтённый запрос к модели: ' . foxyEsc((string) ($last['at'] ?? '')) . ', HTTP ' . foxyEsc((string) ($last['status'] ?? '')) . '.</p>';
}
echo '</section>';
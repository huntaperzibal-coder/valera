<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Разрешён только POST'], JSON_UNESCAPED_UNICODE); exit;
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403); echo json_encode(['error'=>'Запрещённый источник'], JSON_UNESCAPED_UNICODE); exit;
}
$configPath = dirname(__DIR__, 3) . '/valera-private/config.php';
// /home/u551741/hunta.su/www/api => /home/u551741/valera-private/config.php
if (!is_file($configPath)) {
    http_response_code(503); echo json_encode(['error'=>'ИИ пока не настроен'], JSON_UNESCAPED_UNICODE); exit;
}
$config = require $configPath;
if (!is_array($config) || empty($config['api_key']) || empty($config['folder_id']) || empty($config['parent_password_hash'])) {
    http_response_code(503); echo json_encode(['error'=>'Не завершена настройка ИИ'], JSON_UNESCAPED_UNICODE); exit;
}
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); echo json_encode(['error'=>'Требуется HTTPS'], JSON_UNESCAPED_UNICODE); exit;
}
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400); echo json_encode(['error'=>'Неверный JSON'], JSON_UNESCAPED_UNICODE); exit;
}

/** Usage accounting for Foxy only. No prompts, responses or personal data are stored. */
function foxyPrice(string $model): array {
    $rates = [
        'qwen3.6-35b-a3b' => [0.20, 0.30, 0.05],
        'deepseek-v4.1-flash' => [0.30, 0.50, 0.075],
        'deepseek-v4-flash' => [0.30, 0.50, 0.075]
    ];
    return $rates[$model] ?? [0, 0, 0];
}
function foxyCost(array $usage, string $model): ?float {
    $rates = foxyPrice($model);
    if ($rates[0] === 0.0) return null;
    $input = max(0, (int)($usage['prompt_tokens'] ?? 0));
    $output = max(0, (int)($usage['completion_tokens'] ?? 0));
    $cached = min($input, max(0, (int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0)));
    return round((($input - $cached) * $rates[0] + $cached * $rates[2] + $output * $rates[1]) / 1000, 6);
}
function foxyLedgerPath(): string {
    return dirname(__DIR__, 3) . '/valera-private/usage.jsonl';
}
function foxyRecordUsage(array $usage, string $model, int $httpStatus): void {
    $entry = [
        'at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'model' => $model,
        'input' => max(0, (int)($usage['prompt_tokens'] ?? 0)),
        'output' => max(0, (int)($usage['completion_tokens'] ?? 0)),
        'cached' => max(0, (int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0)),
        'reasoning' => max(0, (int)($usage['completion_tokens_details']['reasoning_tokens'] ?? 0)),
        'rub' => foxyCost($usage, $model),
        'status' => $httpStatus
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
    $file = foxyLedgerPath();
    // Configuration directory is private; keep records there, never under public/.
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    if (is_file($file)) @chmod($file, 0600);
}
function foxyUsageSummary(): array {
    $now = time();
    $day = gmdate('Y-m-d', $now);
    $month = gmdate('Y-m', $now);
    $empty = fn() => ['requests'=>0,'input'=>0,'output'=>0,'cached'=>0,'reasoning'=>0,'rub'=>0.0];
    $totals = ['today'=>$empty(), 'month'=>$empty(), 'all'=>$empty()];
    $last = null;
    $file = foxyLedgerPath();
    if (!is_file($file)) return ['periods'=>$totals,'last'=>null,'timezone'=>'UTC','currency'=>'RUB','estimate'=>true];
    $fp = @fopen($file, 'r');
    if (!$fp) return ['periods'=>$totals,'last'=>null,'timezone'=>'UTC','currency'=>'RUB','estimate'=>true];
    while (($line = fgets($fp)) !== false) {
        $e = json_decode($line, true);
        if (!is_array($e) || !isset($e['at'])) continue;
        $last = $e;
        foreach (['all', 'month', 'today'] as $period) {
            if ($period==='month' && substr($e['at'], 0, 7)!==$month) continue;
            if ($period==='today' && substr($e['at'], 0, 10)!==$day) continue;
            $totals[$period]['requests']++;
            foreach (['input','output','cached','reasoning'] as $key) $totals[$period][$key] += max(0,(int)($e[$key] ?? 0));
            $totals[$period]['rub'] += (float)($e['rub'] ?? 0);
        }
    }
    fclose($fp);
    foreach ($totals as &$total) $total['rub'] = round($total['rub'], 5);
    return ['periods'=>$totals,'last'=>$last,'timezone'=>'UTC','currency'=>'RUB','estimate'=>true];
}

$action = $body['action'] ?? 'chat';
if ($action === 'login') {
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    if (strlen($password) > 256 || strlen($password) < 12) {
        http_response_code(403); echo json_encode(['error'=>'Неверный пароль'], JSON_UNESCAPED_UNICODE); exit;
    }
    // Persistent IP-hash-based rate limiting of login attempts, without storing the IP address.
    $rateDir = dirname(__DIR__, 3) . '/valera-private/rate';
    if (!is_dir($rateDir)) { @mkdir($rateDir, 0700, true); }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = $rateDir . '/login-' . hash_hmac('sha256', $ip, $config['parent_password_hash']) . '.json';
    $now = time(); $rate = is_file($rateFile) ? json_decode(file_get_contents($rateFile) ?: '', true) : null;
    if (!is_array($rate) || ($rate['since'] ?? 0) < $now - 900) $rate = ['since'=>$now,'attempts'=>0];
    if ($rate['attempts'] >= 6) {
        http_response_code(429); echo json_encode(['error'=>'Слишком много попыток. Попробуй позже.'], JSON_UNESCAPED_UNICODE); exit;
    }
    if (!password_verify($password, $config['parent_password_hash'])) {
        $rate['attempts']++; @file_put_contents($rateFile,json_encode($rate),LOCK_EX);
        http_response_code(403); echo json_encode(['error'=>'Неверный пароль'], JSON_UNESCAPED_UNICODE); exit;
    }
    @unlink($rateFile);
    session_regenerate_id(true);
    $_SESSION['valera_parent'] = true;
    $_SESSION['valera_csrf'] = bin2hex(random_bytes(16));
    echo json_encode(['ok'=>true,'csrf'=>$_SESSION['valera_csrf']]); exit;
}
if (empty($_SESSION['valera_parent'])) {
    http_response_code(401); echo json_encode(['error'=>'Сначала нужен родительский вход'], JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'usage') {
    echo json_encode(foxyUsageSummary(), JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'status') {
    echo json_encode(['ok'=>true,'csrf'=>$_SESSION['valera_csrf'] ?? '']); exit;
}
if ($action !== 'chat' || !hash_equals((string)($_SESSION['valera_csrf'] ?? ''), (string)($body['csrf'] ?? ''))) {
    http_response_code(403); echo json_encode(['error'=>'Доступ запрещён'], JSON_UNESCAPED_UNICODE); exit;
}
$now = time();
if (($now - ($_SESSION['valera_last'] ?? 0)) < 5) {
    http_response_code(429); echo json_encode(['error'=>'Нужно немного подождать'], JSON_UNESCAPED_UNICODE); exit;
}
if (($now - ($_SESSION['valera_window'] ?? 0)) > 3600) {
    $_SESSION['valera_window'] = $now; $_SESSION['valera_count'] = 0;
}
if (($_SESSION['valera_count'] ?? 0) >= 30) {
    http_response_code(429); echo json_encode(['error'=>'Лимит разговоров на этот час'], JSON_UNESCAPED_UNICODE); exit;
}
$input = trim((string)($body['message'] ?? ''));
if ($input === '' || mb_strlen($input, 'UTF-8') > 400) {
    http_response_code(400); echo json_encode(['error'=>'Слишком длинная или пустая фраза'], JSON_UNESCAPED_UNICODE); exit;
}
$history = [];
if (is_array($body['history'] ?? null)) {
    foreach (array_slice($body['history'], -8) as $turn) {
        if (!is_array($turn) || !in_array($turn['role'] ?? '', ['user','assistant'], true) || !is_string($turn['content'] ?? null)) continue;
        $value = trim($turn['content']);
        if (mb_strlen($value, 'UTF-8') > 500) continue;
        $history[] = ['role'=>$turn['role'],'content'=>$value];
    }
}
$prompt = 'Ты Фокси — добрый вымышленный лисёнок в детском приложении. Твой собеседник — ребёнок пяти лет. Говори по-русски, тепло, просто, коротко: 1–3 предложения. Задавай не больше одного вопроса за раз. Играй в безопасные загадки, сказки и познавательные игры. Не изображай реального человека, не поощряй исключительную дружбу или секреты от родителей. Не спрашивай имя, адрес, школу, телефон, фотографии, местоположение или иные личные сведения. Не давай опасных инструкций или взрослого содержания. Если тема пугающая или опасная, спокойно советуй обратиться к взрослому. Не выдавай догадки за факты. Ссылки, рекламу и внешние сайты не предлагай. На просьбу делать что-то рискованное предложи безопасную игру.';
$messages = array_merge([['role'=>'system','content'=>$prompt]], $history, [['role'=>'user','content'=>$input]]);
$payload = json_encode([
    'model'=>'gpt://' . $config['folder_id'] . '/' . ($config['model'] ?? 'qwen3.6-35b-a3b'),
    'messages'=>$messages,
    'max_completion_tokens'=>350,
    'reasoning_effort'=>'none',
    'store'=>false,
    'stream'=>false
], JSON_UNESCAPED_UNICODE);
if ($payload === false || !function_exists('curl_init')) {
    http_response_code(503); echo json_encode(['error'=>'Ошибка конфигурации сервера'], JSON_UNESCAPED_UNICODE); exit;
}
$_SESSION['valera_last'] = $now;
$_SESSION['valera_count'] = ($_SESSION['valera_count'] ?? 0) + 1;
session_write_close();
$curl = curl_init('https://ai.api.cloud.yandex.net/v1/chat/completions');
curl_setopt_array($curl,[
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Api-Key ' . $config['api_key'],'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>8,
    CURLOPT_TIMEOUT=>40,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2,
]);
$response = curl_exec($curl);
$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
if ($response === false || $http < 200 || $http >= 300) {
    http_response_code(502); echo json_encode(['error'=>'Ошибка обращения к модели','provider_status'=>$http], JSON_UNESCAPED_UNICODE); exit;
}
$data=json_decode($response,true);
$modelName = (string)($config['model'] ?? 'qwen3.6-35b-a3b');
$usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;
if ($usage !== null) foxyRecordUsage($usage, $modelName, $http);
$answer=trim((string)($data['choices'][0]['message']['content'] ?? ''));
if ($answer==='') {
    http_response_code(502); echo json_encode(['error'=>'ИИ не прислал текст ответа','finish_reason'=>substr((string)($data['choices'][0]['finish_reason'] ?? 'unknown'),0,40), 'reasoning_tokens'=>(int)($data['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0)], JSON_UNESCAPED_UNICODE); exit;
}
echo json_encode(['reply'=>mb_substr($answer,0,900,'UTF-8'), 'usage'=>$usage === null ? null : ['input'=>(int)($usage['prompt_tokens'] ?? 0),'output'=>(int)($usage['completion_tokens'] ?? 0),'cached'=>(int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0),'rub'=>foxyCost($usage,$modelName)]], JSON_UNESCAPED_UNICODE);

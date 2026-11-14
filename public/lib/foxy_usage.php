<?php
declare(strict_types=1);

require_once __DIR__ . '/foxy_private.php';

function foxyPrice(string $model): array {
    $rates = [
        'qwen3.6-35b-a3b' => [0.20, 0.30, 0.05],
        'deepseek-v4.1-flash' => [0.30, 0.50, 0.075],
        'deepseek-v4-flash' => [0.30, 0.50, 0.075],
    ];
    return $rates[$model] ?? [0, 0, 0];
}

function foxyCost(array $usage, string $model): ?float {
    $rates = foxyPrice($model);
    if ($rates[0] === 0.0) {
        return null;
    }
    $input = max(0, (int) ($usage['prompt_tokens'] ?? 0));
    $output = max(0, (int) ($usage['completion_tokens'] ?? 0));
    $cached = min($input, max(0, (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0)));
    return round((($input - $cached) * $rates[0] + $cached * $rates[2] + $output * $rates[1]) / 1000, 6);
}

function foxyLedgerPath(): string {
    return foxyPrivateDir() . '/usage.jsonl';
}

function foxyUsageSummary(): array {
    $now = time();
    $day = gmdate('Y-m-d', $now);
    $month = gmdate('Y-m', $now);
    $empty = static fn () => ['requests' => 0, 'input' => 0, 'output' => 0, 'cached' => 0, 'reasoning' => 0, 'rub' => 0.0];
    $totals = ['today' => $empty(), 'month' => $empty(), 'all' => $empty()];
    $last = null;
    $file = foxyLedgerPath();
    if (!is_file($file)) {
        return ['periods' => $totals, 'last' => null, 'timezone' => 'UTC', 'currency' => 'RUB', 'estimate' => true];
    }
    $fp = @fopen($file, 'r');
    if (!$fp) {
        return ['periods' => $totals, 'last' => null, 'timezone' => 'UTC', 'currency' => 'RUB', 'estimate' => true];
    }
    while (($line = fgets($fp)) !== false) {
        $e = json_decode($line, true);
        if (!is_array($e) || !isset($e['at'])) {
            continue;
        }
        $last = $e;
        foreach (['all', 'month', 'today'] as $period) {
            if ($period === 'month' && substr($e['at'], 0, 7) !== $month) {
                continue;
            }
            if ($period === 'today' && substr($e['at'], 0, 10) !== $day) {
                continue;
            }
            $totals[$period]['requests']++;
            foreach (['input', 'output', 'cached', 'reasoning'] as $key) {
                $totals[$period][$key] += max(0, (int) ($e[$key] ?? 0));
            }
            $totals[$period]['rub'] += (float) ($e['rub'] ?? 0);
        }
    }
    fclose($fp);
    foreach ($totals as &$total) {
        $total['rub'] = round($total['rub'], 5);
    }
    return ['periods' => $totals, 'last' => $last, 'timezone' => 'UTC', 'currency' => 'RUB', 'estimate' => true];
}
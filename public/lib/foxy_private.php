<?php
declare(strict_types=1);

/** Shared paths and non-secret settings for Foxy (public site + admin). */
function foxyPrivateDir(): string {
    // public/lib → public → site root (www) → home; private dir is ~/valera-private on Masterhost.
    return dirname(__DIR__, 3) . '/valera-private';
}

function foxyConfigPath(): string {
    return foxyPrivateDir() . '/config.php';
}

function foxyLoadConfig(): ?array {
    $path = foxyConfigPath();
    if (!is_file($path)) {
        return null;
    }
    $config = require $path;
    return is_array($config) ? $config : null;
}

function foxySettingsPath(): string {
    return foxyPrivateDir() . '/admin-settings.json';
}

function foxyDefaultSettings(): array {
    return [
        'model' => null,
        'chat_hourly_limit' => 30,
        'chat_min_interval' => 5,
        'web_search' => true,
        'speech' => [
            'voice' => 'marina',
            'role' => 'friendly',
            'speed' => '0.99',
            'pitchShift' => '-100',
        ],
    ];
}

function foxyLoadSettings(): array {
    $defaults = foxyDefaultSettings();
    $path = foxySettingsPath();
    if (!is_file($path)) {
        return $defaults;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        return $defaults;
    }
    $merged = array_replace_recursive($defaults, $raw);
    foreach (['chat_hourly_limit', 'chat_min_interval'] as $key) {
        $merged[$key] = max(1, min(120, (int) ($merged[$key] ?? $defaults[$key])));
    }
    $merged['web_search'] = (bool) ($merged['web_search'] ?? true);
    if (!is_array($merged['speech'] ?? null)) {
        $merged['speech'] = $defaults['speech'];
    }
    return $merged;
}

function foxySaveSettings(array $settings): void {
    $dir = foxyPrivateDir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось открыть каталог настроек.');
    }
    $path = foxySettingsPath();
    $payload = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($payload === false) {
        throw new RuntimeException('Не удалось подготовить настройки.');
    }
    $tmp = tempnam($dir, '.settings-');
    if ($tmp === false) {
        throw new RuntimeException('Не удалось сохранить настройки.');
    }
    try {
        if (!chmod($tmp, 0600) || file_put_contents($tmp, $payload, LOCK_EX) === false || !rename($tmp, $path)) {
            throw new RuntimeException('Не удалось сохранить настройки.');
        }
        @chmod($path, 0600);
    } finally {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
}

function foxyEffectiveModel(array $config, array $settings): string {
    $fromSettings = $settings['model'] ?? null;
    if (is_string($fromSettings) && $fromSettings !== '') {
        return $fromSettings;
    }
    return (string) ($config['model'] ?? 'qwen3.6-35b-a3b');
}

function foxyEsc(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
<?php
function foxySynthesize(string $reply, array $config): ?string {
    $text = preg_replace('~https?://\S+~iu', '', $reply);
    $text = trim(str_replace(['<', '>', '[', ']', '*', '+'], '', $text));
    if ($text === '') return null;
    $text = preg_replace('/([.!?…])\s+(?=\p{Lu})/u', '$1 sil<[450]> ', $text);
    $payload = json_encode([
        'text' => $text,
        'hints' => [['voice'=>'marina'], ['role'=>'friendly'], ['speed'=>'0.99'], ['pitchShift'=>'-100']],
        'outputAudioSpec' => ['containerAudio'=>['containerAudioType'=>'MP3']],
        'unsafeMode' => true,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) return null;
    $curl = curl_init('https://tts.api.cloud.yandex.net/tts/v3/utteranceSynthesis');
    if ($curl === false) return null;
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Api-Key ' . ($config['speechkit_api_key'] ?? $config['api_key']), 'Content-Type: application/json', 'x-data-logging-enabled: false'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $status !== 200) {
        error_log('Foxy SpeechKit HTTP status: ' . $status);
        return null;
    }
    $audio = '';
    foreach (explode("\n", $response) as $line) {
        if (trim($line) === '') continue;
        $chunk = json_decode($line, true);
        $encoded = $chunk['result']['audioChunk']['data'] ?? null;
        if (!is_string($encoded)) return null;
        $bytes = base64_decode($encoded, true);
        if ($bytes === false) return null;
        $audio .= $bytes;
    }
    return $audio === '' ? null : base64_encode($audio);
}

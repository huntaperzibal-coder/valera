<?php
declare(strict_types=1);

$settings = foxyLoadSettings();
$speech = $settings['speech'] ?? foxyDefaultSettings()['speech'];

echo '<form method="post" class="card">';
echo '<h2>Озвучка Yandex SpeechKit</h2>';
echo '<p>Эти параметры применяются к серверному голосу «Марина» (MP3). Резерв в браузере настраивается на главной странице сайта (Web Speech API).</p>';
echo foxyAdminToken();
echo '<label>Голос<input type="text" name="speech_voice" value="' . foxyEsc((string) $speech['voice']) . '"></label>';
echo '<label>Роль<input type="text" name="speech_role" value="' . foxyEsc((string) $speech['role']) . '"></label>';
echo '<label>Скорость<input type="text" name="speech_speed" value="' . foxyEsc((string) $speech['speed']) . '"></label>';
echo '<label>Сдвиг тона<input type="text" name="speech_pitch" value="' . foxyEsc((string) $speech['pitchShift']) . '"></label>';
echo '<div class="orda-buttons start"><button class="btn-main" type="submit" name="action" value="voice_save">Сохранить</button></div></form>';

echo '<section class="card"><h2>Справка по голосам</h2>';
echo '<p>Для SpeechKit обычно используются голоса <code>marina</code>, <code>alena</code>, <code>ermil</code> и др. Список смотри в документации Yandex Cloud. Отдельный ключ <code>speechkit_api_key</code> можно добавить в config.php при необходимости.</p></section>';
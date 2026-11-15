<?php
declare(strict_types=1);

echo '<div class="orda-buttons start"><a class="btn-secondary" href="https://github.com/huntaperzibal-coder/valera" target="_blank" rel="noopener noreferrer">Открыть репозиторий</a></div>';
echo '<div class="command-panel">';
echo '<div class="command-row"><div><strong>Вход на сервер (Masterhost)</strong><code>ssh u551741@u551741.masterhost.ru</code></div>';
echo '<button class="btn-secondary" type="button" data-copy="ssh u551741@u551741.masterhost.ru">Копировать вход</button></div>';
echo '<div class="command-row"><div><strong>Обновление кода</strong><code>~/valera-sync.sh</code><small>Скрипт делает git pull в ~/valera-repo и rsync public/ → ~/hunta.su/www</small></div>';
echo '<button class="btn-secondary" type="button" data-copy="~/valera-sync.sh">Копировать обновление</button></div>';
echo '<div class="command-row"><div><strong>Ручной pull</strong><code>cd ~/valera-repo &amp;&amp; git pull --ff-only origin main &amp;&amp; rsync -av public/ ~/hunta.su/www/</code></div>';
echo '<button class="btn-secondary" type="button" data-copy="cd ~/valera-repo && git pull --ff-only origin main && rsync -av public/ ~/hunta.su/www/">Копировать</button></div>';
echo '<p class="copy-feedback" role="status" aria-live="polite"></p></div>';
echo '<script defer src="admin.js?v=1"></script>';
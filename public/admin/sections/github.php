<?php
declare(strict_types=1);

echo '<div class="orda-buttons start"><a class="btn-secondary" href="https://github.com/huntaperzibal-coder/valera" target="_blank" rel="noopener noreferrer">Открыть репозиторий</a></div>';
echo '<div class="command-panel">';
$beget = basename(dirname(__DIR__, 2)) === 'public_html';
$host = $beget ? 'Beget' : 'Masterhost';
$login = $beget ? 'ssh altaikemp_foxy@altaikemp.beget.tech' : 'ssh u551741@u551741.masterhost.ru';
$sync = '~/valera-sync.sh';

echo '<div class="command-row"><div><strong>Вход на сервер (' . foxyEsc($host) . ')</strong><code>' . foxyEsc($login) . '</code></div>';
echo '<button class="btn-secondary" type="button" data-copy="' . foxyEsc($login) . '">Копировать вход</button></div>';
echo '<div class="command-row"><div><strong>Обновление кода</strong><code>' . foxyEsc($sync) . '</code><small>Скрипт загружает main из GitHub и выкладывает файлы из public/ в папку сайта.</small></div>';
echo '<button class="btn-secondary" type="button" data-copy="' . foxyEsc($sync) . '">Копировать обновление</button></div>';
echo '<p class="copy-feedback" role="status" aria-live="polite"></p></div>';
echo '<script defer src="admin.js?v=1"></script>';

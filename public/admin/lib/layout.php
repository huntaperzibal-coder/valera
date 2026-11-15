<?php
declare(strict_types=1);

function foxyAdminNav(string $section): void {
    $items = [
        'home' => 'Главная',
        'memory' => 'Память Фокси',
        'ai' => 'Настройки ИИ',
        'voice' => 'Голос Фокси',
        'github' => 'GitHub',
        'backup' => 'Резервное копирование',
        'security' => 'Безопасность',
    ];
    echo '<nav class="side" aria-label="Разделы администратора">';
    echo '<div class="brand"><strong><img src="icon.svg" width="26" height="26" alt="">Тамга</strong><span>Фокси</span></div>';
    echo '<ul class="orda-nav">';
    echo '<li><a class="orda-nav-link" href="../">На сайт</a></li>';
    foreach ($items as $key => $label) {
        $active = $section === $key ? ' active' : '';
        $current = $section === $key ? ' aria-current="page"' : '';
        echo '<li><a class="orda-nav-link' . $active . '"' . $current . ' href="?section=' . foxyEsc($key) . '">' . foxyEsc($label) . '</a></li>';
    }
    echo '</ul>';
    echo '<form class="logout" method="post">' . foxyAdminToken() . '<button class="btn-secondary" name="action" value="logout" type="submit">Выйти</button></form>';
    echo '</nav>';
}

function foxyAdminPageStart(string $section, string $title, string $crumb): void {
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . foxyEsc($title) . ' — админка Фокси</title>';
    echo '<link rel="icon" href="../icons/foxy.svg" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="admin.css?v=1"></head><body>';
    foxyAdminNav($section);
    echo '<main>';
    echo '<div class="orda-breadcrumbs"><a href="../">Фокси</a><span class="separator">›</span><span class="current">' . foxyEsc($crumb) . '</span></div>';
    echo '<h1>' . foxyEsc($title) . '</h1>';
}

function foxyAdminPageEnd(): void {
    echo '</main></body></html>';
}
<?php
declare(strict_types=1);

echo '<section class="card"><h2>Родительский пароль</h2>';
echo '<p>Пароль нужен для входа взрослого на сайте (ИИ и память) и для этой админки. Хранится только как хеш в config.php.</p>';
echo '<form method="post">' . foxyAdminToken();
echo '<label>Новый пароль (от 12 символов)<input type="password" name="new_password" autocomplete="new-password" required minlength="12"></label>';
echo '<label>Повтор<input type="password" name="new_password_repeat" autocomplete="new-password" required minlength="12"></label>';
echo '<div class="orda-buttons start"><button class="btn-main" type="submit" name="action" value="password_change">Сменить пароль</button></div></form></section>';
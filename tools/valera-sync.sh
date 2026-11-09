#!/usr/bin/env bash
set -Eeuo pipefail

REPO="git@github.com:huntaperzibal-coder/valera.git"
BRANCH="main"
SOURCE="$HOME/valera-repo"
WEB_ROOT="$HOME/hunta.su/www"

command -v git >/dev/null || { echo "Ошибка: Git не найден"; exit 1; }
command -v rsync >/dev/null || { echo "Ошибка: rsync не найден"; exit 1; }
[[ -d "$WEB_ROOT" && ! -L "$WEB_ROOT" ]] || { echo "Не найден каталог сайта: $WEB_ROOT"; exit 1; }

if [[ ! -d "$SOURCE/.git" ]]; then
  [[ ! -e "$SOURCE" ]] || { echo "Каталог $SOURCE занят"; exit 1; }
  git clone --branch "$BRANCH" --single-branch "$REPO" "$SOURCE"
fi
cd "$SOURCE"
[[ "$(git remote get-url origin)" == "$REPO" ]] || { echo "Неправильный репозиторий origin"; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "Есть локальные изменения; остановлено"; exit 1; }
git fetch origin "$BRANCH"
git checkout -B "$BRANCH" "origin/$BRANCH"
[[ -f public/index.html ]] || { echo "Нет public/index.html"; exit 1; }
[[ ! -e public/.env && ! -e public/storage && ! -e public/config ]] || { echo "В public обнаружены приватные данные"; exit 1; }

rsync -av --exclude='.git/' --exclude='.env*' --exclude='*.db' --exclude='*.sqlite*' --exclude='storage/' public/ "$WEB_ROOT/"
echo "Готово: $(git rev-parse --short HEAD) → $WEB_ROOT"

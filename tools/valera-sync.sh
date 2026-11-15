#!/usr/bin/env bash
set -Eeuo pipefail

REPO="https://github.com/huntaperzibal-coder/valera.git"
LEGACY_REPO="git@github.com:huntaperzibal-coder/valera.git"
BRANCH="main"
SOURCE="$HOME/valera-repo"
BEGET_ROOT=/home/a/altaikemp/hunta.su/public_html
DEFAULT_WEB_ROOT="$HOME/hunta.su/www"
if [[ "$HOME" == "$BEGET_ROOT" ]]; then
  DEFAULT_WEB_ROOT="$BEGET_ROOT"
fi
WEB_ROOT="${1:-$DEFAULT_WEB_ROOT}"

case "$WEB_ROOT" in
  "$BEGET_ROOT") SOURCE="${BEGET_ROOT%/*}/valera-repo" ;;
  "$HOME/hunta.su/www") ;;
  *) echo "Укажите каталог сайта hunta.su"; exit 1 ;;
esac

command -v git >/dev/null || { echo "Ошибка: Git не найден"; exit 1; }
command -v rsync >/dev/null || { echo "Ошибка: rsync не найден"; exit 1; }
[[ -d "$WEB_ROOT" && ! -L "$WEB_ROOT" ]] || { echo "Не найден каталог сайта: $WEB_ROOT"; exit 1; }

if [[ ! -d "$SOURCE/.git" ]]; then
  [[ ! -e "$SOURCE" ]] || { echo "Каталог $SOURCE занят"; exit 1; }
  git clone --branch "$BRANCH" --single-branch "$REPO" "$SOURCE"
fi
cd "$SOURCE"
origin="$(git remote get-url origin)"
[[ "$origin" == "$REPO" || "$origin" == "$LEGACY_REPO" ]] || { echo "Неправильный репозиторий origin"; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "Есть локальные изменения; остановлено"; exit 1; }
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git merge --ff-only FETCH_HEAD
[[ "$(git rev-parse HEAD)" == "$(git rev-parse FETCH_HEAD)" ]] || { echo "Локальная ветка опережает GitHub"; exit 1; }
[[ -f public/index.html ]] || { echo "Нет public/index.html"; exit 1; }
stage="$(mktemp -d)"
trap 'rm -rf -- "$stage"' EXIT
git archive HEAD public/ | tar -x -C "$stage"
rsync -rcv --delay-updates --exclude='.git/' --exclude='.env*' --exclude='*.db' --exclude='*.sqlite*' \
  --exclude='storage/' --exclude='config/' --exclude='config.php' --exclude='valera-private/' \
  "$stage/public/" "$WEB_ROOT/"
echo "Готово: $(git rev-parse --short HEAD) → $WEB_ROOT"

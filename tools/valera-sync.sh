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
  "$BEGET_ROOT"|"$HOME/hunta.su/www") ;;
  *) echo "Укажите каталог сайта hunta.su" >&2; exit 1 ;;
esac

command -v rsync >/dev/null || { echo "Ошибка: rsync не найден" >&2; exit 1; }
[[ -d "$WEB_ROOT" && ! -L "$WEB_ROOT" ]] || { echo "Не найден каталог сайта: $WEB_ROOT" >&2; exit 1; }
exec 9<"$WEB_ROOT"
flock -n 9 || { echo "Обновление уже запущено или блокировка недоступна" >&2; exit 1; }

stage="$(mktemp -d /tmp/valera-sync.XXXXXXXX)"
trap 'rm -rf -- "$stage"' EXIT
if [[ "$WEB_ROOT" == "$BEGET_ROOT" ]]; then
  archive_url="https://codeload.github.com/huntaperzibal-coder/valera/tar.gz/refs/heads/$BRANCH"
  if curl -fLsS --connect-timeout 10 --max-time 90 "$archive_url" -o "$stage/release.tar.gz"; then
    tar -xzf "$stage/release.tar.gz" --strip-components=1 -C "$stage"
    version="$BRANCH (GitHub)"
  else
    echo "Архив GitHub недоступен. Пробую Git через HTTPS." >&2
    timeout 90 env GIT_TERMINAL_PROMPT=0 git clone --depth 1 --branch "$BRANCH" --single-branch "$REPO" "$stage/repo"
    git -C "$stage/repo" archive HEAD public/ | tar -x -C "$stage"
    version="$(git -C "$stage/repo" rev-parse HEAD)"
  fi
  php=/usr/local/bin/php8.2
else
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
  git archive HEAD public/ | tar -x -C "$stage"
  version="$(git rev-parse HEAD)"
  php=php
fi

for file in index.html api/chat.php api/_paths.php admin/index.php; do
  [[ -f "$stage/public/$file" ]] || { echo "В релизе отсутствует public/$file" >&2; exit 1; }
done
find "$stage/public" -type f -name '*.php' -exec bash -c '
  php=$1
  shift
  for file do "$php" -l "$file" || exit; done
' bash "$php" {} + >/dev/null
rsync -rcv --delay-updates --exclude='.git/' --exclude='.env*' --exclude='*.db' --exclude='*.sqlite*' \
  --exclude='storage/' --exclude='config/' --exclude='config.php' --exclude='valera-private/' \
  "$stage/public/" "$WEB_ROOT/"
echo "Готово: $version → $WEB_ROOT"

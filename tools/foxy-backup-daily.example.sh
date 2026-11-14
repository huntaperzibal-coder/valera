#!/usr/bin/env bash
# Пример cron на Masterhost: ежедневный restic-бэкап (запускать только после настройки в админке).
# 15 3 * * * /home/u551741/valera-private/foxy-backup-daily.sh
set -Eeuo pipefail
PRIVATE="$HOME/valera-private"
RESTIC="$PRIVATE/restic"
CRED="$PRIVATE/selectel.json"
[[ -x "$RESTIC" && -f "$CRED" ]] || exit 0
export $(php -r '$c=json_decode(file_get_contents($argv[1]),true); foreach($c as $k=>$v) echo $k."=".escapeshellarg($v)." ";' "$CRED")
export AWS_DEFAULT_REGION=ru-3
export RESTIC_REPOSITORY=s3:https://s3.ru-3.storage.selcloud.ru/hunta-backups/valera/
STAGE="$PRIVATE/backups/.cron-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$STAGE"
cp -a "$PRIVATE/config.php" "$PRIVATE/admin-settings.json" "$PRIVATE/memory.json" "$STAGE/" 2>/dev/null || true
[[ -f "$PRIVATE/foxy-memory.sqlite" ]] && sqlite3 "$PRIVATE/foxy-memory.sqlite" "VACUUM INTO '$STAGE/foxy-memory.sqlite';"
"$RESTIC" --no-cache backup --tag valera --tag daily "$STAGE"
rm -rf "$STAGE"
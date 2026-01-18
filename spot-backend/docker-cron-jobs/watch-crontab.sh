#!/bin/bash

CRONTAB_SOURCE="/app/laravel-cron/crontab.txt"
CRONTAB_TARGET="/etc/cron.d/laravel-cron"

echo "👀 Watching for changes to crontab.txt..."

# Lặp vô hạn để theo dõi thay đổi
while inotifywait -e close_write "$CRONTAB_SOURCE"; do
    echo "🔄 crontab.txt changed, reloading..."

    if [ -f "$CRONTAB_SOURCE" ]; then
        cp "$CRONTAB_SOURCE" "$CRONTAB_TARGET"
        chmod 0644 "$CRONTAB_TARGET"
        crontab "$CRONTAB_TARGET"
        echo "✅ Crontab reloaded."
    else
        echo "❌ Crontab source file not found!"
    fi
done

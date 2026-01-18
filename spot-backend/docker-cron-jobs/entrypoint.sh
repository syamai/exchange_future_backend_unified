#!/bin/bash

cd /app || exit 1

TODAY=$(date '+%Y-%m-%d')
LOG_DIR="/logs"
LOG_FILE="$LOG_DIR/cron-$TODAY.log"
CRONTAB_SOURCE="/app/laravel-cron/crontab.txt"
CRONTAB_TARGET="/etc/cron.d/laravel-cron"

# Tạo thư mục log và dọn dẹp log cũ
mkdir -p "$LOG_DIR"
find "$LOG_DIR" -type f -name "cron-*.log" -mtime +7 -delete
touch "$LOG_FILE"

echo "🔄 Reloading crontab file..."
if [ -f "$CRONTAB_SOURCE" ]; then
    cp "$CRONTAB_SOURCE" "$CRONTAB_TARGET"
    chmod 0644 "$CRONTAB_TARGET"
    crontab "$CRONTAB_TARGET"
else
    echo "❌ Crontab source file not found: $CRONTAB_SOURCE"
    exit 1
fi

if [ ! -f "vendor/autoload.php" ]; then
    echo "📦 vendor/autoload.php not found. Running composer install..."
    composer install --no-dev --optimize-autoloader || {
        echo "❌ Composer install failed!"
        exit 1
    }
else
    echo "✅ vendor/autoload.php exists."
fi


if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
    php artisan key:generate
fi

echo "⏳ Waiting for Redis..."
until nc -z redis 6379; do
  echo "⌛ Redis not ready yet. Retrying..."
  sleep 2
done
echo "✅ Redis is ready!"


echo "⏳ Waiting for DB..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "⌛ Retrying DB connection..."
    sleep 2
done

# php artisan migrate --force

echo "🕓 Starting cron daemon in background..."
/usr/sbin/cron -f &

# Start watching crontab.txt for changes
/app/laravel-cron/watch-crontab.sh &

echo "📄 Tailing $LOG_FILE..."
tail -f "$LOG_FILE"

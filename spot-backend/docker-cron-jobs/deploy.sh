#!/bin/bash

APP_DIR="../backend"
CONTAINER_NAME="laravel_cron"
LOG_DIR="./logs"
TODAY=$(date '+%Y-%m-%d')
LOG_FILE="$LOG_DIR/cron-$TODAY.log"

# Tạo thư mục log nếu chưa có
mkdir -p "$LOG_DIR"
chmod 777 "$LOG_DIR"

# Dọn log cũ >7 ngày
echo "🧹 Cleaning logs older than 7 days..."
find "$LOG_DIR" -type f -name "cron-*.log" -mtime +7 -delete

# Kiểm tra thư mục Laravel
if [ ! -d "$APP_DIR" ]; then
  echo "❌ Laravel source folder not found: $APP_DIR"
  exit 1
fi

# Build Docker image
echo "📦 Building Docker container..."
docker compose build

# Khởi động container
echo "🚀 Starting Laravel cron container..."
docker compose up -d

# Xóa image treo
docker image prune -f

# Chờ container khởi động
echo "⏳ Waiting 5s for container startup..."
sleep 5

# Kiểm tra container có chạy không
if docker ps --format '{{.Names}}' | grep -q "^$CONTAINER_NAME$"; then
  echo "✅ Container '$CONTAINER_NAME' is running."
  echo "📄 Showing latest 10 lines from today's log:"
  docker exec -it $CONTAINER_NAME tail -n 10 "/logs/cron-$TODAY.log"
else
  echo "❌ Container '$CONTAINER_NAME' is not running. Check with: docker compose logs"
fi

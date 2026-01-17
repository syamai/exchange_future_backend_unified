cd /home/ubuntu/DRX-Matching-Engine
git pull origin main

# check to create folder to save old logs
[ -d /home/ubuntu/me-logs ] || mkdir -p /home/ubuntu/me-logs

# save old log
NOW=$(date +"%Y-%m-%d_%H-%M-%S")
CONTAINER_ID=$(docker inspect --format="{{.Id}}" ticker-engine)
sudo cp /var/lib/docker/containers/$CONTAINER_ID/$CONTAINER_ID-json.log /home/ubuntu/me-logs/log-$NOW.txt

# run ticker Engine
docker rm -f ticker-engine
docker compose -f docker-compose-ticker.dev.yml up -d --build

h=$1
deploy=$2
ssh $h "mkdir -p /var/www/amanpuri-api"
if [ $deploy -eq "1" ]; then
    rsync -avhzL --delete \
            --no-perms --no-owner --no-group \
            --exclude .git \
            --exclude .idea \
            --exclude .env \
            --exclude bootstrap/cache \
            --exclude storage/logs \
            --exclude storage/framework \
            --exclude storage/app \
            --exclude public/storage \
            /root/amanpuri-api/ $h:/var/www/amanpuri-api/
    exit;
fi
rsync -avhzL --delete \
            --no-perms --no-owner --no-group \
            --exclude .git \
            --exclude .idea \
            --exclude .env \
            --exclude bootstrap/cache \
            --exclude storage/logs \
            --exclude storage/framework \
            --exclude storage/app \
            --exclude public/storage \
	    --dry-run \
            /root/amanpuri-api/ $h:/var/www/amanpuri-api/

ssh $h "cd /var/www/amanpuri-api && make reset-queue"

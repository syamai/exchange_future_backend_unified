h=$1
deploy=$2
ssh $h "mkdir -p /var/www/amanpuri-api"
ssh $h "mkdir -p /var/www/amanpuri-api/storage/logs/healthcheck"
if [ "$deploy" = "1" ]; then
    rsync -avhzL --delete --no-perms --no-owner --no-group \
        --exclude "app/*" \
        --exclude "bin/deploy/*" \
        --exclude "bin/queue/*" \
        --exclude "bitgo-express/*" \
        --exclude "bootstrap/*" \
        --exclude "config/*" \
        --exclude "credentials/*" \
        --exclude "database/*" \
        --exclude "docker/*" \
        --exclude "microservices/*" \
        --exclude "modules/*" \
        --exclude "node_modules/*" \
        --exclude "public/*" \
        --exclude "resources/*" \
        --exclude "routes/*" \
        --exclude "sota_wallet/*" \
        --exclude "storage/*" \
        --exclude "tests/*" \
        --exclude ".git/*" \
        --exclude "vendor/*" /root/amanpuri-api/ $h:/var/www/amanpuri-api/
    exit;
fi
rsync -avhzL --delete --no-perms --no-owner --no-group \
        --exclude "app/*" \
        --exclude "bin/deploy/*" \
        --exclude "bin/queue/*" \
        --exclude "bitgo-express/*" \
        --exclude "bootstrap/*" \
        --exclude "config/*" \
        --exclude "credentials/*" \
        --exclude "database/*" \
        --exclude "docker/*" \
        --exclude "microservices/*" \
        --exclude "modules/*" \
        --exclude "node_modules/*" \
        --exclude "public/*" \
        --exclude "resources/*" \
        --exclude "routes/*" \
        --exclude "sota_wallet/*" \
        --exclude "storage/*" \
        --exclude "tests/*" \
        --exclude ".git/*" \
        --exclude "vendor/*" --dry-run  /root/amanpuri-api/ $h:/var/www/amanpuri-api/
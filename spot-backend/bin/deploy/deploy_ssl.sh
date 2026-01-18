host="$1"
scp ./ssl/amanpuri.ca-bundle $host:/etc/ssl/
scp ./ssl/amanpuri.crt $host:/etc/ssl/
scp ./ssl/amanpuri.key $host:/etc/ssl/

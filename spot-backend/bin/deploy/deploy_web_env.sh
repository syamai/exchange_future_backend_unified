scp /root/amanpuri-api/.env amanpuri-web1:/var/www/amanpuri-api
ssh amanpuri-web1 "apachectl restart"
scp /root/amanpuri-api/.env amanpuri-queue:/var/www/amanpuri-api
ssh amanpuri-queue "supervisorctl restart amanpuri-worker:amanpuri-worker_00"

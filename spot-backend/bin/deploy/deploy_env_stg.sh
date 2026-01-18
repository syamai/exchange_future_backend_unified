scp /root/amanpuri-api/.env stg-amanpuri-web1:/var/www/amanpuri-api
ssh stg-amanpuri-web1 "apachectl restart"

scp /root/amanpuri-api/.env stg-amanpuri-web1-lp:/var/www/amanpuri-api
ssh stg-amanpuri-web1-lp "apachectl restart"

scp /root/amanpuri-api/.env stg-amanpuri-queue:/var/www/amanpuri-api
ssh stg-amanpuri-queue "supervisorctl restart amanpuri-worker:amanpuri-worker_00"
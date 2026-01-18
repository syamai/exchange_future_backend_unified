set -x;
./deploy.sh web1 1
./deploy.sh queue 1
ssh amanpuri-queue "supervisorctl restart amanpuri-worker:amanpuri-worker_00"

./send_deploy_mail.sh amanpuri.ops@sotatek.com

RABBITMQ_PREFIX=$1
php artisan queue:work rabbitmq --queue=${RABBITMQ_PREFIX}margin_order --sleep=0.1
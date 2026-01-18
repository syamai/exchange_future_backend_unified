RABBITMQ_PREFIX=$1
php artisan queue:work rabbitmq --queue=${RABBITMQ_PREFIX}place_order_on_bitmex --sleep=0.1
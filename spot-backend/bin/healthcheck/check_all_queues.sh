email=$1

date=`date`
time=`date +%s`

if [ -z $email ]; then
    echo "email is required."
    echo "Checked $queue at $time $date"
    exit
fi

./bin/healthcheck/order_processor.sh $email
./bin/healthcheck/queue.sh $email 02_send_orderbook
./bin/healthcheck/queue.sh $email 03_send_user_orderbook
./bin/healthcheck/queue.sh $email 04_send_balance
./bin/healthcheck/queue.sh $email 05_send_order_list
./bin/healthcheck/queue.sh $email 06_send_prices
./bin/healthcheck/queue.sh $email 07_send_order_event
./bin/healthcheck/queue.sh $email 21_update_user_transaction
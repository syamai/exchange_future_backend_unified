#!/bin/bash

env=$1

env_path=`pwd`/.env

aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)


host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
database=`grep ^DB_DATABASE= $env_path | cut -d'=' -f 2`
username=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
password=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`

last_trade_query="SELECT id FROM order_transactions ORDER BY id DESC LIMIT 1;"
last_trade_id=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$last_trade_query"`

last_processed_price_query="SELECT processed_id FROM processes WHERE \`key\`='update_spot_price' limit 1;"
last_processed_price_id=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$last_processed_price_query"`

last_processed_orderbook_query="SELECT processed_id FROM processes WHERE \`key\`='update_spot_orderbook' limit 1;"
last_processed_orderbook_id=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$last_processed_orderbook_query"`

diff_price=$(( $last_trade_id - $last_processed_price_id ))
diff_orderbook=$(( $last_trade_id - $last_processed_orderbook_id ))

/usr/bin/aws cloudwatch put-metric-data \
    --metric-name "${env}SpotPriceDelay" \
    --dimensions InstanceId=$aws_instance \
    --namespace "SpotExchange" \
    --value $diff_price

/usr/bin/aws cloudwatch put-metric-data \
    --metric-name "${env}SpotOrderbookDelay" \
    --dimensions InstanceId=$aws_instance \
    --namespace "SpotExchange" \
    --value $diff_orderbook

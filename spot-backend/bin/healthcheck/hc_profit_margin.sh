#!/bin/sh

env=$1

env_path=`pwd`/.env

aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)


host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
database=`grep ^DB_DATABASE= $env_path | cut -d'=' -f 2`
username=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
password=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`

last_history_query="SELECT id FROM margin_history ORDER BY id DESC LIMIT 1;"
last_history_id=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$last_history_query"`

last_processed_query="SELECT processed_id FROM processes WHERE \`key\`='update_margin_profit' limit 1;"
last_processed_id=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$last_processed_query"`

diff=$(( $last_history_id - $last_processed_id ))

/usr/bin/aws cloudwatch put-metric-data \
    --metric-name "${env}MarginProfit" \
    --dimensions InstanceId=$aws_instance \
    --namespace "MarginExchange" \
    --value $diff

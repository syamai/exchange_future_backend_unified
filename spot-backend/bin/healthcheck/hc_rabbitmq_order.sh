#!/bin/sh

env=$1

env_path=`pwd`/.env

host=`grep ^RABBITMQ_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^RABBITMQ_PORT= $env_path | cut -d'=' -f 2`
username=`grep ^RABBITMQ_LOGIN= $env_path | cut -d'=' -f 2`
password=`grep ^RABBITMQ_PASSWORD= $env_path | cut -d'=' -f 2`

aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)
# aws_instance=`grep ^AWS_INSTANCE= $env_path | cut -d'=' -f 2`

margin_order=$( /usr/sbin/rabbitmqctl list_queues name messages_ready_ram | grep '\<margin_order\>' )
margin_order_value=$(echo "$margin_order" | grep -o -E '[0-9]+')
echo "$margin_order_value"
/usr/bin/aws cloudwatch put-metric-data --metric-name "${env}MarginNewOrder" --dimensions InstanceId=$aws_instance  --namespace "Rabbitmq" --value $margin_order_value

order=$( /usr/sbin/rabbitmqctl list_queues name messages_ready_ram | grep '\<order\>' )
order_value=$(echo "$order" | grep -o -E '[0-9]+')
echo "$order_value"
/usr/bin/aws cloudwatch put-metric-data --metric-name "${env}SpotNewOrder" --dimensions InstanceId=$aws_instance  --namespace "Rabbitmq" --value $order_value

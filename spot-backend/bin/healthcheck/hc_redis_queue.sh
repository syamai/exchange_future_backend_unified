#!/bin/sh

env=$1

env_path=`pwd`/.env

host=`grep ^REDIS_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^REDIS_PORT= $env_path | cut -d'=' -f 2`
aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)

IFS=','
read -ra queues <<< "SendOrderBook,SendBalance,SendOrderList,SendPrices,SendOrderEvent,CalculateAndRefundReferral"

for queue in "${queues[@]}"; do
    value=$(redis-cli -h $host -p $port -n 1 zcount $queue -9000000000000000 9000000000000000)
    /usr/local/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}Spot$queue" \
        --dimensions InstanceId=$aws_instance \
        --namespace "Redis" \
        --value $value
done

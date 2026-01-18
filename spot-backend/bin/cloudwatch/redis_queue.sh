#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/redis_queue.sh Testnet i-062193837f2e1026a "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/redis_queue.sh Mainnet i-066fad1b8d63294fe "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

IFS=','
read -ra queues <<< "SendOrderBook,SendBalance,SendOrderList,SendPrices,SendOrderEvent,CalculateAndRefundReferral"

for queue in "${queues[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri${env}Spot$queue" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name ${env}Spot$queue \
        --namespace Redis \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 50 \
        --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1
done

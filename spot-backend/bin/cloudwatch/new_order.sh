#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/new_order.sh Testnet i-02c4ee409d10f4245 "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/new_order.sh Mainnet i-0306b35e02a6dba66 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

IFS=','
read -ra metrics <<< "SpotNewOrder,MarginNewOrder"

for metric in "${metrics[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri$env$metric" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name $env$metric \
        --namespace Rabbitmq \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 500 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 1
done

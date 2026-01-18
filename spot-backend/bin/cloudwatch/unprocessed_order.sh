#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/unprocessed_order.sh Testnet i-062193837f2e1026a "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/unprocessed_order.sh Mainnet i-066fad1b8d63294fe "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

IFS=','
read -ra metrics <<< "SpotUnprocessedOrder,MarginUnprocessedOrder"

for metric in "${metrics[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri$env$metric" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name $env$metric \
        --namespace Redis \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 200 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 1
done

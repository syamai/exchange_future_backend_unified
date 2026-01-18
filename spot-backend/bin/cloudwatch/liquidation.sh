#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/liquidation.sh Testnet i-054dedcbe033e5fbe "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/liquidation.sh Mainnet i-07922e8bcc2dd5096 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3


IFS=','
read -ra metrics <<< "MarginLiquidation,MarginAutoDeleverage"

for metric in "${metrics[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri${env}${metric}" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name ${env}${metric} \
        --namespace MarginExchange \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 100 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 1
done

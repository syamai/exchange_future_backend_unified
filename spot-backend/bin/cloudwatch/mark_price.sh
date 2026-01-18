#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/mark_price.sh Testnet BTCUSD,ETHUSD,ETHBTC i-010b3982b1704f3ed "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/mark_price.sh Mainnet BTCUSD,ETHUSD,ETHBTC i-0496153c9e78235bb "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1

IFS=','
read -ra symbols <<< "$2"

instanceId=$3
topic=$4


for symbol in "${symbols[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri${env}MarkPrice$symbol" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name ${env}MarkPrice$symbol \
        --namespace MarginExchange \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Minimum \
        --period 60 \
        --threshold 5 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 3
done

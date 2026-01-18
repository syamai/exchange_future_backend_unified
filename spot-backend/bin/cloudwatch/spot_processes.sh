#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/spot_processes.sh Testnet i-062193837f2e1026a "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/spot_processes.sh Mainnet i-066fad1b8d63294fe "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

IFS=','
read -ra metrics <<< "SpotPriceDelay,SpotOrderbookDelay,SpotTradingVolumeRanking,SpotAmalNetHolding"

for metric in "${metrics[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri$env$metric" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name $env$metric \
        --namespace SpotExchange \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 100 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 1
done

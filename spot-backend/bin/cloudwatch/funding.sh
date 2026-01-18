#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/funding.sh Testnet i-062193837f2e1026a "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/funding.sh Mainnet i-066fad1b8d63294fe "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

IFS=','
read -ra metrics <<< "MarginFundingRate,MarginFundingProcess"

for metric in "${metrics[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "Amanpuri$env$metric" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name $env$metric \
        --namespace MarginExchange \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Minimum \
        --period 60 \
        --threshold 0 \
        --comparison-operator LessThanOrEqualToThreshold \
        --evaluation-periods 1
done

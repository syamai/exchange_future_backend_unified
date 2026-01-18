#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/stop_order.sh Testnet Spot i-0ae6af4c991653bc3 "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"
./bin/cloudwatch/stop_order.sh Testnet Margin i-054dedcbe033e5fbe "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/stop_order.sh Mainnet Spot i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"
./bin/cloudwatch/stop_order.sh Mainnet Margin i-07922e8bcc2dd5096 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
exchange=$2
instanceId=$3
topic=$4


aws cloudwatch put-metric-alarm \
    --alarm-name "Amanpuri${env}${exchange}StopOrder" \
    --alarm-actions "$topic" \
    --insufficient-data-actions "$topic" \
    --metric-name ${env}${exchange}StopOrder \
    --namespace ${exchange}Exchange \
    --dimensions "Name=InstanceId,Value=$instanceId" \
    --statistic Maximum \
    --period 60 \
    --threshold 100 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1

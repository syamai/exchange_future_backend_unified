#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/ifd_order.sh Testnet i-054dedcbe033e5fbe "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/ifd_order.sh Mainnet i-07922e8bcc2dd5096 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3


aws cloudwatch put-metric-alarm \
    --alarm-name "Amanpuri${env}MarginIFDOrder" \
    --alarm-actions "$topic" \
    --insufficient-data-actions "$topic" \
    --metric-name ${env}MarginIFDOrder \
    --namespace MarginExchange \
    --dimensions "Name=InstanceId,Value=$instanceId" \
    --statistic Maximum \
    --period 60 \
    --threshold 100 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1

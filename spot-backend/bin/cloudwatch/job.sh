#!/bin/bash

: '
Example:

* Testnet:
./bin/cloudwatch/job.sh Testnet i-062193837f2e1026a "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

*Mainnet:
./bin/cloudwatch/job.sh Mainnet i-066fad1b8d63294fe "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

'

env=$1
instanceId=$2
topic=$3

aws cloudwatch put-metric-alarm \
    --alarm-name "Amanpuri${env}PendingJobs" \
    --alarm-actions "$topic" \
    --insufficient-data-actions "$topic" \
    --metric-name ${env}PendingJobs \
    --namespace Common \
    --dimensions "Name=InstanceId,Value=$instanceId" \
    --statistic Maximum \
    --period 300 \
    --threshold 50 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1

aws cloudwatch put-metric-alarm \
    --alarm-name "Amanpuri${env}FailedJobs" \
    --alarm-actions "$topic" \
    --insufficient-data-actions "$topic" \
    --metric-name ${env}FailedJobs \
    --namespace Common \
    --dimensions "Name=InstanceId,Value=$instanceId" \
    --statistic Maximum \
    --period 300 \
    --threshold 20 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1
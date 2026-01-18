#!/bin/bash

: '
Example:

* Margin tesnet:
./bin/cloudwatch/matching_engine.sh \
    TestnetMarginBTCUSD,TestnetMarginETHUSD,TestnetMarginETHBTC \
    i-054dedcbe033e5fbe "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"


* Spot testnet:

./bin/cloudwatch/matching_engine.sh \
    TestnetSpotUSDTADA,TestnetSpotUSDTAMAL,TestnetSpotUSDTBCH,TestnetSpotUSDTBTC,TestnetSpotUSDTEOS,TestnetSpotUSDTETH,TestnetSpotUSDTLTC,TestnetSpotUSDTXRP \
    i-0ae6af4c991653bc3 "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

./bin/cloudwatch/matching_engine.sh \
    TestnetSpotUSDADA,TestnetSpotUSDAMAL,TestnetSpotUSDBCH,TestnetSpotUSDBTC,TestnetSpotUSDEOS,TestnetSpotUSDETH,TestnetSpotUSDLTC,TestnetSpotUSDXRP \
    i-0ae6af4c991653bc3 "arn:aws:sns:ap-northeast-1:554611635276:testnet-monitor"

./bin/cloudwatch/matching_engine.sh \
    TestnetSpotBTCADA,TestnetSpotBTCAMAL,TestnetSpotBTCBCH,TestnetSpotBTCEOS,TestnetSpotBTCETH,TestnetSpotBTCLTC,TestnetSpotBTCXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

./bin/cloudwatch/matching_engine.sh \
    TestnetSpotETHADA,TestnetSpotETHAMAL,TestnetSpotETHBCH,TestnetSpotETHEOS,TestnetSpotETHLTC,TestnetSpotETHXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"



* Margin mainnet:
./bin/cloudwatch/matching_engine.sh \
    MainnetMarginBTCUSD,MainnetMarginETHUSD,MainnetMarginETHBTC \
    i-07922e8bcc2dd5096 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"


* Spot mainnet:

./bin/cloudwatch/matching_engine.sh \
    MainnetSpotUSDTADA,MainnetSpotUSDTAMAL,MainnetSpotUSDTBCH,MainnetSpotUSDTBTC,MainnetSpotUSDTEOS,MainnetSpotUSDTETH,MainnetSpotUSDTLTC,MainnetSpotUSDTXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

./bin/cloudwatch/matching_engine.sh \
    MainnetSpotUSDADA,MainnetSpotUSDAMAL,MainnetSpotUSDBCH,MainnetSpotUSDBTC,MainnetSpotUSDEOS,MainnetSpotUSDETH,MainnetSpotUSDLTC,MainnetSpotUSDXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

./bin/cloudwatch/matching_engine.sh \
    MainnetSpotBTCADA,MainnetSpotBTCAMAL,MainnetSpotBTCBCH,MainnetSpotBTCEOS,MainnetSpotBTCETH,MainnetSpotBTCLTC,MainnetSpotBTCXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"

./bin/cloudwatch/matching_engine.sh \
    MainnetSpotETHADA,MainnetSpotETHAMAL,MainnetSpotETHBCH,MainnetSpotETHEOS,MainnetSpotETHLTC,MainnetSpotETHXRP \
    i-0e855b4a40d3b96a7 "arn:aws:sns:ap-northeast-1:554611635276:prod-monitor"
'

IFS=','
read -ra symbols <<< "$1"
instanceId=$2
topic=$3

for symbol in "${symbols[@]}"; do
    aws cloudwatch put-metric-alarm \
        --alarm-name "AmanpuriMatchingEngine$symbol" \
        --alarm-actions "$topic" \
        --insufficient-data-actions "$topic" \
        --metric-name $symbol \
        --namespace MatchingEngine \
        --dimensions "Name=InstanceId,Value=$instanceId" \
        --statistic Maximum \
        --period 60 \
        --threshold 1500 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 1
done

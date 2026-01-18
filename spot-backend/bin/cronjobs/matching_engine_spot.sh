#!/bin/bash

crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet SpotUSDTADA,SpotUSDTAMAL,SpotUSDTBCH,SpotUSDTBTC,SpotUSDTEOS,SpotUSDTETH,SpotUSDTLTC,SpotUSDTXRP"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 5 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet SpotUSDADA,SpotUSDAMAL,SpotUSDBCH,SpotUSDBTC,SpotUSDEOS,SpotUSDETH,SpotUSDLTC,SpotUSDXRP"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 10 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet SpotETHADA,SpotETHAMAL,SpotETHBCH,SpotETHEOS,SpotETHLTC,SpotETHXRP"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 15 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet SpotBTCADA,SpotBTCAMAL,SpotBTCBCH,SpotBTCEOS,SpotBTCETH,SpotBTCLTC,SpotBTCXRP"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 20 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_stop_order.sh Mainnet Spot"; } | crontab -


# Testnet
# crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet SpotUSDTADA,SpotUSDTAMAL,SpotUSDTBCH,SpotUSDTBTC,SpotUSDTEOS,SpotUSDTETH,SpotUSDTLTC,SpotUSDTXRP"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 5 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet SpotUSDADA,SpotUSDAMAL,SpotUSDBCH,SpotUSDBTC,SpotUSDEOS,SpotUSDETH,SpotUSDLTC,SpotUSDXRP"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 10 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet SpotETHADA,SpotETHAMAL,SpotETHBCH,SpotETHEOS,SpotETHLTC,SpotETHXRP"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 15 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet SpotBTCADA,SpotBTCAMAL,SpotBTCBCH,SpotBTCEOS,SpotBTCETH,SpotBTCLTC,SpotBTCXRP"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 20 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_stop_order.sh Testnet Spot"; } | crontab -

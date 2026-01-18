#!/bin/bash

crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet MarginBTCUSD,MarginETHUSD,MarginETHBTC"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Mainnet MarginBTCUSD,MarginETHUSD,MarginETHBTC"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 5 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_stop_order.sh Mainnet Margin"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 10 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_ifd_order.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 15 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_liquidate.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 20 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_auto_deleverage.sh Mainnet"; } | crontab -


# Testnet
# crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet MarginBTCUSD,MarginETHUSD,MarginETHBTC"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_matching_engine.sh Testnet MarginBTCUSD,MarginETHUSD,MarginETHBTC"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 5 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_stop_order.sh Testnet Margin"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 10 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_ifd_order.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 15 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_liquidate.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 20 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_auto_deleverage.sh Testnet"; } | crontab -

#!/bin/bash

crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_margin_mark_price.sh Mainnet BTCUSD,ETHUSD,ETHBTC"; } | crontab -


# Testnet
# crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_margin_mark_price.sh Testnet BTCUSD,ETHUSD,ETHBTC"; } | crontab -

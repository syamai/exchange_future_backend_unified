#!/bin/bash

crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_rabbitmq_order.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_rabbitmq_order.sh Mainnet"; } | crontab -

# Testnet
# crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_rabbitmq_order.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_rabbitmq_order.sh Testnet"; } | crontab -

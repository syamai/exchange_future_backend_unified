#!/bin/bash

crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_redis_order.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_redis_order.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_update_price_orderbook.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_funding.sh Mainnet BTCUSD,ETHUSD,ETHBTC"; } | crontab -
crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_margin_ami_index.sh Mainnet BTC,ETH,ETH_BTC"; } | crontab -
crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_pending_jobs.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_profit_margin.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_leaderbook.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_trading_volume_ranking.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_amal_net.sh Mainnet"; } | crontab -
crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_redis_queue.sh Mainnet"; } | crontab -

# Testnet
# crontab -l | { cat; echo "* * * * * sleep 1 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_redis_order.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * sleep 31 && cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_redis_order.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_update_price_orderbook.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_funding.sh Testnet BTCUSD,ETHUSD,ETHBTC"; } | crontab -
# crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && ./bin/healthcheck/hc_margin_ami_index.sh Testnet BTC,ETH,ETH_BTC"; } | crontab -
# crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_pending_jobs.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_profit_margin.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_leaderbook.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_trading_volume_ranking.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "*/5 * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_amal_net.sh Testnet"; } | crontab -
# crontab -l | { cat; echo "* * * * * cd /var/www/amanpuri-api/ && sh ./bin/healthcheck/hc_redis_queue.sh Testnet"; } | crontab -

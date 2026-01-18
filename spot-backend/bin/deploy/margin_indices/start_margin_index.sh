cd /var/www/amanpuri-api

#pm2 start ./bin/queue/27_trading_volume_ranking_margin.sh
#pm2 start ./bin/queue/28_margin_update_leaderboard.sh
#pm2 start ./bin/queue/29_margin_update_profit.sh
#pm2 start ./bin/queue/30_margin_entry_leaderboard.sh
#pm2 start ./bin/queue/34_margin_entry_balance_history.sh

pm2 startOrRestart ./bin/deploy/margin_indices/indices.config.js
#pm2 start ./bin/deploy/margin_entry/margin_entry.config.js

sh ./bin/deploy/margin_indices/start_perpetual.sh BTCUSD
sh ./bin/deploy/margin_indices/start_perpetual.sh ETHUSD
sh ./bin/deploy/margin_indices/start_perpetual.sh ETHBTC

# sh ./bin/deploy/margin_indices/start_future.sh BTCH20
# sh ./bin/deploy/margin_indices/start_future.sh BTCM20
# sh ./bin/deploy/margin_indices/start_future.sh ADAH20
# sh ./bin/deploy/margin_indices/start_future.sh BCHH20
# sh ./bin/deploy/margin_indices/start_future.sh EOSH20
# sh ./bin/deploy/margin_indices/start_future.sh LTCH20
# sh ./bin/deploy/margin_indices/start_future.sh TRXH20
# sh ./bin/deploy/margin_indices/start_future.sh XRPH20
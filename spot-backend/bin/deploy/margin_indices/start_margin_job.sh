cd /var/www/amanpuri-api

pm2 start ./bin/queue/14_process_margin_order.sh
pm2 start ./bin/queue/17_cancel_margin_order.sh
pm2 start ./bin/deploy/margin_indices/trigger_stop_order.json
pm2 start ./bin/deploy/margin_indices/trigger_ifd_order.json
pm2 start ./bin/deploy/margin_indices/liquidation.json
#pm2 start ./bin/deploy/margin_indices/auto_deleverage.json
pm2 start ./bin/deploy/margin_indices/close_reduce_only.json
pm2 start ./microservices/margin/matching_engines.js
#pm2 startOrRestart ./bin/deploy/mam/mam_closing.json
#pm2 start ./bin/queue/32_margin_update_mapping_order.sh
#pm2 start ./bin/queue/33_place_order_on_bitmex.sh
#pm2 start ./bin/queue/35_margin_update_referral.sh
#pm2 start ./bin/queue/37_calculate_pnl_profit.sh
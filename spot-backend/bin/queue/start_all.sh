prefix=$1
if test -z "$prefix"
then
  prefix="exchange"
fi
prefix="${prefix}_"

pm2 start ./bin/queue/01_order_processor.sh --name "${prefix}01_order_processor" >/dev/null 2>/dev/null
pm2 start ./bin/queue/02_send_orderbook.sh --name "${prefix}02_send_orderbook" >/dev/null 2>/dev/null
pm2 start ./bin/queue/03_send_user_orderbook.sh --name "${prefix}03_send_user_orderbook" >/dev/null 2>/dev/null
pm2 start ./bin/queue/04_send_balance.sh --name "${prefix}04_send_balance" >/dev/null 2>/dev/null
pm2 start ./bin/queue/05_send_order_list.sh --name "${prefix}05_send_order_list" >/dev/null 2>/dev/null
pm2 start ./bin/queue/06_send_prices.sh --name "${prefix}06_send_prices" >/dev/null 2>/dev/null
pm2 start ./bin/queue/07_send_order_event.sh --name "${prefix}07_send_order_event" >/dev/null 2>/dev/null
pm2 start ./bin/queue/08_send_admin_notification.sh --name "${prefix}08_send_admin_notification" >/dev/null 2>/dev/null
pm2 start ./bin/queue/09_send_favorite_symbols.sh --name "${prefix}09_send_favorite_symbols" >/dev/null 2>/dev/null
pm2 start ./bin/queue/10_update_price.sh --name "${prefix}10_update_price" >/dev/null 2>/dev/null
pm2 start ./bin/queue/11_update_orderbook.sh --name "${prefix}11_update_orderbook" >/dev/null 2>/dev/null
pm2 start ./bin/queue/12_trigger_stop_order.sh --name "${prefix}12_trigger_stop_order" >/dev/null 2>/dev/null
pm2 start ./bin/queue/13_process_order.sh --name "${prefix}13_process_order" >/dev/null 2>/dev/null
#pm2 start ./bin/queue/15_circuit_breaker_lock.sh --name "${prefix}15_circuit_breaker_lock" >/dev/null 2>/dev/null
#pm2 start ./bin/queue/16_circuit_breaker_unlock.sh --name "${prefix}16_circuit_breaker_unlock" >/dev/null 2>/dev/null
pm2 start ./bin/queue/18_delete_spot_canceled_orders.sh --name "${prefix}18_delete_spot_canceled_orders" >/dev/null 2>/dev/null
pm2 start ./bin/queue/21_update_user_transaction.sh --name "${prefix}21_update_user_transaction" >/dev/null 2>/dev/null
pm2 start ./bin/queue/22_calculate_referral_commission.sh --name "${prefix}22_calculate_referral_commission" >/dev/null 2>/dev/null
pm2 start ./bin/queue/23_trading_volume_ranking_spot.sh --name "${prefix}23_trading_volume_ranking_spot" >/dev/null 2>/dev/null
#pm2 start ./bin/queue/24_calculate_amal_net.sh --name "${prefix}24_calculate_amal_net.sh" >/dev/null 2>/dev/null
pm2 start ./bin/queue/26_update_trade_volume.sh --name "${prefix}26_update_trade_volume" >/dev/null 2>/dev/null
pm2 status

pm2 restart 01_order_processor  >/dev/null 2>/dev/null
pm2 restart 02_send_orderbook  >/dev/null 2>/dev/null
pm2 restart 03_send_user_orderbook  >/dev/null 2>/dev/null
pm2 restart 04_send_balance  >/dev/null 2>/dev/null
pm2 restart 05_send_order_list  >/dev/null 2>/dev/null
pm2 restart 06_send_prices  >/dev/null 2>/dev/null
pm2 restart 07_send_order_event  >/dev/null 2>/dev/null
pm2 restart 08_send_admin_notification  >/dev/null 2>/dev/null
pm2 restart 09_send_favorite_symbols  >/dev/null 2>/dev/null
pm2 restart 10_update_price  >/dev/null 2>/dev/null
pm2 restart 11_update_orderbook  >/dev/null 2>/dev/null
pm2 restart 21_update_user_transaction  >/dev/null 2>/dev/null
pm2 restart 22_calculate_referral_commission  >/dev/null 2>/dev/null
pm2 restart 23_trading_volume_ranking_spot  >/dev/null 2>/dev/null
pm2 restart 24_calculate_amal_net  >/dev/null 2>/dev/null
pm2 restart 26_update_trade_volume  >/dev/null 2>/dev/null
pm2 restart 27_trading_volume_ranking_margin  >/dev/null 2>/dev/null
pm2 status
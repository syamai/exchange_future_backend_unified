import { enumize } from "src/shares/enums/enumize";

export const KafkaTopics = enumize(
  "orders",
  "trades",
  "test_matching_engine_preload",
  "matching_engine_preload",
  "matching_engine_input",
  "matching_engine_output",
  "orderbook_output",
  "ticker_engine_preload",
  "ticker_engine_output",
  "future_referral",
  "future_reward_center",
  "future_sync_user",
  "spot_transfer",
  "future_transfer",
  "future_reward_referral",
  "future_anti_phishing_code",
  "future_locale_user",
  "future_device_token_user",
  "send_mail",
  "future_referral_liquidation",
  "save_order_from_client",
  "save_order_from_client_v2",
  "prepare_order_to_send_mail_and_notify", 
  "send_mail_on_spot",
  "save_order_to_db",
  "future_event_reward", // receive future event usdt reward
  "cancel_order_from_client",
  "save_user_position_to_cache",
  "future_firebase_notification",
  "save_order_from_client_v2_for_user_market"
);
export const KafkaGroups = enumize(
  "matching_engine_saver_accounts",
  "matching_engine_saver_positions",
  "matching_engine_saver_orders",
  "matching_engine_saver_trades",
  "matching_engine_saver_transactions",
  "matching_engine_saver_position_histories",
  "matching_engine_saver_funding",
  "matching_engine_saver_margin_histories",
  "matching_engine_saver_margin_leverage",
  "matching_engine_notifier",
  "orderbook",
  "ticker",
  "candles",
  "dex_withdrawal",
  "dex_action",
  "spot_transfer",
  "future_sync_user",
  "future_transfer",
  "future_reward_referral",
  "future_anti_phishing_code",
  "future_locale_user",
  "future_device_token_user",
  "send_mail",
  "save_order_from_client",
  "save_order_from_client_v2",
  "process_order_to_send_mail",
  "user_statistic_matching_engine_saver_user_gain_loss",
  "user_statistic_future_transfer_saver_user_deposit",
  "user_statistic_spot_transfer_saver_user_withdraw",
  "user_statistic_update_user_peak_asset",
  "user_statistic_update_user_total_trade_volume",
  "save_order_to_db",
  "future_save_reward_from_event",
  "save_account_to_cache",
  "save_account_to_db",
  "cancel_order_from_client",
  "save_orders_to_cache",
  "save_position_history_by_session_from_margin_history",
  "save_user_position_to_cache",
  "check_to_seed_liq_order_ids",
  "future_send_firebase_notification",
  "save_order_from_client_v2_for_user_market"
);

export const FutureEventKafkaGroup = enumize(
  "update_user_used_reward_balance",
  "update_revoking_user_reward_balance",
  "revoke_reward_when_user_close_position_order",
  "update_user_used_reward_balance_detail",
  "process_trading_volume_session"
)

export const FutureEventKafkaTopic = enumize(
  "transactions_to_process_used_event_rewards",
  "reward_balance_used_to_process_used_detail",
  "rewards_to_process_trading_volume_session",
  "trades_for_process_fee_voucher"
)

// Future Event V2 - Deposit Bonus System
export const FutureEventV2KafkaTopic = enumize(
  "future_event_v2_deposit_approved",       // Deposit transactions to process for bonus
  "future_event_v2_principal_deduction",    // Transactions for principal deduction (fees, losses)
  "future_event_v2_liquidation_trigger"     // Liquidation trigger events
)

export const FutureEventV2KafkaGroup = enumize(
  "future_event_v2_process_deposit",
  "future_event_v2_process_principal_deduction",
  "future_event_v2_process_liquidation"
)

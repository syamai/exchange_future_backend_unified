<?php

namespace App;

class Consts
{
    const DEFAULT_JWT_ALGORITHM = 'HS256';

    const SOCKET_CHANNEL_USER = 'App.User.';
    const SOCKET_CHANNEL_ADMIN = 'App.Models.Admin';
    const SOCKET_CHANNEL_SETTING = 'App.Setting';
    const SOCKET_CHANNEL_CIRCUIT_BREAKER = 'App.CircuitBreaker';

    const DB_CONNECTION_MASTER = 'master';
    const DB_CONNECTION_MATCHING_ENGINE = 'matching_engine';

    const QUEUE_ORDER_BOOK = 'order_book';
    const QUEUE_PROCESS_ORDER = 'process_order';
	const QUEUE_PROCESS_REQUEST_ORDER = 'process_request_order';
    const QUEUE_SOCKET = 'socket';
    const QUEUE_BLOCKCHAIN = 'block_chain';
    const QUEUE_WITHDRAW = 'withdraw';
    const QUEUE_CRAWLER = 'crawler';
    const QUEUE_AIRDROP = 'airdrop';
    const QUEUE_UPDATE_DEPOSIT = 'update_deposit';
    const QUEUE_MARKETING_MAIL = 'marketing_mail';
    const QUEUE_NEEDS_CONFIRM_MAIL = 'needs_confirm_mail';
    const QUEUE_NORMAL_MAIL = 'normal_mail';
	const QUEUE_FUTURE_MAIL = 'future_mail';
    const QUEUE_HANDLE_MAIL = 'handler_mail';
	const QUEUE_ACCOUNT_EVENTS = 'sync_account_event';
    const QUEUE_ACCOUNT_GAMES = 'sync_account_game';
    const QUEUE_ACCOUNT_FUTURE_EVENTS = 'sync_account_future_event';
    const QUEUE_FUTURE_FIREBASE_NOTIFICATION = 'future_firebase';
    const QUEUE_EXPIRE = 'expire';
    const QUEUE_SEND_MAIL_VOUCHER = 'send_mail_voucher';
    const QUEUE_BALANCE_WALLET = 'balance_wallet';
    const QUEUE_BALANCE_LIQ_COMMISSION = 'balance_liq_commission';
    const QUEUE_NEW_ORDER_ME = 'new_order_me';
    const QUEUE_CANCEL_ORDER_ME = 'cancel_order_me';
    const QUEUE_TRADE_FEE_ME = 'trade_fee_me';

    const QUEUE_LIQUIDATION = 'liquidation';
    const QUEUE_UPDATE_AFFILIATE_TREE = 'update_affiliate_tree';
    const QUEUE_CACHE = 'cache';  // For async cache invalidation

    const CONNECTION_SOCKET = 'sync';
    const CONNECTION_RABBITMQ = 'rabbitmq';

    const RC_ORDER_PROCESSOR = 'order_processor';
    const RC_QUEUE = 'queue';
    const RC_MARGIN_MATCHING_ENGINE = 'margin_matching_engine';

    const GENERAL_STATUS_UN_VERIFIED = 0;
    const GENERAL_STATUS_VERIFIED = 1;
    const TRUE = 1;
    const FALSE = 0;

    //new
    const CURRENCY_USD = 'usd';
    const CURRENCY_BTC = 'btc';
    const CURRENCY_ETH = 'eth';
    const CURRENCY_AMAL = 'amal';
    const CURRENCY_BCH = 'bch';
    const CURRENCY_LTC = 'ltc';
    const CURRENCY_XRP = 'xrp';
    const CURRENCY_SOL = 'sol';
    const CURRENCY_EOS = 'eos';
    const CURRENCY_ADA = 'ada';
    const CURRENCY_USDT = 'usdt';
    const CURRENCY_MATIC = 'matic';
    const CURRENCY_TRX = 'trx';
    const CURRENCY_BNB = 'bnb';
    const CURRENCY_WETH = 'weth';
    const UPDATED_CURRENCY_EOS = 'eos.EOS';
    const ETH_TOKEN = 'eth_token';
    const BNB_TOKEN = 'bnb_token';
    const EOS_XRP_DECIMAL = 6;

    const MIN_UID = 10000000;
    const MAX_UID = 99999999;

    const PRICE_FACTORS = [
        Consts::CURRENCY_USD => 1,
        Consts::CURRENCY_USDT => 1,
        Consts::CURRENCY_BTC => 10000,
        Consts::CURRENCY_ETH => 1000,
    ];

    const PRECISION_COLUMN_QUANTITY = 'quantity_precision';
    const PRECISION_COLUMN_PRICE = 'precision';
    const PRECISION_COLUMN_AMOUNT = 'minimum_amount';

    const SETTING_MIN_BLOCKCHAIN_ADDRESS_COUNT = 'min_blockchain_address_count';
    const SETTING_CONTACT_EMAIL = 'contact_email';
    const SETTING_ADMIN_PHONE_NO = 'admin_phone_no';
    const SETTING_CURRENCY_COUNTRY = 'currency_country';

    const ORDER_TRADE_TYPE_SELL = 'sell';
    const ORDER_TRADE_TYPE_BUY = 'buy';
    const ORDER_SIDE_SELL = 'sell';
    const ORDER_SIDE_BUY = 'buy';

    const ORDER_TYPE_LIMIT = 'limit';
    const ORDER_TYPE_MARKET = 'market';
    const ORDER_TYPE_STOP_LIMIT = 'stop_limit';
    const ORDER_TYPE_STOP_MARKET = 'stop_market';

    const ORDER_STOP_TYPE_LIMIT = 'stop_limit';
    const ORDER_STOP_TYPE_MARKET = 'stop_market';
    const ORDER_STOP_TYPE_TRAILING_STOP = 'trailing_stop';
    const ORDER_STOP_TYPE_TAKE_PROFIT_MARKET = 'take_profit_market';
    const ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT = 'take_profit_limit';
    const ORDER_STOP_TYPE_OCO = 'oco';
    const ORDER_STOP_TYPE_IFD = 'ifd';

    const ORDER_STATUS_NEW = 'new';
    const ORDER_STATUS_STOPPING = 'stopping'; // is not actived yet
    const ORDER_STATUS_PENDING = 'pending'; // waiting for other orders to match
    const ORDER_STATUS_EXECUTED = 'executed'; // fully executed
    const ORDER_STATUS_CANCELED = 'canceled';
    const ORDER_STATUS_EXECUTING = 'executing'; // partially executedro
    const ORDER_STATUS_REMOVED = 'removed'; // sub order may be removed, this is replaced by other sub order

    const ORDER_STOP_CONDITION_GE = 'ge'; // greater than or equal
    const ORDER_STOP_CONDITION_LE = 'le'; // less than or equal
    const ORDER_STOP_TRIGGER_LAST = 'last';
    const ORDER_STOP_TRIGGER_MARK = 'mark';
    const ORDER_STOP_TRIGGER_INDEX = 'index';

    const ORDER_TIME_IN_FORCE_GTC = 'gtc'; // GoodTilCancel
    const ORDER_TIME_IN_FORCE_IOC = 'ioc'; // ImmediateOrCancel
    const ORDER_TIME_IN_FORCE_FOK = 'fok'; // FillOrKill

    const ORDER_PAIR_TYPE_OCO = 'oco';
    const ORDER_PAIR_TYPE_IFD = 'ifd';

    const ORDER_EVENT_CREATED = 'created';
    const ORDER_EVENT_MATCHED = 'matched';
    const ORDER_EVENT_CANCELED = 'canceled';
    const ORDER_EVENT_ACTIVATED = 'activated';
    const ORDER_EVENT_LIQUID = 'liquid';

    const ORDER_BOOK_SIZE = 10;
    const MAX_ORDER_BOOK_SIZE = 30;
    const ORDER_BOOK_UPDATE_CREATED = 'created';
    const ORDER_BOOK_UPDATE_CANCELED = 'canceled';
    const ORDER_BOOK_UPDATE_MATCHED = 'matched';
    const ORDER_BOOK_UPDATE_ACTIVATED = 'actived';
    const ORDER_BOOK_UPDATE_RELOAD = 'reload';

    const ORDER_TYPES = [self::ORDER_TYPE_LIMIT, self::ORDER_TYPE_MARKET];
    const ORDER_STOP_TYPES = [self::ORDER_STOP_TYPE_LIMIT, self::ORDER_STOP_TYPE_MARKET];
    const ORDER_SIDES = [self::ORDER_SIDE_BUY, self::ORDER_SIDE_SELL];

    const DEFAULT_PER_PAGE = 10;
    const MAX_PER_PAGE = 1000;
    const MAX_DEVICE = 5;
    const MAX_LIMIT = 1000;

    const TRANSACTION_STATUS_SUCCESS = 'success';
    const TRANSACTION_STATUS_PENDING = 'pending'; // blockchain pending
    const TRANSACTION_STATUS_SUMITTED = 'submitted'; // hasn't sent to blockchain yet
    const TRANSACTION_STATUS_ERROR = 'error';
    const TRANSACTION_STATUS_CANCEL = 'cancel';
    const TRANSACTION_STATUS_REJECTED = 'rejected';

    const TRANSACTION_TYPE_DEPOSIT = 'deposit';
    const TRANSACTION_TYPE_WITHDRAW = 'withdraw';

    const USER_TRANSACTION_TYPE_TRANSFER = 'transfer';
    const USER_TRANSACTION_TYPE_TRADING = 'trading';
    const USER_TRANSACTION_TYPE_COMMISSION = 'commission';
    const USER_TRANSACTION_TYPE_ADMIN_DEPOSIT = 'admin_deposit';

    const WITHDRAW_ERROR_NOT_ENOUGH_BALANCE = 'not_enough_balance';
    const WITHDRAW_ERROR_AMOUNT_WITHDRAW_IS_POSITIVE = 'amount_withdraw_is_positive';
    const WITHDRAW_ERROR_WHITELIST_ADDRESS = 'address_not_exist_in_whitelist';
    const WITHDRAW_ERROR_OVER_LIMIT = 'over_limit';
    const WITHDRAW_ERROR_OVER_DAILY_LIMIT = 'over_daily_limit';
    const WITHDRAW_ERROR_OVER_ONE_TIME_LIMIT = 'over_one_time_limit';
    const WITHDRAW_ERROR_MINIMUM_WITHDRAW = 'minimum_withdraw';
    const WITHDRAW_ERROR_FEE_WITHDRAW = 'fee_withdraw';
    const WITHDRAW_ERROR_LIMIT_WITHDRAW = 'limit_withdraw';
    const WITHDRAW_ERROR_DAILY_LIMIT_WITHDRAW = 'daily_limit_withdraw';
    const WITHDRAW_ERROR_ACCOUNT_NO = 'account_no_false';
    const WITHDRAW_IS_BLOCKING = 'withdraw_is_blocking';
    const DEPOSIT_ERROR_EXIST_DEPOSIT_TRANSACTION_WAITING = 'exist_deposit_transaction_waiting';
    const DEPOSIT_ERROR_EXIST_AMOUNT_INVALID = 'amount_deposit_invalid';

    const CB_VERSION = "2015-07-22";

    const DEFAULT_ORDER_BOOK_SETTINGS = [
        'price_group' => 1,
        'show_empty_group' => 0,
        'click_to_order' => 0,
        'order_confirmation' => 1,
        'notification' => 1,
        'notification_created' => 1,
        'notification_matched' => 1,
        'notification_canceled' => 1,
    ];

    const XRP_TAG_SEPARATOR = '|';

    const DEVICE_STATUS_BLOCKED = 'blocked';
    const DEVICE_STATUS_CONNECTABLE = 'connectable';

    const ENV_TESTING = 'testing';

    const CACHE_KEY_CMC_TICKER_CURRENCY = "CMC:ticker:";
    const CACHE_KEY_CC_BTC_USD = 'CC:btc_usd';

    const SUPPORTED_EXCHANGES = [
        'MarketPriceChanges:bitfinex.com',
        'MarketPriceChanges:bithumb.com',
        'MarketPriceChanges:bittrex.com',
        'MarketPriceChanges:coinone.co.kr',
        'MarketPriceChanges:hitbtc.com',
        'MarketPriceChanges:korbit.co.kr',
        'MarketPriceChanges:kraken.com',
        'MarketPriceChanges:poloniex.com'
    ];

    const FIELD_NOT_SEARCH_INSTRUSMENT = [
        'timestamps',
        'settle_currency',
        'deleverageable',
        'has_liquidity',
        'settled_price',
        'funding_interval',
        'option_strike_price',
        'option_ko_price',
        'fair_method',
    ];

    const SUPPORTED_LOCALES = ['en', 'ja', 'ko', 'zh'];
    const DIVIDEND = 'dividend';
    const CASHBACK = 'CashBack';
    const PAYBONUSTRADING = 'PayBonusTrading';
    const DEPOSIT_BONUS = [Consts::DIVIDEND, Consts::PAYBONUSTRADING, Consts::CASHBACK];

    const DEFAULT_USER_LOCALE = 'en';

    const DEFAULT_TIMEZONE = 'UTC';

    const MAX_CHART_BARS_LENGTH = 5000;

    const SECURITY_LEVEL_EMAIL = 1;
	const SECURITY_LEVEL_IDENTITY = 2;
    const SECURITY_LEVEL_PHONE = 2;
	const SECURITY_LEVEL_OTP = 3;
    const SECURITY_LEVEL_BANK = 3;

    const USER_TYPE_BOT = 'bot';
    const USER_TYPE_REFERRER = 'referrer';
    const USER_TYPE_NORMAL = 'normal';

    const WALLET_TYPE_HOT = 'hot';
    const WALLET_TYPE_COLD = 'cold';

    const COOKIE_USER_DEVICE = 'user_device_identify';

    const MASTERDATA_TABLES = [
        'settings',
        'market_settings',
        'coin_settings',
        'coins_confirmation',
        'coins',
        'countries',
        'fee_levels',
        'price_groups',
        'withdrawal_limits',
        'social_networks',
        'market_fee_setting',
        'circuit_breaker_settings',
        'circuit_breaker_coin_pair_settings',
        'bot_settings'
    ];

    const DOMAIN_SUPPORT = 'https://sotatek-ytest-test.zendesk.com';
    const ANNOUCEMENT_ID = 360004704052;

    //test zendesk
    const DOMAIN_SUPPORT_TEST = 'https://sotatek-ytest-test.zendesk.com';
    const ANNOUCEMENT_ID_TEST = 360005470073;

    const KYC_STATUS_VERIFIED = 'verified';
    const KYC_STATUS_REJECTED = 'rejected';
    const KYC_STATUS_PENDING = 'pending';

    const AUTH_ROUTE_RESET_PASSWORD = '/reset-password?token=';
    const AUTH_ROUTE_CONFIRM_EMAIL = '/login?code=';
    const AUTH_ROUTE_LOGIN = '/login';
    const ROUTE_WITHDRAWAL_VERIFY = '/verify-withdrawal?coin=';
    const ROUTE_WITHDRAWAL_TOKEN = '&&token=';
    const ROUTE_MARGIN_EXCHANGE = '/margin-exchange?symbol=';

    const FEE_MAKER = 'fee maker';
    const FEE_TAKER = 'fee taker';

    const AUTH_ROUTE_GRANT_DEVICE = '/login?device=';
    const ROUTE_VERIFY_ANTI_PHISHING ='/account/anti-phishing/';

    const BANK_STATUS_UNVERIFIED = 'unverified';
    const BANK_STATUS_CREATING = 'creating'; // user submit request create account bank
    const BANK_STATUS_VERIFING = 'verifing'; // user had account bank, request site verify again.
    const BANK_STATUS_VERIFIED = 'verified';
    const BANK_STATUS_REJECTED = 'rejected';

    const USER_ACTIVE = 'active';
    const USER_INACTIVE = 'inactive';
    const USER_WARNING = 'warning';
    
    const PARTNER_ACTIVE = 'active';
    const PARTNER_INACTIVE = 'inactive';

    const PARTNER_REQUEST_PENDING = '0';
    const PARTNER_REQUEST_APPROVED = '1';
    const PARTNER_REQUEST_REJECT = '2';

    const PARTNER_REQUEST_STATUS = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Reject'
    ];

    const PARTNER_REQUEST_PARTNER_CHANGE_RATE = 'c';
    const PARTNER_REQUEST_ADMIN_CHANGE_RATE = 'ac';
    const PARTNER_REQUEST_ADMIN_CHANGE_LIQUIDATION_RATE = 'acl';

    const PARTNER_REQUEST_DETAIL = [
        'c' => 'Partner change user commission rate',
        'ac' => 'Admin change user commission rate',
        'acl' => 'Admin change user liquidation commission rate'
    ];

    const IS_DIRECT_REF_LABEL = [
        0 => 'Indirect',
        1 => 'Direct'
    ];

    const SENDED_EMAIL_MARKETING_STATUS_SUCCESS = 'success';
    const SENDED_EMAIL_MARKETING_STATUS_FAILED = 'failed';

    const MGC_HOLDING = 500;
    const COMMISSION_RATE_DEFAULT = 0.2;
    const COMMISSION_RATE_MAX = 0.4;
    const COMMISSION_RATE_KEY = 'CommissiomRate:user_';

    const ROLE_SUPER_ADMIN = 'Super Admin';
    const ROLE_ADMIN = 'Admin';
	const ROLE_ACCOUNT = 'Account';
	const ROLE_MARKETING = 'Marketing';
	const ROLE_OPERATOR = 'Operator';
    const SUPER_ADMIN_ID = 1;

    const ADMIN_QUEUE = 'admin';

    const CURRENCIES = ['usd', 'eth', 'btc'];

    const MIN_SMALL_AVAILABLE_BALANCE = 0.001;

    const TYPE_MAIN_BALANCE = 'main';
    const TYPE_EXCHANGE_BALANCE = 'spot';
    const TYPE_MARGIN_BALANCE = 'margin';
    const TYPE_FUTURE_BALANCE = 'future';
    const TYPE_MAM_BALANCE = 'mam';

    const SPOT_AMAL_WALLET = 'spot';
    const PERPETUAL_DIVIDEND_BALANCE = 'perpetual_dividend_balance';
    const DIVIDEND_BALANCE = 'dividend_balance';

    const MAPPING_ORDER_MARKET = 'Market';
    const MAPPING_STATUS_PENDING = 'pending';
    const MAPPING_STATUS_RETRYING = 'retrying';
    const MAPPING_STATUS_DONE = 'done';
    const MAPPING_STATUS_FAILED = 'failed';

    const BALANCE = 'balance';
    const UNREALISED_PNL = 'unrealised_pnl';
    const CROSS_BALANCE = 'cross_balance';
    const ISOLATED_BALANCE = 'isolated_balance';
    const CROSS_EQUITY = 'cross_equity';
    const CROSS_MARGIN = 'cross_margin';
    const CROSS_MAINT_MARGIN = 'cross_maint_margin';
    const ORDER_MARGIN = 'order_margin';
    const AVAILABLE_BALANCE = 'available_balance';

    const CREATE_ORDER = 'create_order';
    const CANCEL_ORDER = 'cancel_order';
    const REFUND_WHEN_MATCHING = 'refund_when_matching';
    const OPEN_POSITION = 'open_position';
    const CLOSE_POSITION = 'close_position';

    const MAM_LOCK_PROCESS = 'lock_process';
    const MAM_PROCESS_ROLLOVER_DAILY = 'rollover_daily';
    const MAM_PROCESS_ROLLOVER_MONTHLY = 'rollover_monthly';
    const MAM_PROCESS_CLOSE_MASTER = 'close_master';
    const MAM_PROCESS_API = 'api';

    const MAM_COMMISSION = 'commission';
    const MAM_EXPIRED = 'expired';
    const MAM_CLOSED = 'closed';
    const MAM_CREATED = 'created';

    const MAM_REQUEST_JOIN = 'join';
    const MAM_REQUEST_ASSIGN = 'assign';
    const MAM_REQUEST_REVOKE = 'revoke';

    const MAM_STATUS_SUBMITED = 'submitted';
    const MAM_STATUS_APPROVED = 'approved';
    const MAM_STATUS_EXECUTED = 'executed';
    const MAM_STATUS_REJECTED = 'rejected';
    const MAM_STATUS_FAILED = 'failed';
    const MAM_STATUS_CANCELED = 'canceled';

    const MAM_REVOKE_PROFIT = 'profit';
    const MAM_REVOKE_PARTIAL = 'partial';
    const MAM_REVOKE_ALL = 'all';

    const MAM_MASTER_OPENED = 'opened';
    const MAM_MASTER_CLOSING = 'closing';
    const MAM_MASTER_CLOSED = 'closed';

    const MAM_ACTION_CANCEL = 'cancel';
    const MAM_ACTION_REJECT = 'reject';
    const MAM_ACTION_APPROVE = 'approve';
    const MAM_ACTION_EXECUTE_IM = 'execute_im';

    const MAM_CLOSING_STEP_0 = 0;
    const MAM_CLOSING_STEP_1 = 1;
    const MAM_CLOSING_STEP_2 = 2;
    const MAM_CLOSING_STEP_3 = 3;
    const MAM_CLOSING_STEP_4 = 4;

    const MAM_BALANCE_THRESHOLD = 0.8;

    const MARGIN_DRAFT = 'draft';
    const MARGIN_STARTED = 'started';
    const MARGIN_CLOSED = 'closed';

    const ENTRY_JOINED = 'joined';
    const ENTRY_CANCELED = 'canceled';

    const MARGIN_DEFAULT_BATCH_SIZE = 1000;

    const BTCUSD = 'BTCUSD';
    const DISABLE_BTCUSD_FEE = 'disable_BTCUSD_fee';
    const ON_FEE = 'on';
    const OFF_FEE = 'off';

    const BALANCE_HISTORY_CREATE = 'create_order';
    const BALANCE_HISTORY_CANCEL = 'cancel_order';
    const BALANCE_HISTORY_REACTIVE = 'reactive_order';
    const BALANCE_HISTORY_MATCH = 'match_order';
    const BALANCE_HISTORY_CLOSE_POSITION = 'close_position';
    const BALANCE_HISTORY_OPEN_POSITION = 'open_position';
    const BALANCE_HISTORY_MATCHING_ORDER = 'matching_order';
    const BALANCE_HISTORY_CHANGE_LEVERAGE = 'change_leverage';
    const BALANCE_HISTORY_UPDATE_MARGIN = 'update_margin';
    const BALANCE_HISTORY_CLOSE_BY_LIQUID = 'close_by_liquid';
    const BALANCE_HISTORY_FUNDING = 'funding';
    const BALANCE_HISTORY_TRANSFER = 'transfer';
    const BALANCE_HISTORY_UPDATE_MARK_PRICE = 'update_mark_price';
    const BALANCE_HISTORY_INSURANCE = 'insurance';
    const BALANCE_HISTORY_TRANSFER_BALANCE = 'transfer_balance';

    const POSITION_HISTORY_CREATE = 'create_order';
    const POSITION_HISTORY_CANCEL = 'cancel_order';
    const POSITION_HISTORY_OPEN = 'open_position';
    const POSITION_HISTORY_CLOSE = 'close_position';
    const POSITION_HISTORY_MATCHING = 'matching_order';
    const POSITION_HISTORY_FUNDING = 'funding';

    const TAKER_PAY_FEE = 'taker_pay_fee';
    const MAKER_PAY_FEE = 'maker_pay_fee';
    const TAKER_MAKER_PAY_FEE = 'taker_maker_pay_fee';

    const MARGIN_EXCEPTION_REDUCE = 'reduce';
    const MARGIN_EXCEPTION_LIQUID = 'liquid';

    const MARGIN_ORDER_NOTE_SETTLEMENT = 'settlement';
    const MARGIN_ORDER_NOTE_LIQUIDATION = 'liquidation';
    const MARGIN_ORDER_NOTE_INSURANCE_LIQUIDATION = 'insurance_liquidation';
    const MARGIN_ORDER_NOTE_INSURANCE_FUNDING = 'insurance_funding';
    const MARGIN_ORDER_NOTE_EXPIRED = 'expired';
    const MAM_CLOSE_PARITAL_POSITION = 'close_partial_position';
    const MAM_CLOSE_POSITION = 'mam_close_position';

    const INSURANCE_FUND_EMAIL = 'insurancefund@exchange.io';
    const HACKING_INSURANCE_FUND_EMAIL = 'exchangehackingfund@gmail.com';
    const BUY_BACK_FUND_EMAIL = 'buybackfund@gmail.com';

    const LIQUIDATION_PROGRESS_STARTED = 1;
    const LIQUIDATION_PROGRESS_CLOSING = 2;

    const MARGIN_LOSS_STATUS_PROCESSING = 'processing';
    const MARGIN_LOSS_STATUS_PROCESSED = 'processed';

    const TYPE_AIRDROP_BALANCE = 'airdrop';
    const TYPE_DIVIDEND_BONUS_BALANCE = 'dividend_bonus';
    const COINS_ALLOW_AIRDROP = [
        Consts::CURRENCY_BTC,
        Consts::CURRENCY_ETH,
        Consts::CURRENCY_AMAL
    ];
    const AIRDROP_TABLES = [
        Consts::CURRENCY_AMAL,
    ];
    const MARGIN_TRANSFER_RESTRICT = [
        Consts::CURRENCY_BTC
    ];
    const MAM_TRANSFER_RESTRICT = [
        Consts::CURRENCY_BTC,
        Consts::CURRENCY_USD,
        Consts::CURRENCY_USDT
    ];
    const COIN_PAIR_ENABLE_TRADING = [
        Consts::CURRENCY_BTC . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_BTC . '/' . Consts::CURRENCY_USD,

        Consts::CURRENCY_BCH . '/' . Consts::CURRENCY_BTC,
        Consts::CURRENCY_ETH . '/' . Consts::CURRENCY_BTC,
        // Consts::CURRENCY_BCH . '/' . Consts::CURRENCY_ETH,
        Consts::CURRENCY_LTC . '/' . Consts::CURRENCY_ETH,
        Consts::CURRENCY_BCH . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_BTC . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_ETH . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_AMAL . '/' . Consts::CURRENCY_USDT,

        Consts::CURRENCY_XRP . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_LTC . '/' . Consts::CURRENCY_USDT,
        Consts::CURRENCY_XRP . '/' . Consts::CURRENCY_BTC,
        Consts::CURRENCY_EOS . '/' . Consts::CURRENCY_BTC,
    ];

    const NOTIFICATION_CHANNELS = ['mail', 'line', 'telegram'];
    const LINE_CHANNEL = 'line';
    const MAIL_CHANNEL = 'mail';
    const TELEGRAM_CHANNEL = 'telegram';
    const TELEGRAM_TOKEN = '683034804:AAElnFzht0XvCRQ8iry-QDTT076XVZXcFtA';
    const MAX_LENGTH_INPUT = 190;
    const CONTACT_US_URI = '/hc/en-us/requests/new';
    const NEWS_UNREAD = 0;
    const NEWS_READ = 1;

    const CONTRACT_CALL_OPTION = 0;
    const CONTRACT_FUTURE = 1;
    const CONTRACT_PERPETUAL_SWAP = 2;
    const CONTRACT_PUT_OPTION = 3;

    const INSTRUMENT_STATE_OPEN = 'Open';
    const INSTRUMENT_STATE_PENDING = 'Pending';
    const INSTRUMENT_STATE_CLOSE = 'Close';

    const IMPACT_NOTIONAL = 10; // * 1e8;

    const ERC20_WEBHOOK = 'erc20';
    const BEP20_WEBHOOK = 'bep20';
    const TRC20_WEBHOOK = 'trc20';
    const SPL_WEBHOOK = 'spl';

    const COLD_WALLET_HOLDER_EMAIL = 'cold_wallet_holder_email';
    const AMAL_FEE_DISCOUNT_RATE = 0.5; // 50%
    const OMNI2 = 'omni.2';
    const OMNI31 = 'omni.31';

    const AIRDROP_TYPE_SPECIAL = 'special';
    const AIRDROP_TYPE_ADMIN = 'admin';
    const AIRDROP_UNLOCKING = 'unlocking';
    const AIRDROP_SUCCESS = 'success';
    const AIRDROP_FAIL = 'fail';
    const AIRDROP_PAID = 'paid';
    const AIRDROP_UNPAID = 'unpaid';
    const UNLOCK_ATTEMPTS = 255;
    const AIRDROP_ENABLE = 1;
    const AIRDROP_DISABLE = 0;
    const AIRDROP_SETTING_ACTIVE = 'active';
    const AIRDROP_SETTING_INACTIVE = 'inactive';
    const TIME_RANDOM_KEY_ENCRYPT_LINE_LIVING = 60 * 24; //minute
    const LIMIT_TRADING_VOLUME = 25;
    const NUMBER_OF_LEVELS = 5;
    const REFUND_RATE = 10;
    const MINIMUM_NUMBER_OF_REFERRER = 0;
    const REFERRER_REFUND = 'Refund';
    const REFERRER_COMMISSION = 'Commission';
    const REFERRER_UNPAID = 'UNPAID';
    const REFERRER_PAID = 'PAID';
    const IS_CALCULATED_CLIENT_REF_PROCESSING = 0;
    const IS_CALCULATED_CLIENT_REF_COMPLETED = 1;
    const IS_CALCULATED_CLIENT_REF_FAIL = 2;

    const MIN_AMOUNT_TRANSFER = '0.00000001';
    const DIGITS_NUMBER_PRECISION = 8;
    const DIGITS_NUMBER_PRECISION_2 = 2;

    const WEBHOOK_COMPLETED_STATUS = 'completed';
    const WEBHOOK_COLLECTED_STATUS = 'collected';
    const WEBHOOK_CREATED_STATUS = 'created';
    const WEBHOOK_FAILED_STATUS = 'failed';

    const DEPOSIT_TRANSACTION_OPEN_STATUS = 'open';
    const DEPOSIT_TRANSACTION_COLLECTED_STATUS = 'collected';

    const CIRCUIT_BREAKER_ALLOW_TRADING_STATUS = 0;
    const CIRCUIT_BREAKER_BLOCK_TRADING_STATUS = 1;
    const CIRCUIT_BREAKER_ALLOW_TRADING = true;
    const CIRCUIT_BREAKER_BLOCK_TRADING = false;
    const CIRCUIT_BREAKER_STATUS_ENABLE = 'enable';
    const CIRCUIT_BREAKER_STATUS_DISABLE = 'disable';

    const ENABLE_FEE = 'enable';
    const DISABLE_FEE = 'disable';

    const LEADER_BOARD_LIMIT = 25;
    const LEADER_BOARD_BALANCE_TYPE = 1;
    const LEADER_BOARD_PERCENT_TYPE = 2;


    const ENABLE_WITHDRAWAL = 'enable';
    const DISABLE_WITHDRAWAL = 'disable';
    const ENABLE_TRADING = 'enable';
    const DISABLE_TRADING = 'disable';
    const WAITING_TRADING = 'waiting';
    const IGNORE_TRADING = 'ignore';

    const API_KEY = 'APIKEY';
    const TIMESTAMP_HEADER = 'Timestamp';
    const SIGNATURE_HEADER = 'Signature';

    const API_KEY_ENCRYPTCODE = '6fe17230cd48b9a5';

    const HEALTH_CHECK_DOMAIN_SPOT = 'Spot';
    const HEALTH_CHECK_DOMAIN_MARGIN = 'Margin';
    const HEALTH_CHECK_DOMAIN_COMMON = 'Common';
    const HEALTH_CHECK_SERVICE_DIVIDEND = 'Dividend';
    const HEALTH_CHECK_SERVICE_MANUAL_DIVIDEND = 'Manual-Dividend';
    const HEALTH_CHECK_SERVICE_MATCHING_ENGINE = 'MatchingEngine';
    const HEALTH_CHECK_SERVICE_EXCHANGE_DATA_QUEUE = 'Exchange-data-queue';
    const HEALTH_CHECK_SERVICE_CIRCUIT_BREAKER_LOCK = 'CircuitBreaker-Lock';
    const HEALTH_CHECK_SERVICE_CIRCUIT_BREAKER_UNLOCK = 'CircuitBreaker-Unlock';
    const HEALTH_CHECK_SERVICE_REFERRAL_COMMISSION = 'Referral-commission';
    const HEALTH_CHECK_SERVICE_AMAL_NET = 'AMAL-Net';
    const HEALTH_CHECK_SERVICE_AUTO_DELEVERAGE = 'MarginAutoDeleverage';
    const HEALTH_CHECK_SERVICE_LIQUIDATION = 'MarginLiquidation';
    const HEALTH_CHECK_SERVICE_TRIGGER_IFD_ORDER = 'MarginIFDOrder';
    const HEALTH_CHECK_SERVICE_MARGIN_STOP_ORDER = 'MarginStopOrder';
    const HEALTH_CHECK_SERVICE_SPOT_STOP_ORDER = 'SpotStopOrder';
    const HEALTH_CHECK_SERVICE_MARGIN_MARK_PRICE = 'MarginMarkPrice';
    const HEALTH_CHECK_SERVICE_FUNDING = 'Funding';
    // const HEALTH_CHECK_SERVICE_FUTURE_INDEX = 'Future-Index';
    // const HEALTH_CHECK_SERVICE_PERPETUAL_INDEX = 'Perpetual-Index';

    const BONUS_BALANCE_TRANSFER = [
        'pending' => 1,
        'success' => 2,
        'fail' => 3
    ];

    const TYPE_TOP_VOLUME = 0;
    const TYPE_TOP_LISTING = 1;
    const TYPE_TOP_GAINER_COIN = 2;
    const TYPE_TOP_LOSER_COIN = 3;

    const TYPE_TOKEN_BNB = 'bnb';

    const LIMIT_TOP_MARKET = 3;

    const APP_LINK = 'app_link';

    const ORDER_MARKET_TYPE_TYPE_NORMAL = 0;
    const ORDER_MARKET_TYPE_TYPE_CONVERT = 1;

    const MAKER = 'MAKER';

    const TAKER = 'TAKER';

    const TOPIC_CONSUMER_REWARD_FUTURE = 'future_reward_center';

    const TOPIC_PRODUCER_REWARD_FUTURE = 'future_reward_referral';

    const TYPE_REWARD = 'reward';

    const TOPIC_CONSUMER_FUTURE_REFERRAL = 'future_referral';
    const TOPIC_PRODUCER_FUTURE_REFERRAL = 'future_reward_referral';
    const TOPIC_PRODUCER_SYNC_USER = 'future_sync_user';
    const TOPIC_PRODUCER_SYNC_ANTI_PHISHING_CODE = 'future_anti_phishing_code';
    const TOPIC_PRODUCER_TRANSFER = 'future_transfer';
    const TOPIC_CONSUMER_TRANSFER_FUTURE = 'spot_transfer';
    const TOPIC_CONSUMER_LIQUIDATION_REFERRAL_FUTURE = 'future_referral_liquidation';
	const TOPIC_CONSUMER_SEND_MAIL_FUTURE = 'send_mail_on_spot';
	const TOPIC_CONSUMER_FIREBASE_NOTIFICATION_FUTURE = 'future_firebase_notification';

	const TOPIC_FUTURE_EVENT_USER_REGISTER = 'event_new_user_register';
    const TOPIC_FUTURE_EVENT_USER_KYC = 'event_user_kyc';
    const TOPIC_FUTURE_EVENT_USER_FIRST_LOGIN = 'event_user_first_login';

    const PARSE_COIN_IDS = [
        'btc' => 'bitcoin',
        'eth' => 'ethereum',
        'usdt' => 'tether',
        'matic' => 'polygon',
        'trx' => 'tron',
        'bnb' => 'binance-coin-wormhole',
        'sol' => 'solana',
    ];

    const PAIRS = ['usdt', 'usd', 'btc', 'eth'];
    const TOPIC_PRODUCER_LOCALE = 'future_locale_user';
    const TOPIC_PRODUCER_DEVICE_TOKEN = 'future_device_token_user';
    const TRANSFER_HISTORY_MAIN = 'main';
    const TRANSFER_HISTORY_FUTURE = 'future';
    const TRANSFER_HISTORY_SPOT = 'spot';

    const CACHE_MARKET_STATISTIC = 'cache_market_statistic';

    const SORT_ASC = 'asc';
    const SORT_DESC = 'desc';

    const ALGORITHM_AES_256_ECB = 'aes-256-ecb';

    // 600 seconds
    const EXPIRED_DECRYPT_DATA = 600;

    const PERMISSION_SUB_KEY = 'permission_';

    const ZONE_DEFAULT = 0;

    const DOMAIN_BINANCE_API = 'https://api.binance.com';
    const DOMAIN_COINBASE_API = 'https://api.coinbase.com';

    const FAKE_CURRENCY_COINS = [
        'sol_usdt' => 'SOLUSDT',
        'bnb_usdt' => 'BNBUSDT',
        'ltc_usdt' => 'LTCUSDT',
        'xrp_usdt' => 'XRPUSDT',
        'ada_usdt' => 'ADAUSDT',
        'bch_usdt' => 'BCHUSDT',
        'btc_usdt' => 'BTCUSDT',
        'eos_usdt' => 'EOSUSDT',
        'eth_usdt' => 'ETHUSDT',
        'matic_usdt' => 'MATICUSDT',
        'trx_usdt' => 'TRXUSDT',
        /*'xrp_btc' => 'XRPBTC',
        'bnb_btc' => 'BNBBTC',
        'ltc_btc' => 'LTCBTC',
        'xrp_eth' => 'XRPETH',
        'bnb_eth' => 'BNBETH',*/
    ];

    const MARKET_TAG_HOT = 'HOT';
    const KAFKA_TOPIC_ME_INIT = 'engine.init';
	const KAFKA_TOPIC_ME_BE_INIT = 'engine.be_init';
    const KAFKA_TOPIC_ME_COMMAND = 'engine.command';
    const KAFKA_TOPIC_ME_COMMAND_RESULT = 'engine.command.result';
    const KAFKA_TOPIC_ME_COMMAND_REJECT = 'engine.command.reject';
    const KAFKA_TOPIC_ME_TRADE = 'engine.trade';


    const INQUIRY_STATUS_PENDING = 1;
    const INQUIRY_STATUS_REPLIED = 2;
    const INQUIRY_STATUS_CANCEL = 3;
    const SOCIAL_MAX_PIN=4;
	const BLOG_MAX_PIN=4;

    const NEWS_NOTIFICATION_STATUS_POSTED = 'posted';
    const NEWS_NOTIFICATION_STATUS_HIDDEN = 'hidden';
    const FEATURE_NEWS_NOTIFICATION= 'Notification';
    const FEATURE_SOCIAL_NEWS= 'Social Management';
    const FEATURE_CHATBOT = 'Chatbot Management';
    const FEATURE_ACOUNT = 'Acount Management';
    const FEATURE_BLOG = 'Blog Management';

    const PROMOTIONS_STATUS_POSTED = 'posted';
    const PROMOTIONS_STATUS_HIDDEN = 'hidden';

    const PROMETHEUS_REDIS = "prometheus";
    const MEMORY_METRICS = "memory_metrics";
    const PERFORMANCE_METRICS = "performance_metrics";

    const ENABLE_STATUS = 'enable';
    const DISABLE_STATUS = 'disable';
    const STATUS_POSTED = 'posted';
    const STATUS_HIDDEN = 'hidden';
	const TYPE_WHITELIST = 'whitelist';
	const TYPE_BLACKLIST = 'blacklist';


    const TIME_FILTER_TODAY = 0;
    const TIME_FILTER_THIS_WEEK = 1;
    const TIME_FILTER_THIS_MONTH = 2;
    const TIME_FILTER_LAST_MONTH = 3;
    const TIME_FILTER_THIS_YEAR = 4;

    const TIME_FILTER_OPTION = [
        'Today',
        'This Week',
        'This Month',
        'Last Month',
        'This Year',
    ];

    const TOP_LIMIT = 5;
    const ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN = 0;
    const ACTIVITY_HISTORY_TYPE_ADD_PARTNER = '0';
    const ACTIVITY_HISTORY_TYPE_APPROVE_REQUEST = '1';
    const ACTIVITY_HISTORY_TYPE_REJECT_REQUEST = '2';
    const ACTIVITY_HISTORY_TYPE_CHANGE_COMMISSION_RATE = '3';
    const ACTIVITY_HISTORY_TYPE_CHANGE_INACTIVE = '4';
    const ACTIVITY_HISTORY_TYPE_CHANGE_ACTIVE = '5';
    const ACTIVITY_HISTORY_TYPE_CHANGE_LIQUIDATION_COMMISSION_RATE = '6';

    const CHART_COLUMN_LABEL = [
        Consts::TIME_FILTER_TODAY => [
            '00:00',
            '03:00',
            '06:00',
            '09:00',
            '12:00',
            '15:00',
            '18:00',
            '21:00'
        ],
        Consts::TIME_FILTER_THIS_WEEK => [
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat',
            'Sun'
        ],
        Consts::TIME_FILTER_THIS_MONTH => [
            '1',
            '4',
            '7',
            '10',
            '13',
            '16',
            '19',
            '22',
            '25',
            '28',
            '30'
        ],
        Consts::TIME_FILTER_LAST_MONTH => [
            '1',
            '4',
            '7',
            '10',
            '13',
            '16',
            '19',
            '22',
            '25',
            '28',
            '30'
        ],
        Consts::TIME_FILTER_THIS_YEAR => [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ],
    ];
}

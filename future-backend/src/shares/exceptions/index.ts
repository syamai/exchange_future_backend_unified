export const httpErrors = {
  // user error
  ACCOUNT_NOT_FOUND: {
    message: "Account not found.",
    code: "USER_00000",
  },
  ACCOUNT_EXISTED: {
    message: "Account already existed.",
    code: "USER_00001",
  },
  ACCOUNT_HASH_NOT_MATCH: {
    message: "Account adress and hash message are not matched.",
    code: "USER_00002",
  },
  UNAUTHORIZED: {
    message: "Unauthorized user.",
    code: "USER_00003",
  },
  LOCKED_USER: {
    message: "User has been locked.",
    code: "USER_00004",
  },
  VERIFY_SIGNATURE_FAIL: {
    message: "System has been failed to verify signture.",
    code: "USER_00005",
  },
  REFRESH_TOKEN_EXPIRED: {
    message: "Refresh tokens is expired.",
    code: "USER_00006",
  },
  ACCESS_TOKEN_EXPIRED: {
    message: "Refresh tokens is expired.",
    code: "USER_00007",
  },
  FORBIDDEN: {
    message: "You are not authorized to access this resource.",
    code: "USER_00008",
  },
  USER_EMAIL_EXISTED: {
    message: "Email has been associted with an other account.",
    code: "USER_00025",
  },
  USER_EMAIL_VERIFY_FAIL: {
    message: "Failed to verify this email.",
    code: "USER_00026",
  },
  EMAIL_CONFIRM_NOT_FOUND: {
    message: "Email request not found!",
    code: "USER_00027",
  },
  EMAIL_WAIT_TIME: {
    message: "Too much request",
    code: "USER_00028",
  },
  USER_SETTING_NOT_FOUND: {
    message: "User setting not found",
    code: "USER_00029",
  },

  USER_SETTING_TP_SL_NOTIFICATION: {
    message: "The number of notifications exceeded the limit",
    code: "USER_00030",
  },

  USER_NOT_FOUND: {
    message: "User not found",
    code: "USER_00031",
  },

  // latest block
  LATEST_BLOCK_EXISTED: {
    message: "Latest block exist.",
    code: "LATEST_BLOCK_00001",
  },
  POSITION_NOT_FOUND: {
    message: "Position not found.",
    code: "POSITION_00002",
  },
  POSITION_QUANTITY_NOT_ENOUGH: {
    message: "Current quantity not enough",
  },
  POSITION_INVALID_QUANTITY: {
    message: "Invalid quantity",
  },
  ACCOUNT_HAS_NO_POSITION: {
    message: "Account has no position",
    code: "POSITION_00003",
  },
  PARAMS_UPDATE_POSITION_NOT_VALID: {
    message: "params update position not valid",
    code: "POSITION_00004",
  },
  PARAMS_REMOVE_TP_SL_POSITION_NOT_VALID: {
    message: "params remove tp sl for position",
    code: "POSITION_00005",
  },
  CLOSE_MARKET_POSITION_NOT_VALID: {
    message: "Close market position not valid",
    code: "POSITION_00006",
  },
  // order error
  ORDER_NOT_FOUND: {
    message: "Order not found.",
    code: "ORDER_00001",
  },
  ORDER_CANCEL_DENIED: {
    message: "You do not have permission to cancel this order.",
    code: "ORDER_00002",
  },
  ORDER_ALREADY_CANCELED: {
    message: "This order have been already canceled and waiting to confirm.",
    code: "ORDER_00003",
  },
  ORDER_UNKNOWN_VALIDATION_FAIL: {
    message: "Order validation failed.",
    code: "ORDER_00004",
  },
  ORDER_PRICE_VALIDATION_FAIL: {
    message: "Order price validation failed.",
    code: "ORDER_00005",
  },
  ORDER_TRIGGER_VALIDATION_FAIL: {
    message: "Order trigger validation failed.",
    code: "ORDER_00006",
  },
  ORDER_STOP_PRICE_VALIDATION_FAIL: {
    message: "Order stop price validation failed.",
    code: "ORDER_00007",
  },
  ORDER_ACTIVATION_PRICE_VALIDATION_FAIL: {
    message: "Order activation price validation failed.",
    code: "ORDER_00008",
  },
  ORDER_MINIMUM_QUANTITY_VALIDATION_FAIL: {
    message: "Your order size is smaller than the minimum size.",
    code: "ORDER_00009",
  },
  ORDER_MAXIMUM_QUANTITY_VALIDATION_FAIL: {
    message: "Your order size is greater than the maximum size.",
    code: "ORDER_00010",
  },
  ORDER_QUANTITY_VALIDATION_FAIL: {
    message: "Your order size is null.",
    code: "ORDER_00010_1",
  },
  ORDER_AVAILABLE_BALANCE_VALIDATION_FAIL: {
    message: "You have insufficient margin to place this order.",
    code: "ORDER_00011",
  },
  ORDER_QUANTITY_PRECISION_VALIDATION_FAIL: {
    message: "Your order quantity precision is not match.",
    code: "ORDER_00012",
  },
  ORDER_PRICE_PRECISION_VALIDATION_FAIL: {
    message: "Your order price precision is not match.",
    code: "ORDER_00013",
  },
  ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL: {
    message: "Your order stop price precision is not match.",
    code: "ORDER_00014",
  },
  ORDER_TRAIL_VALUE_PRECISION_VALIDATION_FAIL: {
    message: "Your order stop price precision is not match.",
    code: "ORDER_00015",
  },
  CALLBACK_RATE_VALIDATION_FAIL: {
    message: "Your callback validation fail",
    code: "ORDER_00016",
  },
  TRAILING_STOP_ORDER_TYPE_NOT_VALID: {
    message: "Trailing stop order type not valid",
    code: "ORDER_00017",
  },
  UNINITIALIZED_POSITION: {
    message: "Your position has not been initialized.",
    code: "ORDER_00018",
  },
  INVALID_REDUCE_ONLY: {
    message: "Invalid reduce only order.",
    code: "ORDER_00019",
  },
  NOT_HAVE_STOP_CONDITION: {
    message: "STOP CONDITION VALIDATION FAIL",
    code: "ORDER_00020",
  },
  TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID: {
    message: "Take profit price or take profit trigger not valid",
    code: "ORDER_00021",
  },
  STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID: {
    message: "Stop loss price or stop loss trigger not valid",
    code: "ORDER_00022",
  },
  TAKE_PROFIT_CONDITION_UNDEFINED: {
    message: "Take profit condition undefined",
    code: "ORDER_00023",
  },
  STOP_LOSS_CONDITION_UNDEFINED: {
    message: "Stop loss condition undefined",
    code: "ORDER_00024",
  },

  // instrument
  INSTRUMENT_DOES_NOT_EXIST: {
    message: "Instrument does not exist.",
    code: "INSTRUMENT_00001",
  },

  SYMBOL_ALREADY_EXIST: {
    message: "Symbol already exist.",
    code: "INSTRUMENT_00002",
  },
  MIN_PRICE_NOT_VALID: {
    message: "Min price must be equal to tick size",
    code: "INSTRUMENT_00003",
  },

  INSURANCE_ACCOUNT_ALREADY_EXIST: {
    message: "Insurance Account already exist.",
    code: "INSTRUMENT_00004",
  },

  // setting
  SETTING_NOT_FOUND: {
    message: "This setting does not exist.",
    code: "SETTING_00001",
  },
  SETTING_NOT_VALID: {
    message: "This setting does not valid.",
    code: "SETTING_00002",
  },

  // withdraw
  AMOUNT_LT_MINIMUM_WITHDRAWAL: {
    message: "Amount withdraw must be greater than or equal minumum amount.",
    code: "WITHDRAWL_00001",
  },
  WITHDRAW_FEE_CHANGED: {
    message: "Withdrawal fee has been just changed.",
    code: "WITHDRAWL_00002",
  },

  // api key
  APIKEY_TIMESTAMP_TOO_OLD: {
    message: "Timestamp is too old.",
    code: "APIKEY_00001",
  },
  //access token
  ACCESS_TOKEN_EXIST: {
    message: "Access token is already exist",
    code: "ACCESS_TOKEN_00000",
  },
  ACCESS_TOKEN_NOT_FOUND: {
    message: "Access token not found",
    code: "ACCESS_TOKEN_00001",
  },

  //margin mode
  MARGIN_MODE_DOES_NOT_EXIST: {
    message: "margin mode does not exist.",
    code: "MARGIN_MODE_00001",
  },

  MARGIN_MODE_COULD_NOT_BE_CHANGE: {
    message:
      "The margin mode cannot be changed while you have an open order/position",
    code: "MARGIN_MODE_00002",
  },

  LEVERAGE_COULD_NOT_BE_CHANGE: {
    message:
      "Leverage reduction is not supported in Isolated Margin Mode with open positions.",
    code: "MARGIN_MODE_00003",
  },

  // trading rule
  TRADING_RULES_DOES_NOT_EXIST: {
    message: "trading rule does not exist.",
    code: "TRADING_RULE_00001",
  },

  //balance
  NOT_ENOUGH_BALANCE: {
    message: "Account does not enough balance.",
    code: "BALANCE_00001",
  },

  //coin-info
  SYMBOL_DOES_NOT_EXIST: {
    message: "symbol does not exist.",
    code: "COIN_INFO_00001",
  },

  //api key
  SIGNATURE_IS_NOT_VALID: {
    message: "signature is not valid.",
    code: "API_KEY_00001",
  },

  NOT_HAVE_ACCESS: {
    message: "not have access.",
    code: "API_KEY_00002",
  },

  TIMESTAMP_EXPIRED: {
    message: "timestamp expired.",
    code: "API_KEY_00003",
  },

  //trade
  TRADE_NOT_FOUND: {
    message: "Trade not found.",
    code: "TRADE_00001",
  },

  // position history by session 
  POSITION_HISTORY_NOT_FOUND: {
    message: "Position history by session not found.",
    code: "POSITION_HISTORY_00001",
  }
};

import { enumize } from "src/shares/enums/enumize";

export enum OrderSide {
  BUY = "BUY",
  SELL = "SELL",
}

export enum OrderType {
  LIMIT = "LIMIT",
  MARKET = "MARKET",
  STOP_LIMIT = "STOP_LIMIT",
  STOP_MARKET = "STOP_MARKET",
  TRAILING_STOP = "TRAILING_STOP",
  LIQUIDATION = "LIQUIDATION",
  TAKE_PROFIT_MARKET = "TAKE_PROFIT_MARKET",
  STOP_LOSS_MARKET = "STOP_LOSS_MARKET",
}

export enum OrderStatus {
  PENDING = "PENDING",
  ACTIVE = "ACTIVE",
  FILLED = "FILLED",
  CANCELED = "CANCELED",
  UNTRIGGERED = "UNTRIGGERED",
  REJECTED = "REJECTED",
  PARTIALLY_FILLED = "PARTIALLY_FILLED",
}

export enum TpSlType {
  TAKE_PROFIT_LIMIT = "TAKE_PROFIT_LIMIT",
  TAKE_PROFIT_MARKET = "TAKE_PROFIT_MARKET",
  STOP_LIMIT = "STOP_LIMIT",
  STOP_MARKET = "STOP_MARKET",
  TRAILING_STOP = "TRAILING_STOP",
  LIQUIDATION = "LIQUIDATION",
  STOP_LOSS_MARKET = "STOP_LOSS_MARKET"
}

export const OrderStopCondition = enumize("GT", "LT");

export const AssetOrder = enumize(
  "USD",
  "USDT",
  // coin-M
  "BTC",
  "ETH",
  "BNB",
  "LTC",
  "SOL",
  "ATOM",
  "MATIC",
  "UNI",
  "XRP"
);

export enum OrderTimeInForce {
  GTC = "GTC",
  IOC = "IOC",
  FOK = "FOK",
}

export const OrderPairType = enumize();

export enum OrderTrigger {
  LAST = "LAST",
  INDEX = "INDEX",
  ORACLE = "ORACLE",
}

export enum OrderNote {
  LIQUIDATION = "LIQUIDATION",
  INSURANCE_LIQUIDATION = "INSURANCE_LIQUIDATION",
  INSURANCE_FUNDING = "INSURANCE_FUNDING",
  REDUCE_ONLY_CANCELED = "REDUCE_ONLY_CANCELED",
}

export enum MarginMode {
  CROSS = "CROSS",
  ISOLATE = "ISOLATE",
}

export enum CANCEL_ORDER_TYPE {
  ALL = "ALL",
  LIMIT = "LIMIT",
  STOP = "STOP",
}

export enum ORDER_TPSL {
  TAKE_PROFIT = "TAKE_PROFIT",
  STOP_LOSS = "STOP_LOSS",
}

export enum ContractType {
  COIN_M = "COIN_M",
  USD_M = "USD_M",
  ALL = "ALL",
}

export enum NotificationErrorCode {
  E001 = "E001",
}

export enum EOrderBy {
  TIME = "time",
  SYMBOL = "symbol",
  SIDE = "side",
  QUANTITY = "quantity",
  PRICE = "price",
  LEVERAGE = "leverage",
  COST = "cost",
  FILLED = "filled",
  STOP_PRICE = "stop price",
  STATUS = "status",
  EMAIL = "email",
}

export enum EDirection {
  DESC = "DESC",
  ASC = "ASC",
}

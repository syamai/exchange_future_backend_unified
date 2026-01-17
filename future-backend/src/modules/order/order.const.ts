import { OrderType, TpSlType } from "src/shares/enums/order.enum";

export const CANCEL_STOP_TYPES = [
  OrderType.STOP_LIMIT,
  OrderType.STOP_MARKET,
  TpSlType.TRAILING_STOP,
  TpSlType.STOP_LIMIT,
  TpSlType.STOP_MARKET,
  TpSlType.TAKE_PROFIT_LIMIT,
  TpSlType.TAKE_PROFIT_MARKET,
];
export const ENABLE_CREATE_ORDER = "enable_create_order";
export const BOT_STOP_CREATE_ORDER = "bot_stop_create_order";
export const CANCEL_LIMIT_TYPES = [OrderType.LIMIT];
export const TMP_ORDER_CACHE = "tmp_order";
export const TMP_ORDER_ID_PREFIX = "order_id_";
export const TMP_ORDER_TTL = 8 * 60 * 60;
export const RECENT_FILLED_ORDER_SENT_MAIL_PREFIX = "RECENT_FILLED_ORDER_SENT_MAIL";
export const RECENT_LIQUIDATION_ORDER_SENT_MAIL_PREFIX = "RECENT_LIQUIDATION_ORDER_SENT_MAIL_PREFIX";
export const ORDER_ID_PREFIX = "orderId_";
export const RECENT_PARTIAL_FILLED_ORDER_SENT_MAIL_PREFIX = "RECENT_PARTIAL_FILLED_ORDER_SENT_MAIL";

export enum OrderTypeSendEmail {
  LIMIT = "LIMIT",
  STOP_LIMIT = "STOP_LIMIT",
  STOP_MARKET = "STOP_MARKET",
  TRAILING_STOP = "TRAILING_STOP",
  POST_ONLY = "POST_ONLY",
  STOP_LOSS = "STOP_LOSS",
  TAKE_PROFIT = "TAKE_PROFIT",
  LIQUIDATION = "LIQUIDATION"
}

export const SEND_EMAIL_ORDER_TYPE_TEMPLATE_1 = [
  OrderTypeSendEmail.LIMIT,
  OrderTypeSendEmail.STOP_LIMIT,
  OrderTypeSendEmail.STOP_MARKET,
  OrderTypeSendEmail.TRAILING_STOP,
  OrderTypeSendEmail.POST_ONLY,
];

export const SEND_EMAIL_ORDER_TYPE_TEMPLATE_2 = [OrderTypeSendEmail.STOP_LOSS];

export const SEND_EMAIL_ORDER_TYPE_TEMPLATE_3 = [OrderTypeSendEmail.TAKE_PROFIT];

export const SEND_EMAIL_ORDER_TYPE_TEMPLATE_4 = [OrderTypeSendEmail.LIQUIDATION];

export enum ORDER_FILLED_TYPE {
  FILLED = "FILLED",
  PARTIAL_FILLED = "PARTIAL_FILLED"
}

export enum SEND_EMAIL_TEMPLATE {
  TEMPLATE_1 = "TEMPLATE_1",
  TEMPLATE_2 = "TEMPLATE_2",
  TEMPLATE_3 = "TEMPLATE_3",
  TEMPLATE_4 = "TEMPLATE_4",
}

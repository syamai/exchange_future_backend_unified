import { enumize } from "src/shares/enums/enumize";

export const CommandCode = enumize(
  "INITIALIZE_ENGINE",
  "START_ENGINE",
  "UPDATE_INSTRUMENT",
  "UPDATE_INSTRUMENT_EXTRA",
  "CREATE_ACCOUNT",
  "LOAD_BOT_ACCOUNT",
  "LOAD_POSITION",
  "LOAD_POSITION_HISTORY",
  "LOAD_FUNDING_HISTORY",
  "LOAD_ORDER",
  "WITHDRAW",
  "DEPOSIT",
  "LIQUIDATE",
  "PAY_FUNDING",
  "PLACE_ORDER",
  "CANCEL_ORDER",
  "TRIGGER_ORDER",
  "DUMP",
  "ADJUST_MARGIN_POSITION",
  "ADJUST_LEVERAGE",
  "ADJUST_TP_SL",
  "LOAD_LEVERAGE_MARGIN",
  "LOAD_TRADING_RULE",
  "ADJUST_TP_SL_PRICE",
  "CLOSE_INSURANCE",
  "MAIL_FUNDING",
  "START_MEASURE_TPS",
  "STOP_SAVE_ACCOUNTS_TO_CACHE",
  "STOP_SAVE_ACCOUNTS_TO_DB",
  "STOP_SAVE_MARGIN_HISTORY", 
  "STOP_SAVE_ORDERS_TO_CACHE",
  "STOP_SAVE_ORDERS_TO_DB",
  "STOP_SAVE_POSITION_HISTORY", 
  "STOP_SAVE_POSITIONS",
  "STOP_SAVE_ORDERS_FROM_CLIENT", 
  "STOP_UPDATE_USER_PEAK_ASSET",
  "STOP_SAVE_POSITION_HISTORY_BY_SESSION_FROM_MARGIN_HISTORY",
  "SEED_LIQUIDATION_ORDER_ID"
);

export const ActionAdjustTpSl = enumize("PLACE", "CANCEL");
export const Coin = enumize("USDT", "USD");

export const NotificationEvent = enumize(
  "OrderPlaced",
  "OrderCanceled",
  "OrderMatched",
  "OrderTriggered",
  "PositionLiquidated",
  "WithdrawSubmitted",
  "WithdrawUnsuccessfully",
  "WithdrawSuccessfully",
  "DepositSuccessfully"
);

export const NotificationType = enumize("success", "error");

export const BATCH_SIZE = 5000;

export interface CommandOutput {
  code: string;
  data: Record<string, unknown>;
  accounts: Record<string, unknown>[];
  positions: Record<string, unknown>[];
  orders: Record<string, unknown>[];
  trades: Record<string, unknown>[];
  transactions: Record<string, unknown>[];
  marginHistories: Record<string, unknown>[];
  positionHistories: Record<string, unknown>[];
  fundingHistories: Record<string, unknown>[];
  errors: Record<string, unknown>[];
  liquidatedPositions: Record<string, unknown>[];
  adjustLeverage: Record<string, unknown>;
  accHasNoOpenOrdersAndPositionsList: { accountId: number, userId: number }[];
  shouldSeedLiquidationOrderId?: boolean;
}

export interface Notification {
  event: string;
  type: string;
  userId: number;
  title: string;
  message: string;
  amount?: string;
  asset?: string;
  code?: string;
  orderType?: string;
  tpSlType?: string;
  isHidden?: boolean;
  side?: string;
  status?: string;
  quantity?: string;
  remaining?: string;
  contractType?: string;
}

export const POSITION_HISTORY_TIMESTAMP_KEY =
  "matching_engine_position_history_timestamp";
export const FUNDING_HISTORY_TIMESTAMP_KEY =
  "matching_engine_funding_history_timestamp";

export const POSITION_HISTORY_TIMESTAMP_TTL = 24 * 60 * 60; // 24시간
export const FUNDING_HISTORY_TIMESTAMP_TTL = 24 * 60 * 60; // 24시간

export const MATCHING_ENGINE_TTL = 43200; // 12시간

export const FUNDING_INTERVAL = "8";

export const PREFIX_ASSET = "PREFIX_ASSET_";

const delayTime = 50;

export const handleTimeout = async (fn: any) => {
  await fn();
  await sleep(delayTime);
  await handleTimeout(fn);
};

export const sleep = (milliseconds: number) => {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
};

export enum NOTIFICATION_TYPE {
  TP_SL_ORDER_TRIGGER = "TP_SL_ORDER_TRIGGER",
  FUNDING_FEE = "FUNDING_FEE",
  LIMIT = "LIMIT"
}

export const NOTIFICATION_MESSAGE = new Map([
  ["TP_SL_ORDER_TRIGGER_EN", "Future TP/SL Stop order has been triggered"],
  [
    "TP_SL_ORDER_TRIGGER_VI",
    "Lệnh Dừng chốt lãi/ cắt lỗ Future đã được kích hoạt",
  ],
  [
    "TP_SL_ORDER_TRIGGER_KR",
    "선물 이익실현/손절매 스탑 주문이 활성화되었습니다",
  ],
  ["FUNDING_FEE_EN", "Future Funding Fee has reached threshold"],
  ["FUNDING_FEE_KR", "선물 펀딩비가 한계점에 도달했습니다"],
  ["FUNDING_FEE_VI", "Phí cấp vốn Future đã đạt ngưỡng"],
]);

export enum LANGUAGE {
  ENGLISH = "EN",
  VIETNAMESE = "VI",
  KOREAN = "KR",
}

import { enumize } from "src/shares/enums/enumize";

export const TransactionStatus = enumize("PENDING", "APPROVED", "REJECTED", "CONFIRMED");

export const TransactionType = enumize(
  "TRANSFER",
  "DEPOSIT",
  "WITHDRAWAL",
  "TRADE",
  "FUNDING_FEE",
  "TRADING_FEE",
  "REALIZED_PNL",
  "LIQUIDATION_CLEARANCE",
  "REFERRAL",
  "REWARD",
  "EVENT_REWARD",
  "REVOKE_EVENT_REWARD",
  "MARGIN_INSURANCE_FEE"
);

export enum TransactionHistory {
  ONE_DAY = "ONE_DAY",
  ONE_WEEK = "ONE_WEEK",
  ONE_MONTH = "ONE_MONTH",
  THREE_MONTHS = "THREE_MONTHS",
}

export enum AssetType {
  BTC = "BTC",
  ETH = "ETH",
  BNB = "BNB",
  LTC = "LTC",
  XRP = "XRP",
  USDT = "USDT",
  SOL = "SOL",
  TRX = "TRX",
  MATIC = "MATIC",
  LINK = "LINK",
  MANA = "MANA",
  FIL = "FIL",
  ATOM = "ATOM",
  AAVE = "AAVE",
  DOGE = "DOGE",
  DOT = "DOT",
  UNI = "UNI",
  USD = "USD",
}

export enum SpotTransactionType {
  REFERRAL = "REFERRAL",
  REWARD = "REWARD",
}

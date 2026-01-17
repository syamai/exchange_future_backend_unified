export enum ActionType {
  TRADE = "TRADE",
  FUNDING = "FUNDING",
  WITHDRAW = "WITHDRAW",
}

export enum DexTransactionStatus {
  PENDING = "PENDING",
  SENT = "SENT",
  SUCCESS = "SUCCESS",
  REVERT = "REVERT",
}

export enum MatchAction {
  MATCHING_BUY = "MATCHING_BUY",
  MATCHING_SELL = "MATCHING_SELL",
  FUNDING = "FUNDING",
  WITHDRAW = "WITHDRAW",
}

export enum BalanceValidStatus {
  PENDING = "PENDING",
  SUCCESS = "SUCCESS",
}

export enum DexLiquidationSide {
  NONE = 0,
  BUY = 1,
  SELL = 2,
}

export enum DexRunningChain {
  BSCSIDECHAIN = "bscsidechain",
  SOL = "sol",
}

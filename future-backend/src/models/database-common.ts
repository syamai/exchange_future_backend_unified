import { Global, Module } from "@nestjs/common";
import { TypeOrmModule } from "@nestjs/typeorm";
import { AccountHistoryRepository } from "src/models/repositories/account-history.repository";
import { AccountRepository } from "src/models/repositories/account.repository";
import { ApiKeyRepository } from "src/models/repositories/api-key.repository";
import { CandlesRepository } from "src/models/repositories/candles.repository";
import { DexActionHistoryRepository } from "src/models/repositories/dex-action-history-repository";
import { DexActionSolTxRepository } from "src/models/repositories/dex-action-sol-txs.repository";
import { DexActionTransactionRepository } from "src/models/repositories/dex-action-transaction.repository";
import { DexActionRepository } from "src/models/repositories/dex-action.repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";
import { FundingRepository } from "src/models/repositories/funding.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { LatestBlockRepository } from "src/models/repositories/latest-block.repository";
import { LatestSignatureRepository } from "src/models/repositories/latest-signature.repository";
import { LoginHistoryRepository } from "src/models/repositories/login-history.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { MarketDataRepository } from "src/models/repositories/market-data.repository";
import { MarketIndexRepository } from "src/models/repositories/market-indices.repository";
import { MetadataRepository } from "src/models/repositories/metadata.repository";
import { OrderRepository } from "src/models/repositories/order.repository";
import { PositionHistoryRepository } from "src/models/repositories/position-history.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { SettingRepository } from "src/models/repositories/setting.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { CoinInfoRepository } from "src/models/repositories/coin-info.repository";
import { AccessTokenRepository } from "./repositories/access-token.repository";
import { TradingRulesRepository } from "./repositories/trading-rules.repository";
import { LeverageMarginRepository } from "./repositories/leverage-margin.repository";
import { UserMarginModeRepository } from "./repositories/user-margin-mode.repository";
import { MarketFeeRepository } from "./repositories/market_fee.repository";
import { AssetsRepository } from "./repositories/assets.repository";
import { OrderInvertedIndexCreatedAtSymbolTypeStatusRepository } from "./repositories/order-inverted-index-created-at-symbol-type-status.repository";
import { OrderAverageByTradeRepository } from "./repositories/order-average-by-trade.repository";
import { UserStatisticRepository } from "./repositories/user-statistics.repository";
import { UserRewardFutureEventRepository } from "./repositories/user-reward-future-event.repository";
import { UserRewardFutureEventUsedRepository } from "./repositories/user-reward-future-event-used.repository";
import { UserTradeToRemoveBotOrderRepository } from "./repositories/user-trade-to-remove-bot-order.repository";
import { PositionHistoriesTmpRepository } from "./repositories/position-histories-tmp.repository";
import { OrderWithPositionHistoryBySessionRepository } from "./repositories/order-with-position-history-by-session.repository";
import { PositionHistoryBySessionRepository } from "./repositories/position-history-by-session.repository";
import { UserRewardFutureEventUsedDetailRepository } from "./repositories/user-reward-future-event-used-detail.repository";
import { TradingVolumeSessionRepository } from "./repositories/trading-volume-session.repository";
import { TradingVolumeSessionLogRepository } from "./repositories/trading-volume-session-log.repository";

const commonRepositories = [
  AccountRepository,
  FundingRepository,
  InstrumentRepository,
  LoginHistoryRepository,
  LeverageMarginRepository,
  OrderRepository,
  PositionRepository,
  TradeRepository,
  SettingRepository,
  UserRepository,
  MarketDataRepository,
  MarketIndexRepository,
  TransactionRepository,
  LatestBlockRepository,
  MetadataRepository,
  CandlesRepository,
  MarginHistoryRepository,
  AccountHistoryRepository,
  PositionHistoryRepository,
  FundingHistoryRepository,
  UserSettingRepository,
  ApiKeyRepository,
  DexActionRepository,
  DexActionTransactionRepository,
  DexActionHistoryRepository,
  DexActionSolTxRepository,
  LatestSignatureRepository,
  CoinInfoRepository,
  AccessTokenRepository,
  UserMarginModeRepository,
  TradingRulesRepository,
  MarketFeeRepository,
  AssetsRepository,
  OrderInvertedIndexCreatedAtSymbolTypeStatusRepository,
  OrderAverageByTradeRepository,
  UserStatisticRepository,
  UserRewardFutureEventRepository,
  UserRewardFutureEventUsedRepository,
  UserTradeToRemoveBotOrderRepository,
  PositionHistoriesTmpRepository,
  PositionHistoryBySessionRepository,
  OrderWithPositionHistoryBySessionRepository,
  UserRewardFutureEventUsedDetailRepository,
  TradingVolumeSessionRepository,
  TradingVolumeSessionLogRepository
];

@Global()
@Module({
  imports: [
    TypeOrmModule.forFeature(commonRepositories, "master"),
    TypeOrmModule.forFeature(commonRepositories, "report"),
  ],
  exports: [TypeOrmModule],
})
export class DatabaseCommonModule {}

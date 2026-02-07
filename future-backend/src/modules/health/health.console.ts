import {
  CloudWatchClient,
  MetricDatum,
  PutMetricDataCommand,
} from "@aws-sdk/client-cloudwatch";
// JsonRpcProvider is used via ethers.providers below
import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { PublicKey } from "@solana/web3.js";
import { providers } from "ethers";
import { Command, Console } from "nestjs-console";
import { Dex } from "src/configs/dex.config";
import { Health } from "src/configs/health.config";
import { SolDex } from "src/configs/sol-dex.config";
import { DexActionSolTxEntity } from "src/models/entities/dex-action-sol-tx.entity";
import { AccountHistoryRepository } from "src/models/repositories/account-history.repository";
import { AccountRepository } from "src/models/repositories/account.repository";
import { DexActionHistoryRepository } from "src/models/repositories/dex-action-history-repository";
import { DexActionSolTxRepository } from "src/models/repositories/dex-action-sol-txs.repository";
import { DexActionTransactionRepository } from "src/models/repositories/dex-action-transaction.repository";
import { DexActionRepository } from "src/models/repositories/dex-action.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { LatestBlockRepository } from "src/models/repositories/latest-block.repository";
import { LatestSignatureRepository } from "src/models/repositories/latest-signature.repository";
import { MarketIndexRepository } from "src/models/repositories/market-indices.repository";
import { CandleService } from "src/modules/candle/candle.service";
import {
  BalanceValidStatus,
  DexRunningChain,
  DexTransactionStatus,
} from "src/modules/dex/dex.constant";
import { FundingService } from "src/modules/funding/funding.service";
import { HEALTH_INTERVAL } from "src/modules/health/health.const";
import { IndexService } from "src/modules/index/index.service";
import { MailService } from "src/modules/mail/mail.service";
import { SotaDexWrapper } from "src/shares/helpers/sotadex-wrapper";
import { sleep } from "src/shares/helpers/utils";
import { Equal, In, Not } from "typeorm";

const { dexId, dexProgram, finalizedConnection, usdcId } = SolDex;

@Console()
@Injectable()
export class HealthConsole {
  private readonly logger = new Logger(HealthConsole.name);
  private cloudWatchClient: CloudWatchClient;
  private provider: providers.JsonRpcProvider;
  private readonly dexWrapper: SotaDexWrapper;
  private sotadexAccount: PublicKey;

  constructor(
    private readonly fundingService: FundingService,
    private readonly indexService: IndexService,
    @InjectRepository(MarketIndexRepository, "report")
    public readonly indexRepository: MarketIndexRepository,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepository: InstrumentRepository,
    @InjectRepository(LatestBlockRepository, "report")
    public readonly latestBlockRepository: LatestBlockRepository,
    @InjectRepository(LatestSignatureRepository, "report")
    public readonly latestSignatureRepository: LatestSignatureRepository,
    @InjectRepository(AccountHistoryRepository, "report")
    public readonly accountHistoryRepository: AccountHistoryRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepository: AccountRepository,
    @InjectRepository(DexActionRepository, "report")
    public readonly dexActionRepository: DexActionRepository,
    @InjectRepository(DexActionTransactionRepository, "report")
    public readonly dexActionTransactionRepository: DexActionTransactionRepository,
    @InjectRepository(DexActionHistoryRepository, "report")
    public readonly dexActionHistoryRepository: DexActionHistoryRepository,
    @InjectRepository(DexActionSolTxRepository, "report")
    public readonly dexActionSolTxRepository: DexActionSolTxRepository,
    private readonly mailService: MailService,
    private readonly candleService: CandleService
  ) {
    this.cloudWatchClient = new CloudWatchClient({});
    this.provider = new providers.JsonRpcProvider(Health.rpcHost);
    this.dexWrapper = new SotaDexWrapper(dexProgram, dexId, usdcId);
  }

  @Command({
    command: "health:check",
    description: "Put metrics to cloudwatch",
  })
  async healthCheck(): Promise<void> {
    let startTime = Date.now();
    while (true) {
      startTime = Date.now();
      if (Dex.runningChain === DexRunningChain.BSCSIDECHAIN) {
        await this.putLatestBlockMetrics();
      } else {
        await this.putLatestSignatureMetrics();
        await this.putSignatureCrawlerMetric();
      }
      await this.putIndexMetrics();
      await this.putFundingMetrics();
      await this.putAccountHistoryMetric();
      await this.putInsuranceFundMetric();
      await this.putEmailMetrics();
      await this.putDexActionMetric();
      await this.putDexActionTransactionMetric();
      await this.putDexActionHistoryMetric();
      await this.putCandleMetric();
      const sleepTime = startTime + HEALTH_INTERVAL - Date.now();
      if (sleepTime > 0) {
        await sleep(sleepTime);
      }
    }
  }

  private async putLatestBlockMetrics(): Promise<void> {
    const keys = ["TransactionCrawler", "dex-action-history"];
    const metricData: MetricDatum[] = [];
    const blockNumber = await this.provider.getBlockNumber();
    for (const service of keys) {
      const latestBlock = await this.latestBlockRepository.findOne({ service });
      const serviceBlockNumber = latestBlock?.blockNumber || 0;
      const value = blockNumber - serviceBlockNumber;
      if (Math.abs(value) < 500) {
        metricData.push({
          MetricName: service,
          Unit: "Count",
          Value: value,
        });
      } else {
        this.logger.log(`Invalid value (${value}) for metric ${service}`);
      }
    }

    await this.putMetrics(metricData);
  }

  private async putLatestSignatureMetrics(): Promise<void> {
    const keys = ["TransactionCrawler", "handleHistoryDexActions"];
    const metricData: MetricDatum[] = [];
    const latestSignature = await this.getLatestSignature();
    const latestSignatureId = latestSignature ? Number(latestSignature.id) : 0;
    for (const service of keys) {
      const latestBlock = await this.latestSignatureRepository.findOne({
        service,
      });
      const serviceSignatureId = await this.getSignatureId(
        latestBlock?.signature || ""
      );
      const value = latestSignatureId - serviceSignatureId;
      if (Math.abs(value) < 500) {
        metricData.push({
          MetricName: service,
          Unit: "Count",
          Value: value,
        });
      } else {
        this.logger.log(`Invalid value (${value}) for metric ${service}`);
      }
    }

    await this.putMetrics(metricData);
  }

  private async getSignatureId(signature: string): Promise<number> {
    const tx = await this.dexActionSolTxRepository.findOne({
      where: {
        txid: signature,
      },
    });
    return tx ? Number(tx.id) : 0;
  }

  private async getLatestSignature(): Promise<DexActionSolTxEntity> {
    return await this.dexActionSolTxRepository.findOne({
      order: {
        id: "DESC",
      },
    });
  }

  private async putSignatureCrawlerMetric(): Promise<void> {
    if (!this.sotadexAccount) {
      const [sotadexAccount] = await this.dexWrapper.getSotadexAccount();
      this.sotadexAccount = sotadexAccount;
    }
    const options: any = { limit: 50 };
    const fetchedSignatures = await finalizedConnection.getSignaturesForAddress(
      this.sotadexAccount,
      options
    );
    const signatures = fetchedSignatures.map((s) => s.signature);
    const latestCrawledSignature = await this.getLatestSignature();
    let value = 0;
    if (signatures.length > 0) {
      value = signatures.indexOf(latestCrawledSignature.txid || "");
      if (value < 0) {
        value = 50;
      }
    }
    await this.putMetrics([
      {
        MetricName: "SignatureCrawler",
        Unit: "Count",
        Value: value,
      },
    ]);
  }

  private async putIndexMetrics(): Promise<void> {
    const metricData: MetricDatum[] = [];
    const instrumentCount = await this.instrumentRepository.count();
    const indexUpdateCount = await this.indexService.getUpdateCount();
    metricData.push({
      MetricName: "MissingIndexUpdateCount",
      Unit: "Count",
      Value: instrumentCount - indexUpdateCount,
    });

    const lastUpdate = await this.indexService.getLastUpdate();
    const updateDelay = (Date.now() - lastUpdate) / 1000;
    if (updateDelay < 2000) {
      metricData.push({
        MetricName: "IndexUpdateDelay",
        Unit: "Seconds",
        Value: updateDelay,
      });
    } else {
      this.logger.log(
        `Invalid value (${updateDelay}) for metric IndexUpdateDelay`
      );
    }

    const updateError = await this.indexService.getUpdateError();
    metricData.push({
      MetricName: "IndexUpdateError",
      Unit: "Count",
      Value: updateError === "true" ? 1 : 0,
    });

    await this.putMetrics(metricData);
  }

  private async putFundingMetrics(): Promise<void> {
    const metricData: MetricDatum[] = [];

    const lastUpdate = await this.fundingService.getLastUpdate();
    const updateDelay = (Date.now() - lastUpdate) / 1000;
    if (updateDelay < 2000) {
      metricData.push({
        MetricName: "FundingUpdateDelay",
        Unit: "Seconds",
        Value: updateDelay,
      });
    } else {
      this.logger.log(
        `Invalid value (${updateDelay}) for metric FundingUpdateDelay`
      );
    }
    const lastPay = await this.fundingService.getLastPay();
    const payDelay = (Date.now() - lastPay) / 1000;
    if (payDelay < 20000) {
      metricData.push({
        MetricName: "FundingPayDelay",
        Unit: "Seconds",
        Value: payDelay,
      });
    } else {
      this.logger.log(`Invalid value (${payDelay}) for metric FundingPayDelay`);
    }

    await this.putMetrics(metricData);
  }

  private async putAccountHistoryMetric(): Promise<void> {
    const accountHistory = await this.accountHistoryRepository.findOne({
      order: {
        id: "DESC",
      },
    });

    let value = 1; // Error
    if (accountHistory) {
      const updateDelay = accountHistory.createdAt.getTime() - Date.now();
      // 1 day + 30 minutes
      if (updateDelay < 86400000 + 1800000) {
        value = 0; // OK
      }
    }
    await this.putMetrics([
      {
        MetricName: "AccountHistoryError",
        Unit: "Count",
        Value: value,
      },
    ]);
  }

  private async putInsuranceFundMetric(): Promise<void> {
    // const insuranceAccount = await this.accountRepository.findOne({
    //   where: {
    //     id: Health.insuranceAccountId,
    //   },
    // });

    // const balance = insuranceAccount?.availableBalance ? Number(insuranceAccount.availableBalance) : 0;

    await this.putMetrics([
      {
        MetricName: "InsuranceFundBalance",
        Unit: "Count",
        // Value: balance,
      },
    ]);
  }

  private async putEmailMetrics(): Promise<void> {
    const {
      activeCount,
      failedCount,
      waitingCount,
    } = await this.mailService.getQueueStats();
    const metricData = [
      {
        MetricName: "EmailActiveCount",
        Unit: "Count",
        Value: activeCount,
      },
      {
        MetricName: "EmailFailedCount",
        Unit: "Count",
        Value: failedCount,
      },
      {
        MetricName: "EmailWaitingCount",
        Unit: "Count",
        Value: waitingCount,
      },
    ];
    await this.putMetrics(metricData);
  }

  private async putDexActionMetric(): Promise<void> {
    const pendingCount = await this.dexActionRepository.count({
      where: {
        dexActionTransactionId: 0,
      },
    });
    await this.putMetrics([
      {
        MetricName: "PendingDexAction",
        Unit: "Count",
        Value: pendingCount,
      },
    ]);
  }

  private async putDexActionTransactionMetric(): Promise<void> {
    const pendingCount = await this.dexActionTransactionRepository.count({
      where: {
        status: Not(In([DexTransactionStatus.SUCCESS, "DELETED"])),
      },
    });
    await this.putMetrics([
      {
        MetricName: "PendingDexActionTransaction",
        Unit: "Count",
        Value: pendingCount,
      },
    ]);
  }

  private async putDexActionHistoryMetric(): Promise<void> {
    const pendingCount = await this.dexActionHistoryRepository.count({
      where: {
        validStatus: Not(Equal(BalanceValidStatus.SUCCESS)),
      },
    });
    await this.putMetrics([
      {
        MetricName: "PendingDexActionHistory",
        Unit: "Count",
        Value: pendingCount,
      },
    ]);
  }

  private async putCandleMetric(): Promise<void> {
    const metricData: MetricDatum[] = [];

    const lastUpdate = await this.candleService.getLastUpdate();
    const updateDelay = (Date.now() - lastUpdate) / 1000;
    if (updateDelay < 2000) {
      metricData.push({
        MetricName: "CandleUpdateDelay",
        Unit: "Seconds",
        Value: updateDelay,
      });
    } else {
      this.logger.log(
        `Invalid value (${updateDelay}) for metric CandleUpdateDelay`
      );
    }

    await this.putMetrics(metricData);
  }

  private async putMetrics(metricData: MetricDatum[]): Promise<void> {
    if (metricData.length === 0) {
      return;
    }
    const input = {
      Namespace: Health.namespace,
      MetricData: metricData,
    };

    const command = new PutMetricDataCommand(input);
    await this.cloudWatchClient.send(command);
  }
}

import { Injectable, Logger, OnModuleInit } from "@nestjs/common";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import {
  ShardInfo,
  ShardStatus,
  ShardRole,
  MatchingEngineCommand,
  RoutingResult,
} from "./shard-info.interface";
import {
  DEFAULT_SHARD_CONFIG,
  DEFAULT_SYMBOL_MAPPING,
  DEFAULT_SHARD_ID,
  LEGACY_MATCHING_ENGINE_TOPIC,
} from "./shard-config";
import {
  ShardUnavailableException,
  SymbolPausedException,
  UnknownShardException,
} from "./order-router.exception";
import { getConfig } from "src/configs/index";

@Injectable()
export class OrderRouterService implements OnModuleInit {
  private readonly logger = new Logger(OrderRouterService.name);

  private shardInfoMap: Map<string, ShardInfo> = new Map();
  private symbolToShardMapping: Map<string, ShardInfo> = new Map();
  private pausedSymbols: Set<string> = new Set();
  private defaultShard: ShardInfo;

  private shardingEnabled = false;

  constructor(private readonly kafkaClient: KafkaClient) {}

  async onModuleInit(): Promise<void> {
    await this.initialize();
  }

  /**
   * Initialize the router with configuration
   */
  async initialize(): Promise<void> {
    try {
      this.shardingEnabled =
        getConfig().get<boolean>("sharding.enabled") ?? false;
    } catch {
      this.shardingEnabled = false;
    }

    if (!this.shardingEnabled) {
      this.logger.log("Sharding is disabled. Using legacy single topic mode.");
      return;
    }

    this.loadShardConfig();
    this.loadSymbolMapping();

    this.logger.log(
      `OrderRouter initialized with ${this.shardInfoMap.size} shards and ${this.symbolToShardMapping.size} symbol mappings`
    );
  }

  /**
   * Route a command to the appropriate shard based on symbol
   */
  async routeCommand<T = unknown>(
    symbol: string,
    command: MatchingEngineCommand<T>
  ): Promise<RoutingResult> {
    if (!this.shardingEnabled) {
      await this.kafkaClient.send(LEGACY_MATCHING_ENGINE_TOPIC, command);
      return {
        shardId: "legacy",
        topic: LEGACY_MATCHING_ENGINE_TOPIC,
        success: true,
      };
    }

    if (this.pausedSymbols.has(symbol)) {
      throw new SymbolPausedException(symbol);
    }

    const shard = this.getShardForSymbol(symbol);

    if (!this.isShardAvailable(shard)) {
      throw new ShardUnavailableException(shard.shardId);
    }

    try {
      await this.kafkaClient.send(shard.kafkaInputTopic, command);

      this.logger.debug(
        `Routed command ${command.code} for symbol ${symbol} to shard ${shard.shardId}`
      );

      return {
        shardId: shard.shardId,
        topic: shard.kafkaInputTopic,
        success: true,
      };
    } catch (error) {
      this.logger.error(
        `Failed to route command to shard ${shard.shardId}: ${error.message}`
      );
      return {
        shardId: shard.shardId,
        topic: shard.kafkaInputTopic,
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * Route multiple commands for the same symbol (batch)
   */
  async routeCommands<T = unknown>(
    symbol: string,
    commands: MatchingEngineCommand<T>[]
  ): Promise<RoutingResult> {
    if (!this.shardingEnabled) {
      for (const command of commands) {
        await this.kafkaClient.send(LEGACY_MATCHING_ENGINE_TOPIC, command);
      }
      return {
        shardId: "legacy",
        topic: LEGACY_MATCHING_ENGINE_TOPIC,
        success: true,
      };
    }

    if (this.pausedSymbols.has(symbol)) {
      throw new SymbolPausedException(symbol);
    }

    const shard = this.getShardForSymbol(symbol);

    if (!this.isShardAvailable(shard)) {
      throw new ShardUnavailableException(shard.shardId);
    }

    try {
      for (const command of commands) {
        await this.kafkaClient.send(shard.kafkaInputTopic, command);
      }

      return {
        shardId: shard.shardId,
        topic: shard.kafkaInputTopic,
        success: true,
      };
    } catch (error) {
      return {
        shardId: shard.shardId,
        topic: shard.kafkaInputTopic,
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * Get the shard responsible for a symbol
   */
  getShardForSymbol(symbol: string): ShardInfo {
    const shard = this.symbolToShardMapping.get(symbol);
    if (shard) {
      return shard;
    }
    return this.defaultShard;
  }

  /**
   * Get the Kafka topic for a symbol
   */
  getTopicForSymbol(symbol: string): string {
    if (!this.shardingEnabled) {
      return LEGACY_MATCHING_ENGINE_TOPIC;
    }
    return this.getShardForSymbol(symbol).kafkaInputTopic;
  }

  /**
   * Check if sharding is enabled
   */
  isShardingEnabled(): boolean {
    return this.shardingEnabled;
  }

  /**
   * Check if a shard is available for processing
   */
  private isShardAvailable(shard: ShardInfo): boolean {
    return (
      shard.status === ShardStatus.ACTIVE && shard.role === ShardRole.PRIMARY
    );
  }

  /**
   * Load shard configuration from environment or defaults
   */
  private loadShardConfig(): void {
    const config = getConfig();

    for (const defaultShard of DEFAULT_SHARD_CONFIG) {
      const shardId = defaultShard.shardId;
      const shardNum = shardId.split("-")[1];

      let inputTopic = defaultShard.kafkaInputTopic;
      let outputTopic = defaultShard.kafkaOutputTopic;

      try {
        inputTopic =
          config.get<string>(`sharding.shard${shardNum}.inputTopic`) ??
          inputTopic;
        outputTopic =
          config.get<string>(`sharding.shard${shardNum}.outputTopic`) ??
          outputTopic;
      } catch {
        // Use defaults
      }

      const shardInfo: ShardInfo = {
        ...defaultShard,
        kafkaInputTopic: inputTopic,
        kafkaOutputTopic: outputTopic,
      };

      this.shardInfoMap.set(shardId, shardInfo);

      if (shardId === DEFAULT_SHARD_ID) {
        this.defaultShard = shardInfo;
      }
    }
  }

  /**
   * Load symbol to shard mapping from environment or defaults
   */
  private loadSymbolMapping(): void {
    const config = getConfig();

    const shardSymbolsConfig: Record<string, string[]> = {
      "shard-1": [],
      "shard-2": [],
      "shard-3": [],
    };

    try {
      const shard1Symbols = config.get<string>("sharding.shard1.symbols");
      if (shard1Symbols) {
        shardSymbolsConfig["shard-1"] = shard1Symbols
          .split(",")
          .map((s) => s.trim())
          .filter((s) => s);
      }
    } catch {
      // Use defaults
    }

    try {
      const shard2Symbols = config.get<string>("sharding.shard2.symbols");
      if (shard2Symbols) {
        shardSymbolsConfig["shard-2"] = shard2Symbols
          .split(",")
          .map((s) => s.trim())
          .filter((s) => s);
      }
    } catch {
      // Use defaults
    }

    try {
      const shard3Symbols = config.get<string>("sharding.shard3.symbols");
      if (shard3Symbols) {
        shardSymbolsConfig["shard-3"] = shard3Symbols
          .split(",")
          .map((s) => s.trim())
          .filter((s) => s);
      }
    } catch {
      // Use defaults
    }

    for (const [shardId, symbols] of Object.entries(shardSymbolsConfig)) {
      const shard = this.shardInfoMap.get(shardId);
      if (shard) {
        for (const symbol of symbols) {
          this.symbolToShardMapping.set(symbol, shard);
        }
      }
    }

    for (const [symbol, shardId] of Object.entries(DEFAULT_SYMBOL_MAPPING)) {
      if (!this.symbolToShardMapping.has(symbol)) {
        const shard = this.shardInfoMap.get(shardId);
        if (shard) {
          this.symbolToShardMapping.set(symbol, shard);
        }
      }
    }
  }

  /**
   * Pause a symbol (for rebalancing)
   */
  pauseSymbol(symbol: string): void {
    this.pausedSymbols.add(symbol);
    this.logger.log(`Paused symbol ${symbol}`);
  }

  /**
   * Resume a symbol (after rebalancing)
   */
  resumeSymbol(symbol: string): void {
    this.pausedSymbols.delete(symbol);
    this.logger.log(`Resumed symbol ${symbol}`);
  }

  /**
   * Update symbol mapping dynamically
   */
  updateSymbolMapping(symbol: string, newShardId: string): void {
    const shard = this.shardInfoMap.get(newShardId);
    if (!shard) {
      throw new UnknownShardException(newShardId);
    }
    this.symbolToShardMapping.set(symbol, shard);
    this.logger.log(`Updated symbol ${symbol} mapping to shard ${newShardId}`);
  }

  /**
   * Get all shard info (for monitoring/admin)
   */
  getAllShards(): ShardInfo[] {
    return Array.from(this.shardInfoMap.values());
  }

  /**
   * Get symbols for a specific shard
   */
  getSymbolsForShard(shardId: string): string[] {
    const symbols: string[] = [];
    for (const [symbol, shard] of this.symbolToShardMapping.entries()) {
      if (shard.shardId === shardId) {
        symbols.push(symbol);
      }
    }
    return symbols;
  }

  /**
   * Check if a symbol is paused
   */
  isSymbolPaused(symbol: string): boolean {
    return this.pausedSymbols.has(symbol);
  }
}

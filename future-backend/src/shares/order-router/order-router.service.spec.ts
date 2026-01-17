import { Test, TestingModule } from "@nestjs/testing";
import { OrderRouterService } from "./order-router.service";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { ShardStatus, ShardRole } from "./shard-info.interface";
import {
  ShardUnavailableException,
  SymbolPausedException,
} from "./order-router.exception";
import * as configModule from "src/configs/index";

describe("OrderRouterService", () => {
  let service: OrderRouterService;

  const mockKafkaClient = {
    send: jest.fn().mockResolvedValue([]),
  };

  describe("when sharding is disabled", () => {
    beforeEach(async () => {
      jest.spyOn(configModule, "getConfig").mockReturnValue({
        get: (key: string) => {
          if (key === "sharding.enabled") return false;
          return undefined;
        },
      } as any);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          {
            provide: KafkaClient,
            useValue: mockKafkaClient,
          },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();

      jest.clearAllMocks();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it("should use legacy topic for all commands", async () => {
      const result = await service.routeCommand("BTCUSDT", {
        code: "PLACE_ORDER",
        data: { id: 1 },
      });

      expect(result.shardId).toBe("legacy");
      expect(result.topic).toBe("matching_engine_input");
      expect(result.success).toBe(true);
      expect(mockKafkaClient.send).toHaveBeenCalledWith(
        "matching_engine_input",
        { code: "PLACE_ORDER", data: { id: 1 } }
      );
    });

    it("should return false for isShardingEnabled", () => {
      expect(service.isShardingEnabled()).toBe(false);
    });

    it("should return legacy topic for getTopicForSymbol", () => {
      expect(service.getTopicForSymbol("BTCUSDT")).toBe("matching_engine_input");
    });

    it("should return undefined for getShardForSymbol", () => {
      const shard = service.getShardForSymbol("BTCUSDT");
      expect(shard).toBeUndefined();
    });

    it("should send multiple commands to legacy topic", async () => {
      const commands = [
        { code: "PLACE_ORDER", data: { id: 1 } },
        { code: "PLACE_ORDER", data: { id: 2 } },
      ];

      const result = await service.routeCommands("BTCUSDT", commands);

      expect(result.shardId).toBe("legacy");
      expect(result.success).toBe(true);
      expect(mockKafkaClient.send).toHaveBeenCalledTimes(2);
    });

    it("should return empty array for getAllShards", () => {
      const shards = service.getAllShards();
      expect(shards).toEqual([]);
    });

    it("should return empty array for getSymbolsForShard", () => {
      const symbols = service.getSymbolsForShard("shard-1");
      expect(symbols).toEqual([]);
    });
  });

  describe("when sharding is enabled", () => {
    beforeEach(async () => {
      jest.spyOn(configModule, "getConfig").mockReturnValue({
        get: (key: string) => {
          if (key === "sharding.enabled") return true;
          if (key === "sharding.shard1.symbols") return "BTCUSDT,BTCBUSD";
          if (key === "sharding.shard2.symbols") return "ETHUSDT,ETHBUSD";
          return undefined;
        },
      } as any);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          {
            provide: KafkaClient,
            useValue: mockKafkaClient,
          },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();

      jest.clearAllMocks();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it("should return true for isShardingEnabled", () => {
      expect(service.isShardingEnabled()).toBe(true);
    });

    it("should route BTCUSDT to shard-1", async () => {
      const result = await service.routeCommand("BTCUSDT", {
        code: "PLACE_ORDER",
        data: { id: 1 },
      });

      expect(result.shardId).toBe("shard-1");
      expect(result.topic).toBe("matching-engine-shard-1-input");
      expect(result.success).toBe(true);
    });

    it("should route ETHUSDT to shard-2", async () => {
      const result = await service.routeCommand("ETHUSDT", {
        code: "PLACE_ORDER",
        data: { id: 1 },
      });

      expect(result.shardId).toBe("shard-2");
      expect(result.topic).toBe("matching-engine-shard-2-input");
      expect(result.success).toBe(true);
    });

    it("should route unknown symbol to default shard (shard-3)", async () => {
      const result = await service.routeCommand("SOLUSDT", {
        code: "PLACE_ORDER",
        data: { id: 1 },
      });

      expect(result.shardId).toBe("shard-3");
      expect(result.topic).toBe("matching-engine-shard-3-input");
      expect(result.success).toBe(true);
    });

    it("should return correct topic for symbol", () => {
      expect(service.getTopicForSymbol("BTCUSDT")).toBe(
        "matching-engine-shard-1-input"
      );
      expect(service.getTopicForSymbol("ETHUSDT")).toBe(
        "matching-engine-shard-2-input"
      );
      expect(service.getTopicForSymbol("SOLUSDT")).toBe(
        "matching-engine-shard-3-input"
      );
    });

    it("should return all 3 shards", () => {
      const shards = service.getAllShards();
      expect(shards.length).toBe(3);
      expect(shards.map((s) => s.shardId)).toEqual([
        "shard-1",
        "shard-2",
        "shard-3",
      ]);
    });

    it("should return symbols for shard", () => {
      const shard1Symbols = service.getSymbolsForShard("shard-1");
      expect(shard1Symbols).toContain("BTCUSDT");
      expect(shard1Symbols).toContain("BTCBUSD");
    });

    it("should throw SymbolPausedException for paused symbol", async () => {
      service.pauseSymbol("BTCUSDT");

      await expect(
        service.routeCommand("BTCUSDT", {
          code: "PLACE_ORDER",
          data: { id: 1 },
        })
      ).rejects.toThrow(SymbolPausedException);

      service.resumeSymbol("BTCUSDT");
    });

    it("should route batch commands to correct shard", async () => {
      const commands = [
        { code: "PLACE_ORDER", data: { id: 1 } },
        { code: "PLACE_ORDER", data: { id: 2 } },
      ];

      const result = await service.routeCommands("BTCUSDT", commands);

      expect(result.shardId).toBe("shard-1");
      expect(result.success).toBe(true);
      expect(mockKafkaClient.send).toHaveBeenCalledTimes(2);
    });

    it("should update symbol mapping dynamically", () => {
      service.updateSymbolMapping("SOLUSDT", "shard-1");

      const shard = service.getShardForSymbol("SOLUSDT");
      expect(shard.shardId).toBe("shard-1");
    });
  });

  describe("pause and resume symbols", () => {
    beforeEach(async () => {
      jest.spyOn(configModule, "getConfig").mockReturnValue({
        get: () => false,
      } as any);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          {
            provide: KafkaClient,
            useValue: mockKafkaClient,
          },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it("should pause and resume symbols", () => {
      service.pauseSymbol("BTCUSDT");
      expect(service.isSymbolPaused("BTCUSDT")).toBe(true);

      service.resumeSymbol("BTCUSDT");
      expect(service.isSymbolPaused("BTCUSDT")).toBe(false);
    });
  });
});

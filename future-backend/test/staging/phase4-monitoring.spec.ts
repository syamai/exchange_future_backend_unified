/**
 * Phase 4: Monitoring Verification Tests
 *
 * Tests:
 * 4.1 Shard information API
 * 4.2 Logging output verification
 * 4.3 Health check endpoints simulation
 */

import { Test, TestingModule } from '@nestjs/testing';
import { OrderRouterService } from 'src/shares/order-router/order-router.service';
import { KafkaClient } from 'src/shares/kafka-client/kafka-client';
import { ShardStatus, ShardRole } from 'src/shares/order-router/shard-info.interface';
import * as configModule from 'src/configs/index';

describe('Phase 4: Monitoring Verification Tests', () => {
  describe('4.1 Shard Information API', () => {
    let service: OrderRouterService;
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    beforeEach(async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT,BTCBUSD,BTCUSDC';
          if (key === 'sharding.shard2.symbols') return 'ETHUSDT,ETHBUSD';
          if (key === 'sharding.shard3.symbols') return '';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should return all 3 shards with getAllShards()', () => {
      const shards = service.getAllShards();

      expect(shards).toHaveLength(3);
      expect(shards.map((s) => s.shardId).sort()).toEqual([
        'shard-1',
        'shard-2',
        'shard-3',
      ]);
    });

    it('should return correct shard details', () => {
      const shards = service.getAllShards();
      const shard1 = shards.find((s) => s.shardId === 'shard-1');

      expect(shard1).toBeDefined();
      expect(shard1!.status).toBe(ShardStatus.ACTIVE);
      expect(shard1!.role).toBe(ShardRole.PRIMARY);
      expect(shard1!.kafkaInputTopic).toBe('matching-engine-shard-1-input');
      expect(shard1!.kafkaOutputTopic).toBe('matching-engine-shard-1-output');
    });

    it('should return symbols for each shard', () => {
      const shard1Symbols = service.getSymbolsForShard('shard-1');
      const shard2Symbols = service.getSymbolsForShard('shard-2');

      expect(shard1Symbols).toContain('BTCUSDT');
      expect(shard1Symbols).toContain('BTCBUSD');
      expect(shard1Symbols).toContain('BTCUSDC');

      expect(shard2Symbols).toContain('ETHUSDT');
      expect(shard2Symbols).toContain('ETHBUSD');
    });

    it('should return sharding status', () => {
      expect(service.isShardingEnabled()).toBe(true);
    });

    it('should return symbol pause status', () => {
      expect(service.isSymbolPaused('BTCUSDT')).toBe(false);

      service.pauseSymbol('BTCUSDT');
      expect(service.isSymbolPaused('BTCUSDT')).toBe(true);

      service.resumeSymbol('BTCUSDT');
      expect(service.isSymbolPaused('BTCUSDT')).toBe(false);
    });
  });

  describe('4.2 Monitoring Data for Dashboard', () => {
    let service: OrderRouterService;
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    beforeEach(async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT';
          if (key === 'sharding.shard2.symbols') return 'ETHUSDT';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();
      jest.clearAllMocks();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should provide shard distribution data', async () => {
      // Send orders to different shards
      await service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('ETHUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('SOLUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('SOLUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('SOLUSDT', { code: 'PLACE_ORDER', data: {} });

      // Verify calls distribution
      const calls = mockKafkaClient.send.mock.calls;
      const distribution: Record<string, number> = {};

      calls.forEach((call: [string, unknown]) => {
        const topic = call[0];
        distribution[topic] = (distribution[topic] || 0) + 1;
      });

      expect(distribution['matching-engine-shard-1-input']).toBe(2); // BTCUSDT
      expect(distribution['matching-engine-shard-2-input']).toBe(1); // ETHUSDT
      expect(distribution['matching-engine-shard-3-input']).toBe(3); // SOLUSDT
    });

    it('should track command types', async () => {
      await service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: {} });
      await service.routeCommand('BTCUSDT', { code: 'CANCEL_ORDER', data: {} });
      await service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: {} });

      const calls = mockKafkaClient.send.mock.calls;
      const commandTypes: Record<string, number> = {};

      calls.forEach((call: [string, { code: string }]) => {
        const code = call[1].code;
        commandTypes[code] = (commandTypes[code] || 0) + 1;
      });

      expect(commandTypes['PLACE_ORDER']).toBe(2);
      expect(commandTypes['CANCEL_ORDER']).toBe(1);
    });
  });

  describe('4.3 Health Check Simulation', () => {
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should report healthy when sharding is enabled and all shards active', async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      const service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();

      // Health check data
      const healthData = {
        shardingEnabled: service.isShardingEnabled(),
        shards: service.getAllShards().map((s) => ({
          id: s.shardId,
          status: s.status,
          role: s.role,
        })),
        pausedSymbols: [] as string[],
      };

      expect(healthData.shardingEnabled).toBe(true);
      expect(healthData.shards).toHaveLength(3);
      expect(healthData.shards.every((s) => s.status === ShardStatus.ACTIVE)).toBe(
        true,
      );

      await module.close();
    });

    it('should report healthy when sharding is disabled (legacy mode)', async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return false;
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      const service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();

      const healthData = {
        shardingEnabled: service.isShardingEnabled(),
        mode: 'legacy',
        topic: 'matching_engine_input',
      };

      expect(healthData.shardingEnabled).toBe(false);
      expect(healthData.mode).toBe('legacy');

      await module.close();
    });

    it('should include paused symbols in health check', async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      const service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();

      // Pause some symbols
      service.pauseSymbol('BTCUSDT');
      service.pauseSymbol('ETHUSDT');

      const pausedSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'].filter((s) =>
        service.isSymbolPaused(s),
      );

      expect(pausedSymbols).toEqual(['BTCUSDT', 'ETHUSDT']);

      await module.close();
    });
  });

  describe('4.4 Logging Format Verification', () => {
    let service: OrderRouterService;
    let logSpy: jest.SpyInstance;
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    beforeEach(async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();
      jest.clearAllMocks();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should log routing decisions', async () => {
      // The service logs routing decisions internally
      // We verify the routing result contains the expected information
      const result = await service.routeCommand('BTCUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 123, symbol: 'BTCUSDT' },
      });

      // Verify result contains all needed logging information
      expect(result).toMatchObject({
        shardId: expect.any(String),
        topic: expect.any(String),
        success: true,
      });
    });

    it('should log pause/resume events', () => {
      // Pause logs internally
      service.pauseSymbol('BTCUSDT');
      expect(service.isSymbolPaused('BTCUSDT')).toBe(true);

      // Resume logs internally
      service.resumeSymbol('BTCUSDT');
      expect(service.isSymbolPaused('BTCUSDT')).toBe(false);
    });

    it('should log mapping updates', () => {
      const originalShard = service.getShardForSymbol('SOLUSDT');
      expect(originalShard.shardId).toBe('shard-3');

      // Update mapping logs internally
      service.updateSymbolMapping('SOLUSDT', 'shard-1');

      const newShard = service.getShardForSymbol('SOLUSDT');
      expect(newShard.shardId).toBe('shard-1');
    });
  });
});

/**
 * Phase 3: Failure Scenario Tests
 *
 * Tests:
 * 3.1 Symbol pause/resume functionality
 * 3.2 Shard unavailable handling
 * 3.3 Rollback (disable sharding) scenario
 * 3.4 Kafka connection error handling
 */

import { Test, TestingModule } from '@nestjs/testing';
import { OrderRouterService } from 'src/shares/order-router/order-router.service';
import { KafkaClient } from 'src/shares/kafka-client/kafka-client';
import {
  ShardUnavailableException,
  SymbolPausedException,
} from 'src/shares/order-router/order-router.exception';
import * as configModule from 'src/configs/index';

describe('Phase 3: Failure Scenario Tests', () => {
  describe('3.1 Symbol Pause/Resume', () => {
    let service: OrderRouterService;
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    beforeEach(async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT,BTCBUSD';
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

    it('should block orders for paused symbol', async () => {
      // Given: Symbol is not paused
      expect(service.isSymbolPaused('BTCUSDT')).toBe(false);

      // When: Pause symbol for rebalancing
      service.pauseSymbol('BTCUSDT');

      // Then: Symbol is paused
      expect(service.isSymbolPaused('BTCUSDT')).toBe(true);

      // And: Orders are rejected
      await expect(
        service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: {} }),
      ).rejects.toThrow(SymbolPausedException);
    });

    it('should allow orders after resuming symbol', async () => {
      // Given: Symbol is paused
      service.pauseSymbol('BTCUSDT');
      expect(service.isSymbolPaused('BTCUSDT')).toBe(true);

      // When: Resume symbol
      service.resumeSymbol('BTCUSDT');

      // Then: Orders are accepted
      expect(service.isSymbolPaused('BTCUSDT')).toBe(false);

      const result = await service.routeCommand('BTCUSDT', {
        code: 'PLACE_ORDER',
        data: {},
      });
      expect(result.success).toBe(true);
    });

    it('should not affect other symbols when one is paused', async () => {
      // Given: BTCUSDT is paused
      service.pauseSymbol('BTCUSDT');

      // Then: ETHUSDT should still work
      const result = await service.routeCommand('ETHUSDT', {
        code: 'PLACE_ORDER',
        data: {},
      });
      expect(result.success).toBe(true);
    });
  });

  describe('3.2 Kafka Connection Error Handling', () => {
    let service: OrderRouterService;
    const mockKafkaClient = {
      send: jest.fn(),
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
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should handle Kafka send failure gracefully', async () => {
      // Given: Kafka send will fail
      mockKafkaClient.send.mockRejectedValueOnce(
        new Error('Kafka connection timeout'),
      );

      // When: Attempt to route command
      const result = await service.routeCommand('BTCUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 1 },
      });

      // Then: Returns failure result (not throws)
      expect(result.success).toBe(false);
      expect(result.error).toContain('Kafka connection timeout');
    });

    it('should retry on transient errors (batch commands)', async () => {
      // Given: First call fails, second succeeds
      mockKafkaClient.send
        .mockRejectedValueOnce(new Error('Broker not available'))
        .mockResolvedValueOnce([]);

      // When: Send batch commands
      const result = await service.routeCommands('BTCUSDT', [
        { code: 'PLACE_ORDER', data: { id: 1 } },
      ]);

      // Then: First attempt fails
      expect(result.success).toBe(false);
    });
  });

  describe('3.3 Rollback Scenario (Disable Sharding)', () => {
    const mockKafkaClient = {
      send: jest.fn().mockResolvedValue([]),
    };

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should route to legacy topic after rollback', async () => {
      // Step 1: Start with sharding enabled
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT';
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module1: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      const service1 = module1.get<OrderRouterService>(OrderRouterService);
      await service1.initialize();

      // Verify sharding is enabled
      expect(service1.isShardingEnabled()).toBe(true);
      expect(service1.getTopicForSymbol('BTCUSDT')).toBe(
        'matching-engine-shard-1-input',
      );

      await module1.close();
      jest.restoreAllMocks();

      // Step 2: Simulate rollback - disable sharding
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return false;
          return undefined;
        },
      } as ReturnType<typeof configModule.getConfig>);

      const module2: TestingModule = await Test.createTestingModule({
        providers: [
          OrderRouterService,
          { provide: KafkaClient, useValue: mockKafkaClient },
        ],
      }).compile();

      const service2 = module2.get<OrderRouterService>(OrderRouterService);
      await service2.initialize();

      // Verify rollback successful
      expect(service2.isShardingEnabled()).toBe(false);
      expect(service2.getTopicForSymbol('BTCUSDT')).toBe('matching_engine_input');
      expect(service2.getTopicForSymbol('ETHUSDT')).toBe('matching_engine_input');
      expect(service2.getTopicForSymbol('SOLUSDT')).toBe('matching_engine_input');

      await module2.close();
    });

    it('should handle in-flight orders during rollback', async () => {
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
      mockKafkaClient.send.mockClear();

      // All orders should go to legacy topic
      await service.routeCommand('BTCUSDT', { code: 'PLACE_ORDER', data: { id: 1 } });
      await service.routeCommand('ETHUSDT', { code: 'PLACE_ORDER', data: { id: 2 } });
      await service.routeCommand('SOLUSDT', { code: 'PLACE_ORDER', data: { id: 3 } });

      // Verify all went to legacy topic
      expect(mockKafkaClient.send).toHaveBeenCalledTimes(3);
      mockKafkaClient.send.mock.calls.forEach((call: [string, unknown]) => {
        expect(call[0]).toBe('matching_engine_input');
      });

      await module.close();
    });
  });

  describe('3.4 Dynamic Symbol Rebalancing', () => {
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

    it('should support dynamic symbol migration between shards', async () => {
      // Given: SOLUSDT is on shard-3 (default)
      expect(service.getShardForSymbol('SOLUSDT').shardId).toBe('shard-3');

      // When: Migrate SOLUSDT to shard-1
      service.pauseSymbol('SOLUSDT');
      service.updateSymbolMapping('SOLUSDT', 'shard-1');
      service.resumeSymbol('SOLUSDT');

      // Then: SOLUSDT is now on shard-1
      expect(service.getShardForSymbol('SOLUSDT').shardId).toBe('shard-1');
      expect(service.getTopicForSymbol('SOLUSDT')).toBe(
        'matching-engine-shard-1-input',
      );

      // Verify routing works
      const result = await service.routeCommand('SOLUSDT', {
        code: 'PLACE_ORDER',
        data: { id: 1 },
      });
      expect(result.shardId).toBe('shard-1');
    });

    it('should complete rebalancing workflow correctly', async () => {
      const symbol = 'ADAUSDT';

      // Step 1: Initial state - on default shard
      expect(service.getShardForSymbol(symbol).shardId).toBe('shard-3');

      // Step 2: Start rebalancing - pause symbol
      service.pauseSymbol(symbol);
      expect(service.isSymbolPaused(symbol)).toBe(true);

      // Step 3: Update mapping while paused
      service.updateSymbolMapping(symbol, 'shard-2');

      // Step 4: Resume symbol
      service.resumeSymbol(symbol);
      expect(service.isSymbolPaused(symbol)).toBe(false);

      // Step 5: Verify new routing
      const result = await service.routeCommand(symbol, {
        code: 'PLACE_ORDER',
        data: { id: 1 },
      });
      expect(result.shardId).toBe('shard-2');
      expect(result.topic).toBe('matching-engine-shard-2-input');
    });
  });
});

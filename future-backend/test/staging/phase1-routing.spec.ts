/**
 * Phase 1: Routing Integration Test
 *
 * Tests:
 * 1.1 Sharding disabled - all orders go to legacy topic
 * 1.2 Sharding enabled - orders routed to correct shard topics
 */

import { Test, TestingModule } from '@nestjs/testing';
import { OrderRouterService } from 'src/shares/order-router/order-router.service';
import { KafkaClient } from 'src/shares/kafka-client/kafka-client';
import * as configModule from 'src/configs/index';

const mockKafkaClient = {
  send: jest.fn().mockResolvedValue([]),
};

describe('Phase 1: Routing Integration Test', () => {
  describe('Phase 1.1: Sharding Disabled', () => {
    let service: OrderRouterService;

    beforeEach(async () => {
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

      service = module.get<OrderRouterService>(OrderRouterService);
      await service.initialize();
      jest.clearAllMocks();
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('should route BTCUSDT to legacy topic', () => {
      expect(service.getTopicForSymbol('BTCUSDT')).toBe('matching_engine_input');
    });

    it('should route ETHUSDT to legacy topic', () => {
      expect(service.getTopicForSymbol('ETHUSDT')).toBe('matching_engine_input');
    });

    it('should route SOLUSDT to legacy topic', () => {
      expect(service.getTopicForSymbol('SOLUSDT')).toBe('matching_engine_input');
    });

    it('should route XRPUSDT to legacy topic', () => {
      expect(service.getTopicForSymbol('XRPUSDT')).toBe('matching_engine_input');
    });

    it('should send command to legacy topic via routeCommand', async () => {
      const result = await service.routeCommand('BTCUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 123 },
      });

      expect(result.shardId).toBe('legacy');
      expect(result.topic).toBe('matching_engine_input');
      expect(result.success).toBe(true);
      expect(mockKafkaClient.send).toHaveBeenCalledWith('matching_engine_input', {
        code: 'PLACE_ORDER',
        data: { orderId: 123 },
      });
    });
  });

  describe('Phase 1.2: Sharding Enabled', () => {
    let service: OrderRouterService;

    beforeEach(async () => {
      jest.spyOn(configModule, 'getConfig').mockReturnValue({
        get: (key: string) => {
          if (key === 'sharding.enabled') return true;
          if (key === 'sharding.shard1.symbols') return 'BTCUSDT,BTCBUSD,BTCUSDC';
          if (key === 'sharding.shard2.symbols') return 'ETHUSDT,ETHBUSD,ETHUSDC';
          if (key === 'sharding.shard3.symbols') return '';
          if (key === 'sharding.shard1.inputTopic')
            return 'matching-engine-shard-1-input';
          if (key === 'sharding.shard2.inputTopic')
            return 'matching-engine-shard-2-input';
          if (key === 'sharding.shard3.inputTopic')
            return 'matching-engine-shard-3-input';
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

    // Shard 1: BTC pairs
    it('should route BTCUSDT to shard-1', () => {
      expect(service.getTopicForSymbol('BTCUSDT')).toBe(
        'matching-engine-shard-1-input',
      );
    });

    it('should route BTCBUSD to shard-1', () => {
      expect(service.getTopicForSymbol('BTCBUSD')).toBe(
        'matching-engine-shard-1-input',
      );
    });

    it('should route BTCUSDC to shard-1', () => {
      expect(service.getTopicForSymbol('BTCUSDC')).toBe(
        'matching-engine-shard-1-input',
      );
    });

    // Shard 2: ETH pairs
    it('should route ETHUSDT to shard-2', () => {
      expect(service.getTopicForSymbol('ETHUSDT')).toBe(
        'matching-engine-shard-2-input',
      );
    });

    it('should route ETHBUSD to shard-2', () => {
      expect(service.getTopicForSymbol('ETHBUSD')).toBe(
        'matching-engine-shard-2-input',
      );
    });

    it('should route ETHUSDC to shard-2', () => {
      expect(service.getTopicForSymbol('ETHUSDC')).toBe(
        'matching-engine-shard-2-input',
      );
    });

    // Shard 3: Other symbols (default)
    it('should route SOLUSDT to shard-3 (default)', () => {
      expect(service.getTopicForSymbol('SOLUSDT')).toBe(
        'matching-engine-shard-3-input',
      );
    });

    it('should route XRPUSDT to shard-3 (default)', () => {
      expect(service.getTopicForSymbol('XRPUSDT')).toBe(
        'matching-engine-shard-3-input',
      );
    });

    it('should route ADAUSDT to shard-3 (default)', () => {
      expect(service.getTopicForSymbol('ADAUSDT')).toBe(
        'matching-engine-shard-3-input',
      );
    });

    it('should route DOTUSDT to shard-3 (default)', () => {
      expect(service.getTopicForSymbol('DOTUSDT')).toBe(
        'matching-engine-shard-3-input',
      );
    });

    it('should route MATICUSDT to shard-3 (default)', () => {
      expect(service.getTopicForSymbol('MATICUSDT')).toBe(
        'matching-engine-shard-3-input',
      );
    });

    // Verify routeCommand with Kafka
    it('should send BTCUSDT command to shard-1 topic', async () => {
      const result = await service.routeCommand('BTCUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 1 },
      });

      expect(result.shardId).toBe('shard-1');
      expect(result.topic).toBe('matching-engine-shard-1-input');
      expect(result.success).toBe(true);
      expect(mockKafkaClient.send).toHaveBeenCalledWith(
        'matching-engine-shard-1-input',
        { code: 'PLACE_ORDER', data: { orderId: 1 } },
      );
    });

    it('should send ETHUSDT command to shard-2 topic', async () => {
      const result = await service.routeCommand('ETHUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 2 },
      });

      expect(result.shardId).toBe('shard-2');
      expect(result.topic).toBe('matching-engine-shard-2-input');
      expect(result.success).toBe(true);
    });

    it('should send SOLUSDT command to shard-3 topic', async () => {
      const result = await service.routeCommand('SOLUSDT', {
        code: 'PLACE_ORDER',
        data: { orderId: 3 },
      });

      expect(result.shardId).toBe('shard-3');
      expect(result.topic).toBe('matching-engine-shard-3-input');
      expect(result.success).toBe(true);
    });
  });
});

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

interface TestResult {
  testCase: string;
  symbol: string;
  expectedTopic: string;
  actualTopic: string;
  passed: boolean;
}

const mockKafkaClient = {
  send: jest.fn().mockResolvedValue([]),
};

async function runPhase1Tests(): Promise<void> {
  console.log('='.repeat(60));
  console.log('Phase 1: Routing Integration Test');
  console.log('='.repeat(60));

  const results: TestResult[] = [];

  // Phase 1.1: Sharding Disabled Test
  console.log('\n📋 Phase 1.1: Sharding Disabled Test');
  console.log('-'.repeat(40));

  jest.spyOn(configModule, 'getConfig').mockReturnValue({
    get: (key: string) => {
      if (key === 'sharding.enabled') return false;
      return undefined;
    },
  } as ReturnType<typeof configModule.getConfig>);

  const module1: TestingModule = await Test.createTestingModule({
    providers: [
      OrderRouterService,
      { provide: KafkaClient, useValue: mockKafkaClient },
    ],
  }).compile();

  const router1 = module1.get<OrderRouterService>(OrderRouterService);
  await router1.initialize();

  const disabledTests = [
    { symbol: 'BTCUSDT', expected: 'matching_engine_input' },
    { symbol: 'ETHUSDT', expected: 'matching_engine_input' },
    { symbol: 'SOLUSDT', expected: 'matching_engine_input' },
    { symbol: 'XRPUSDT', expected: 'matching_engine_input' },
  ];

  for (const test of disabledTests) {
    const actualTopic = router1.getTopicForSymbol(test.symbol);
    const passed = actualTopic === test.expected;

    results.push({
      testCase: 'Sharding Disabled',
      symbol: test.symbol,
      expectedTopic: test.expected,
      actualTopic,
      passed,
    });

    console.log(
      `  ${passed ? '✅' : '❌'} ${test.symbol}: ${actualTopic} (expected: ${test.expected})`,
    );
  }

  // Verify actual Kafka send
  console.log('\n  📨 Testing actual Kafka routing...');
  const sendResult = await router1.routeCommand('BTCUSDT', {
    code: 'PLACE_ORDER',
    data: { orderId: 123, symbol: 'BTCUSDT' },
  });
  console.log(
    `  ${sendResult.success ? '✅' : '❌'} routeCommand → ${sendResult.topic} (shardId: ${sendResult.shardId})`,
  );

  await module1.close();
  jest.restoreAllMocks();

  // Phase 1.2: Sharding Enabled Test
  console.log('\n📋 Phase 1.2: Sharding Enabled Routing Test');
  console.log('-'.repeat(40));

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

  const module2: TestingModule = await Test.createTestingModule({
    providers: [
      OrderRouterService,
      { provide: KafkaClient, useValue: mockKafkaClient },
    ],
  }).compile();

  const router2 = module2.get<OrderRouterService>(OrderRouterService);
  await router2.initialize();

  const enabledTests = [
    // Shard 1: BTC pairs
    { symbol: 'BTCUSDT', expected: 'matching-engine-shard-1-input' },
    { symbol: 'BTCBUSD', expected: 'matching-engine-shard-1-input' },
    { symbol: 'BTCUSDC', expected: 'matching-engine-shard-1-input' },
    // Shard 2: ETH pairs
    { symbol: 'ETHUSDT', expected: 'matching-engine-shard-2-input' },
    { symbol: 'ETHBUSD', expected: 'matching-engine-shard-2-input' },
    { symbol: 'ETHUSDC', expected: 'matching-engine-shard-2-input' },
    // Shard 3: Other symbols (default)
    { symbol: 'SOLUSDT', expected: 'matching-engine-shard-3-input' },
    { symbol: 'XRPUSDT', expected: 'matching-engine-shard-3-input' },
    { symbol: 'ADAUSDT', expected: 'matching-engine-shard-3-input' },
    { symbol: 'DOTUSDT', expected: 'matching-engine-shard-3-input' },
    { symbol: 'MATICUSDT', expected: 'matching-engine-shard-3-input' },
  ];

  for (const test of enabledTests) {
    const actualTopic = router2.getTopicForSymbol(test.symbol);
    const passed = actualTopic === test.expected;

    results.push({
      testCase: 'Sharding Enabled',
      symbol: test.symbol,
      expectedTopic: test.expected,
      actualTopic,
      passed,
    });

    console.log(
      `  ${passed ? '✅' : '❌'} ${test.symbol}: ${actualTopic} (expected: ${test.expected})`,
    );
  }

  // Verify actual Kafka routing for each shard
  console.log('\n  📨 Testing actual Kafka routing...');
  mockKafkaClient.send.mockClear();

  const btcResult = await router2.routeCommand('BTCUSDT', {
    code: 'PLACE_ORDER',
    data: { orderId: 1, symbol: 'BTCUSDT' },
  });
  console.log(
    `  ${btcResult.success ? '✅' : '❌'} BTCUSDT → ${btcResult.topic} (shard: ${btcResult.shardId})`,
  );

  const ethResult = await router2.routeCommand('ETHUSDT', {
    code: 'PLACE_ORDER',
    data: { orderId: 2, symbol: 'ETHUSDT' },
  });
  console.log(
    `  ${ethResult.success ? '✅' : '❌'} ETHUSDT → ${ethResult.topic} (shard: ${ethResult.shardId})`,
  );

  const solResult = await router2.routeCommand('SOLUSDT', {
    code: 'PLACE_ORDER',
    data: { orderId: 3, symbol: 'SOLUSDT' },
  });
  console.log(
    `  ${solResult.success ? '✅' : '❌'} SOLUSDT → ${solResult.topic} (shard: ${solResult.shardId})`,
  );

  // Verify Kafka send calls
  console.log('\n  📊 Kafka send verification:');
  console.log(`  Total sends: ${mockKafkaClient.send.mock.calls.length}`);
  mockKafkaClient.send.mock.calls.forEach(
    (call: [string, { code: string }], i: number) => {
      console.log(`  ${i + 1}. Topic: ${call[0]}, Command: ${call[1].code}`);
    },
  );

  await module2.close();
  jest.restoreAllMocks();

  // Summary
  console.log('\n' + '='.repeat(60));
  console.log('Test Summary');
  console.log('='.repeat(60));

  const passed = results.filter((r) => r.passed).length;
  const failed = results.filter((r) => !r.passed).length;
  const total = results.length;

  console.log(`Total: ${total}`);
  console.log(`Passed: ${passed} ✅`);
  console.log(`Failed: ${failed} ${failed > 0 ? '❌' : ''}`);
  console.log(`Pass Rate: ${((passed / total) * 100).toFixed(1)}%`);

  if (failed > 0) {
    console.log('\nFailed Tests:');
    results
      .filter((r) => !r.passed)
      .forEach((r) => {
        console.log(
          `  - ${r.testCase} / ${r.symbol}: got ${r.actualTopic}, expected ${r.expectedTopic}`,
        );
      });
    process.exit(1);
  }

  console.log('\n✅ All Phase 1 tests passed!');
}

// Run tests
runPhase1Tests().catch((err) => {
  console.error('Test failed:', err);
  process.exit(1);
});

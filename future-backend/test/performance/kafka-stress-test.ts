/**
 * Kafka Producer Stress Test
 *
 * Tests actual Kafka message throughput to shard topics.
 * Requires running Kafka/Redpanda instance.
 *
 * Usage:
 *   npx ts-node test/performance/kafka-stress-test.ts [options]
 *
 * Options:
 *   --broker=HOST:PORT  Kafka broker (default: localhost:19092)
 *   --messages=N        Number of messages to send (default: 50000)
 *   --batch-size=N      Messages per batch (default: 1000)
 *   --concurrency=N     Concurrent producers (default: 3)
 */

import { Kafka, Producer, CompressionTypes, logLevel } from "kafkajs";
import { performance } from "perf_hooks";

interface TestConfig {
  broker: string;
  messages: number;
  batchSize: number;
  concurrency: number;
}

interface TopicStats {
  messageCount: number;
  bytesSent: number;
  errors: number;
}

interface StressTestResult {
  totalMessages: number;
  durationMs: number;
  messagesPerSecond: number;
  throughputMBps: number;
  avgBatchLatencyMs: number;
  topicStats: Record<string, TopicStats>;
  errors: string[];
}

const SHARD_TOPICS = [
  "matching-engine-shard-1-input",
  "matching-engine-shard-2-input",
  "matching-engine-shard-3-input",
];

const SYMBOLS_BY_SHARD: Record<string, string[]> = {
  "matching-engine-shard-1-input": ["BTCUSDT", "BTCBUSD", "BTCUSDC"],
  "matching-engine-shard-2-input": ["ETHUSDT", "ETHBUSD", "ETHUSDC"],
  "matching-engine-shard-3-input": [
    "SOLUSDT",
    "XRPUSDT",
    "ADAUSDT",
    "DOTUSDT",
  ],
};

function generateOrderMessage(orderId: number, symbol: string): string {
  return JSON.stringify({
    code: "PLACE_ORDER",
    data: {
      id: orderId,
      symbol,
      side: orderId % 2 === 0 ? "BUY" : "SELL",
      type: orderId % 3 === 0 ? "MARKET" : "LIMIT",
      price: (50000 + Math.random() * 1000).toFixed(2),
      quantity: (Math.random() * 10).toFixed(4),
      userId: 1000 + (orderId % 1000),
      timestamp: Date.now(),
      leverage: 10,
      marginType: "CROSS",
    },
  });
}

async function createProducer(broker: string): Promise<Producer> {
  const kafka = new Kafka({
    clientId: `stress-test-${Date.now()}`,
    brokers: [broker],
    logLevel: logLevel.ERROR,
  });

  const producer = kafka.producer({
    allowAutoTopicCreation: false,
    transactionTimeout: 30000,
  });

  await producer.connect();
  return producer;
}

async function sendBatch(
  producer: Producer,
  topic: string,
  messages: { value: string }[],
  stats: TopicStats
): Promise<number> {
  const startTime = performance.now();

  try {
    await producer.send({
      topic,
      messages,
      compression: CompressionTypes.GZIP,
    });

    stats.messageCount += messages.length;
    stats.bytesSent += messages.reduce((sum, m) => sum + m.value.length, 0);
  } catch (error) {
    stats.errors++;
    throw error;
  }

  return performance.now() - startTime;
}

async function runStressTest(config: TestConfig): Promise<StressTestResult> {
  console.log("\n📊 Initializing Kafka stress test...");

  const producers: Producer[] = [];
  const topicStats: Record<string, TopicStats> = {};
  const errors: string[] = [];
  const batchLatencies: number[] = [];

  // Initialize stats for each topic
  SHARD_TOPICS.forEach((topic) => {
    topicStats[topic] = { messageCount: 0, bytesSent: 0, errors: 0 };
  });

  // Create producers
  console.log(`Creating ${config.concurrency} producers...`);
  for (let i = 0; i < config.concurrency; i++) {
    try {
      const producer = await createProducer(config.broker);
      producers.push(producer);
      console.log(`  Producer ${i + 1} connected ✓`);
    } catch (error) {
      errors.push(`Failed to create producer ${i + 1}: ${error}`);
    }
  }

  if (producers.length === 0) {
    throw new Error("No producers could be created");
  }

  // Calculate messages per producer
  const messagesPerProducer = Math.ceil(config.messages / producers.length);
  const batchesPerProducer = Math.ceil(messagesPerProducer / config.batchSize);

  console.log(`\nSending ${config.messages.toLocaleString()} messages...`);
  console.log(
    `  ${messagesPerProducer.toLocaleString()} messages per producer`
  );
  console.log(`  ${batchesPerProducer} batches per producer`);
  console.log(`  ${config.batchSize} messages per batch\n`);

  const startTime = performance.now();
  let sentCount = 0;

  // Run concurrent producers
  const producerTasks = producers.map(async (producer, producerIdx) => {
    for (let batch = 0; batch < batchesPerProducer; batch++) {
      // Rotate through topics
      const topicIdx = (producerIdx + batch) % SHARD_TOPICS.length;
      const topic = SHARD_TOPICS[topicIdx];
      const symbols = SYMBOLS_BY_SHARD[topic];

      // Generate batch messages
      const messages: { value: string }[] = [];
      for (let i = 0; i < config.batchSize; i++) {
        const orderId = producerIdx * messagesPerProducer + batch * config.batchSize + i;
        const symbol = symbols[orderId % symbols.length];
        messages.push({ value: generateOrderMessage(orderId, symbol) });
      }

      try {
        const latency = await sendBatch(
          producer,
          topic,
          messages,
          topicStats[topic]
        );
        batchLatencies.push(latency);

        sentCount += messages.length;
        if (sentCount % 10000 === 0) {
          const elapsed = (performance.now() - startTime) / 1000;
          const rate = sentCount / elapsed;
          process.stdout.write(
            `\r  Sent: ${sentCount.toLocaleString()} (${rate.toFixed(0)} msg/s)`
          );
        }
      } catch (error) {
        errors.push(`Batch error: ${error}`);
      }
    }
  });

  await Promise.all(producerTasks);
  const endTime = performance.now();

  console.log("\n");

  // Disconnect producers
  await Promise.all(producers.map((p) => p.disconnect()));

  // Calculate results
  const durationMs = endTime - startTime;
  const totalMessages = Object.values(topicStats).reduce(
    (sum, s) => sum + s.messageCount,
    0
  );
  const totalBytes = Object.values(topicStats).reduce(
    (sum, s) => sum + s.bytesSent,
    0
  );

  const avgBatchLatency =
    batchLatencies.length > 0
      ? batchLatencies.reduce((a, b) => a + b, 0) / batchLatencies.length
      : 0;

  return {
    totalMessages,
    durationMs,
    messagesPerSecond: (totalMessages / durationMs) * 1000,
    throughputMBps: totalBytes / 1024 / 1024 / (durationMs / 1000),
    avgBatchLatencyMs: avgBatchLatency,
    topicStats,
    errors,
  };
}

function formatResult(result: StressTestResult): string {
  const target = 50000; // More realistic for actual Kafka
  const achieved = result.messagesPerSecond >= target;
  const statusEmoji = achieved ? "✅" : "⚠️";

  let output = `
╔══════════════════════════════════════════════════════════════╗
║              Kafka Stress Test Results                        ║
╠══════════════════════════════════════════════════════════════╣
║  Total Messages:      ${result.totalMessages.toLocaleString().padStart(12)}                        ║
║  Duration:            ${result.durationMs.toFixed(2).padStart(12)} ms                     ║
║  Throughput:          ${result.messagesPerSecond.toFixed(0).padStart(12)} msg/sec ${statusEmoji}          ║
║  Target:              ${target.toLocaleString().padStart(12)} msg/sec                  ║
╠══════════════════════════════════════════════════════════════╣
║  Data Throughput:     ${result.throughputMBps.toFixed(2).padStart(12)} MB/s                   ║
║  Avg Batch Latency:   ${result.avgBatchLatencyMs.toFixed(2).padStart(12)} ms                     ║
╠══════════════════════════════════════════════════════════════╣
║  Per-Topic Stats:                                             ║`;

  for (const [topic, stats] of Object.entries(result.topicStats)) {
    const shortTopic = topic.replace("matching-engine-", "");
    output += `
║    ${shortTopic.padEnd(20)} ${stats.messageCount.toLocaleString().padStart(8)} msgs, ${(stats.bytesSent / 1024).toFixed(0).padStart(6)} KB ║`;
  }

  output += `
╠══════════════════════════════════════════════════════════════╣
║  Errors:              ${result.errors.length.toString().padStart(12)}                        ║
╚══════════════════════════════════════════════════════════════╝
`;

  if (result.errors.length > 0) {
    output += "\nErrors:\n";
    result.errors.slice(0, 5).forEach((e) => {
      output += `  - ${e}\n`;
    });
    if (result.errors.length > 5) {
      output += `  ... and ${result.errors.length - 5} more\n`;
    }
  }

  return output;
}

function parseArgs(): TestConfig {
  const args = process.argv.slice(2);
  const config: TestConfig = {
    broker: "localhost:19092",
    messages: 50000,
    batchSize: 1000,
    concurrency: 3,
  };

  for (const arg of args) {
    const [key, value] = arg.replace("--", "").split("=");
    if (key === "broker") config.broker = value;
    if (key === "messages") config.messages = parseInt(value, 10);
    if (key === "batch-size") config.batchSize = parseInt(value, 10);
    if (key === "concurrency") config.concurrency = parseInt(value, 10);
  }

  return config;
}

async function main() {
  console.log("╔══════════════════════════════════════════════════════════════╗");
  console.log("║           Kafka Producer Stress Test                          ║");
  console.log("╚══════════════════════════════════════════════════════════════╝");

  const config = parseArgs();
  console.log(`\nConfiguration:`);
  console.log(`  Broker:      ${config.broker}`);
  console.log(`  Messages:    ${config.messages.toLocaleString()}`);
  console.log(`  Batch Size:  ${config.batchSize}`);
  console.log(`  Concurrency: ${config.concurrency}`);

  try {
    const result = await runStressTest(config);
    console.log(formatResult(result));
  } catch (error) {
    console.error("\n❌ Stress test failed:", error);
    process.exit(1);
  }
}

main().catch(console.error);

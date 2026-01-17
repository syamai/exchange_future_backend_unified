/**
 * OrderRouter Performance Benchmark
 *
 * Tests the throughput of OrderRouter for routing orders to shards.
 * Goal: 100K orders/sec
 *
 * Usage:
 *   npx ts-node test/performance/order-router-benchmark.ts [options]
 *
 * Options:
 *   --orders=N       Number of orders to send (default: 100000)
 *   --concurrency=N  Concurrent workers (default: 10)
 *   --warmup=N       Warmup orders before measuring (default: 1000)
 */

import { performance } from "perf_hooks";

// Mock KafkaClient to measure pure routing performance
class MockKafkaClient {
  private messageCount = 0;
  private totalBytes = 0;

  async send(topic: string, message: unknown): Promise<void> {
    this.messageCount++;
    this.totalBytes += JSON.stringify(message).length;
    // Simulate minimal async overhead
    return Promise.resolve();
  }

  getStats() {
    return {
      messageCount: this.messageCount,
      totalBytes: this.totalBytes,
    };
  }

  reset() {
    this.messageCount = 0;
    this.totalBytes = 0;
  }
}

// Simplified OrderRouter for benchmark (avoids NestJS DI overhead)
class BenchmarkOrderRouter {
  private symbolToShard: Map<string, string> = new Map();
  private shardToTopic: Map<string, string> = new Map();
  private defaultShard = "shard-3";

  constructor(private kafkaClient: MockKafkaClient) {
    // Initialize shard mappings
    const shard1Symbols = ["BTCUSDT", "BTCBUSD", "BTCUSDC"];
    const shard2Symbols = ["ETHUSDT", "ETHBUSD", "ETHUSDC"];

    shard1Symbols.forEach((s) => this.symbolToShard.set(s, "shard-1"));
    shard2Symbols.forEach((s) => this.symbolToShard.set(s, "shard-2"));

    this.shardToTopic.set("shard-1", "matching-engine-shard-1-input");
    this.shardToTopic.set("shard-2", "matching-engine-shard-2-input");
    this.shardToTopic.set("shard-3", "matching-engine-shard-3-input");
  }

  async routeCommand(symbol: string, command: unknown): Promise<void> {
    const shard = this.symbolToShard.get(symbol) || this.defaultShard;
    const topic = this.shardToTopic.get(shard)!;
    await this.kafkaClient.send(topic, command);
  }
}

// Test data generators
const SYMBOLS = [
  "BTCUSDT",
  "BTCBUSD",
  "ETHUSDT",
  "ETHBUSD",
  "SOLUSDT",
  "XRPUSDT",
  "ADAUSDT",
  "DOTUSDT",
  "MATICUSDT",
  "AVAXUSDT",
];

const ORDER_TYPES = ["LIMIT", "MARKET"];
const SIDES = ["BUY", "SELL"];

function generateOrder(orderId: number) {
  return {
    code: "PLACE_ORDER",
    data: {
      id: orderId,
      symbol: SYMBOLS[orderId % SYMBOLS.length],
      side: SIDES[orderId % 2],
      type: ORDER_TYPES[orderId % 2],
      price: 50000 + Math.random() * 1000,
      quantity: Math.random() * 10,
      userId: 1000 + (orderId % 100),
      timestamp: Date.now(),
    },
  };
}

interface BenchmarkResult {
  totalOrders: number;
  durationMs: number;
  ordersPerSecond: number;
  avgLatencyUs: number;
  p50LatencyUs: number;
  p95LatencyUs: number;
  p99LatencyUs: number;
  maxLatencyUs: number;
  throughputMBps: number;
  shardDistribution: Record<string, number>;
}

async function runBenchmark(
  totalOrders: number,
  concurrency: number,
  warmupOrders: number
): Promise<BenchmarkResult> {
  const kafkaClient = new MockKafkaClient();
  const router = new BenchmarkOrderRouter(kafkaClient);

  // Warmup phase
  console.log(`\nWarmup: Sending ${warmupOrders} orders...`);
  for (let i = 0; i < warmupOrders; i++) {
    const order = generateOrder(i);
    await router.routeCommand(order.data.symbol, order);
  }
  kafkaClient.reset();

  // Track latencies
  const latencies: number[] = [];
  const shardCounts: Record<string, number> = {
    "shard-1": 0,
    "shard-2": 0,
    "shard-3": 0,
  };

  // Main benchmark
  console.log(
    `\nBenchmark: Sending ${totalOrders} orders with ${concurrency} concurrent workers...`
  );
  const startTime = performance.now();

  // Create order batches for workers
  const batchSize = Math.ceil(totalOrders / concurrency);
  const workers: Promise<void>[] = [];

  for (let w = 0; w < concurrency; w++) {
    const startIdx = w * batchSize;
    const endIdx = Math.min(startIdx + batchSize, totalOrders);

    workers.push(
      (async () => {
        for (let i = startIdx; i < endIdx; i++) {
          const order = generateOrder(i);
          const orderStart = performance.now();
          await router.routeCommand(order.data.symbol, order);
          const orderEnd = performance.now();

          latencies.push((orderEnd - orderStart) * 1000); // Convert to microseconds

          // Track shard distribution
          const symbol = order.data.symbol;
          if (["BTCUSDT", "BTCBUSD", "BTCUSDC"].includes(symbol)) {
            shardCounts["shard-1"]++;
          } else if (["ETHUSDT", "ETHBUSD", "ETHUSDC"].includes(symbol)) {
            shardCounts["shard-2"]++;
          } else {
            shardCounts["shard-3"]++;
          }
        }
      })()
    );
  }

  await Promise.all(workers);
  const endTime = performance.now();

  // Calculate statistics
  const durationMs = endTime - startTime;
  const ordersPerSecond = (totalOrders / durationMs) * 1000;

  latencies.sort((a, b) => a - b);
  const avgLatency = latencies.reduce((a, b) => a + b, 0) / latencies.length;
  const p50 = latencies[Math.floor(latencies.length * 0.5)];
  const p95 = latencies[Math.floor(latencies.length * 0.95)];
  const p99 = latencies[Math.floor(latencies.length * 0.99)];
  const maxLatency = latencies[latencies.length - 1];

  const stats = kafkaClient.getStats();
  const throughputMBps = stats.totalBytes / 1024 / 1024 / (durationMs / 1000);

  return {
    totalOrders,
    durationMs,
    ordersPerSecond,
    avgLatencyUs: avgLatency,
    p50LatencyUs: p50,
    p95LatencyUs: p95,
    p99LatencyUs: p99,
    maxLatencyUs: maxLatency,
    throughputMBps,
    shardDistribution: shardCounts,
  };
}

function formatResult(result: BenchmarkResult): string {
  const target = 100000;
  const achieved = result.ordersPerSecond >= target;
  const statusEmoji = achieved ? "✅" : "❌";

  return `
╔══════════════════════════════════════════════════════════════╗
║              OrderRouter Performance Results                  ║
╠══════════════════════════════════════════════════════════════╣
║  Total Orders:        ${result.totalOrders.toLocaleString().padStart(12)}                        ║
║  Duration:            ${result.durationMs.toFixed(2).padStart(12)} ms                     ║
║  Throughput:          ${result.ordersPerSecond.toFixed(0).padStart(12)} orders/sec ${statusEmoji}        ║
║  Target:              ${target.toLocaleString().padStart(12)} orders/sec              ║
╠══════════════════════════════════════════════════════════════╣
║  Latency (microseconds):                                      ║
║    Average:           ${result.avgLatencyUs.toFixed(2).padStart(12)} µs                     ║
║    P50:               ${result.p50LatencyUs.toFixed(2).padStart(12)} µs                     ║
║    P95:               ${result.p95LatencyUs.toFixed(2).padStart(12)} µs                     ║
║    P99:               ${result.p99LatencyUs.toFixed(2).padStart(12)} µs                     ║
║    Max:               ${result.maxLatencyUs.toFixed(2).padStart(12)} µs                     ║
╠══════════════════════════════════════════════════════════════╣
║  Data Throughput:     ${result.throughputMBps.toFixed(2).padStart(12)} MB/s                   ║
╠══════════════════════════════════════════════════════════════╣
║  Shard Distribution:                                          ║
║    Shard-1 (BTC):     ${result.shardDistribution["shard-1"].toLocaleString().padStart(12)} orders                  ║
║    Shard-2 (ETH):     ${result.shardDistribution["shard-2"].toLocaleString().padStart(12)} orders                  ║
║    Shard-3 (Other):   ${result.shardDistribution["shard-3"].toLocaleString().padStart(12)} orders                  ║
╚══════════════════════════════════════════════════════════════╝
`;
}

// Parse command line arguments
function parseArgs(): { orders: number; concurrency: number; warmup: number } {
  const args = process.argv.slice(2);
  const options = {
    orders: 100000,
    concurrency: 10,
    warmup: 1000,
  };

  for (const arg of args) {
    const [key, value] = arg.replace("--", "").split("=");
    if (key === "orders") options.orders = parseInt(value, 10);
    if (key === "concurrency") options.concurrency = parseInt(value, 10);
    if (key === "warmup") options.warmup = parseInt(value, 10);
  }

  return options;
}

// Main
async function main() {
  console.log("╔══════════════════════════════════════════════════════════════╗");
  console.log("║         OrderRouter Performance Benchmark                     ║");
  console.log("╚══════════════════════════════════════════════════════════════╝");

  const options = parseArgs();
  console.log(`\nConfiguration:`);
  console.log(`  Orders:      ${options.orders.toLocaleString()}`);
  console.log(`  Concurrency: ${options.concurrency}`);
  console.log(`  Warmup:      ${options.warmup.toLocaleString()}`);

  const result = await runBenchmark(
    options.orders,
    options.concurrency,
    options.warmup
  );

  console.log(formatResult(result));

  // Exit with error if target not met
  if (result.ordersPerSecond < 100000) {
    console.log("⚠️  Performance target not met. Consider optimization.");
    process.exit(1);
  }
}

main().catch(console.error);

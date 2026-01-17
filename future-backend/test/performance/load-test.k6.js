/**
 * k6 Load Test for Order API Endpoints
 *
 * Tests the full order flow through REST API including OrderRouter.
 *
 * Installation:
 *   brew install k6  (macOS)
 *   # or download from https://k6.io/docs/getting-started/installation/
 *
 * Usage:
 *   k6 run test/performance/load-test.k6.js
 *   k6 run --vus 50 --duration 60s test/performance/load-test.k6.js
 *   k6 run --env BASE_URL=http://staging:3000 test/performance/load-test.k6.js
 *
 * Environment Variables:
 *   BASE_URL    API base URL (default: http://localhost:3000)
 *   API_TOKEN   Authentication token
 */

import http from "k6/http";
import { check, sleep, group } from "k6";
import { Counter, Rate, Trend } from "k6/metrics";
import { randomIntBetween, randomItem } from "https://jslib.k6.io/k6-utils/1.4.0/index.js";

// Custom metrics
const orderCreated = new Counter("orders_created");
const orderCancelled = new Counter("orders_cancelled");
const orderErrors = new Counter("order_errors");
const orderLatency = new Trend("order_latency", true);
const shardRouting = {
  shard1: new Counter("shard_1_orders"),
  shard2: new Counter("shard_2_orders"),
  shard3: new Counter("shard_3_orders"),
};

// Test configuration
export const options = {
  scenarios: {
    // Smoke test
    smoke: {
      executor: "constant-vus",
      vus: 5,
      duration: "30s",
      tags: { scenario: "smoke" },
      exec: "orderFlow",
    },
    // Load test
    load: {
      executor: "ramping-vus",
      startVUs: 0,
      stages: [
        { duration: "1m", target: 20 },
        { duration: "3m", target: 50 },
        { duration: "1m", target: 100 },
        { duration: "2m", target: 100 },
        { duration: "1m", target: 0 },
      ],
      tags: { scenario: "load" },
      exec: "orderFlow",
      startTime: "30s",
    },
    // Stress test
    stress: {
      executor: "ramping-arrival-rate",
      startRate: 10,
      timeUnit: "1s",
      preAllocatedVUs: 200,
      maxVUs: 500,
      stages: [
        { duration: "1m", target: 100 },
        { duration: "2m", target: 500 },
        { duration: "1m", target: 1000 },
        { duration: "30s", target: 0 },
      ],
      tags: { scenario: "stress" },
      exec: "orderFlow",
      startTime: "9m",
    },
  },
  thresholds: {
    http_req_duration: ["p(95)<500", "p(99)<1000"],
    http_req_failed: ["rate<0.01"],
    order_latency: ["p(95)<300"],
    orders_created: ["count>100"],
  },
};

// Configuration
const BASE_URL = __ENV.BASE_URL || "http://localhost:3000";
const API_PREFIX = "/v1";

// Test data
const SYMBOLS = {
  shard1: ["BTCUSDT", "BTCBUSD", "BTCUSDC"],
  shard2: ["ETHUSDT", "ETHBUSD", "ETHUSDC"],
  shard3: ["SOLUSDT", "XRPUSDT", "ADAUSDT", "DOTUSDT", "MATICUSDT"],
};

const ALL_SYMBOLS = [...SYMBOLS.shard1, ...SYMBOLS.shard2, ...SYMBOLS.shard3];

// Helper functions
function getAuthHeaders() {
  const token = __ENV.API_TOKEN || "test-token";
  return {
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  };
}

function generateOrderPayload(symbol) {
  const side = randomItem(["BUY", "SELL"]);
  const type = randomItem(["LIMIT", "MARKET"]);

  const basePrice = {
    BTCUSDT: 50000,
    BTCBUSD: 50000,
    BTCUSDC: 50000,
    ETHUSDT: 3000,
    ETHBUSD: 3000,
    ETHUSDC: 3000,
    SOLUSDT: 100,
    XRPUSDT: 0.5,
    ADAUSDT: 0.5,
    DOTUSDT: 7,
    MATICUSDT: 1,
  }[symbol] || 100;

  const payload = {
    symbol,
    side,
    type,
    quantity: (Math.random() * 10).toFixed(4),
    leverage: randomItem([1, 5, 10, 20]),
    marginType: randomItem(["CROSS", "ISOLATED"]),
  };

  if (type === "LIMIT") {
    const priceOffset = side === "BUY" ? -0.01 : 0.01;
    payload.price = (basePrice * (1 + priceOffset * Math.random())).toFixed(2);
    payload.timeInForce = randomItem(["GTC", "IOC", "FOK"]);
  }

  return payload;
}

function trackShardRouting(symbol) {
  if (SYMBOLS.shard1.includes(symbol)) {
    shardRouting.shard1.add(1);
  } else if (SYMBOLS.shard2.includes(symbol)) {
    shardRouting.shard2.add(1);
  } else {
    shardRouting.shard3.add(1);
  }
}

// Main test function
export function orderFlow() {
  const symbol = randomItem(ALL_SYMBOLS);
  const headers = getAuthHeaders();

  group("Order Creation", function () {
    const payload = generateOrderPayload(symbol);
    const startTime = Date.now();

    const createRes = http.post(
      `${BASE_URL}${API_PREFIX}/order`,
      JSON.stringify(payload),
      { headers, tags: { name: "create_order" } }
    );

    const latency = Date.now() - startTime;
    orderLatency.add(latency);

    const success = check(createRes, {
      "order created": (r) => r.status === 201 || r.status === 200,
      "has order id": (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.data && body.data.id;
        } catch {
          return false;
        }
      },
    });

    if (success) {
      orderCreated.add(1);
      trackShardRouting(symbol);

      // Occasionally cancel orders
      if (Math.random() < 0.3) {
        try {
          const body = JSON.parse(createRes.body);
          const orderId = body.data.id;

          sleep(randomIntBetween(1, 3));

          const cancelRes = http.delete(
            `${BASE_URL}${API_PREFIX}/order/${orderId}`,
            { headers, tags: { name: "cancel_order" } }
          );

          if (check(cancelRes, { "order cancelled": (r) => r.status === 200 })) {
            orderCancelled.add(1);
          }
        } catch (e) {
          // Ignore cancel errors
        }
      }
    } else {
      orderErrors.add(1);
    }
  });

  sleep(randomIntBetween(1, 3) / 10);
}

// Shard-specific test
export function shardDistributionTest() {
  const headers = getAuthHeaders();

  // Test each shard equally
  for (const [shardName, symbols] of Object.entries(SYMBOLS)) {
    const symbol = randomItem(symbols);
    const payload = generateOrderPayload(symbol);

    const res = http.post(
      `${BASE_URL}${API_PREFIX}/order`,
      JSON.stringify(payload),
      { headers, tags: { name: `order_${shardName}` } }
    );

    check(res, {
      [`${shardName} order success`]: (r) => r.status === 201 || r.status === 200,
    });

    trackShardRouting(symbol);
  }

  sleep(0.5);
}

// Setup function
export function setup() {
  console.log(`\n🚀 Starting load test against ${BASE_URL}`);
  console.log(`   Testing symbols: ${ALL_SYMBOLS.join(", ")}`);

  // Verify API is reachable
  const healthRes = http.get(`${BASE_URL}/health`);
  if (healthRes.status !== 200) {
    console.warn(`⚠️  Health check failed: ${healthRes.status}`);
  }

  return { startTime: Date.now() };
}

// Teardown function
export function teardown(data) {
  const duration = (Date.now() - data.startTime) / 1000;
  console.log(`\n✅ Load test completed in ${duration.toFixed(1)}s`);
}

// Summary handler
export function handleSummary(data) {
  const summary = {
    timestamp: new Date().toISOString(),
    duration: data.state.testRunDurationMs,
    metrics: {
      orders_created: data.metrics.orders_created?.values?.count || 0,
      orders_cancelled: data.metrics.orders_cancelled?.values?.count || 0,
      order_errors: data.metrics.order_errors?.values?.count || 0,
      http_req_duration_p95: data.metrics.http_req_duration?.values?.["p(95)"] || 0,
      http_req_failed_rate: data.metrics.http_req_failed?.values?.rate || 0,
      shard_distribution: {
        shard1: data.metrics.shard_1_orders?.values?.count || 0,
        shard2: data.metrics.shard_2_orders?.values?.count || 0,
        shard3: data.metrics.shard_3_orders?.values?.count || 0,
      },
    },
  };

  return {
    "test/performance/results/load-test-summary.json": JSON.stringify(summary, null, 2),
    stdout: textSummary(data, { indent: "  ", enableColors: true }),
  };
}

function textSummary(data, opts) {
  const ordersCreated = data.metrics.orders_created?.values?.count || 0;
  const duration = (data.state.testRunDurationMs / 1000).toFixed(1);
  const rate = ordersCreated / (data.state.testRunDurationMs / 1000);

  return `
╔══════════════════════════════════════════════════════════════╗
║                 k6 Load Test Summary                          ║
╠══════════════════════════════════════════════════════════════╣
║  Duration:            ${duration.padStart(10)}s                        ║
║  Orders Created:      ${ordersCreated.toString().padStart(10)}                         ║
║  Orders/sec:          ${rate.toFixed(1).padStart(10)}                         ║
║  HTTP P95 Latency:    ${(data.metrics.http_req_duration?.values?.["p(95)"] || 0).toFixed(2).padStart(10)} ms                    ║
║  Error Rate:          ${((data.metrics.http_req_failed?.values?.rate || 0) * 100).toFixed(2).padStart(10)}%                     ║
╚══════════════════════════════════════════════════════════════╝
`;
}

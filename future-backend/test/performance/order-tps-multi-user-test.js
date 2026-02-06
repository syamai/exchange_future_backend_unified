import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const orderSuccessRate = new Rate('order_success_rate');
const orderDuration = new Trend('order_duration');
const orderCount = new Counter('order_count');
const orderErrors = new Counter('order_errors');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://a5e62f0c62ed143c894d967b5f010892-8f62b95861b41f5f.elb.ap-northeast-2.amazonaws.com';

// Multiple tokens for multi-user testing
// Pass as: -e TOKENS='["token1","token2",...]'
const TOKENS = JSON.parse(__ENV.TOKENS || '[]');
if (TOKENS.length === 0) {
  throw new Error('TOKENS environment variable is required. Pass as: -e TOKENS=\'["token1","token2",...]\'');
}

const symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'];
const sides = ['BUY', 'SELL'];

export const options = {
  stages: [
    { duration: '20s', target: 50 },    // Warm up
    { duration: '30s', target: 100 },   // 100 concurrent users
    { duration: '30s', target: 200 },   // 200 concurrent users
    { duration: '30s', target: 300 },   // 300 concurrent users
    { duration: '30s', target: 400 },   // 400 concurrent users
    { duration: '20s', target: 0 },     // Cool down
  ],

  thresholds: {
    http_req_failed: ['rate<0.20'],
    order_success_rate: ['rate>0.80'],
  },
};

export default function () {
  // Random user selection for each request
  const token = TOKENS[Math.floor(Math.random() * TOKENS.length)];

  const symbol = symbols[Math.floor(Math.random() * symbols.length)];
  const side = sides[Math.floor(Math.random() * sides.length)];
  const price = symbol === 'BTCUSDT' ? (95000 + Math.random() * 1000).toFixed(2) :
                symbol === 'ETHUSDT' ? (3200 + Math.random() * 100).toFixed(2) :
                (180 + Math.random() * 10).toFixed(2);
  const quantity = '0.001';

  const payload = JSON.stringify({
    side: side,
    contractType: 'USD_M',
    symbol: symbol,
    type: 'LIMIT',
    quantity: quantity,
    price: price,
    timeInForce: 'GTC',
    isPostOnly: false,
    asset: 'USDT'
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
  };

  const res = http.post(`${BASE_URL}/api/v1/order`, payload, params);
  orderCount.add(1);

  const success = check(res, {
    'order: status 2xx': (r) => r.status >= 200 && r.status < 300,
    'order: has order id': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.data && body.data.id;
      } catch {
        return false;
      }
    },
    'order: response < 500ms': (r) => r.timings.duration < 500,
  });

  orderSuccessRate.add(success);
  orderDuration.add(res.timings.duration);

  if (!success) {
    orderErrors.add(1);
  }

  sleep(Math.random() * 0.1);
}

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration || { values: { avg: 0, med: 0, 'p(90)': 0, 'p(95)': 0, 'p(99)': 0 } };
  const reqs = data.metrics.http_reqs || { values: { count: 0, rate: 0 } };
  const failed = data.metrics.http_req_failed || { values: { passes: 0, rate: 0 } };
  const orderSuccess = data.metrics.order_success_rate || { values: { rate: 0 } };

  console.log('\n');
  console.log('╔════════════════════════════════════════════════════════════════╗');
  console.log('║        MULTI-USER ORDER TPS TEST - THROUGHPUT ANALYSIS         ║');
  console.log('╠════════════════════════════════════════════════════════════════╣');
  console.log(`║  Users:                ${String(TOKENS.length).padStart(10)}                          ║`);
  console.log(`║  Total Orders:         ${String(reqs.values.count).padStart(10)}                          ║`);
  console.log(`║  Order TPS:            ${String(reqs.values.rate.toFixed(2)).padStart(10)} orders/sec               ║`);
  console.log(`║  Success Rate:         ${String((orderSuccess.values.rate * 100).toFixed(2)).padStart(10)}%                        ║`);
  console.log(`║  Failed Orders:        ${String(failed.values.passes).padStart(10)} (${(failed.values.rate * 100).toFixed(2)}%)                 ║`);
  console.log('╠════════════════════════════════════════════════════════════════╣');
  console.log('║  Response Time                                                 ║');
  console.log(`║    Average:            ${String(duration.values.avg.toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    Median:             ${String(duration.values.med.toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P90:                ${String(duration.values['p(90)'].toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P95:                ${String(duration.values['p(95)'].toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P99:                ${String(duration.values['p(99)'] ? duration.values['p(99)'].toFixed(2) : 'N/A').padStart(10)} ms                      ║`);
  console.log('╚════════════════════════════════════════════════════════════════╝');
  console.log('\n');

  return {
    stdout: JSON.stringify({
      users: TOKENS.length,
      totalOrders: reqs.values.count,
      orderTPS: reqs.values.rate.toFixed(2),
      successRate: (orderSuccess.values.rate * 100).toFixed(2),
      avgResponseTime: duration.values.avg.toFixed(2),
      p95ResponseTime: duration.values['p(95)'].toFixed(2),
    }, null, 2),
  };
}

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const orderSuccessRate = new Rate('order_success_rate');
const orderDuration = new Trend('order_duration');

const BASE_URL = __ENV.BASE_URL || 'http://a9c6a186c22eb41608af8f5f7d83c2cb-b996d2874664ae92.elb.ap-northeast-2.amazonaws.com';
const TOKEN = __ENV.TOKEN || 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImVtYWlsIjoidGVzdEB0ZXN0LmNvbSIsImlhdCI6MTc2ODgxMzkyNSwiZXhwIjoxNzY4OTAwMzI1fQ.DIibjn_1j75i7ftZTn7oup6pYAjvdi8Plr5IGTI90rn2dw39AxuthpXMJ2byGjMMtd4d1ExEifEMvbXxEBkiWeVgTjOtWmWrSGqSQsgXkmAD0rymtWLnyDUgD2R2lGg9Pi7CXqZKP7Nc0rzuSt_EqQcXJfyYIM3lXDhDkpEil-xCyc6cVpELMbe76NpvL3itbCHFVTsgmhplIKPE8djI7huOXCO9cC2l0zWOFO0_yTQrkRb6YqfUPmocC3ynkci84Nfa4rw1XA1DdwRfB5wL8FOZNHKnCKv5piLtfiNKdoYHbQlyniRfKQtkzAFt5wBYtmsX4ebcSXHbnbF57JDoUg';

const symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'];
const sides = ['BUY', 'SELL'];

export const options = {
  stages: [
    { duration: '20s', target: 200 },
    { duration: '30s', target: 400 },
    { duration: '30s', target: 600 },
    { duration: '30s', target: 800 },
    { duration: '30s', target: 1000 },
    { duration: '20s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.30'],
  },
};

export default function () {
  const symbol = symbols[Math.floor(Math.random() * symbols.length)];
  const side = sides[Math.floor(Math.random() * sides.length)];
  const price = symbol === 'BTCUSDT' ? (95000 + Math.random() * 1000).toFixed(2) :
                symbol === 'ETHUSDT' ? (3200 + Math.random() * 100).toFixed(2) :
                (180 + Math.random() * 10).toFixed(2);

  const payload = JSON.stringify({
    side: side,
    contractType: 'USD_M',
    symbol: symbol,
    type: 'LIMIT',
    quantity: '0.001',
    price: price,
    timeInForce: 'GTC',
    isPostOnly: false,
    asset: 'USDT'
  });

  const res = http.post(`${BASE_URL}/v1/order`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${TOKEN}`,
    },
  });

  const success = check(res, {
    'status 200': (r) => r.status === 200,
  });

  orderSuccessRate.add(success);
  orderDuration.add(res.timings.duration);

  sleep(Math.random() * 0.05);
}

export function handleSummary(data) {
  const reqs = data.metrics.http_reqs;
  const duration = data.metrics.http_req_duration;
  const failed = data.metrics.http_req_failed;

  console.log('\n');
  console.log('╔══════════════════════════════════════════════════════════════╗');
  console.log('║           ORDER BREAKPOINT TEST - MAX TPS ANALYSIS           ║');
  console.log('╠══════════════════════════════════════════════════════════════╣');
  console.log('║  Total Orders:       ' + String(reqs.values.count).padStart(10) + '                        ║');
  console.log('║  Peak TPS:           ' + String(reqs.values.rate.toFixed(2)).padStart(10) + ' orders/sec             ║');
  console.log('║  Error Rate:         ' + String((failed.values.rate * 100).toFixed(2)).padStart(10) + '%                      ║');
  console.log('╠══════════════════════════════════════════════════════════════╣');
  console.log('║  Avg Response:       ' + String(duration.values.avg.toFixed(2)).padStart(10) + ' ms                    ║');
  console.log('║  P95 Response:       ' + String(duration.values['p(95)'].toFixed(2)).padStart(10) + ' ms                    ║');
  console.log('║  P99 Response:       ' + String(duration.values['p(99)'].toFixed(2)).padStart(10) + ' ms                    ║');
  console.log('║  Max Response:       ' + String(duration.values.max.toFixed(2)).padStart(10) + ' ms                    ║');
  console.log('╚══════════════════════════════════════════════════════════════╝');

  return {};
}

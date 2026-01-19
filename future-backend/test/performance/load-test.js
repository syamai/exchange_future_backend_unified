import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success_rate');
const instrumentsTrend = new Trend('instruments_duration');
const requestCount = new Counter('total_requests');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://a9c6a186c22eb41608af8f5f7d83c2cb-b996d2874664ae92.elb.ap-northeast-2.amazonaws.com';

export const options = {
  // Load test: ramp up to 50 VUs over 5 minutes
  stages: [
    { duration: '30s', target: 10 },   // Warm up
    { duration: '1m', target: 25 },    // Ramp up
    { duration: '2m', target: 50 },    // Stay at 50 VUs
    { duration: '1m', target: 25 },    // Ramp down
    { duration: '30s', target: 0 },    // Cool down
  ],

  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],  // P95 < 500ms, P99 < 1s
    http_req_failed: ['rate<0.05'],    // Error rate < 5%
    errors: ['rate<0.05'],
    success_rate: ['rate>0.95'],       // Success rate > 95%
  },
};

export default function () {
  // Test 1: GET /v1/instruments (main API)
  const instrumentsRes = http.get(`${BASE_URL}/v1/instruments`);
  requestCount.add(1);

  const instrumentsCheck = check(instrumentsRes, {
    'instruments: status 200': (r) => r.status === 200,
    'instruments: has data': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.data && body.data.length > 0;
      } catch {
        return false;
      }
    },
    'instruments: response < 500ms': (r) => r.timings.duration < 500,
  });

  errorRate.add(!instrumentsCheck);
  successRate.add(instrumentsCheck);
  instrumentsTrend.add(instrumentsRes.timings.duration);

  sleep(Math.random() * 2 + 0.5); // Random sleep 0.5-2.5s
}

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration;
  const reqs = data.metrics.http_reqs;
  const failed = data.metrics.http_req_failed;

  console.log('\n');
  console.log('╔════════════════════════════════════════════════════════════╗');
  console.log('║                    LOAD TEST SUMMARY                       ║');
  console.log('╠════════════════════════════════════════════════════════════╣');
  console.log(`║  Total Requests:     ${String(reqs.values.count).padStart(10)}                        ║`);
  console.log(`║  Throughput:         ${String(reqs.values.rate.toFixed(2)).padStart(10)} req/s                   ║`);
  console.log(`║  Failed Requests:    ${String(failed.values.passes).padStart(10)} (${(failed.values.rate * 100).toFixed(2)}%)               ║`);
  console.log('╠════════════════════════════════════════════════════════════╣');
  console.log('║  Response Time                                             ║');
  console.log(`║    Average:          ${String(duration.values.avg.toFixed(2)).padStart(10)} ms                    ║`);
  console.log(`║    Median:           ${String(duration.values.med.toFixed(2)).padStart(10)} ms                    ║`);
  console.log(`║    P90:              ${String(duration.values['p(90)'].toFixed(2)).padStart(10)} ms                    ║`);
  console.log(`║    P95:              ${String(duration.values['p(95)'].toFixed(2)).padStart(10)} ms                    ║`);
  console.log(`║    P99:              ${String(duration.values['p(99)'].toFixed(2)).padStart(10)} ms                    ║`);
  console.log(`║    Max:              ${String(duration.values.max.toFixed(2)).padStart(10)} ms                    ║`);
  console.log('╠════════════════════════════════════════════════════════════╣');

  // Check thresholds
  const p95Pass = duration.values['p(95)'] < 500 ? '✅ PASS' : '❌ FAIL';
  const p99Pass = duration.values['p(99)'] < 1000 ? '✅ PASS' : '❌ FAIL';
  const errorPass = failed.values.rate < 0.05 ? '✅ PASS' : '❌ FAIL';

  console.log('║  Thresholds                                                ║');
  console.log(`║    P95 < 500ms:      ${p95Pass.padEnd(20)}                ║`);
  console.log(`║    P99 < 1000ms:     ${p99Pass.padEnd(20)}                ║`);
  console.log(`║    Error Rate < 5%:  ${errorPass.padEnd(20)}                ║`);
  console.log('╚════════════════════════════════════════════════════════════╝');
  console.log('\n');

  return {
    stdout: JSON.stringify({
      totalRequests: reqs.values.count,
      throughput: reqs.values.rate.toFixed(2),
      avgResponseTime: duration.values.avg.toFixed(2),
      p95ResponseTime: duration.values['p(95)'].toFixed(2),
      p99ResponseTime: duration.values['p(99)'].toFixed(2),
      errorRate: (failed.values.rate * 100).toFixed(2),
    }, null, 2),
  };
}

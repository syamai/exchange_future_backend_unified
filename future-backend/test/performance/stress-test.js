import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const successRate = new Rate('success_rate');
const instrumentsTrend = new Trend('instruments_duration');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://a9c6a186c22eb41608af8f5f7d83c2cb-b996d2874664ae92.elb.ap-northeast-2.amazonaws.com';

export const options = {
  // Stress test: push to 200 VUs to find breaking point
  stages: [
    { duration: '30s', target: 50 },    // Warm up to baseline
    { duration: '1m', target: 100 },    // Ramp up to 100
    { duration: '1m', target: 150 },    // Push to 150
    { duration: '1m', target: 200 },    // Peak at 200
    { duration: '30s', target: 100 },   // Scale back
    { duration: '30s', target: 0 },     // Cool down
  ],

  thresholds: {
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],  // More relaxed for stress
    http_req_failed: ['rate<0.10'],    // Allow up to 10% errors under stress
    errors: ['rate<0.10'],
  },
};

export default function () {
  const instrumentsRes = http.get(`${BASE_URL}/v1/instruments`);

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
    'instruments: response < 1000ms': (r) => r.timings.duration < 1000,
  });

  errorRate.add(!instrumentsCheck);
  successRate.add(instrumentsCheck);
  instrumentsTrend.add(instrumentsRes.timings.duration);

  sleep(Math.random() * 1 + 0.3); // Shorter sleep for higher load
}

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration;
  const reqs = data.metrics.http_reqs;
  const failed = data.metrics.http_req_failed;

  console.log('\n');
  console.log('╔════════════════════════════════════════════════════════════╗');
  console.log('║                   STRESS TEST SUMMARY                      ║');
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

  // Determine system capacity
  let capacityStatus = '🟢 HEALTHY';
  if (failed.values.rate > 0.05) {
    capacityStatus = '🟡 DEGRADED';
  }
  if (failed.values.rate > 0.10 || duration.values['p(95)'] > 2000) {
    capacityStatus = '🔴 OVERLOADED';
  }

  console.log(`║  System Status:      ${capacityStatus.padEnd(20)}                ║`);
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
      status: capacityStatus,
    }, null, 2),
  };
}

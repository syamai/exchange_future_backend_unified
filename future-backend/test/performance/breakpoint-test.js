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
  // Breakpoint test: keep increasing until system breaks
  stages: [
    { duration: '30s', target: 100 },   // Warm up
    { duration: '1m', target: 200 },    // 200 VUs
    { duration: '1m', target: 300 },    // 300 VUs
    { duration: '1m', target: 400 },    // 400 VUs
    { duration: '1m', target: 500 },    // 500 VUs
    { duration: '1m', target: 600 },    // 600 VUs
    { duration: '1m', target: 700 },    // 700 VUs
    { duration: '1m', target: 800 },    // 800 VUs
    { duration: '30s', target: 0 },     // Cool down
  ],

  // No thresholds - we want to find the breaking point
  thresholds: {
    http_req_failed: ['rate<0.50'],  // Stop if > 50% errors
  },
};

export default function () {
  const instrumentsRes = http.get(`${BASE_URL}/v1/instruments`);

  const instrumentsCheck = check(instrumentsRes, {
    'status 200': (r) => r.status === 200,
    'has data': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.data && body.data.length > 0;
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!instrumentsCheck);
  successRate.add(instrumentsCheck);
  instrumentsTrend.add(instrumentsRes.timings.duration);

  sleep(Math.random() * 0.5 + 0.1); // Very short sleep for max load
}

export function handleSummary(data) {
  const duration = data.metrics.http_req_duration;
  const reqs = data.metrics.http_reqs;
  const failed = data.metrics.http_req_failed;

  console.log('\n');
  console.log('╔════════════════════════════════════════════════════════════════╗');
  console.log('║              BREAKPOINT TEST - MAX CAPACITY ANALYSIS           ║');
  console.log('╠════════════════════════════════════════════════════════════════╣');
  console.log(`║  Total Requests:       ${String(reqs.values.count).padStart(10)}                          ║`);
  console.log(`║  Peak Throughput:      ${String(reqs.values.rate.toFixed(2)).padStart(10)} req/s                     ║`);
  console.log(`║  Failed Requests:      ${String(failed.values.passes).padStart(10)} (${(failed.values.rate * 100).toFixed(2)}%)                 ║`);
  console.log('╠════════════════════════════════════════════════════════════════╣');
  console.log('║  Response Time                                                 ║');
  console.log(`║    Average:            ${String(duration.values.avg.toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    Median:             ${String(duration.values.med.toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P90:                ${String(duration.values['p(90)'].toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P95:                ${String(duration.values['p(95)'].toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    P99:                ${String(duration.values['p(99)'].toFixed(2)).padStart(10)} ms                      ║`);
  console.log(`║    Max:                ${String(duration.values.max.toFixed(2)).padStart(10)} ms                      ║`);
  console.log('╠════════════════════════════════════════════════════════════════╣');

  // Capacity analysis
  let capacityStatus, recommendation;
  const errorPercent = failed.values.rate * 100;
  const p95 = duration.values['p(95)'];

  if (errorPercent < 1 && p95 < 500) {
    capacityStatus = '🟢 EXCELLENT';
    recommendation = 'System can handle more load';
  } else if (errorPercent < 5 && p95 < 1000) {
    capacityStatus = '🟡 GOOD';
    recommendation = 'Approaching optimal capacity';
  } else if (errorPercent < 10 && p95 < 2000) {
    capacityStatus = '🟠 WARNING';
    recommendation = 'Near maximum capacity';
  } else {
    capacityStatus = '🔴 OVERLOADED';
    recommendation = 'System capacity exceeded';
  }

  console.log(`║  Capacity Status:      ${capacityStatus.padEnd(20)}                  ║`);
  console.log(`║  Recommendation:       ${recommendation.padEnd(35)}    ║`);
  console.log('╠════════════════════════════════════════════════════════════════╣');

  // Estimated safe capacity (80% of achieved with < 5% errors)
  const safeCapacity = errorPercent < 5
    ? Math.floor(reqs.values.rate * 0.8)
    : Math.floor(reqs.values.rate * 0.5);

  console.log(`║  Estimated Safe Capacity: ~${String(safeCapacity).padStart(5)} req/s                        ║`);
  console.log('╚════════════════════════════════════════════════════════════════╝');
  console.log('\n');

  return {
    stdout: JSON.stringify({
      totalRequests: reqs.values.count,
      peakThroughput: reqs.values.rate.toFixed(2),
      avgResponseTime: duration.values.avg.toFixed(2),
      p95ResponseTime: duration.values['p(95)'].toFixed(2),
      p99ResponseTime: duration.values['p(99)'].toFixed(2),
      maxResponseTime: duration.values.max.toFixed(2),
      errorRate: errorPercent.toFixed(2),
      capacityStatus,
      estimatedSafeCapacity: safeCapacity,
    }, null, 2),
  };
}

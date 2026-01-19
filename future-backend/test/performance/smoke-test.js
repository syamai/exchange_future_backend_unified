import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const instrumentsTrend = new Trend('instruments_duration');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://a9c6a186c22eb41608af8f5f7d83c2cb-b996d2874664ae92.elb.ap-northeast-2.amazonaws.com';

export const options = {
  // Smoke test: 5 VUs for 1 minute
  vus: 5,
  duration: '1m',

  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% requests < 500ms
    http_req_failed: ['rate<0.01'],    // Error rate < 1%
    errors: ['rate<0.01'],
  },
};

export default function () {
  // Test 1: GET /v1/instruments
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
    'instruments: response < 500ms': (r) => r.timings.duration < 500,
  });

  errorRate.add(!instrumentsCheck);
  instrumentsTrend.add(instrumentsRes.timings.duration);

  sleep(1);

  // Test 2: GET / (should return 404, but server responds)
  const rootRes = http.get(`${BASE_URL}/`);

  check(rootRes, {
    'root: server responds': (r) => r.status === 404 || r.status === 200,
  });

  sleep(1);
}

export function handleSummary(data) {
  const summary = {
    'Total Requests': data.metrics.http_reqs.values.count,
    'Failed Requests': data.metrics.http_req_failed.values.passes,
    'Avg Response Time': `${data.metrics.http_req_duration.values.avg.toFixed(2)}ms`,
    'P95 Response Time': `${data.metrics.http_req_duration.values['p(95)'].toFixed(2)}ms`,
    'P99 Response Time': `${data.metrics.http_req_duration.values['p(99)'].toFixed(2)}ms`,
    'Throughput': `${(data.metrics.http_reqs.values.rate).toFixed(2)} req/s`,
  };

  console.log('\n========== SMOKE TEST SUMMARY ==========');
  for (const [key, value] of Object.entries(summary)) {
    console.log(`${key}: ${value}`);
  }
  console.log('=========================================\n');

  return {
    stdout: JSON.stringify(summary, null, 2),
  };
}

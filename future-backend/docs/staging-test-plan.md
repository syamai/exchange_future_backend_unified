# 스테이징 환경 테스트 계획

## 개요

매칭 엔진 샤딩 기능의 스테이징 환경 End-to-End 테스트 계획

### 목표
- 샤딩 활성화 시 정상 동작 검증
- 기존 기능 회귀 테스트
- 성능 목표 달성 확인
- 장애 시나리오 대응 검증

### 테스트 범위
- Backend OrderRouter 통합
- Kafka 토픽 라우팅
- 매칭 엔진 샤드 동작
- Primary/Standby 전환
- 모니터링 및 알림

---

## 1. 사전 준비

### 1.1 인프라 체크리스트

| 항목 | 상태 | 담당자 |
|-----|------|-------|
| Kafka 클러스터 준비 | ⬜ | |
| 샤드 토픽 생성 (9개) | ⬜ | |
| 매칭 엔진 샤드 배포 (3 Primary + 3 Standby) | ⬜ | |
| Backend 스테이징 배포 | ⬜ | |
| Prometheus/Grafana 설정 | ⬜ | |
| 알림 채널 설정 (Slack/PagerDuty) | ⬜ | |

### 1.2 Kafka 토픽 생성

```bash
# 스테이징 Kafka에서 실행
./scripts/create-shard-topics.sh \
  --bootstrap staging-kafka:9092 \
  --partitions 3 \
  --replication 2
```

예상 토픽:
```
matching-engine-shard-1-input
matching-engine-shard-1-output
shard-sync-shard-1
matching-engine-shard-2-input
matching-engine-shard-2-output
shard-sync-shard-2
matching-engine-shard-3-input
matching-engine-shard-3-output
shard-sync-shard-3
```

### 1.3 설정 확인

**Backend 설정** (`config/staging.yml`):
```yaml
sharding:
  enabled: true
  shard1:
    symbols: "BTCUSDT,BTCBUSD,BTCUSDC"
    inputTopic: "matching-engine-shard-1-input"
    outputTopic: "matching-engine-shard-1-output"
  shard2:
    symbols: "ETHUSDT,ETHBUSD,ETHUSDC"
    inputTopic: "matching-engine-shard-2-input"
    outputTopic: "matching-engine-shard-2-output"
  shard3:
    symbols: ""
    inputTopic: "matching-engine-shard-3-input"
    outputTopic: "matching-engine-shard-3-output"
```

---

## 2. 테스트 단계

### Phase 1: 기본 기능 테스트 (Day 1)

#### 2.1.1 샤딩 비활성화 상태 확인

**목적**: 샤딩 OFF 상태에서 기존 동작 정상 확인

```bash
# 설정: sharding.enabled = false
```

| 테스트 케이스 | 예상 결과 | 실제 결과 | Pass/Fail |
|-------------|----------|----------|-----------|
| BTCUSDT 주문 생성 | `matching_engine_input` 토픽으로 전송 | | |
| ETHUSDT 주문 생성 | `matching_engine_input` 토픽으로 전송 | | |
| SOLUSDT 주문 생성 | `matching_engine_input` 토픽으로 전송 | | |
| 주문 취소 | 정상 취소 | | |
| 시장가 주문 | 즉시 체결 | | |

#### 2.1.2 샤딩 활성화 라우팅 테스트

**목적**: 심볼별 올바른 샤드로 라우팅 확인

```bash
# 설정: sharding.enabled = true
```

| 테스트 케이스 | 예상 토픽 | 실제 토픽 | Pass/Fail |
|-------------|----------|----------|-----------|
| BTCUSDT 주문 | `matching-engine-shard-1-input` | | |
| BTCBUSD 주문 | `matching-engine-shard-1-input` | | |
| ETHUSDT 주문 | `matching-engine-shard-2-input` | | |
| ETHBUSD 주문 | `matching-engine-shard-2-input` | | |
| SOLUSDT 주문 | `matching-engine-shard-3-input` | | |
| XRPUSDT 주문 | `matching-engine-shard-3-input` | | |
| ADAUSDT 주문 | `matching-engine-shard-3-input` | | |

**검증 방법**:
```bash
# Kafka 토픽 메시지 확인
kafka-console-consumer --bootstrap-server staging-kafka:9092 \
  --topic matching-engine-shard-1-input \
  --from-beginning --max-messages 10
```

#### 2.1.3 주문 체결 테스트

| 테스트 케이스 | 예상 결과 | Pass/Fail |
|-------------|----------|-----------|
| BTCUSDT Limit Buy → Limit Sell 매칭 | 체결, 포지션 생성 | |
| ETHUSDT Market Buy | 즉시 체결 | |
| SOLUSDT Stop-Limit 트리거 | 가격 도달 시 체결 | |
| 크로스 마진 청산 | 청산 정상 처리 | |

---

### Phase 2: 성능 테스트 (Day 2)

#### 2.2.1 처리량 테스트

**목표**: 100K orders/sec

```bash
# 성능 테스트 실행
./test/performance/run-perf-test.sh all
```

| 메트릭 | 목표 | 측정값 | Pass/Fail |
|-------|------|-------|-----------|
| OrderRouter 처리량 | ≥100K/sec | | |
| Kafka 처리량 | ≥50K/sec | | |
| API P95 Latency | <500ms | | |
| API P99 Latency | <1000ms | | |
| Error Rate | <0.1% | | |

#### 2.2.2 부하 테스트 시나리오

```bash
# k6 부하 테스트
k6 run --env BASE_URL=https://staging-api.example.com \
  --vus 100 --duration 10m \
  test/performance/load-test.k6.js
```

**점진적 부하 증가**:
| 단계 | VUs | 기간 | 예상 RPS |
|-----|-----|------|---------|
| Warmup | 10 | 1m | 100 |
| Ramp-up | 50 | 2m | 500 |
| Peak | 100 | 5m | 1000 |
| Sustain | 100 | 5m | 1000 |
| Cool-down | 10 | 2m | 100 |

#### 2.2.3 샤드별 부하 분산 확인

```bash
# Prometheus 쿼리
sum(rate(order_router_commands_total[5m])) by (shard)
```

예상 분포:
| 샤드 | 예상 비율 | 실제 비율 |
|-----|----------|----------|
| shard-1 (BTC) | 40-50% | |
| shard-2 (ETH) | 25-35% | |
| shard-3 (Other) | 20-30% | |

---

### Phase 3: 장애 시나리오 테스트 (Day 3)

#### 2.3.1 샤드 장애 테스트

| 시나리오 | 테스트 방법 | 예상 동작 | Pass/Fail |
|---------|-----------|----------|-----------|
| Shard-1 Primary 다운 | `kubectl delete pod shard-1-primary-0` | Standby 승격, 트래픽 전환 | |
| Shard-2 네트워크 파티션 | 네트워크 정책 적용 | 타임아웃 후 에러 반환 | |
| Shard-3 Kafka 연결 끊김 | Kafka 재시작 | 재연결 후 복구 | |

#### 2.3.2 롤백 테스트

```bash
# 샤딩 비활성화 롤백
kubectl set env deployment/backend SHARDING_ENABLED=false
kubectl rollout restart deployment/backend
```

| 확인 항목 | 예상 결과 | Pass/Fail |
|---------|----------|-----------|
| 기존 토픽으로 라우팅 전환 | `matching_engine_input` 사용 | |
| 진행 중 주문 처리 | 정상 완료 | |
| 신규 주문 처리 | 정상 처리 | |

#### 2.3.3 카나리 배포 테스트

```bash
# 10% 트래픽만 샤딩 활성화
kubectl apply -f k8s/canary/10-percent.yaml
```

| 확인 항목 | 예상 결과 | Pass/Fail |
|---------|----------|-----------|
| 90% 트래픽 → Legacy | 기존 토픽 | |
| 10% 트래픽 → Sharded | 샤드 토픽 | |
| 에러율 비교 | 동일 수준 | |

---

### Phase 4: 모니터링 검증 (Day 4)

#### 2.4.1 Grafana 대시보드 확인

| 대시보드 | 확인 항목 | Pass/Fail |
|---------|----------|-----------|
| Matching Engine Overview | 샤드별 처리량 표시 | |
| JVM Metrics | 메모리/GC 정상 | |
| Order Processing | 지연시간 그래프 정상 | |

#### 2.4.2 알림 테스트

| 알림 규칙 | 트리거 방법 | 알림 수신 확인 | Pass/Fail |
|---------|-----------|--------------|-----------|
| ShardDown | 샤드 중지 | Slack 알림 | |
| HighLatency | 부하 증가 | Slack 알림 | |
| HighErrorRate | 에러 주입 | PagerDuty 알림 | |
| KafkaLag | Consumer 중지 | Slack 알림 | |

#### 2.4.3 로그 확인

```bash
# 샤드 라우팅 로그 확인
kubectl logs -l app=backend --tail=100 | grep "OrderRouter"
```

예상 로그:
```
[OrderRouterService] Routed command PLACE_ORDER for symbol BTCUSDT to shard shard-1
[OrderRouterService] Routed command PLACE_ORDER for symbol ETHUSDT to shard shard-2
```

---

## 3. 테스트 완료 기준

### 3.1 필수 통과 항목

- [ ] 모든 심볼이 올바른 샤드로 라우팅됨
- [ ] 주문 생성/취소/체결 정상 동작
- [ ] 성능 목표 달성 (100K orders/sec)
- [ ] API P95 Latency < 500ms
- [ ] Error Rate < 0.1%
- [ ] 샤드 장애 시 Standby 전환 성공
- [ ] 롤백 절차 정상 동작
- [ ] 모니터링 알림 정상 수신

### 3.2 성능 기준

| 메트릭 | 최소 | 목표 | 최대 |
|-------|-----|------|-----|
| 처리량 | 50K/s | 100K/s | - |
| P50 Latency | - | <50ms | 100ms |
| P95 Latency | - | <200ms | 500ms |
| P99 Latency | - | <500ms | 1000ms |
| Error Rate | - | <0.01% | 0.1% |

---

## 4. 테스트 일정

| 일자 | 단계 | 주요 활동 | 담당자 |
|-----|------|---------|-------|
| Day 1 | Phase 1 | 기본 기능 테스트 | |
| Day 2 | Phase 2 | 성능 테스트 | |
| Day 3 | Phase 3 | 장애 시나리오 테스트 | |
| Day 4 | Phase 4 | 모니터링 검증 | |
| Day 5 | 리뷰 | 결과 정리 및 Go/No-Go 결정 | |

---

## 5. 리스크 및 대응

| 리스크 | 영향도 | 대응 방안 |
|-------|-------|---------|
| 샤드 라우팅 오류 | High | 롤백 절차 실행, 샤딩 비활성화 |
| 성능 목표 미달 | Medium | 파티션 수 증가, 배치 크기 조정 |
| Kafka 연결 불안정 | High | 재시도 로직 확인, 타임아웃 조정 |
| 모니터링 누락 | Medium | 알림 규칙 추가 |

---

## 6. Sign-off

| 역할 | 이름 | 서명 | 날짜 |
|-----|------|-----|-----|
| QA Lead | | | |
| Dev Lead | | | |
| Ops Lead | | | |
| Product Owner | | | |

---

## 부록

### A. 테스트 데이터

```sql
-- 테스트용 사용자 생성
INSERT INTO users (id, email) VALUES (9999, 'perf-test@example.com');

-- 테스트용 계좌 생성
INSERT INTO accounts (id, user_id, balance) VALUES (9999, 9999, 1000000);
```

### B. 유용한 명령어

```bash
# Kafka 토픽 상태 확인
kafka-topics --bootstrap-server staging-kafka:9092 --describe

# Consumer Lag 확인
kafka-consumer-groups --bootstrap-server staging-kafka:9092 \
  --group matching-engine-consumer --describe

# 샤드 헬스 체크
curl http://shard-1-primary:8080/health
curl http://shard-2-primary:8080/health
curl http://shard-3-primary:8080/health

# 메트릭 확인
curl http://shard-1-primary:9090/metrics | grep order_
```

### C. 롤백 명령어

```bash
# 긴급 롤백
./scripts/emergency-rollback.sh

# 점진적 롤백
kubectl set env deployment/backend SHARDING_ENABLED=false
kubectl rollout status deployment/backend
```

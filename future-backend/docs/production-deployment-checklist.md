# 프로덕션 배포 체크리스트

## 매칭 엔진 샤딩 프로덕션 배포

**배포 버전**: _______________
**배포 예정일**: _______________
**배포 담당자**: _______________
**롤백 담당자**: _______________

---

## 1. 배포 전 준비 (D-7 ~ D-1)

### 1.1 스테이징 테스트 완료 확인

| 항목 | 완료 | 확인자 | 날짜 |
|-----|:----:|-------|-----|
| Phase 1: 기본 기능 테스트 통과 (19/19) | ✅ | Claude Code | 2025-01-17 |
| Phase 2: 성능 테스트 통과 (113K/sec 달성) | ✅ | Claude Code | 2025-01-17 |
| Phase 3: 장애 시나리오 테스트 통과 (9/9) | ✅ | Claude Code | 2025-01-17 |
| Phase 4: 모니터링 검증 통과 (13/13) | ✅ | Claude Code | 2025-01-17 |
| 스테이징 Sign-off 완료 | ⬜ | | |

#### 스테이징 테스트 상세 결과 (2025-01-17)

**Phase 1: 기본 기능 테스트**
- 샤딩 비활성화 → Legacy 토픽 라우팅: ✅ 5/5
- 샤딩 활성화 → 샤드별 라우팅: ✅ 14/14
- 테스트 파일: `test/staging/phase1-routing.spec.ts`

**Phase 2: 성능 테스트**
| 메트릭 | 목표 | 결과 |
|-------|------|------|
| OrderRouter 처리량 | 100K/sec | 113,164/sec ✅ |
| P50 Latency | <100ms | 33.79µs ✅ |
| P95 Latency | <500ms | 99.17µs ✅ |
| P99 Latency | <1000ms | 323.13µs ✅ |

**Phase 3: 장애 시나리오 테스트**
- 심볼 일시중지/재개: ✅ 3/3
- Kafka 에러 핸들링: ✅ 2/2
- 롤백 시나리오: ✅ 2/2
- 동적 리밸런싱: ✅ 2/2
- 테스트 파일: `test/staging/phase3-failure-scenarios.spec.ts`

**Phase 4: 모니터링 검증**
- Shard API: ✅ 5/5
- 대시보드 데이터: ✅ 2/2
- 헬스체크: ✅ 3/3
- 로깅: ✅ 3/3
- 테스트 파일: `test/staging/phase4-monitoring.spec.ts`

### 1.2 인프라 준비

| 항목 | 완료 | 확인자 | 비고 |
|-----|:----:|-------|-----|
| Kafka 클러스터 용량 확인 | ⬜ | | 최소 3 broker |
| Kafka 토픽 생성 완료 | ⬜ | | 9개 토픽 |
| 토픽 파티션 수 확인 | ⬜ | | 권장: 6-12 |
| 토픽 Replication Factor 확인 | ⬜ | | 최소: 2 |
| 매칭 엔진 Docker 이미지 Push | ⬜ | | ECR/GCR |
| K8s 매니페스트 검토 완료 | ⬜ | | |
| ConfigMap/Secret 업데이트 | ⬜ | | |
| PDB (Pod Disruption Budget) 설정 | ⬜ | | |

```bash
# Kafka 토픽 생성 명령어
./scripts/create-shard-topics.sh \
  --bootstrap prod-kafka:9092 \
  --partitions 6 \
  --replication 2
```

### 1.3 모니터링 준비

| 항목 | 완료 | 확인자 | 비고 |
|-----|:----:|-------|-----|
| Grafana 대시보드 배포 | ⬜ | | 3개 대시보드 |
| Prometheus ServiceMonitor 설정 | ⬜ | | |
| 알림 규칙 배포 | ⬜ | | 11개 규칙 |
| Slack 알림 채널 확인 | ⬜ | | #matching-engine-alerts |
| PagerDuty 연동 확인 | ⬜ | | Critical 알림용 |
| 온콜 담당자 지정 | ⬜ | | |

### 1.4 문서 및 커뮤니케이션

| 항목 | 완료 | 확인자 | 비고 |
|-----|:----:|-------|-----|
| 롤백 절차서 검토 | ⬜ | | |
| 운영팀 브리핑 완료 | ⬜ | | |
| CS팀 공지 완료 | ⬜ | | |
| 배포 공지 (내부) | ⬜ | | |
| 유지보수 공지 (외부) | ⬜ | | 필요시 |

### 1.5 백업 및 복구

| 항목 | 완료 | 확인자 | 비고 |
|-----|:----:|-------|-----|
| DB 백업 완료 | ⬜ | | |
| Redis 스냅샷 생성 | ⬜ | | |
| 현재 설정 백업 | ⬜ | | ConfigMap |
| 이전 버전 이미지 태그 확인 | ⬜ | | 롤백용 |

---

## 2. 배포 당일 (D-Day)

### 2.1 배포 전 최종 확인 (T-2h)

| 항목 | 완료 | 확인자 | 시간 |
|-----|:----:|-------|-----|
| 시스템 상태 정상 확인 | ⬜ | | |
| 에러율 기준선 기록 | ⬜ | | ___% |
| 지연시간 기준선 기록 | ⬜ | | ___ms |
| 처리량 기준선 기록 | ⬜ | | ___/sec |
| Kafka Consumer Lag 확인 | ⬜ | | |
| 온콜 담당자 대기 확인 | ⬜ | | |

```bash
# 현재 메트릭 기록
curl -s http://prometheus:9090/api/v1/query?query=rate(http_requests_total[5m])
curl -s http://prometheus:9090/api/v1/query?query=histogram_quantile(0.95,rate(http_request_duration_seconds_bucket[5m]))
```

### 2.2 배포 순서

#### Step 1: 매칭 엔진 샤드 배포 (T-0)

```bash
# 샤드 이미지 확인
docker pull your-registry/matching-engine-shard:v1.0.0

# 샤드 배포 (순차적)
kubectl apply -f k8s/overlays/prod/
```

| 샤드 | Primary | Standby | 상태 |
|-----|:-------:|:-------:|:----:|
| Shard-1 (BTC) | ⬜ | ⬜ | |
| Shard-2 (ETH) | ⬜ | ⬜ | |
| Shard-3 (Other) | ⬜ | ⬜ | |

**각 샤드 배포 후 확인**:
```bash
# 헬스 체크
kubectl get pods -l app=matching-engine -w
curl http://shard-1-primary:8080/health

# 로그 확인
kubectl logs -l shard=shard-1 --tail=50
```

#### Step 2: Backend 카나리 배포 (T+30m)

```bash
# 5% 트래픽으로 시작
kubectl apply -f k8s/canary/backend-canary-5.yaml
```

| 단계 | 트래픽 | 기간 | 상태 | 에러율 |
|-----|:-----:|:----:|:----:|:-----:|
| Canary 5% | 5% | 15분 | ⬜ | ___% |
| Canary 25% | 25% | 15분 | ⬜ | ___% |
| Canary 50% | 50% | 15분 | ⬜ | ___% |
| Full 100% | 100% | - | ⬜ | ___% |

**각 단계 확인 항목**:
- [ ] 에러율 < 0.1%
- [ ] P95 Latency < 500ms
- [ ] 샤드 라우팅 로그 정상
- [ ] Kafka Consumer Lag 정상

#### Step 3: 전체 배포 완료 (T+1h~2h)

```bash
# 전체 트래픽 전환
kubectl apply -f k8s/production/backend-full.yaml

# 이전 버전 Pod 정리 (안정화 후)
kubectl delete deployment backend-legacy
```

---

## 3. 배포 후 검증 (D-Day ~ D+1)

### 3.1 즉시 확인 (배포 후 30분)

| 항목 | 기준 | 실제 | Pass/Fail |
|-----|------|-----|:---------:|
| API 에러율 | <0.1% | | |
| API P95 Latency | <500ms | | |
| 주문 처리량 | ≥기준선 | | |
| Kafka Consumer Lag | <1000 | | |
| 샤드 상태 | 모두 ACTIVE | | |

### 3.2 심볼별 라우팅 확인

```bash
# 각 샤드 토픽 메시지 확인
kafka-console-consumer --bootstrap-server prod-kafka:9092 \
  --topic matching-engine-shard-1-input \
  --max-messages 5
```

| 심볼 | 예상 샤드 | 실제 샤드 | Pass/Fail |
|-----|---------|---------|:---------:|
| BTCUSDT | shard-1 | | |
| ETHUSDT | shard-2 | | |
| SOLUSDT | shard-3 | | |

### 3.3 기능 검증

| 테스트 | 방법 | 결과 | Pass/Fail |
|-------|-----|-----|:---------:|
| 주문 생성 | API 호출 | | |
| 주문 취소 | API 호출 | | |
| 주문 체결 | 매칭 확인 | | |
| 포지션 확인 | API 조회 | | |
| WebSocket 업데이트 | 실시간 수신 | | |

### 3.4 모니터링 확인

| 대시보드 | 확인 항목 | 상태 |
|---------|---------|:----:|
| Overview | 샤드별 처리량 | ⬜ |
| JVM | 메모리/GC 정상 | ⬜ |
| Orders | 지연시간 그래프 | ⬜ |

---

## 4. 안정화 모니터링 (D+1 ~ D+7)

### 4.1 일일 체크리스트

| 날짜 | 에러율 | P95 | 처리량 | 이슈 | 확인자 |
|-----|:-----:|:---:|:-----:|-----|-------|
| D+1 | | | | | |
| D+2 | | | | | |
| D+3 | | | | | |
| D+4 | | | | | |
| D+5 | | | | | |
| D+6 | | | | | |
| D+7 | | | | | |

### 4.2 주간 리뷰 항목

- [ ] 전체 에러율 추이
- [ ] 샤드별 부하 분산 비율
- [ ] 피크 시간 성능
- [ ] 알림 발생 내역
- [ ] 사용자 피드백

---

## 5. 롤백 절차

### 5.1 롤백 트리거 조건

| 조건 | 임계값 | 자동/수동 |
|-----|-------|:--------:|
| 에러율 급증 | >1% (5분간) | 수동 |
| P95 Latency | >2000ms | 수동 |
| 샤드 전체 장애 | 2개 이상 | 수동 |
| 데이터 불일치 | 발견 즉시 | 수동 |

### 5.2 긴급 롤백 명령어

```bash
# 1. Backend 샤딩 비활성화 (즉시)
kubectl set env deployment/backend SHARDING_ENABLED=false
kubectl rollout restart deployment/backend

# 2. 롤백 확인
kubectl rollout status deployment/backend

# 3. 기존 토픽 Consumer 확인
kafka-consumer-groups --bootstrap-server prod-kafka:9092 \
  --group matching-engine --describe
```

### 5.3 롤백 체크리스트

| 항목 | 완료 | 확인자 | 시간 |
|-----|:----:|-------|-----|
| 샤딩 비활성화 적용 | ⬜ | | |
| 모든 Pod 재시작 완료 | ⬜ | | |
| Legacy 토픽 메시지 수신 확인 | ⬜ | | |
| 에러율 정상화 확인 | ⬜ | | |
| 사후 분석 티켓 생성 | ⬜ | | |

---

## 6. 비상 연락처

| 역할 | 이름 | 연락처 | 백업 |
|-----|------|-------|-----|
| 배포 담당 | | | |
| 백엔드 개발 | | | |
| 인프라/DevOps | | | |
| DBA | | | |
| 온콜 담당 | | | |

---

## 7. Sign-off

### 배포 승인

| 역할 | 이름 | 서명 | 날짜 |
|-----|------|-----|-----|
| Engineering Lead | | | |
| QA Lead | | | |
| DevOps Lead | | | |
| Product Owner | | | |

### 배포 완료 확인

| 항목 | 확인자 | 날짜 |
|-----|-------|-----|
| 배포 완료 | | |
| 안정화 확인 (D+1) | | |
| 최종 승인 (D+7) | | |

---

## 부록

### A. 주요 명령어 모음

```bash
# 배포 상태 확인
kubectl get pods -n matching-engine
kubectl get pods -n backend

# 로그 확인
kubectl logs -f deployment/backend -c backend
kubectl logs -f statefulset/shard-1-primary

# 메트릭 확인
curl http://prometheus:9090/api/v1/query?query=up{job="matching-engine"}

# Kafka 상태
kafka-topics --bootstrap-server prod-kafka:9092 --list
kafka-consumer-groups --bootstrap-server prod-kafka:9092 --list
```

### B. 관련 문서

- [매칭 엔진 샤딩 가이드](./implementation-guide/matching-engine-sharding.md)
- [롤백 절차서](./implementation-guide/rollback-procedure.md)
- [스테이징 테스트 계획](./staging-test-plan.md)
- [성능 테스트 가이드](../test/performance/README.md)

### C. 배포 타임라인 예시

```
T-2h    최종 확인, 기준선 기록
T-0     매칭 엔진 샤드 배포 시작
T+15m   샤드 헬스 체크 완료
T+30m   Backend 카나리 5% 배포
T+45m   카나리 25% 전환
T+1h    카나리 50% 전환
T+1h30m 전체 100% 전환
T+2h    배포 완료, 모니터링 시작
T+24h   D+1 안정화 확인
T+7d    최종 승인
```

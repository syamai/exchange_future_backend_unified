# 매칭 엔진 롤백 절차서

## 개요

이 문서는 샤딩된 매칭 엔진의 롤백 절차를 정의합니다. 배포 실패, 장애 발생, 또는 예기치 않은 동작 시 신속하게 이전 상태로 복구하기 위한 가이드입니다.

## 목차

1. [롤백 시나리오 분류](#롤백-시나리오-분류)
2. [사전 준비사항](#사전-준비사항)
3. [롤백 절차](#롤백-절차)
4. [샤드별 롤백](#샤드별-롤백)
5. [긴급 롤백](#긴급-롤백)
6. [롤백 후 검증](#롤백-후-검증)
7. [트러블슈팅](#트러블슈팅)

---

## 롤백 시나리오 분류

### Level 1: 단순 배포 롤백
- **상황**: 새 버전 배포 후 버그 발견
- **영향**: 특정 기능 오작동
- **조치**: Kubernetes 롤백
- **소요시간**: 5-10분

### Level 2: 샤드 단위 롤백
- **상황**: 특정 샤드에서만 문제 발생
- **영향**: 해당 샤드의 심볼만 영향
- **조치**: 해당 샤드만 롤백
- **소요시간**: 10-15분

### Level 3: 전체 시스템 롤백
- **상황**: 전체 매칭 엔진 장애
- **영향**: 모든 거래 중단
- **조치**: 전체 롤백 + 상태 복구
- **소요시간**: 30-60분

### Level 4: 긴급 복구 (재해 복구)
- **상황**: 데이터 손상, 인프라 장애
- **영향**: 전체 시스템 불가
- **조치**: DR 사이트 전환 또는 백업 복구
- **소요시간**: 1-4시간

---

## 사전 준비사항

### 1. 롤백 체크리스트

```
□ 이전 버전 이미지 태그 확인
□ 현재 상태 스냅샷 생성
□ Kafka consumer offset 기록
□ 활성 주문 수 확인
□ 롤백 담당자 지정
□ 커뮤니케이션 채널 준비 (Slack, PagerDuty)
```

### 2. 필수 정보 수집

```bash
# 현재 배포 상태 확인
kubectl get statefulset -n matching-engine -o wide
kubectl get pods -n matching-engine -o wide

# 현재 이미지 버전 확인
kubectl get statefulset matching-engine-shard-1 -n matching-engine \
  -o jsonpath='{.spec.template.spec.containers[0].image}'

# Revision 히스토리 확인
kubectl rollout history statefulset/matching-engine-shard-1 -n matching-engine

# 활성 주문 수 확인 (Prometheus)
curl -s "http://prometheus:9090/api/v1/query?query=sum(matching_engine_active_orders_total)" | jq
```

### 3. 롤백 대상 버전 결정

```bash
# 사용 가능한 이미지 태그 목록
aws ecr describe-images \
  --repository-name exchange/matching-engine-shard \
  --query 'imageDetails[*].imageTags' \
  --output table

# 최근 배포 이력 (마지막 5개)
kubectl rollout history statefulset/matching-engine-shard-1 -n matching-engine --revision=5
```

---

## 롤백 절차

### Level 1: Kubernetes 롤백 (권장)

#### Step 1: 롤백 전 상태 저장

```bash
#!/bin/bash
# save-state-before-rollback.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/tmp/rollback-backup-${TIMESTAMP}"
mkdir -p ${BACKUP_DIR}

# 현재 매니페스트 저장
for shard in 1 2 3; do
  kubectl get statefulset matching-engine-shard-${shard} -n matching-engine -o yaml \
    > ${BACKUP_DIR}/shard-${shard}-statefulset.yaml
done

# Pod 상태 저장
kubectl get pods -n matching-engine -o wide > ${BACKUP_DIR}/pods-status.txt

# 메트릭 스냅샷
curl -s "http://prometheus:9090/api/v1/query?query=matching_engine_active_orders_total" \
  > ${BACKUP_DIR}/active-orders.json

echo "Backup saved to: ${BACKUP_DIR}"
```

#### Step 2: 트래픽 차단 (선택적)

```bash
# 새 주문 일시 중지 (Backend에서 처리)
kubectl annotate service matching-engine -n matching-engine \
  traffic.sidecar.istio.io/excludeInboundPorts="8080"

# 또는 HPA 비활성화
kubectl patch hpa matching-engine-shard-1 -n matching-engine \
  -p '{"spec":{"minReplicas":0,"maxReplicas":0}}'
```

#### Step 3: 롤백 실행

```bash
# 방법 1: 이전 리비전으로 롤백
kubectl rollout undo statefulset/matching-engine-shard-1 -n matching-engine
kubectl rollout undo statefulset/matching-engine-shard-2 -n matching-engine
kubectl rollout undo statefulset/matching-engine-shard-3 -n matching-engine

# 방법 2: 특정 리비전으로 롤백
kubectl rollout undo statefulset/matching-engine-shard-1 -n matching-engine --to-revision=3

# 방법 3: 특정 이미지로 롤백
kubectl set image statefulset/matching-engine-shard-1 \
  matching-engine=exchange/matching-engine-shard:v1.2.0 \
  -n matching-engine
```

#### Step 4: 롤백 상태 모니터링

```bash
# 롤백 진행 상황 확인
kubectl rollout status statefulset/matching-engine-shard-1 -n matching-engine --timeout=300s

# Pod 상태 확인
watch -n 2 'kubectl get pods -n matching-engine -l app.kubernetes.io/name=matching-engine'

# 로그 확인
kubectl logs -f statefulset/matching-engine-shard-1 -n matching-engine --tail=100
```

#### Step 5: 트래픽 복구

```bash
# 트래픽 재개
kubectl annotate service matching-engine -n matching-engine \
  traffic.sidecar.istio.io/excludeInboundPorts-

# Health check 확인
for shard in 1 2 3; do
  POD=$(kubectl get pod -n matching-engine -l app.kubernetes.io/component=shard-${shard} -o jsonpath='{.items[0].metadata.name}')
  kubectl exec -n matching-engine ${POD} -- curl -s http://localhost:8080/health/ready
  echo ""
done
```

---

## 샤드별 롤백

### 특정 샤드만 롤백

특정 샤드에서만 문제가 발생한 경우, 해당 샤드만 롤백합니다.

```bash
#!/bin/bash
# rollback-single-shard.sh

SHARD_ID=${1:-1}
TARGET_REVISION=${2:-""}

echo "=== Rolling back Shard ${SHARD_ID} ==="

# 1. 해당 샤드의 심볼 일시 중지
SYMBOLS=$(kubectl get configmap matching-engine-config -n matching-engine \
  -o jsonpath='{.data.shard-mapping\.yaml}' | yq ".shards.shard-${SHARD_ID}.symbols[]")

echo "Pausing symbols: ${SYMBOLS}"
# API 호출로 심볼 일시 중지 (Backend에서 구현 필요)
# curl -X POST "http://backend/api/admin/symbols/pause" -d "{\"symbols\": ${SYMBOLS}}"

# 2. 롤백 실행
if [ -z "$TARGET_REVISION" ]; then
  kubectl rollout undo statefulset/matching-engine-shard-${SHARD_ID} -n matching-engine
else
  kubectl rollout undo statefulset/matching-engine-shard-${SHARD_ID} -n matching-engine \
    --to-revision=${TARGET_REVISION}
fi

# 3. 롤백 완료 대기
kubectl rollout status statefulset/matching-engine-shard-${SHARD_ID} -n matching-engine \
  --timeout=300s

# 4. Health check
sleep 10
kubectl exec -n matching-engine matching-engine-shard-${SHARD_ID}-0 -- \
  curl -s http://localhost:8080/health/ready

# 5. 심볼 재개
echo "Resuming symbols..."
# curl -X POST "http://backend/api/admin/symbols/resume" -d "{\"symbols\": ${SYMBOLS}}"

echo "=== Shard ${SHARD_ID} rollback completed ==="
```

### 샤드 간 불일치 해결

샤드 간 버전 불일치 시 동기화:

```bash
# 모든 샤드를 동일 버전으로 통일
TARGET_IMAGE="exchange/matching-engine-shard:v1.2.0"

for shard in 1 2 3; do
  kubectl set image statefulset/matching-engine-shard-${shard} \
    matching-engine=${TARGET_IMAGE} \
    -n matching-engine
done

# 순차적 롤아웃 확인
for shard in 1 2 3; do
  echo "Waiting for shard-${shard}..."
  kubectl rollout status statefulset/matching-engine-shard-${shard} -n matching-engine
done
```

---

## 긴급 롤백

### 긴급 상황 판단 기준

| 지표 | 임계값 | 조치 |
|-----|-------|-----|
| 주문 처리량 | 0 (5분 이상) | 즉시 롤백 |
| 에러율 | 50% 이상 | 즉시 롤백 |
| P99 지연 | 5초 이상 | 즉시 롤백 |
| 메모리 사용 | 99% 이상 | 즉시 롤백 |
| Pod Crash | 3회 이상/분 | 즉시 롤백 |

### 긴급 롤백 스크립트

```bash
#!/bin/bash
# emergency-rollback.sh
# 사용법: ./emergency-rollback.sh [target-version]

set -e

TARGET_VERSION=${1:-"previous"}
NAMESPACE="matching-engine"
SLACK_WEBHOOK="${SLACK_WEBHOOK_URL}"

# 알림 함수
notify() {
  local message=$1
  local severity=${2:-"info"}

  echo "[$(date '+%Y-%m-%d %H:%M:%S')] ${message}"

  if [ -n "$SLACK_WEBHOOK" ]; then
    curl -s -X POST -H 'Content-type: application/json' \
      --data "{\"text\":\"[${severity^^}] Matching Engine: ${message}\"}" \
      ${SLACK_WEBHOOK}
  fi
}

# 1. 긴급 알림
notify "🚨 Emergency rollback initiated" "critical"

# 2. 현재 상태 기록
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
kubectl get all -n ${NAMESPACE} > /tmp/emergency-state-${TIMESTAMP}.txt

# 3. 모든 샤드 동시 롤백
notify "Rolling back all shards..."

if [ "$TARGET_VERSION" == "previous" ]; then
  # 이전 버전으로 롤백
  kubectl rollout undo statefulset/matching-engine-shard-1 -n ${NAMESPACE} &
  kubectl rollout undo statefulset/matching-engine-shard-2 -n ${NAMESPACE} &
  kubectl rollout undo statefulset/matching-engine-shard-3 -n ${NAMESPACE} &
else
  # 특정 버전으로 롤백
  IMAGE="exchange/matching-engine-shard:${TARGET_VERSION}"
  kubectl set image statefulset/matching-engine-shard-1 matching-engine=${IMAGE} -n ${NAMESPACE} &
  kubectl set image statefulset/matching-engine-shard-2 matching-engine=${IMAGE} -n ${NAMESPACE} &
  kubectl set image statefulset/matching-engine-shard-3 matching-engine=${IMAGE} -n ${NAMESPACE} &
fi

wait

# 4. 롤백 완료 대기
notify "Waiting for rollback to complete..."

for shard in 1 2 3; do
  kubectl rollout status statefulset/matching-engine-shard-${shard} -n ${NAMESPACE} \
    --timeout=300s || {
      notify "Shard ${shard} rollback timeout!" "critical"
      exit 1
    }
done

# 5. Health 확인
sleep 15
HEALTHY=0
for shard in 1 2 3; do
  POD="matching-engine-shard-${shard}-0"
  if kubectl exec -n ${NAMESPACE} ${POD} -- curl -sf http://localhost:8080/health/ready > /dev/null; then
    HEALTHY=$((HEALTHY + 1))
  fi
done

if [ $HEALTHY -eq 3 ]; then
  notify "✅ Emergency rollback completed successfully. All 3 shards healthy." "info"
else
  notify "⚠️ Rollback completed but only ${HEALTHY}/3 shards healthy!" "warning"
fi

# 6. 메트릭 확인
echo ""
echo "=== Post-rollback Status ==="
kubectl get pods -n ${NAMESPACE} -o wide
```

### 긴급 정지 (Kill Switch)

모든 거래를 즉시 중단해야 하는 경우:

```bash
#!/bin/bash
# kill-switch.sh - 모든 매칭 엔진 즉시 중단

NAMESPACE="matching-engine"

echo "⚠️  WARNING: This will stop ALL trading immediately!"
read -p "Type 'STOP ALL TRADING' to confirm: " CONFIRM

if [ "$CONFIRM" != "STOP ALL TRADING" ]; then
  echo "Aborted."
  exit 1
fi

# 1. 모든 StatefulSet 스케일 다운
kubectl scale statefulset --all -n ${NAMESPACE} --replicas=0

# 2. 확인
kubectl get pods -n ${NAMESPACE}

echo ""
echo "All matching engines stopped."
echo "To restore: kubectl scale statefulset --all -n ${NAMESPACE} --replicas=2"
```

---

## 롤백 후 검증

### 1. 기본 상태 확인

```bash
#!/bin/bash
# verify-rollback.sh

NAMESPACE="matching-engine"

echo "=== 1. Pod Status ==="
kubectl get pods -n ${NAMESPACE} -o wide

echo ""
echo "=== 2. Version Check ==="
for shard in 1 2 3; do
  VERSION=$(kubectl get statefulset matching-engine-shard-${shard} -n ${NAMESPACE} \
    -o jsonpath='{.spec.template.spec.containers[0].image}')
  echo "Shard ${shard}: ${VERSION}"
done

echo ""
echo "=== 3. Health Status ==="
for shard in 1 2 3; do
  POD="matching-engine-shard-${shard}-0"
  echo -n "Shard ${shard}: "
  kubectl exec -n ${NAMESPACE} ${POD} -- curl -sf http://localhost:8080/health 2>/dev/null || echo "UNHEALTHY"
done

echo ""
echo "=== 4. Recent Errors ==="
kubectl logs -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine \
  --since=5m --tail=50 | grep -i "error\|exception" | tail -10

echo ""
echo "=== 5. Metrics Check ==="
# 주문 처리량
echo -n "Orders/sec: "
curl -sf "http://prometheus:9090/api/v1/query?query=sum(rate(matching_engine_orders_processed_total[1m]))" \
  | jq -r '.data.result[0].value[1] // "N/A"'

# 활성 주문 수
echo -n "Active Orders: "
curl -sf "http://prometheus:9090/api/v1/query?query=sum(matching_engine_active_orders_total)" \
  | jq -r '.data.result[0].value[1] // "N/A"'
```

### 2. 기능 테스트

```bash
# 테스트 주문 전송 (Staging/Dev 환경에서만)
curl -X POST "http://backend/api/v1/orders/test" \
  -H "Content-Type: application/json" \
  -d '{
    "symbol": "BTCUSDT",
    "side": "BUY",
    "type": "LIMIT",
    "quantity": "0.001",
    "price": "50000"
  }'
```

### 3. 검증 체크리스트

```
□ 모든 Pod Running 상태
□ Health endpoint 응답 정상
□ 주문 처리량 정상 범위
□ 에러 로그 없음
□ 메모리 사용량 안정
□ Kafka consumer lag 정상
□ Grafana 대시보드 정상
□ 테스트 주문 성공 (해당 시)
```

---

## 트러블슈팅

### 문제 1: 롤백이 진행되지 않음

```bash
# 원인 확인
kubectl describe statefulset matching-engine-shard-1 -n matching-engine
kubectl get events -n matching-engine --sort-by='.lastTimestamp' | tail -20

# 해결: Pod 강제 삭제 후 재생성
kubectl delete pod matching-engine-shard-1-0 -n matching-engine --force --grace-period=0
```

### 문제 2: 롤백 후에도 오류 지속

```bash
# 더 이전 버전으로 롤백
kubectl rollout history statefulset/matching-engine-shard-1 -n matching-engine
kubectl rollout undo statefulset/matching-engine-shard-1 -n matching-engine --to-revision=2

# 또는 알려진 안정 버전으로 직접 지정
kubectl set image statefulset/matching-engine-shard-1 \
  matching-engine=exchange/matching-engine-shard:v1.0.0-stable \
  -n matching-engine
```

### 문제 3: Kafka Consumer Lag 증가

```bash
# Consumer 상태 확인
kafka-consumer-groups.sh --bootstrap-server kafka:9092 \
  --group matching-engine-shard-1 --describe

# 오프셋 리셋 (주의: 데이터 유실 가능)
kafka-consumer-groups.sh --bootstrap-server kafka:9092 \
  --group matching-engine-shard-1 \
  --topic matching-engine-shard-1-input \
  --reset-offsets --to-latest --execute
```

### 문제 4: PVC 마운트 실패

```bash
# PVC 상태 확인
kubectl get pvc -n matching-engine

# PVC 재생성 필요시
kubectl delete pvc logs-matching-engine-shard-1-0 -n matching-engine
# Pod가 다시 시작되면 새 PVC 생성됨
```

### 문제 5: Primary/Standby 역할 혼란

```bash
# Pod 라벨 확인
kubectl get pods -n matching-engine -L role

# 강제로 역할 재할당 (Pod 재시작으로)
kubectl delete pod matching-engine-shard-1-0 -n matching-engine
kubectl delete pod matching-engine-shard-1-1 -n matching-engine
# Pod-0이 Primary, Pod-1이 Standby로 재시작됨
```

---

## 롤백 이력 관리

### 롤백 기록 템플릿

```markdown
## 롤백 기록

| 날짜 | 담당자 | 샤드 | 이전 버전 | 롤백 버전 | 원인 | 소요시간 |
|-----|-------|-----|---------|---------|-----|--------|
| 2024-01-15 14:30 | 홍길동 | 전체 | v1.3.0 | v1.2.5 | 메모리 누수 | 15분 |
| 2024-01-10 09:15 | 김철수 | shard-1 | v1.2.5 | v1.2.4 | BTC 주문 처리 오류 | 8분 |
```

### 사후 분석 (Post-mortem)

롤백 후 반드시 사후 분석을 수행합니다:

1. **근본 원인 분석**: 왜 롤백이 필요했는가?
2. **영향 범위**: 얼마나 많은 사용자/거래가 영향받았는가?
3. **탐지 시간**: 문제 발생부터 탐지까지 얼마나 걸렸는가?
4. **복구 시간**: 탐지부터 복구까지 얼마나 걸렸는가?
5. **재발 방지**: 어떻게 하면 같은 문제를 방지할 수 있는가?

---

## 연락처

| 역할 | 담당자 | 연락처 |
|-----|-------|-------|
| 매칭엔진 Lead | - | Slack: @matching-engine-lead |
| SRE 당직 | - | PagerDuty: exchange-sre |
| 인프라팀 | - | Slack: @infra-oncall |

---

*최종 수정일: 2024-01-16*
*문서 버전: 1.0*

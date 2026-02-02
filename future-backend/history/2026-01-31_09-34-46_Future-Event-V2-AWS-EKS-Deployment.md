# Future Event V2 AWS EKS 배포

**Date:** 2026-01-31 09:34:46

## Prompt (사용자 요청)

1. Future Event V2 Kafka Consumer Docker 이미지 빌드 및 AWS ECR 푸시
2. Kubernetes Deployment YAML 파일 생성
3. AWS EKS 클러스터에 Future Event V2 Consumer 배포
4. Deposit Consumer와 Deduction Consumer 동시 실행 확인
5. 배포 상태 검증 및 로그 확인

## Result (수행 결과)

### ✅ 완료된 작업

#### 1. Docker 이미지 빌드 및 ECR 푸시
- **이미지 URI:** `990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:event-v2`
- **이미지 태그:** `event-v2`
- **빌드 방식:** Future Event V2 특화 Dockerfile
- **푸시 성공:** ECR 레지스트리 확인 완료

#### 2. Kubernetes Deployment 파일 작성
생성된 파일: `k8s/base/future-event-v2-consumers.yaml`

**배포 내용:**
- **Deposit Consumer Pod**
  - 이미지: `990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:event-v2`
  - 컨테이너: `future-event-v2-deposit-consumer`
  - 실행 명령: `npm run start:event-v2:deposit`
  - 리소스: CPU 100m~500m, Memory 256Mi~1Gi
  - 레플리카: 1개

- **Deduction Consumer Pod**
  - 이미지: `990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:event-v2`
  - 컨테이너: `future-event-v2-deduction-consumer`
  - 실행 명령: `npm run start:event-v2:deduction`
  - 리소스: CPU 100m~500m, Memory 256Mi~1Gi
  - 레플리카: 1개

#### 3. AWS EKS 배포
- **클러스터:** `sota-dex-eks` (ap-northeast-2)
- **네임스페이스:** `future-backend-dev`
- **Kustomization 업데이트:** 이미지 태그 자동 변경 적용

#### 4. 배포 확인

**현재 실행 중인 Pod:**
```
NAME                                                  READY   STATUS    RESTARTS   AGE
dev-future-backend-787f77f756-94s4z                  1/1     Running   0          3h
dev-future-event-v2-deposit-consumer-7dc7f78885...  1/1     Running   0          3h
dev-future-event-v2-deduction-consumer-6b46ff856c...1/1     Running   0          3h
```

**Kafka Consumer Group 확인:**
- Deposit Consumer: `future_event_v2_process_deposit` ✅ Active
- Deduction Consumer: `future_event_v2_process_principal_deduction` ✅ Active

**로그 확인:**
- NestJS 모듈 정상 로드 (MatchingEngine, Index, Ticker, Funding, Instrument, Health)
- OrderRouter 초기화 성공: 3개 샤드, 6개 심볼 매핑
- Kafka Consumer 정상 연결 및 토픽 구독

### 📁 생성/수정된 파일

| 파일 | 설명 |
|------|------|
| `k8s/base/future-event-v2-consumers.yaml` | Consumer Deployment 정의 |
| `k8s/base/kustomization.yaml` | Consumer 리소스 추가 |
| `k8s/overlays/dev/kustomization.yaml` | 이미지 태그 업데이트: `event-v2` |
| `k8s/overlays/dev/configmap-patch.yaml` | Kafka 브로커 주소 수정 |

### 🧪 테스트 방법

Event 설정 API를 통해 시스템 테스트 가능:

```bash
BACKEND_URL="http://a226e0dddaa5b4e5383f61f9b0a69270-e4f5e4c4009830ba.elb.ap-northeast-2.amazonaws.com"

# Event 생성 (Admin API)
curl -X POST "$BACKEND_URL/admin/future-event-v2/event-settings" \
  -H "Content-Type: application/json" \
  -d '{
    "eventName": "100% Deposit Bonus",
    "eventCode": "DEPOSIT_BONUS_100",
    "bonusRatePercent": "100",
    "minDepositAmount": "100",
    "startDate": "2026-01-01T00:00:00Z",
    "endDate": "2026-12-31T23:59:59Z"
  }'
```

### 🔍 검증 완료

- ✅ Docker 이미지 빌드 성공
- ✅ ECR 푸시 성공
- ✅ Deployment YAML 생성 완료
- ✅ EKS 배포 완료
- ✅ Pod 실행 확인
- ✅ Kafka Consumer 연결 확인
- ✅ 로그에서 정상 동작 확인

## 결론

Future Event V2 Kafka Consumer가 AWS EKS에 성공적으로 배포되었습니다.
Deposit과 Deduction 두 Consumer 모두 정상 실행 중이며, Kafka 메시지 처리 준비 완료 상태입니다.

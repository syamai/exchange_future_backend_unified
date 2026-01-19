# AWS EKS 배포 트러블슈팅 가이드

이 문서는 Exchange 프로젝트를 AWS EKS에 배포하면서 발생한 문제들과 해결 방법을 정리합니다.

## 목차

1. [RDS 인스턴스 클래스 오류](#1-rds-인스턴스-클래스-오류)
2. [Docker 이미지 아키텍처 불일치](#2-docker-이미지-아키텍처-불일치)
3. [JWT 키 미설정 오류](#3-jwt-키-미설정-오류)
4. [데이터베이스 테이블 미존재](#4-데이터베이스-테이블-미존재)
5. [Health Check 엔드포인트 404](#5-health-check-엔드포인트-404)
6. [Redis 연결 타임아웃](#6-redis-연결-타임아웃)
7. [Kafka 브로커 설정 오류](#7-kafka-브로커-설정-오류)
8. [Prometheus CRD 미설치](#8-prometheus-crd-미설치)

---

## 1. RDS 인스턴스 클래스 오류

### 문제
CDK 배포 시 `Invalid DB Instance class: db.db.t3.large` 오류 발생

```
Error: Invalid DB Instance class: db.db.t3.large
```

### 원인
`infra/config/dev.ts`에서 `rdsInstanceClass: 'db.t3.large'`로 설정했는데, CDK의 `ec2.InstanceType()`가 자동으로 `db.` 접두사를 추가하여 `db.db.t3.large`가 됨

### 해결
설정에서 `db.` 접두사 제거

```typescript
// Before
rdsInstanceClass: 'db.t3.large'

// After
rdsInstanceClass: 't3.large'
```

### 관련 파일
- `infra/config/dev.ts`

---

## 2. Docker 이미지 아키텍처 불일치

### 문제
Pod 시작 시 `exec format error` 발생

```
exec /usr/local/bin/docker-entrypoint.sh: exec format error
```

### 원인
Apple Silicon (M1/M2)에서 빌드한 ARM64 이미지를 AMD64 기반 EKS 노드에서 실행

### 해결
`--platform linux/amd64` 옵션으로 재빌드

```bash
# Backend
docker buildx build --platform linux/amd64 --no-cache \
  -t 233244340438.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:v1.0.2-amd64 \
  --push .

# Matching Engine
docker buildx build --platform linux/amd64 -f Dockerfile.shard \
  -t 233244340438.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/matching-engine-shard:v1.0.0 \
  --push .
```

### 주의사항
- `imagePullPolicy: IfNotPresent`로 설정되어 있으면 기존 캐시된 이미지를 사용
- 새 태그로 푸시하거나 `imagePullPolicy: Always`로 변경 필요
- 프로덕션에서는 CI/CD에서 multi-platform 빌드 설정 권장

```bash
# Multi-platform 빌드 예시
docker buildx build --platform linux/amd64,linux/arm64 \
  -t <image>:<tag> --push .
```

---

## 3. JWT 키 미설정 오류

### 문제
Backend 시작 시 JWT 오류 발생

```
TypeError: JwtStrategy requires a secret or key
```

### 원인
`jwt_key.public`과 `jwt_key.private` 값이 ConfigMap에서 빈 문자열로 설정됨

### 해결
RSA 키 쌍 생성 후 ConfigMap에 Base64 인코딩하여 추가

```bash
# RSA 키 생성
openssl genrsa -out /tmp/jwt_private.pem 2048
openssl rsa -in /tmp/jwt_private.pem -pubout -out /tmp/jwt_public.pem

# Base64 인코딩
JWT_PUBLIC=$(cat /tmp/jwt_public.pem | base64 | tr -d '\n')
JWT_PRIVATE=$(cat /tmp/jwt_private.pem | base64 | tr -d '\n')

# ConfigMap 패치
kubectl patch configmap dev-future-backend-config -n future-backend-dev \
  --type=json -p="[{\"op\": \"replace\", \"path\": \"/data/local.yml\", \"value\": \"...\"}]"
```

### 관련 파일
- `future-backend/k8s/overlays/dev/configmap-patch.yaml`
- `future-backend/src/modules/auth/strategies/jwt.strategy.ts`

### 프로덕션 권장사항
- JWT 키는 AWS Secrets Manager에 저장
- External Secrets Operator를 사용하여 K8s Secret으로 동기화

---

## 4. 데이터베이스 테이블 미존재

### 문제
Backend 시작 시 테이블 없음 오류

```
QueryFailedError: Table 'future_exchange.users' doesn't exist
```

### 원인
1. RDS에 데이터베이스는 존재하지만 테이블이 없음
2. Docker 이미지에 소스 코드가 없어 `yarn typeorm:run` 마이그레이션 실행 불가
3. TypeORM `synchronize: true` 설정해도 앱 시작 시 쿼리가 먼저 실행되어 실패

### 해결
MySQL 클라이언트로 직접 테이블 생성

```bash
kubectl run mysql-init --image=mysql:8 --rm -it --restart=Never \
  -n future-backend-dev -- mysql -h <RDS_HOST> -u admin -p<PASSWORD> \
  future_exchange -e "
    CREATE TABLE IF NOT EXISTS users (...);
    CREATE TABLE IF NOT EXISTS trades (...);
    CREATE TABLE IF NOT EXISTS orders (...);
    CREATE TABLE IF NOT EXISTS accounts (...);
    CREATE TABLE IF NOT EXISTS positions (...);
    ...
  "
```

### 생성이 필요한 핵심 테이블 (15개)
| 테이블 | 설명 |
|--------|------|
| users | 사용자 정보 |
| trades | 체결 내역 |
| orders | 주문 내역 |
| accounts | 계정 잔고 |
| positions | 포지션 정보 |
| instruments | 거래 상품 |
| fundings | 펀딩 정보 |
| funding_histories | 펀딩 이력 |
| transactions | 입출금 내역 |
| candles | 캔들 데이터 |
| market_data | 시장 데이터 |
| market_indices | 지수 데이터 |
| trading_rules | 거래 규칙 |
| leverage_margin | 레버리지 마진 |
| settings | 설정 |

### 프로덕션 권장사항
- 마이그레이션 전용 Docker 이미지 생성 또는
- Init Container에서 마이그레이션 실행 또는
- K8s Job으로 마이그레이션 실행

```yaml
# 마이그레이션 Job 예시
apiVersion: batch/v1
kind: Job
metadata:
  name: db-migration
spec:
  template:
    spec:
      containers:
      - name: migration
        image: <backend-image-with-source>
        command: ["yarn", "typeorm:run"]
      restartPolicy: Never
```

---

## 5. Health Check 엔드포인트 404

### 문제
Pod가 계속 재시작됨

```
exception.getResponse() { statusCode: 404, message: 'Cannot GET /health' }
```

### 원인
Deployment의 `livenessProbe`와 `readinessProbe`가 `/health` 경로로 설정되어 있으나, 실제 앱에 해당 엔드포인트 없음

### 해결
HTTP 체크 대신 TCP 소켓 체크로 변경

```bash
kubectl patch deployment dev-future-backend -n future-backend-dev --type='json' -p='[
  {"op": "replace", "path": "/spec/template/spec/containers/0/livenessProbe",
   "value": {"tcpSocket": {"port": 3000}, "initialDelaySeconds": 30, "periodSeconds": 10}},
  {"op": "replace", "path": "/spec/template/spec/containers/0/readinessProbe",
   "value": {"tcpSocket": {"port": 3000}, "initialDelaySeconds": 10, "periodSeconds": 5}}
]'
```

### 프로덕션 권장사항
앱에 실제 health check 엔드포인트 구현

```typescript
// NestJS Health Module 예시
@Controller('health')
export class HealthController {
  @Get()
  @HealthCheck()
  check() {
    return this.health.check([
      () => this.db.pingCheck('database'),
      () => this.redis.pingCheck('redis'),
    ]);
  }
}
```

---

## 6. Redis 연결 타임아웃

### 문제
Backend에서 Redis 연결 실패

```
[ioredis] Unhandled error event: Error: connect ETIMEDOUT
```

### 원인
1. ElastiCache Security Group이 CDK에서 생성한 `eksSecurityGroup`만 허용
2. 실제 EKS 노드는 자동 생성된 다른 Security Group 사용
3. Isolated subnet에 있는 Redis와 Private subnet에 있는 EKS 노드 간 SG 불일치

### 진단 방법
```bash
# Redis DNS 확인
kubectl run dns-test --image=busybox --rm -it --restart=Never \
  -n future-backend-dev -- nslookup <redis-endpoint>

# Redis 연결 테스트
kubectl run redis-test --image=busybox --rm -it --restart=Never \
  -n future-backend-dev -- nc -zv <redis-endpoint> 6379
```

### 해결
ElastiCache Security Group에 VPC CIDR 전체 허용 규칙 추가

```typescript
// infra/lib/stacks/elasticache-stack.ts
redisSecurityGroup.addIngressRule(
  ec2.Peer.ipv4(vpc.vpcCidrBlock),  // 10.0.0.0/16
  ec2.Port.tcp(6379),
  'Redis from VPC'
);
```

```bash
# CDK 재배포
npx cdk deploy Exchange-dev-Redis -c env=dev --require-approval never
```

### 관련 파일
- `infra/lib/stacks/elasticache-stack.ts`

---

## 7. Kafka 브로커 설정 오류

### 문제
Kafka 연결 시 브로커 찾을 수 없음

```
broker replied that the controller broker is 0, but did not reply with that broker in the broker list
```

### 원인
Redpanda의 `advertise-kafka-addr` 미설정으로 클라이언트가 올바른 주소를 받지 못함

### 해결
Redpanda 시작 시 advertise 주소 명시

```bash
rpk redpanda start \
  --kafka-addr PLAINTEXT://0.0.0.0:9092 \
  --advertise-kafka-addr PLAINTEXT://10.0.2.51:9092
```

### Docker Compose 설정 예시
```yaml
services:
  redpanda:
    command:
      - redpanda start
      - --kafka-addr PLAINTEXT://0.0.0.0:9092
      - --advertise-kafka-addr PLAINTEXT://${HOST_IP}:9092
```

---

## 8. Prometheus CRD 미설치

### 문제
Matching Engine 배포 시 경고

```
no matches for kind "PrometheusRule" in version "monitoring.coreos.com/v1"
no matches for kind "ServiceMonitor" in version "monitoring.coreos.com/v1"
```

### 원인
Prometheus Operator CRD가 클러스터에 설치되어 있지 않음

### 영향
- 핵심 리소스(StatefulSet, Service 등)는 정상 생성됨
- 모니터링 리소스만 생성 실패
- 개발 환경에서는 무시 가능

### 해결 (프로덕션)
Prometheus Operator 설치

```bash
# Helm으로 설치
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install prometheus prometheus-community/kube-prometheus-stack \
  --namespace monitoring --create-namespace
```

---

## 문제 해결 요약

| # | 문제 | 근본 원인 | 해결 시간 |
|---|------|----------|----------|
| 1 | RDS 인스턴스 클래스 | CDK 자동 접두사 추가 | 즉시 |
| 2 | Docker 아키텍처 | ARM64 vs AMD64 | ~10분 |
| 3 | JWT 키 미설정 | ConfigMap 빈 값 | ~5분 |
| 4 | DB 테이블 없음 | 마이그레이션 미실행 | ~15분 |
| 5 | Health Check 404 | 엔드포인트 불일치 | 즉시 |
| 6 | Redis 타임아웃 | Security Group 불일치 | ~10분 |
| 7 | Kafka 브로커 | advertise-addr 미설정 | ~5분 |
| 8 | Prometheus CRD | CRD 미설치 | 선택적 |

---

## 배포 전 체크리스트

### 인프라
- [ ] CDK 설정에서 인스턴스 클래스 접두사 확인
- [ ] Security Group 규칙 확인 (VPC CIDR 허용)
- [ ] 서브넷 라우팅 테이블 확인

### 애플리케이션
- [ ] Docker 이미지 아키텍처 확인 (linux/amd64)
- [ ] ConfigMap/Secret 값 확인 (JWT 키, DB 연결 정보)
- [ ] Health check 엔드포인트 확인

### 데이터베이스
- [ ] 데이터베이스 생성 확인
- [ ] 마이그레이션 실행 또는 테이블 생성
- [ ] 연결 테스트

### 네트워크
- [ ] Redis 연결 테스트
- [ ] Kafka 연결 테스트
- [ ] DNS 확인

---

## 유용한 디버깅 명령어

```bash
# Pod 로그 확인
kubectl logs -f <pod-name> -n <namespace>

# Pod 상태 확인
kubectl describe pod <pod-name> -n <namespace>

# 네트워크 연결 테스트
kubectl run debug --image=busybox --rm -it --restart=Never -- nc -zv <host> <port>

# DNS 확인
kubectl run debug --image=busybox --rm -it --restart=Never -- nslookup <hostname>

# ConfigMap 확인
kubectl get configmap <name> -n <namespace> -o yaml

# Secret 확인 (base64 디코딩)
kubectl get secret <name> -n <namespace> -o jsonpath='{.data.<key>}' | base64 -d
```

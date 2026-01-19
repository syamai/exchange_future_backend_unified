# AWS CDK 트러블슈팅 가이드

AWS CDK로 EKS, RDS, ElastiCache 등을 배포할 때 자주 발생하는 문제와 해결 방법을 정리한 범용 가이드입니다.

> 이 문서의 문제들은 **다른 CDK 프로젝트에서도 반복적으로 발생**할 수 있습니다.

## 목차

1. [RDS 인스턴스 클래스 중복 접두사](#1-rds-인스턴스-클래스-중복-접두사)
2. [Docker 이미지 아키텍처 불일치](#2-docker-이미지-아키텍처-불일치)
3. [EKS + ElastiCache Security Group 불일치](#3-eks--elasticache-security-group-불일치)
4. [ConfigMap/Secret 값 미설정](#4-configmapsecret-값-미설정)
5. [데이터베이스 마이그레이션 미실행](#5-데이터베이스-마이그레이션-미실행)
6. [Kubernetes Health Check 엔드포인트 불일치](#6-kubernetes-health-check-엔드포인트-불일치)
7. [Kafka/Redpanda advertise 주소 미설정](#7-kafkaredpanda-advertise-주소-미설정)
8. [Prometheus CRD 미설치](#8-prometheus-crd-미설치)

---

## 1. RDS 인스턴스 클래스 중복 접두사

### 재발 위험도: 🔴 높음

CDK로 RDS를 처음 설정하는 개발자가 자주 겪는 문제입니다.

### 증상
```
Error: Invalid DB Instance class: db.db.t3.large
```

### 원인
CDK의 `ec2.InstanceType()`은 RDS에서 사용할 때 자동으로 `db.` 접두사를 추가합니다.
설정에서 `db.t3.large`로 지정하면 `db.db.t3.large`가 됩니다.

### 잘못된 코드
```typescript
// ❌ 잘못된 설정
const config = {
  rdsInstanceClass: 'db.t3.large'  // db. 접두사 포함
};

new rds.DatabaseInstance(this, 'Database', {
  instanceType: new ec2.InstanceType(config.rdsInstanceClass),
  // 결과: db.db.t3.large (오류)
});
```

### 올바른 코드
```typescript
// ✅ 올바른 설정
const config = {
  rdsInstanceClass: 't3.large'  // db. 접두사 제외
};

new rds.DatabaseInstance(this, 'Database', {
  instanceType: new ec2.InstanceType(config.rdsInstanceClass),
  // 결과: db.t3.large (정상)
});
```

### 예방법
- 설정 파일에 주석으로 `db.` 접두사 불필요 명시
- 타입 정의로 강제: `rdsInstanceClass: 't3.large' | 't3.medium' | ...`

---

## 2. Docker 이미지 아키텍처 불일치

### 재발 위험도: 🔴 높음 (Apple Silicon 사용자)

M1/M2/M3 Mac에서 빌드한 이미지를 AMD64 기반 EKS에서 실행할 때 발생합니다.

### 증상
```
exec /usr/local/bin/docker-entrypoint.sh: exec format error
```

### 원인
- 로컬 빌드: ARM64 (Apple Silicon)
- EKS 노드: AMD64 (x86_64)

### 해결
```bash
# 명시적으로 AMD64 플랫폼 지정
docker buildx build --platform linux/amd64 \
  -t <ECR_REPO>:<TAG> \
  --push .
```

### 예방법

**1. CI/CD에서만 빌드 (권장)**
```yaml
# GitHub Actions
- name: Build and Push
  run: |
    docker buildx build --platform linux/amd64 \
      -t ${{ env.ECR_REPO }}:${{ github.sha }} \
      --push .
```

**2. Multi-platform 빌드**
```bash
docker buildx build --platform linux/amd64,linux/arm64 \
  -t <IMAGE>:<TAG> --push .
```

**3. Makefile/스크립트로 강제**
```makefile
# Makefile
build-prod:
	docker buildx build --platform linux/amd64 -t $(IMAGE):$(TAG) --push .
```

### 주의사항
- `imagePullPolicy: IfNotPresent`면 기존 캐시 이미지 사용
- 새 태그로 푸시하거나 `imagePullPolicy: Always`로 변경 필요

---

## 3. EKS + ElastiCache Security Group 불일치

### 재발 위험도: 🔴🔴 매우 높음

**CDK로 EKS와 ElastiCache를 함께 배포할 때 거의 100% 발생하는 대표적인 함정입니다.**

### 증상
```
[ioredis] Unhandled error event: Error: connect ETIMEDOUT
```

### 원인
```
┌─────────────────────────────────────────────────────────────────┐
│  CDK가 생성하는 eksSecurityGroup ≠ EKS 노드의 실제 SG          │
│                                                                 │
│  CDK 코드:                                                      │
│    redisSecurityGroup.addIngressRule(eksSecurityGroup, ...)    │
│                                                                 │
│  실제 상황:                                                     │
│    EKS 노드 → 자동 생성된 eks-cluster-sg-xxx 사용              │
│    Redis SG → eksSecurityGroup만 허용                          │
│    결과: 연결 거부                                              │
└─────────────────────────────────────────────────────────────────┘
```

### 잘못된 코드
```typescript
// ❌ EKS Security Group만 허용 - 실제로는 작동하지 않을 수 있음
redisSecurityGroup.addIngressRule(
  eksSecurityGroup,
  ec2.Port.tcp(6379),
  'Redis from EKS'
);
```

### 올바른 코드
```typescript
// ✅ VPC CIDR 전체 허용 추가
redisSecurityGroup.addIngressRule(
  eksSecurityGroup,
  ec2.Port.tcp(6379),
  'Redis from EKS'
);

// 추가: EKS 노드가 다른 SG를 사용할 경우를 대비
redisSecurityGroup.addIngressRule(
  ec2.Peer.ipv4(vpc.vpcCidrBlock),  // 예: 10.0.0.0/16
  ec2.Port.tcp(6379),
  'Redis from VPC'
);
```

### 진단 방법
```bash
# 1. DNS 확인
kubectl run dns-test --image=busybox --rm -it --restart=Never \
  -- nslookup <redis-endpoint>

# 2. 포트 연결 테스트
kubectl run redis-test --image=busybox --rm -it --restart=Never \
  -- nc -zv <redis-endpoint> 6379

# 3. EKS 노드의 실제 Security Group 확인
aws ec2 describe-instances \
  --filters "Name=tag:eks:cluster-name,Values=<CLUSTER_NAME>" \
  --query "Reservations[].Instances[].SecurityGroups[]"
```

### 예방법
- **RDS, ElastiCache, Kafka 등 모든 데이터 레이어에 VPC CIDR 규칙 추가**
- CDK 템플릿에 기본으로 포함

---

## 4. ConfigMap/Secret 값 미설정

### 재발 위험도: 🟡 중간

### 증상
```
TypeError: JwtStrategy requires a secret or key
Error: REDIS_HOST is not defined
```

### 원인
Kustomize overlay에서 ConfigMap/Secret 값이 빈 문자열 또는 placeholder로 설정됨

### 해결 예시 (JWT 키)
```bash
# RSA 키 생성
openssl genrsa -out /tmp/jwt_private.pem 2048
openssl rsa -in /tmp/jwt_private.pem -pubout -out /tmp/jwt_public.pem

# Base64 인코딩
JWT_PUBLIC=$(cat /tmp/jwt_public.pem | base64 | tr -d '\n')
JWT_PRIVATE=$(cat /tmp/jwt_private.pem | base64 | tr -d '\n')

# ConfigMap/Secret 패치
kubectl patch configmap <name> -n <namespace> \
  --type=json -p="[{\"op\": \"replace\", \"path\": \"/data/JWT_PUBLIC_KEY\", \"value\": \"$JWT_PUBLIC\"}]"
```

### 예방법

**1. AWS Secrets Manager + External Secrets Operator (권장)**
```yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: jwt-keys
spec:
  secretStoreRef:
    name: aws-secrets-manager
    kind: ClusterSecretStore
  target:
    name: jwt-keys
  data:
    - secretKey: JWT_PRIVATE_KEY
      remoteRef:
        key: /myapp/jwt-keys
        property: private_key
```

**2. Sealed Secrets**
```bash
kubeseal --format=yaml < secret.yaml > sealed-secret.yaml
```

**3. 배포 스크립트에서 검증**
```bash
#!/bin/bash
# deploy.sh
if [ -z "$JWT_PRIVATE_KEY" ]; then
  echo "ERROR: JWT_PRIVATE_KEY is not set"
  exit 1
fi
```

---

## 5. 데이터베이스 마이그레이션 미실행

### 재발 위험도: 🔴 높음 (새 환경마다 발생)

### 증상
```
QueryFailedError: Table 'mydb.users' doesn't exist
```

### 원인
1. RDS는 생성되었으나 테이블은 없음
2. Docker 이미지에 마이그레이션 도구가 없거나
3. 마이그레이션이 자동 실행되지 않음

### 임시 해결 (수동)
```bash
kubectl run db-init --image=mysql:8 --rm -it --restart=Never \
  -n <namespace> -- mysql -h <RDS_HOST> -u <USER> -p<PASSWORD> \
  <DATABASE> -e "CREATE TABLE IF NOT EXISTS users (...);"
```

### 예방법 (자동화)

**1. Init Container 사용**
```yaml
spec:
  initContainers:
    - name: db-migrate
      image: <app-image>
      command: ["yarn", "typeorm:run"]
      env:
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: db-credentials
              key: url
  containers:
    - name: app
      image: <app-image>
```

**2. Kubernetes Job**
```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: db-migration
  annotations:
    argocd.argoproj.io/hook: PreSync  # ArgoCD 사용 시
spec:
  template:
    spec:
      containers:
        - name: migration
          image: <app-image>
          command: ["yarn", "typeorm:run"]
      restartPolicy: Never
  backoffLimit: 3
```

**3. Helm Hook**
```yaml
apiVersion: batch/v1
kind: Job
metadata:
  annotations:
    "helm.sh/hook": pre-install,pre-upgrade
    "helm.sh/hook-weight": "-5"
```

---

## 6. Kubernetes Health Check 엔드포인트 불일치

### 재발 위험도: 🔴 높음

### 증상
```
Liveness probe failed: HTTP probe failed with statuscode: 404
```
Pod가 계속 재시작됨

### 원인
Deployment의 `livenessProbe`/`readinessProbe`가 `/health` 경로로 설정되어 있으나,
실제 앱에 해당 엔드포인트가 없음

### 임시 해결 (TCP 소켓)
```bash
kubectl patch deployment <name> -n <namespace> --type='json' -p='[
  {"op": "replace", "path": "/spec/template/spec/containers/0/livenessProbe",
   "value": {"tcpSocket": {"port": 3000}, "initialDelaySeconds": 30, "periodSeconds": 10}},
  {"op": "replace", "path": "/spec/template/spec/containers/0/readinessProbe",
   "value": {"tcpSocket": {"port": 3000}, "initialDelaySeconds": 10, "periodSeconds": 5}}
]'
```

### 올바른 해결 (앱 코드)

**NestJS**
```typescript
// health.controller.ts
import { Controller, Get } from '@nestjs/common';
import { HealthCheck, HealthCheckService, TypeOrmHealthIndicator } from '@nestjs/terminus';

@Controller('health')
export class HealthController {
  constructor(
    private health: HealthCheckService,
    private db: TypeOrmHealthIndicator,
  ) {}

  @Get()
  @HealthCheck()
  check() {
    return this.health.check([
      () => this.db.pingCheck('database'),
    ]);
  }

  @Get('live')
  live() {
    return { status: 'ok' };
  }

  @Get('ready')
  ready() {
    return this.health.check([
      () => this.db.pingCheck('database'),
    ]);
  }
}
```

**Express**
```typescript
app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

app.get('/health/ready', async (req, res) => {
  try {
    await db.query('SELECT 1');
    res.json({ status: 'ok' });
  } catch (e) {
    res.status(503).json({ status: 'error' });
  }
});
```

**Spring Boot**
```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health
  endpoint:
    health:
      probes:
        enabled: true
```

### 예방법
- 프로젝트 템플릿에 health check 엔드포인트 기본 포함
- K8s manifest와 앱 코드의 경로 일치 확인

---

## 7. Kafka/Redpanda advertise 주소 미설정

### 재발 위험도: 🟡 중간 (Kafka 사용 시)

### 증상
```
broker replied that the controller broker is 0, but did not reply with that broker in the broker list
```

### 원인
Kafka/Redpanda의 `advertise` 주소가 설정되지 않아 클라이언트가 올바른 브로커 주소를 받지 못함

### 해결
```bash
# Redpanda
rpk redpanda start \
  --kafka-addr PLAINTEXT://0.0.0.0:9092 \
  --advertise-kafka-addr PLAINTEXT://<ACTUAL_IP>:9092
```

### Docker Compose
```yaml
services:
  redpanda:
    command:
      - redpanda start
      - --kafka-addr PLAINTEXT://0.0.0.0:9092
      - --advertise-kafka-addr PLAINTEXT://${HOST_IP}:9092
```

### Kubernetes
```yaml
env:
  - name: POD_IP
    valueFrom:
      fieldRef:
        fieldPath: status.podIP
  - name: KAFKA_ADVERTISED_LISTENERS
    value: "PLAINTEXT://$(POD_IP):9092"
```

---

## 8. Prometheus CRD 미설치

### 재발 위험도: 🟢 낮음

### 증상
```
no matches for kind "PrometheusRule" in version "monitoring.coreos.com/v1"
no matches for kind "ServiceMonitor" in version "monitoring.coreos.com/v1"
```

### 영향
- 핵심 리소스(Deployment, Service 등)는 정상 생성
- 모니터링 리소스만 생성 실패
- 개발 환경에서는 무시 가능

### 해결 (프로덕션)
```bash
# Prometheus Operator 설치
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install prometheus prometheus-community/kube-prometheus-stack \
  --namespace monitoring --create-namespace
```

### 예방법
- 모니터링 리소스는 별도 Kustomize overlay로 분리
- 조건부 적용: `kustomize build overlays/dev` vs `overlays/prod-with-monitoring`

---

## CDK 베스트 프랙티스 체크리스트

### 배포 전 확인

**인프라 설정**
- [ ] RDS 인스턴스 클래스에 `db.` 접두사 없음
- [ ] Security Group에 VPC CIDR 규칙 포함
- [ ] 서브넷 라우팅 테이블 확인

**Docker/컨테이너**
- [ ] 이미지 아키텍처 확인 (linux/amd64)
- [ ] `imagePullPolicy` 설정 확인
- [ ] ECR 권한 확인

**애플리케이션**
- [ ] Health check 엔드포인트 구현됨
- [ ] ConfigMap/Secret 값 모두 설정됨
- [ ] 환경변수 검증 로직 있음

**데이터베이스**
- [ ] 마이그레이션 자동화 구성됨
- [ ] 연결 테스트 완료

### 디버깅 명령어

```bash
# Pod 로그
kubectl logs -f <pod-name> -n <namespace>

# Pod 상태 상세
kubectl describe pod <pod-name> -n <namespace>

# 네트워크 연결 테스트
kubectl run debug --image=busybox --rm -it --restart=Never \
  -- nc -zv <host> <port>

# DNS 확인
kubectl run debug --image=busybox --rm -it --restart=Never \
  -- nslookup <hostname>

# ConfigMap 확인
kubectl get configmap <name> -n <namespace> -o yaml

# Secret 확인 (base64 디코딩)
kubectl get secret <name> -n <namespace> \
  -o jsonpath='{.data.<key>}' | base64 -d

# EKS 노드 Security Group 확인
aws ec2 describe-instances \
  --filters "Name=tag:eks:cluster-name,Values=<CLUSTER>" \
  --query "Reservations[].Instances[].SecurityGroups[]"
```

---

## 문제 해결 요약

| # | 문제 | 재발 위험 | 근본 해결책 |
|---|------|:---:|------------|
| 1 | RDS 인스턴스 클래스 | 🔴 | 설정 컨벤션 문서화 |
| 2 | Docker 아키텍처 | 🔴 | CI/CD에서만 빌드 |
| 3 | Security Group 불일치 | 🔴🔴 | VPC CIDR 규칙 기본 추가 |
| 4 | ConfigMap/Secret 미설정 | 🟡 | External Secrets 사용 |
| 5 | DB 마이그레이션 | 🔴 | Init Container/Job 자동화 |
| 6 | Health Check 404 | 🔴 | 앱 코드에 엔드포인트 구현 |
| 7 | Kafka advertise | 🟡 | advertise 주소 명시 |
| 8 | Prometheus CRD | 🟢 | 별도 overlay로 분리 |

---

## 관련 문서

- [AWS CDK Best Practices](https://docs.aws.amazon.com/cdk/v2/guide/best-practices.html)
- [EKS Best Practices Guide](https://aws.github.io/aws-eks-best-practices/)
- [Kubernetes Troubleshooting](https://kubernetes.io/docs/tasks/debug/)

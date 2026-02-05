# Future Backend 2000 TPS 달성 업그레이드 계획

## 현재 상태

| 지표 | 현재값 | 목표값 |
|------|--------|--------|
| TPS | 357 (5 Pods) | 2,000 |
| 실패율 | 39.74% | < 1% |
| Median RT | 154ms | < 100ms |
| Pods | 5 | 10-15 |

### 문제 분석

**39% 실패의 근본 원인**: DB Connection 고갈

```
현재 상황:
- RDS db.t3.large max_connections: ~90 (기본값)
- 각 Pod의 connection pool: 50 (master) + 50 (report) = 100
- 5 Pods 요청: 5 × 100 = 500 connections
- 실제 가용: 90 connections
→ 500 - 90 = 410 connections 부족 → 39% 실패
```

---

## Phase 1: DB Connection Pool 최적화 (즉시 적용)

### 1.1 Pod당 Connection 수 축소

현재 50개 → 10개로 축소 (더 많은 Pod가 연결 가능)

**파일**: `future-backend/src/configs/database.config.ts`

```typescript
// Before
extra: {
  connectionLimit: 50,
  queueLimit: 0,
  waitForConnections: true,
}

// After
extra: {
  connectionLimit: 10,           // 50 → 10 축소
  queueLimit: 100,               // 0 → 100 제한 (무한 대기 방지)
  waitForConnections: true,
  connectTimeout: 10000,         // 10초 타임아웃 추가
  acquireTimeout: 10000,         // 연결 획득 타임아웃
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
}
```

**효과**:
- 10 Pods × 10 connections × 2 (master/report) = 200 connections
- RDS max_connections 250으로 설정하면 충분

### 1.2 환경변수 기반 설정 (권장)

**파일**: `future-backend/config/default.yml`

```yaml
database:
  connectionLimit: ${DB_CONNECTION_LIMIT:10}
  queueLimit: ${DB_QUEUE_LIMIT:100}
  connectTimeout: ${DB_CONNECT_TIMEOUT:10000}
```

**K8s ConfigMap 패치** (`k8s/overlays/dev/configmap-patch.yaml`):

```yaml
database:
  connectionLimit: 10
  queueLimit: 100
  connectTimeout: 10000
```

---

## Phase 2: RDS 업그레이드 및 설정 변경

### 2.1 Parameter Group 수정 (비용 없음)

**현재 RDS**: `exchange-cicd-dev-mysql` (db.t3.large)

```bash
# 현재 max_connections 확인
AWS_PROFILE=critonex aws rds describe-db-parameters \
  --db-parameter-group-name default.mysql8.0 \
  --region ap-northeast-2 \
  --query "Parameters[?ParameterName=='max_connections']"
```

**Parameter Group 생성 및 적용**:

```bash
# 1. 커스텀 파라미터 그룹 생성
AWS_PROFILE=critonex aws rds create-db-parameter-group \
  --db-parameter-group-name exchange-dev-optimized \
  --db-parameter-group-family mysql8.0 \
  --description "Optimized for 2000 TPS" \
  --region ap-northeast-2

# 2. max_connections 설정 (250 → 더 여유있게)
AWS_PROFILE=critonex aws rds modify-db-parameter-group \
  --db-parameter-group-name exchange-dev-optimized \
  --parameters "ParameterName=max_connections,ParameterValue=300,ApplyMethod=immediate" \
  --region ap-northeast-2

# 3. RDS 인스턴스에 파라미터 그룹 적용
AWS_PROFILE=critonex aws rds modify-db-instance \
  --db-instance-identifier exchange-cicd-dev-mysql \
  --db-parameter-group-name exchange-dev-optimized \
  --apply-immediately \
  --region ap-northeast-2
```

### 2.2 RDS 인스턴스 업그레이드 (2000+ TPS용)

| 인스턴스 | vCPU | RAM | max_connections | 월 비용 | 추천 TPS |
|----------|------|-----|-----------------|---------|----------|
| db.t3.large | 2 | 8GB | ~90 (기본) | $75 | 500 |
| **db.r6g.large** | 2 | 16GB | ~600 | $140 | 1,500 |
| **db.r6g.xlarge** | 4 | 32GB | ~1,200 | $280 | 3,000 |

**권장**: `db.r6g.large` (메모리 2배, 비용 1.9배)

```bash
# RDS 인스턴스 업그레이드
AWS_PROFILE=critonex aws rds modify-db-instance \
  --db-instance-identifier exchange-cicd-dev-mysql \
  --db-instance-class db.r6g.large \
  --apply-immediately \
  --region ap-northeast-2
```

---

## Phase 3: HPA 확장 및 Pod 리소스 조정

### 3.1 HPA 설정 변경

**파일**: `future-backend/k8s/base/hpa.yaml`

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: future-backend-hpa
spec:
  scaleTargetRef:
    kind: Deployment
    name: future-backend

  # 스케일 범위 확장
  minReplicas: 3          # 2 → 3 (고가용성)
  maxReplicas: 15         # 5 → 15 (2000 TPS 지원)

  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 60    # 70 → 60 (더 빠른 스케일업)

  # 더 공격적인 스케일업
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 15   # 30 → 15초
      policies:
      - type: Pods
        value: 4                        # 2 → 4개씩 추가
        periodSeconds: 15
      - type: Percent
        value: 100                      # 100% 증가 허용
        periodSeconds: 15
      selectPolicy: Max

    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
      - type: Pods
        value: 1
        periodSeconds: 60
```

### 3.2 Pod 리소스 증가

**파일**: `future-backend/k8s/overlays/dev/kustomization.yaml` (패치)

```yaml
# Dev 환경 리소스 증가
resources:
  requests:
    cpu: "250m"       # 100m → 250m
    memory: "512Mi"   # 256Mi → 512Mi
  limits:
    cpu: "1000m"      # 500m → 1000m
    memory: "1Gi"     # 512Mi → 1Gi
```

### 3.3 EKS 노드 그룹 확장

현재 3-4개 노드 → 6-8개 필요

```bash
# 노드 그룹 스케일 업
AWS_PROFILE=critonex aws eks update-nodegroup-config \
  --cluster-name exchange-cicd-dev \
  --nodegroup-name ng-spot-1 \
  --scaling-config minSize=4,maxSize=10,desiredSize=6 \
  --region ap-northeast-2
```

---

## Phase 4: Connection Pooler 도입 (선택사항)

### 4.1 ProxySQL 배포 (Kubernetes)

더 많은 Pod가 필요하거나 connection 관리가 복잡해지면 ProxySQL 도입 권장.

**ProxySQL 아키텍처**:
```
[Pod 1] ─┐
[Pod 2] ─┼─→ [ProxySQL] ─→ [RDS]
[Pod 3] ─┤      (300 connections 공유)
  ...    │
[Pod N] ─┘
```

**장점**:
- Connection Multiplexing (수백 개 앱 연결 → 수십 개 DB 연결)
- Query 캐싱
- 자동 페일오버

**파일**: `future-backend/k8s/base/proxysql.yaml`

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: proxysql
spec:
  replicas: 2
  selector:
    matchLabels:
      app: proxysql
  template:
    metadata:
      labels:
        app: proxysql
    spec:
      containers:
      - name: proxysql
        image: proxysql/proxysql:2.5.5
        ports:
        - containerPort: 6033  # MySQL 프로토콜
        - containerPort: 6032  # Admin
        env:
        - name: MYSQL_HOST
          value: "exchange-cicd-dev-mysql.cv882g4ue5py.ap-northeast-2.rds.amazonaws.com"
        - name: MYSQL_USER
          valueFrom:
            secretKeyRef:
              name: db-credentials
              key: username
        - name: MYSQL_PASSWORD
          valueFrom:
            secretKeyRef:
              name: db-credentials
              key: password
        resources:
          requests:
            cpu: "100m"
            memory: "256Mi"
          limits:
            cpu: "500m"
            memory: "512Mi"
---
apiVersion: v1
kind: Service
metadata:
  name: proxysql
spec:
  selector:
    app: proxysql
  ports:
  - port: 3306
    targetPort: 6033
```

**Backend ConfigMap 변경** (ProxySQL 사용 시):

```yaml
_master:
  host: "proxysql.future-backend-dev.svc.cluster.local"  # ProxySQL로 변경
  port: 3306
```

---

## 구현 순서 및 예상 비용

### 단계별 구현

| 단계 | 작업 | 소요시간 | 다운타임 | 예상 TPS |
|------|------|----------|----------|----------|
| **1** | Connection Pool 축소 (10개) | 10분 | 재배포 | 500 |
| **2** | RDS max_connections 300 | 5분 | 없음 | 800 |
| **3** | HPA maxReplicas 15 | 5분 | 없음 | 1,200 |
| **4** | Pod 리소스 증가 | 10분 | 재배포 | 1,500 |
| **5** | RDS db.r6g.large 업그레이드 | 15분 | 자동 | 2,000 |
| **6** | (선택) ProxySQL 도입 | 1시간 | 없음 | 3,000+ |

### 월간 비용 변화

| 항목 | 현재 | 목표 | 차이 |
|------|------|------|------|
| RDS | $75 (t3.large) | $140 (r6g.large) | +$65 |
| EKS 노드 | ~$150 (4개) | ~$225 (6개) | +$75 |
| **총 증가** | | | **+$140/월** |

---

## 즉시 실행 가능한 명령어

### Step 1: Connection Pool 최적화 적용

```bash
cd future-backend

# 1. database.config.ts 수정 후
# 2. 이미지 빌드 및 푸시
docker build -t 990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:main .
docker push 990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:main

# 3. 재배포
kubectl rollout restart deployment/dev-future-backend -n future-backend-dev
```

### Step 2: RDS Parameter Group 설정

```bash
# 파라미터 그룹 생성 + max_connections 설정
AWS_PROFILE=critonex aws rds create-db-parameter-group \
  --db-parameter-group-name exchange-dev-optimized \
  --db-parameter-group-family mysql8.0 \
  --description "Optimized for high TPS" \
  --region ap-northeast-2

AWS_PROFILE=critonex aws rds modify-db-parameter-group \
  --db-parameter-group-name exchange-dev-optimized \
  --parameters "ParameterName=max_connections,ParameterValue=300,ApplyMethod=immediate" \
  --region ap-northeast-2

AWS_PROFILE=critonex aws rds modify-db-instance \
  --db-instance-identifier exchange-cicd-dev-mysql \
  --db-parameter-group-name exchange-dev-optimized \
  --apply-immediately \
  --region ap-northeast-2
```

### Step 3: HPA 업데이트

```bash
# k8s/base/hpa.yaml 수정 후
kubectl apply -k future-backend/k8s/overlays/dev
```

---

## 모니터링 체크리스트

테스트 중 확인할 메트릭:

```bash
# 1. Pod 상태
kubectl get pods -n future-backend-dev -w

# 2. HPA 상태
kubectl get hpa -n future-backend-dev -w

# 3. RDS 연결 수 (CloudWatch)
aws cloudwatch get-metric-statistics \
  --namespace AWS/RDS \
  --metric-name DatabaseConnections \
  --dimensions Name=DBInstanceIdentifier,Value=exchange-cicd-dev-mysql \
  --start-time $(date -u -v-10M +%Y-%m-%dT%H:%M:%SZ) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%SZ) \
  --period 60 \
  --statistics Maximum \
  --region ap-northeast-2

# 4. k6 테스트 재실행
k6 run -e BASE_URL=https://f-api.borntobit.com \
  -e TOKEN="$JWT_TOKEN" \
  test/performance/order-tps-test.js
```

---

## 요약

| 변경 사항 | 효과 | 비용 |
|-----------|------|------|
| Connection Pool 50→10 | 더 많은 Pod 지원 | 무료 |
| RDS max_connections 300 | 연결 고갈 방지 | 무료 |
| HPA maxReplicas 15 | 자동 스케일링 | 무료 |
| RDS r6g.large | 성능 2배 | +$65/월 |
| 노드 4→6개 | Pod 수용 | +$75/월 |

**예상 결과**: 2,000 TPS 달성, 실패율 < 1%, 월 $140 추가

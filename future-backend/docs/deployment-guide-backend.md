# Future Backend 배포 가이드

## 개요

NestJS 기반 Future Exchange Backend 서버의 배포 가이드입니다.

---

## 1. 사전 요구사항

### 1.1 인프라

| 구성요소 | 버전 | 용도 |
|---------|------|------|
| Node.js | 14.17.0+ | 런타임 |
| MySQL | 8.x | 데이터베이스 (Master/Report) |
| Redis | 6.x | 캐싱, 세션 |
| Kafka | 3.x | 메시지 브로커 |

### 1.2 환경변수

```bash
# 필수 환경변수
NODE_ENV=production
PORT=3000

# Database
DB_HOST=mysql-master.example.com
DB_PORT=3306
DB_USERNAME=future_user
DB_PASSWORD=<secret>
DB_DATABASE=future_exchange

# Report DB (읽기 전용)
REPORT_DB_HOST=mysql-report.example.com

# Redis
REDIS_HOST=redis.example.com
REDIS_PORT=6379

# Kafka
KAFKA_BROKERS=kafka1:9092,kafka2:9092,kafka3:9092

# Sharding (선택)
SHARDING_ENABLED=false
```

---

## 2. 빌드

### 2.1 로컬 빌드

```bash
# 의존성 설치
yarn install --frozen-lockfile

# TypeScript 컴파일
yarn build

# 빌드 결과 확인
ls -la dist/
```

### 2.2 Docker 이미지 빌드

```bash
# 이미지 빌드
docker build -t future-backend:latest .

# 태그 지정
docker tag future-backend:latest your-registry/future-backend:v1.0.0

# 레지스트리 Push
docker push your-registry/future-backend:v1.0.0
```

### 2.3 Dockerfile 예시

```dockerfile
FROM node:14.17-alpine AS builder
WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY . .
RUN yarn build

FROM node:14.17-alpine
WORKDIR /app
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./
EXPOSE 3000
CMD ["node", "dist/main.js"]
```

---

## 3. 배포 방법

### 3.1 Docker Compose (개발/스테이징)

```yaml
# docker-compose.yml
version: '3.8'
services:
  backend:
    image: future-backend:latest
    ports:
      - "3000:3000"
    environment:
      - NODE_ENV=production
      - DB_HOST=mysql
      - REDIS_HOST=redis
      - KAFKA_BROKERS=kafka:9092
    depends_on:
      - mysql
      - redis
      - kafka
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
```

```bash
# 실행
docker-compose up -d

# 로그 확인
docker-compose logs -f backend
```

### 3.2 Kubernetes (프로덕션)

#### Deployment

```yaml
# k8s/backend-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: future-backend
  namespace: future-exchange
spec:
  replicas: 3
  selector:
    matchLabels:
      app: future-backend
  template:
    metadata:
      labels:
        app: future-backend
    spec:
      containers:
      - name: backend
        image: your-registry/future-backend:v1.0.0
        ports:
        - containerPort: 3000
        env:
        - name: NODE_ENV
          value: "production"
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: backend-secrets
              key: db-password
        envFrom:
        - configMapRef:
            name: backend-config
        resources:
          requests:
            memory: "512Mi"
            cpu: "500m"
          limits:
            memory: "1Gi"
            cpu: "1000m"
        livenessProbe:
          httpGet:
            path: /health
            port: 3000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 3000
          initialDelaySeconds: 5
          periodSeconds: 5
```

#### Service

```yaml
# k8s/backend-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: future-backend
  namespace: future-exchange
spec:
  selector:
    app: future-backend
  ports:
  - port: 80
    targetPort: 3000
  type: ClusterIP
```

#### ConfigMap

```yaml
# k8s/backend-configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: backend-config
  namespace: future-exchange
data:
  DB_HOST: "mysql-master.future-exchange.svc.cluster.local"
  DB_PORT: "3306"
  DB_DATABASE: "future_exchange"
  REDIS_HOST: "redis.future-exchange.svc.cluster.local"
  REDIS_PORT: "6379"
  KAFKA_BROKERS: "kafka-0:9092,kafka-1:9092,kafka-2:9092"
  SHARDING_ENABLED: "false"
```

#### 배포 명령어

```bash
# ConfigMap/Secret 적용
kubectl apply -f k8s/backend-configmap.yaml
kubectl apply -f k8s/backend-secrets.yaml

# 배포
kubectl apply -f k8s/backend-deployment.yaml
kubectl apply -f k8s/backend-service.yaml

# 상태 확인
kubectl get pods -l app=future-backend
kubectl rollout status deployment/future-backend
```

---

## 4. 샤딩 활성화 배포

### 4.1 샤딩 설정

```yaml
# config/production.yml 또는 환경변수
sharding:
  enabled: true
  shard1:
    symbols: "BTCUSDT,BTCBUSD,BTCUSDC"
    inputTopic: "matching-engine-shard-1-input"
  shard2:
    symbols: "ETHUSDT,ETHBUSD,ETHUSDC"
    inputTopic: "matching-engine-shard-2-input"
  shard3:
    symbols: ""
    inputTopic: "matching-engine-shard-3-input"
```

### 4.2 카나리 배포

```bash
# Step 1: 5% 트래픽
kubectl apply -f k8s/canary/backend-canary-5.yaml

# 모니터링 (15분)
kubectl logs -l app=future-backend,version=canary --tail=100

# Step 2: 25% 트래픽
kubectl apply -f k8s/canary/backend-canary-25.yaml

# Step 3: 50% 트래픽
kubectl apply -f k8s/canary/backend-canary-50.yaml

# Step 4: 100% 전환
kubectl apply -f k8s/production/backend-full.yaml
```

### 4.3 롤백 (샤딩 비활성화)

```bash
# 긴급 롤백
kubectl set env deployment/future-backend SHARDING_ENABLED=false
kubectl rollout restart deployment/future-backend

# 확인
kubectl rollout status deployment/future-backend
```

---

## 5. 데이터베이스 마이그레이션

### 5.1 마이그레이션 실행

```bash
# 마이그레이션 상태 확인
yarn typeorm:show

# 마이그레이션 실행
yarn typeorm:run

# 롤백 (필요시)
yarn typeorm:revert
```

### 5.2 프로덕션 마이그레이션

```bash
# K8s Job으로 실행
kubectl apply -f k8s/jobs/migration-job.yaml

# 로그 확인
kubectl logs job/db-migration
```

---

## 6. 헬스체크 & 모니터링

### 6.1 헬스체크 엔드포인트

| 엔드포인트 | 용도 |
|-----------|------|
| `GET /health` | 기본 헬스체크 |
| `GET /health/live` | Liveness probe |
| `GET /health/ready` | Readiness probe |

### 6.2 모니터링 메트릭

```bash
# Prometheus 메트릭
curl http://localhost:3000/metrics
```

### 6.3 로그 확인

```bash
# Docker
docker logs -f future-backend

# Kubernetes
kubectl logs -f deployment/future-backend

# 특정 Pod
kubectl logs -f future-backend-xxx-yyy
```

---

## 7. 워커 프로세스 배포

### 7.1 워커 목록

| 워커 | 명령어 | 용도 |
|-----|-------|------|
| matching-engine:load | `yarn console:dev matching-engine:load` | 매칭 엔진 초기화 |
| matching-engine:notify | `yarn console:dev matching-engine:notify` | WebSocket 알림 |
| funding:pay | `yarn console:dev funding:pay` | 펀딩비 지급 |

### 7.2 Kubernetes CronJob (펀딩비)

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: funding-pay
spec:
  schedule: "0 */8 * * *"  # 8시간마다
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: funding
            image: your-registry/future-backend:v1.0.0
            command: ["yarn", "console:dev", "funding:pay"]
          restartPolicy: OnFailure
```

---

## 8. 트러블슈팅

### 8.1 일반적인 문제

| 문제 | 원인 | 해결 |
|-----|------|------|
| DB 연결 실패 | 네트워크/인증 | 환경변수 확인, 방화벽 확인 |
| Kafka 연결 실패 | 브로커 주소 | KAFKA_BROKERS 확인 |
| 메모리 부족 | 힙 크기 | NODE_OPTIONS="--max-old-space-size=4096" |
| 타임아웃 | 느린 쿼리 | DB 인덱스 확인 |

### 8.2 로그 레벨 변경

```bash
# 환경변수로 설정
LOG_LEVEL=debug

# 런타임 변경 (지원시)
curl -X POST http://localhost:3000/admin/log-level -d '{"level":"debug"}'
```

---

## 9. 체크리스트

### 배포 전

- [ ] 환경변수 설정 완료
- [ ] DB 마이그레이션 완료
- [ ] Docker 이미지 빌드 및 Push
- [ ] ConfigMap/Secret 업데이트
- [ ] 헬스체크 엔드포인트 확인

### 배포 후

- [ ] Pod 상태 확인 (Running)
- [ ] 헬스체크 통과
- [ ] 로그 에러 없음
- [ ] API 응답 정상
- [ ] 메트릭 수집 정상

---

## 부록: 유용한 명령어

```bash
# Pod 상태 확인
kubectl get pods -l app=future-backend -w

# 리소스 사용량
kubectl top pods -l app=future-backend

# 이전 버전으로 롤백
kubectl rollout undo deployment/future-backend

# 스케일 조정
kubectl scale deployment/future-backend --replicas=5

# 로그 (모든 Pod)
kubectl logs -l app=future-backend --all-containers=true -f

# Pod 재시작
kubectl rollout restart deployment/future-backend
```

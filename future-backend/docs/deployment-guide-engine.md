# Future Engine (매칭 엔진) 배포 가이드

## 개요

Java 17 기반 고성능 주문 매칭 엔진의 배포 가이드입니다.
단일 인스턴스 배포와 샤딩된 다중 인스턴스 배포 방법을 모두 다룹니다.

---

## 1. 사전 요구사항

### 1.1 인프라

| 구성요소 | 버전 | 용도 |
|---------|------|------|
| Java | 17+ | 런타임 |
| Maven | 3.8+ | 빌드 |
| Kafka | 3.x | 메시지 브로커 |

### 1.2 시스템 요구사항

| 환경 | CPU | Memory | Disk |
|-----|-----|--------|------|
| 개발 | 2 cores | 4GB | 10GB |
| 스테이징 | 4 cores | 8GB | 20GB |
| 프로덕션 (샤드당) | 4-8 cores | 8-16GB | 50GB |

### 1.3 Kafka 토픽

#### 단일 인스턴스
```
matching_engine_input    # 주문 입력
matching_engine_output   # 체결 결과
orderbook_output         # 오더북 업데이트
```

#### 샤딩 모드 (3샤드)
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

---

## 2. 빌드

### 2.1 로컬 빌드

```bash
cd future-engine

# 클린 빌드
mvn clean package -DskipTests

# 테스트 포함 빌드
mvn clean verify

# 빌드 결과
ls -la target/MatchingEngine-1.0-shaded.jar
```

### 2.2 Docker 이미지 빌드 (단일)

```bash
# 기본 이미지 빌드
docker build -t matching-engine:latest .

# 레지스트리 Push
docker tag matching-engine:latest your-registry/matching-engine:v1.0.0
docker push your-registry/matching-engine:v1.0.0
```

### 2.3 Docker 이미지 빌드 (샤딩)

```bash
# 샤딩 이미지 빌드
./scripts/build-shard-image.sh -t v1.0.0

# 또는 직접
docker build -f Dockerfile.shard -t matching-engine-shard:v1.0.0 .

# 레지스트리 Push
docker push your-registry/matching-engine-shard:v1.0.0
```

---

## 3. 단일 인스턴스 배포

### 3.1 직접 실행

```bash
# 환경변수 설정
export KAFKA_BOOTSTRAP_SERVERS=localhost:9092
export ENGINE_MODE=single

# JAR 실행
java -Xms4g -Xmx8g \
  -XX:+UseG1GC \
  -XX:MaxGCPauseMillis=50 \
  -jar target/MatchingEngine-1.0-shaded.jar
```

### 3.2 Docker Compose

```yaml
# docker-compose.yml
version: '3.8'
services:
  matching-engine:
    image: matching-engine:latest
    environment:
      - KAFKA_BOOTSTRAP_SERVERS=kafka:9092
      - JAVA_OPTS=-Xms4g -Xmx8g -XX:+UseG1GC
    depends_on:
      - kafka
    deploy:
      resources:
        limits:
          memory: 10G
        reservations:
          memory: 8G
```

### 3.3 Kubernetes

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: matching-engine
  namespace: future-exchange
spec:
  replicas: 1
  selector:
    matchLabels:
      app: matching-engine
  template:
    metadata:
      labels:
        app: matching-engine
    spec:
      containers:
      - name: engine
        image: your-registry/matching-engine:v1.0.0
        env:
        - name: KAFKA_BOOTSTRAP_SERVERS
          value: "kafka:9092"
        - name: JAVA_OPTS
          value: "-Xms4g -Xmx8g -XX:+UseG1GC -XX:MaxGCPauseMillis=50"
        resources:
          requests:
            memory: "8Gi"
            cpu: "4"
          limits:
            memory: "12Gi"
            cpu: "8"
        ports:
        - containerPort: 8080
          name: health
        - containerPort: 9090
          name: metrics
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 60
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health/ready
            port: 8080
          initialDelaySeconds: 30
          periodSeconds: 5
```

---

## 4. 샤딩 배포 (프로덕션 권장)

### 4.1 샤드 구성

| 샤드 | 심볼 | Memory | 설명 |
|-----|------|--------|------|
| shard-1 | BTCUSDT, BTCBUSD, BTCUSDC | 8-12GB | 최고 트래픽 |
| shard-2 | ETHUSDT, ETHBUSD, ETHUSDC | 6-8GB | 높은 트래픽 |
| shard-3 | 기타 모든 심볼 | 4-6GB | 기본 샤드 |

### 4.2 Kafka 토픽 생성

```bash
# 토픽 생성 스크립트 실행
./scripts/create-shard-topics.sh \
  --bootstrap prod-kafka:9092 \
  --partitions 6 \
  --replication 2
```

### 4.3 Docker Compose (개발/스테이징)

```bash
# 3샤드 실행
docker-compose -f docker-compose-sharded.yml up -d

# 상태 확인
docker-compose -f docker-compose-sharded.yml ps

# 로그 확인
docker-compose -f docker-compose-sharded.yml logs -f shard-1-primary
```

### 4.4 Kubernetes (프로덕션)

#### StatefulSet (샤드 1 예시)

```yaml
# k8s/shard-1-statefulset.yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: shard-1
  namespace: matching-engine
spec:
  serviceName: shard-1
  replicas: 2  # Primary + Standby
  selector:
    matchLabels:
      shard: shard-1
  template:
    metadata:
      labels:
        shard: shard-1
        app: matching-engine
    spec:
      affinity:
        podAntiAffinity:
          requiredDuringSchedulingIgnoredDuringExecution:
          - labelSelector:
              matchLabels:
                shard: shard-1
            topologyKey: kubernetes.io/hostname
      containers:
      - name: engine
        image: your-registry/matching-engine-shard:v1.0.0
        args:
        - "--shard-id=shard-1"
        - "--symbols=BTCUSDT,BTCBUSD,BTCUSDC"
        - "--kafka-bootstrap=kafka:9092"
        env:
        - name: POD_NAME
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        - name: SHARD_ROLE
          value: "PRIMARY"  # 첫 번째 Pod, 나머지는 STANDBY
        - name: JAVA_OPTS
          value: "-Xms8g -Xmx12g -XX:+UseG1GC"
        resources:
          requests:
            memory: "10Gi"
            cpu: "4"
          limits:
            memory: "14Gi"
            cpu: "8"
        ports:
        - containerPort: 8080
          name: health
        - containerPort: 9090
          name: metrics
        volumeMounts:
        - name: data
          mountPath: /data
  volumeClaimTemplates:
  - metadata:
      name: data
    spec:
      accessModes: ["ReadWriteOnce"]
      resources:
        requests:
          storage: 50Gi
```

#### 배포 스크립트

```bash
# 개발 환경
./scripts/deploy-k8s.sh dev

# 프로덕션 환경
./scripts/deploy-k8s.sh prod

# Dry-run 확인
./scripts/deploy-k8s.sh prod --dry-run
```

#### 배포 순서

```bash
# 1. ConfigMap/Secret 적용
kubectl apply -f k8s/base/configmap.yaml
kubectl apply -f k8s/base/secret.yaml

# 2. 샤드 순차 배포
kubectl apply -f k8s/base/shard-1-statefulset.yaml
kubectl rollout status statefulset/shard-1 -n matching-engine

kubectl apply -f k8s/base/shard-2-statefulset.yaml
kubectl rollout status statefulset/shard-2 -n matching-engine

kubectl apply -f k8s/base/shard-3-statefulset.yaml
kubectl rollout status statefulset/shard-3 -n matching-engine

# 3. 서비스 적용
kubectl apply -f k8s/base/services.yaml
```

---

## 5. Primary/Standby 전환

### 5.1 자동 Failover

Standby가 Primary 헬스체크 실패 감지 시 자동 승격:

```
Primary 다운 → Standby 감지 (5초) → 승격 (10초) → 서비스 복구
```

### 5.2 수동 전환

```bash
# Primary → Standby 강제 전환
curl -X POST http://shard-1-primary:8080/admin/demote

# Standby → Primary 승격
curl -X POST http://shard-1-standby:8080/admin/promote

# 상태 확인
curl http://shard-1-primary:8080/health
```

---

## 6. 모니터링

### 6.1 헬스체크 엔드포인트

| 엔드포인트 | 용도 |
|-----------|------|
| `GET /health` | 전체 상태 |
| `GET /health/live` | Liveness |
| `GET /health/ready` | Readiness |
| `GET /metrics` | Prometheus 메트릭 |

### 6.2 주요 메트릭

```
# 처리량
matching_engine_orders_processed_total
matching_engine_trades_executed_total

# 지연시간
matching_engine_order_latency_seconds

# 오더북
matching_engine_orderbook_depth{side="bid|ask"}

# JVM
jvm_memory_used_bytes
jvm_gc_pause_seconds
```

### 6.3 Grafana 대시보드

```bash
# 대시보드 import
kubectl apply -f monitoring/dashboards/

# 대시보드 목록:
# - matching-engine-overview.json
# - matching-engine-jvm.json
# - matching-engine-orders.json
```

---

## 7. 롤백

### 7.1 이전 버전으로 롤백

```bash
# StatefulSet 롤백
kubectl rollout undo statefulset/shard-1 -n matching-engine
kubectl rollout undo statefulset/shard-2 -n matching-engine
kubectl rollout undo statefulset/shard-3 -n matching-engine

# 특정 리비전으로 롤백
kubectl rollout undo statefulset/shard-1 --to-revision=2
```

### 7.2 긴급 롤백 스크립트

```bash
# 대화형 롤백
./scripts/rollback.sh

# 긴급 롤백 (자동)
./scripts/emergency-rollback.sh

# 롤백 검증
./scripts/verify-rollback.sh
```

---

## 8. 트러블슈팅

### 8.1 일반적인 문제

| 문제 | 원인 | 해결 |
|-----|------|------|
| OOM Killed | 메모리 부족 | Xmx 증가, 리소스 limits 조정 |
| Kafka 연결 실패 | 브로커 주소 | KAFKA_BOOTSTRAP_SERVERS 확인 |
| GC Pause 길어짐 | 힙 크기/GC 설정 | G1GC 튜닝, MaxGCPauseMillis 조정 |
| Standby 동기화 지연 | 네트워크/부하 | sync 토픽 파티션 확인 |

### 8.2 로그 확인

```bash
# Docker
docker logs -f shard-1-primary

# Kubernetes
kubectl logs -f shard-1-0 -n matching-engine

# 특정 시간 범위
kubectl logs shard-1-0 --since=1h
```

### 8.3 JVM 디버깅

```bash
# 힙 덤프
kubectl exec shard-1-0 -- jmap -dump:format=b,file=/tmp/heap.hprof 1

# 스레드 덤프
kubectl exec shard-1-0 -- jstack 1 > thread_dump.txt

# GC 로그 확인
kubectl exec shard-1-0 -- cat /var/log/gc.log
```

---

## 9. JVM 튜닝 권장값

### 프로덕션 설정

```bash
JAVA_OPTS="
  -Xms8g
  -Xmx12g
  -XX:+UseG1GC
  -XX:MaxGCPauseMillis=50
  -XX:G1HeapRegionSize=16m
  -XX:+ParallelRefProcEnabled
  -XX:+UseStringDeduplication
  -XX:+HeapDumpOnOutOfMemoryError
  -XX:HeapDumpPath=/var/log/heap_dump.hprof
  -Xlog:gc*:file=/var/log/gc.log:time,uptime:filecount=5,filesize=100m
"
```

---

## 10. 체크리스트

### 배포 전

- [ ] Maven 빌드 성공
- [ ] 단위 테스트 통과
- [ ] Docker 이미지 빌드/Push
- [ ] Kafka 토픽 생성
- [ ] ConfigMap/Secret 준비
- [ ] 리소스 할당 확인

### 배포 후

- [ ] 모든 샤드 Running 상태
- [ ] 헬스체크 통과
- [ ] Kafka Consumer 연결 확인
- [ ] 메트릭 수집 확인
- [ ] 로그 에러 없음
- [ ] Primary/Standby 동기화 확인

---

## 부록: 주요 명령어

```bash
# 빌드
mvn clean package -DskipTests

# 샤드 이미지 빌드
./scripts/build-shard-image.sh -t v1.0.0

# K8s 배포
./scripts/deploy-k8s.sh prod

# 상태 확인
kubectl get pods -n matching-engine -w

# 샤드 헬스 체크
for i in 1 2 3; do
  echo "Shard $i:"
  curl -s http://shard-$i:8080/health | jq .
done

# 롤백
./scripts/emergency-rollback.sh

# 로그 (전체)
kubectl logs -l app=matching-engine -n matching-engine --all-containers -f
```

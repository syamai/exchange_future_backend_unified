# 장애 복구(Disaster Recovery) 구현 가이드

## 개요

24/7 무중단 서비스를 위한 장애 복구 시스템을 구축합니다. Hot Standby, WAL 기반 이벤트 재생, 멀티 리전 배포를 통해 고가용성을 달성합니다.

## 목표 지표

| 지표 | 목표 | 설명 |
|------|------|------|
| **RTO** (Recovery Time Objective) | < 30초 | 장애 발생부터 복구까지 시간 |
| **RPO** (Recovery Point Objective) | 0 | 데이터 손실 허용량 (없음) |
| **가용성** | 99.99% | 연간 다운타임 약 52분 |

## 장애 복구 아키텍처

```
┌─────────────────────────────────────────────────────────────────┐
│                    Primary Region (Active)                       │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Matching    │  │   Backend    │  │  WebSocket   │          │
│  │  Engine      │  │   Service    │  │   Gateway    │          │
│  │  (Primary)   │  │              │  │              │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                 │                 │                   │
│  ┌──────▼─────────────────▼─────────────────▼───────┐          │
│  │                    Kafka Cluster                  │          │
│  │                 (Primary + Replica)               │          │
│  └──────────────────────────┬───────────────────────┘          │
│                             │                                   │
│  ┌──────────────┐  ┌───────▼──────┐  ┌──────────────┐          │
│  │  PostgreSQL  │  │    Redis     │  │ TimescaleDB  │          │
│  │  (Primary)   │  │   Cluster    │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                         Cross-Region
                          Replication
                                │
┌───────────────────────────────▼─────────────────────────────────┐
│                    DR Region (Standby)                           │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Matching    │  │   Backend    │  │  WebSocket   │          │
│  │  Engine      │  │   Service    │  │   Gateway    │          │
│  │  (Standby)   │  │  (Standby)   │  │  (Standby)   │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                 │                 │                   │
│  ┌──────▼─────────────────▼─────────────────▼───────┐          │
│  │                    Kafka Cluster                  │          │
│  │                     (Mirror)                      │          │
│  └──────────────────────────┬───────────────────────┘          │
│                             │                                   │
│  ┌──────────────┐  ┌───────▼──────┐  ┌──────────────┐          │
│  │  PostgreSQL  │  │    Redis     │  │ TimescaleDB  │          │
│  │  (Replica)   │  │  (Replica)   │  │  (Replica)   │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Write-Ahead Log (WAL) 구현

### WAL 스키마

```sql
-- WAL 테이블 (PostgreSQL)
CREATE TABLE wal_events (
    id BIGSERIAL PRIMARY KEY,
    sequence_number BIGINT NOT NULL,
    shard_id VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(shard_id, sequence_number)
);

CREATE INDEX idx_wal_shard_seq ON wal_events(shard_id, sequence_number);
CREATE INDEX idx_wal_created_at ON wal_events(created_at);

-- WAL 체크포인트 테이블
CREATE TABLE wal_checkpoints (
    id SERIAL PRIMARY KEY,
    shard_id VARCHAR(50) UNIQUE NOT NULL,
    last_sequence_number BIGINT NOT NULL,
    last_checkpoint_at TIMESTAMPTZ DEFAULT NOW(),
    state_snapshot JSONB,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### WAL Writer 구현 (Java)

```java
package com.sotatek.future.wal;

import java.security.MessageDigest;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.LinkedBlockingQueue;

public class WALWriter {

    private final String shardId;
    private final BlockingQueue<WALEvent> writeQueue;
    private final WALRepository walRepository;
    private long currentSequence;
    private volatile boolean running = true;

    public WALWriter(String shardId, WALRepository walRepository) {
        this.shardId = shardId;
        this.walRepository = walRepository;
        this.writeQueue = new LinkedBlockingQueue<>(10000);
        this.currentSequence = walRepository.getLastSequence(shardId);
    }

    /**
     * WAL에 이벤트 기록
     */
    public long append(WALEvent event) {
        long sequence = ++currentSequence;
        event.setSequenceNumber(sequence);
        event.setShardId(shardId);
        event.setChecksum(calculateChecksum(event));

        writeQueue.add(event);
        return sequence;
    }

    /**
     * WAL 기록 스레드
     */
    public void startWriter() {
        Thread writerThread = new Thread(() -> {
            while (running) {
                try {
                    WALEvent event = writeQueue.take();
                    walRepository.save(event);

                    // 배치 처리를 위해 추가 이벤트 확인
                    List<WALEvent> batch = new ArrayList<>();
                    batch.add(event);
                    writeQueue.drainTo(batch, 99);

                    if (batch.size() > 1) {
                        walRepository.saveBatch(batch.subList(1, batch.size()));
                    }

                    // Kafka로 이벤트 발행 (비동기)
                    publishToKafka(batch);

                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    break;
                }
            }
        });
        writerThread.setName("WAL-Writer-" + shardId);
        writerThread.start();
    }

    /**
     * 체크포인트 생성
     */
    public void checkpoint(Object stateSnapshot) {
        WALCheckpoint checkpoint = new WALCheckpoint();
        checkpoint.setShardId(shardId);
        checkpoint.setLastSequenceNumber(currentSequence);
        checkpoint.setStateSnapshot(stateSnapshot);

        walRepository.saveCheckpoint(checkpoint);

        // 오래된 WAL 정리 (체크포인트 이전)
        walRepository.cleanupBefore(shardId, currentSequence - RETENTION_COUNT);
    }

    private String calculateChecksum(WALEvent event) {
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            String data = event.getSequenceNumber() + event.getEventType() + event.getPayload();
            byte[] hash = md.digest(data.getBytes());
            return bytesToHex(hash);
        } catch (Exception e) {
            throw new RuntimeException("Checksum calculation failed", e);
        }
    }

    public void stop() {
        running = false;
    }
}
```

### WAL Recovery 구현

```java
package com.sotatek.future.wal;

public class WALRecovery {

    private final WALRepository walRepository;
    private final ShardedMatchingEngine engine;

    /**
     * WAL에서 상태 복구
     */
    public void recover(String shardId) {
        log.info("Starting WAL recovery for shard: {}", shardId);

        // 1. 마지막 체크포인트 로드
        WALCheckpoint checkpoint = walRepository.getLastCheckpoint(shardId);

        long startSequence = 0;
        if (checkpoint != null) {
            // 체크포인트에서 상태 복원
            engine.restoreFromSnapshot(checkpoint.getStateSnapshot());
            startSequence = checkpoint.getLastSequenceNumber();
            log.info("Restored from checkpoint at sequence: {}", startSequence);
        }

        // 2. 체크포인트 이후 이벤트 재생
        long eventsReplayed = 0;
        for (WALEvent event : walRepository.getEventsAfter(shardId, startSequence)) {
            // 체크섬 검증
            String expectedChecksum = calculateChecksum(event);
            if (!expectedChecksum.equals(event.getChecksum())) {
                throw new WALCorruptionException(
                    "Checksum mismatch at sequence " + event.getSequenceNumber()
                );
            }

            // 이벤트 재생
            replayEvent(event);
            eventsReplayed++;

            if (eventsReplayed % 10000 == 0) {
                log.info("Replayed {} events...", eventsReplayed);
            }
        }

        log.info("WAL recovery completed. Total events replayed: {}", eventsReplayed);
    }

    /**
     * 이벤트 재생
     */
    private void replayEvent(WALEvent event) {
        switch (event.getEventType()) {
            case "ORDER_PLACED":
                engine.replayOrderPlaced(event.getPayload());
                break;
            case "ORDER_MATCHED":
                engine.replayOrderMatched(event.getPayload());
                break;
            case "ORDER_CANCELLED":
                engine.replayOrderCancelled(event.getPayload());
                break;
            case "POSITION_UPDATED":
                engine.replayPositionUpdated(event.getPayload());
                break;
            case "ACCOUNT_UPDATED":
                engine.replayAccountUpdated(event.getPayload());
                break;
            // ... 기타 이벤트
        }
    }
}
```

## Hot Standby 구현

### Standby Engine

```java
package com.sotatek.future.engine;

public class StandbyMatchingEngine extends ShardedMatchingEngine {

    private final WALConsumer walConsumer;
    private volatile boolean isPrimary = false;
    private long lastAppliedSequence = 0;

    public StandbyMatchingEngine(String shardId) {
        super(shardId, Collections.emptySet());
        this.walConsumer = new WALConsumer(shardId, this::applyWALEvent);
    }

    /**
     * Standby 모드 시작
     */
    public void startStandby() {
        log.info("Starting standby mode for shard: {}", shardId);

        // 1. WAL에서 초기 상태 복구
        WALRecovery recovery = new WALRecovery(walRepository, this);
        recovery.recover(shardId);

        // 2. 실시간 WAL 이벤트 구독
        walConsumer.start();
    }

    /**
     * WAL 이벤트 적용 (Primary로부터 수신)
     */
    private void applyWALEvent(WALEvent event) {
        if (event.getSequenceNumber() <= lastAppliedSequence) {
            // 이미 적용된 이벤트 스킵
            return;
        }

        if (event.getSequenceNumber() != lastAppliedSequence + 1) {
            // 시퀀스 갭 발생 - 복구 필요
            log.warn("Sequence gap detected. Expected: {}, Got: {}",
                lastAppliedSequence + 1, event.getSequenceNumber());
            requestRecovery();
            return;
        }

        // 이벤트 적용
        replayEvent(event);
        lastAppliedSequence = event.getSequenceNumber();

        // 지연 메트릭 기록
        long lag = System.currentTimeMillis() - event.getCreatedAt().getTime();
        metrics.recordStandbyLag(lag);
    }

    /**
     * Primary로 승격
     */
    public void promoteTooPrimary() {
        log.info("Promoting standby to primary for shard: {}", shardId);

        // 1. WAL Consumer 중지
        walConsumer.stop();

        // 2. Primary 모드 전환
        isPrimary = true;

        // 3. WAL Writer 시작
        startWALWriter();

        // 4. 명령 처리 시작
        start();

        log.info("Shard {} promoted to primary successfully", shardId);
    }

    /**
     * Standby 지연 시간 조회
     */
    public long getStandbyLag() {
        return System.currentTimeMillis() - lastAppliedTimestamp;
    }
}
```

### Health Monitor & Failover Manager

```java
package com.sotatek.future.ha;

public class FailoverManager {

    private final Map<String, ShardHealth> shardHealthMap;
    private final ZookeeperClient zkClient;
    private final AlertService alertService;

    private static final int HEALTH_CHECK_INTERVAL_MS = 1000;
    private static final int FAILOVER_THRESHOLD_MS = 5000;
    private static final int MAX_STANDBY_LAG_MS = 1000;

    /**
     * 헬스 체크 루프
     */
    @Scheduled(fixedRate = HEALTH_CHECK_INTERVAL_MS)
    public void checkHealth() {
        for (Map.Entry<String, ShardHealth> entry : shardHealthMap.entrySet()) {
            String shardId = entry.getKey();
            ShardHealth health = entry.getValue();

            // Primary 헬스 체크
            boolean primaryHealthy = checkPrimaryHealth(shardId);

            if (!primaryHealthy) {
                health.incrementFailureCount();

                if (health.getConsecutiveFailures() * HEALTH_CHECK_INTERVAL_MS >= FAILOVER_THRESHOLD_MS) {
                    // 페일오버 시작
                    initiateFailover(shardId);
                }
            } else {
                health.resetFailureCount();
            }

            // Standby 지연 체크
            long standbyLag = getStandbyLag(shardId);
            if (standbyLag > MAX_STANDBY_LAG_MS) {
                alertService.sendAlert(AlertLevel.WARNING,
                    String.format("Standby lag too high for shard %s: %dms", shardId, standbyLag));
            }
        }
    }

    /**
     * 페일오버 실행
     */
    private void initiateFailover(String shardId) {
        log.warn("Initiating failover for shard: {}", shardId);

        try {
            // 1. 분산 락 획득 (중복 페일오버 방지)
            String lockPath = "/failover/lock/" + shardId;
            if (!zkClient.acquireLock(lockPath, 30000)) {
                log.info("Failover already in progress for shard: {}", shardId);
                return;
            }

            // 2. Standby 상태 확인
            StandbyMatchingEngine standby = getStandbyEngine(shardId);
            if (standby == null || standby.getStandbyLag() > MAX_STANDBY_LAG_MS * 2) {
                alertService.sendAlert(AlertLevel.CRITICAL,
                    "Cannot failover shard " + shardId + ": Standby not ready");
                return;
            }

            // 3. Standby를 Primary로 승격
            standby.promoteTooPrimary();

            // 4. 라우팅 업데이트
            updateRouting(shardId, standby.getAddress());

            // 5. DNS/Load Balancer 업데이트
            updateLoadBalancer(shardId, standby.getAddress());

            // 6. 알림 발송
            alertService.sendAlert(AlertLevel.CRITICAL,
                String.format("Failover completed for shard %s. New primary: %s",
                    shardId, standby.getAddress()));

            // 7. 이전 Primary를 Standby로 복구 시도 (비동기)
            scheduleOldPrimaryRecovery(shardId);

        } catch (Exception e) {
            log.error("Failover failed for shard {}: {}", shardId, e.getMessage());
            alertService.sendAlert(AlertLevel.CRITICAL,
                "Failover failed for shard " + shardId + ": " + e.getMessage());
        } finally {
            zkClient.releaseLock("/failover/lock/" + shardId);
        }
    }

    /**
     * Primary 헬스 체크
     */
    private boolean checkPrimaryHealth(String shardId) {
        try {
            String primaryAddress = getPrimaryAddress(shardId);
            HealthCheckResponse response = httpClient.get(
                primaryAddress + "/health",
                HealthCheckResponse.class,
                Duration.ofMillis(1000)
            );
            return response != null && response.isHealthy();
        } catch (Exception e) {
            log.warn("Health check failed for shard {}: {}", shardId, e.getMessage());
            return false;
        }
    }
}
```

## Kafka MirrorMaker 설정 (Cross-Region)

```yaml
# mirror-maker-config.yaml

apiVersion: kafka.strimzi.io/v1beta2
kind: KafkaMirrorMaker2
metadata:
  name: exchange-mirror
spec:
  version: 3.5.0
  replicas: 3
  connectCluster: "target"
  clusters:
    - alias: "source"
      bootstrapServers: primary-kafka:9092
      config:
        config.storage.replication.factor: 3
    - alias: "target"
      bootstrapServers: dr-kafka:9092
      config:
        config.storage.replication.factor: 3
  mirrors:
    - sourceCluster: "source"
      targetCluster: "target"
      sourceConnector:
        tasksMax: 10
        config:
          replication.factor: 3
          offset-syncs.topic.replication.factor: 3
          sync.topic.acls.enabled: false
          refresh.topics.interval.seconds: 30
      heartbeatConnector:
        config:
          heartbeats.topic.replication.factor: 3
      checkpointConnector:
        config:
          checkpoints.topic.replication.factor: 3
          sync.group.offsets.enabled: true
      topicsPattern: ".*"
      groupsPattern: ".*"
```

## PostgreSQL Streaming Replication

```bash
# primary postgresql.conf
wal_level = replica
max_wal_senders = 10
max_replication_slots = 10
wal_keep_size = 1GB
synchronous_commit = on
synchronous_standby_names = 'dr_standby'

# DR replica recovery.conf
primary_conninfo = 'host=primary-db port=5432 user=replicator password=xxx application_name=dr_standby'
primary_slot_name = 'dr_slot'
recovery_target_timeline = 'latest'
```

## Redis Replication (Cross-Region)

```yaml
# redis-sentinel.yaml

apiVersion: v1
kind: ConfigMap
metadata:
  name: redis-sentinel-config
data:
  sentinel.conf: |
    sentinel monitor mymaster primary-redis 6379 2
    sentinel down-after-milliseconds mymaster 5000
    sentinel failover-timeout mymaster 60000
    sentinel parallel-syncs mymaster 1
    sentinel auth-pass mymaster ${REDIS_PASSWORD}

---
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: redis-sentinel
spec:
  serviceName: redis-sentinel
  replicas: 3
  selector:
    matchLabels:
      app: redis-sentinel
  template:
    spec:
      containers:
        - name: sentinel
          image: redis:7-alpine
          command:
            - redis-sentinel
            - /etc/redis/sentinel.conf
          volumeMounts:
            - name: config
              mountPath: /etc/redis
      volumes:
        - name: config
          configMap:
            name: redis-sentinel-config
```

## 모니터링 및 알림

### Prometheus 알림 규칙

```yaml
# alerts.yaml

groups:
  - name: exchange-ha
    rules:
      # Primary 다운
      - alert: MatchingEnginePrimaryDown
        expr: up{job="matching-engine", role="primary"} == 0
        for: 10s
        labels:
          severity: critical
        annotations:
          summary: "Matching engine primary is down"
          description: "Primary matching engine for shard {{ $labels.shard }} has been down for more than 10 seconds"

      # Standby 지연
      - alert: StandbyLagHigh
        expr: matching_engine_standby_lag_seconds > 2
        for: 30s
        labels:
          severity: warning
        annotations:
          summary: "Standby replication lag is high"
          description: "Standby for shard {{ $labels.shard }} is {{ $value }}s behind primary"

      # WAL 크기
      - alert: WALSizeHigh
        expr: wal_events_count > 1000000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "WAL size is growing"
          description: "WAL for shard {{ $labels.shard }} has {{ $value }} events. Consider checkpointing."

      # 페일오버 발생
      - alert: FailoverOccurred
        expr: increase(failover_total[5m]) > 0
        labels:
          severity: critical
        annotations:
          summary: "Failover occurred"
          description: "Failover was triggered for shard {{ $labels.shard }}"

      # DB 복제 지연
      - alert: PostgreSQLReplicationLag
        expr: pg_replication_lag_seconds > 5
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "PostgreSQL replication lag is high"
          description: "Replication lag is {{ $value }}s"

      # Redis Sentinel 페일오버
      - alert: RedisSentinelFailover
        expr: increase(redis_sentinel_failover_total[5m]) > 0
        labels:
          severity: critical
        annotations:
          summary: "Redis Sentinel triggered failover"
```

### Grafana 대시보드

```json
{
  "dashboard": {
    "title": "Exchange HA Dashboard",
    "panels": [
      {
        "title": "Shard Status",
        "type": "stat",
        "targets": [
          {
            "expr": "up{job=\"matching-engine\"}",
            "legendFormat": "{{ shard }} - {{ role }}"
          }
        ]
      },
      {
        "title": "Standby Lag",
        "type": "graph",
        "targets": [
          {
            "expr": "matching_engine_standby_lag_seconds",
            "legendFormat": "{{ shard }}"
          }
        ]
      },
      {
        "title": "WAL Events",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(wal_events_total[5m])",
            "legendFormat": "{{ shard }}"
          }
        ]
      },
      {
        "title": "Failover History",
        "type": "table",
        "targets": [
          {
            "expr": "failover_info",
            "format": "table"
          }
        ]
      }
    ]
  }
}
```

## 페일오버 테스트 절차

### 계획된 페일오버 테스트

```bash
#!/bin/bash
# planned-failover-test.sh

SHARD_ID=$1

echo "=== Planned Failover Test for Shard: $SHARD_ID ==="

# 1. 현재 상태 확인
echo "1. Checking current status..."
curl -s http://primary-$SHARD_ID:8080/health
curl -s http://standby-$SHARD_ID:8080/health

# 2. Standby 지연 확인
echo "2. Checking standby lag..."
LAG=$(curl -s http://standby-$SHARD_ID:8080/metrics | grep standby_lag)
echo "Current lag: $LAG"

# 3. 테스트 주문 생성
echo "3. Creating test orders..."
for i in {1..10}; do
  curl -X POST http://api/order -d '{"symbol":"BTCUSDT","side":"BUY","price":"50000","qty":"0.001"}'
done

# 4. Primary 중지
echo "4. Stopping primary..."
kubectl scale deployment matching-engine-$SHARD_ID-primary --replicas=0

# 5. 페일오버 대기
echo "5. Waiting for failover..."
sleep 10

# 6. 새 Primary 확인
echo "6. Checking new primary..."
curl -s http://standby-$SHARD_ID:8080/health

# 7. 서비스 테스트
echo "7. Testing service..."
curl -X POST http://api/order -d '{"symbol":"BTCUSDT","side":"BUY","price":"50000","qty":"0.001"}'

# 8. 결과 확인
echo "8. Verifying results..."
curl -s http://api/orders?symbol=BTCUSDT

echo "=== Test Complete ==="
```

### 비계획 페일오버 테스트 (Chaos Engineering)

```yaml
# chaos-test.yaml (Chaos Mesh)

apiVersion: chaos-mesh.org/v1alpha1
kind: PodChaos
metadata:
  name: kill-matching-engine-primary
spec:
  action: pod-kill
  mode: one
  selector:
    labelSelectors:
      app: matching-engine
      role: primary
      shard: shard-1
  duration: "60s"
  scheduler:
    cron: "@every 24h"
```

## 체크리스트

### 구현

- [ ] WAL Writer 구현
- [ ] WAL Recovery 구현
- [ ] Standby Engine 구현
- [ ] Failover Manager 구현
- [ ] Health Monitor 구현
- [ ] Cross-region 복제 설정

### 테스트

- [ ] 계획된 페일오버 테스트
- [ ] 비계획 페일오버 테스트
- [ ] 복구 시간 측정 (RTO)
- [ ] 데이터 손실 검증 (RPO)
- [ ] 부하 테스트 중 페일오버

### 운영

- [ ] 알림 규칙 설정
- [ ] 대시보드 구성
- [ ] 런북(Runbook) 작성
- [ ] 페일오버 훈련 일정

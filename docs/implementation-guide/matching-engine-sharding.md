# 매칭 엔진 샤딩 구현 가이드

## 개요

현재 단일 JVM에서 모든 심볼을 처리하는 구조를 심볼별 샤딩으로 전환하여 수평 확장성과 장애 격리를 달성합니다.

## 현재 아키텍처의 문제점

```
현재 구조:
┌─────────────────────────────────────┐
│         Single JVM                   │
│  ┌─────────────────────────────┐    │
│  │      MatchingEngine         │    │
│  │  ┌───────┐ ┌───────┐       │    │
│  │  │BTC/USD│ │ETH/USD│ ...   │    │  ← 모든 심볼이 하나의 프로세스
│  │  │Matcher│ │Matcher│       │    │
│  │  └───────┘ └───────┘       │    │
│  └─────────────────────────────┘    │
└─────────────────────────────────────┘

문제점:
1. 단일 장애점 (Single Point of Failure)
2. 수직 확장만 가능 (더 큰 서버)
3. BTC 트래픽이 다른 심볼에 영향
4. 배포 시 전체 시스템 다운
```

## 타겟 아키텍처

```
샤딩 구조:
┌─────────────────────────────────────────────────────────────┐
│                      Order Router                            │
│                 (Symbol → Shard Mapping)                     │
└─────────────────────────────────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│   Shard 1     │  │   Shard 2     │  │   Shard 3     │
│   (JVM 1)     │  │   (JVM 2)     │  │   (JVM 3)     │
│               │  │               │  │               │
│ ┌───────────┐ │  │ ┌───────────┐ │  │ ┌───────────┐ │
│ │  BTC/USDT │ │  │ │  ETH/USDT │ │  │ │  SOL/USDT │ │
│ │  Primary  │ │  │ │  Primary  │ │  │ │  XRP/USDT │ │
│ └─────┬─────┘ │  │ └─────┬─────┘ │  │ │  ...      │ │
│       │       │  │       │       │  │ └───────────┘ │
│ ┌─────▼─────┐ │  │ ┌─────▼─────┐ │  │               │
│ │  Standby  │ │  │ │  Standby  │ │  │               │
│ └───────────┘ │  │ └───────────┘ │  │               │
└───────────────┘  └───────────────┘  └───────────────┘

장점:
1. 장애 격리 (BTC 샤드 다운 → ETH 정상 운영)
2. 수평 확장 (샤드 추가로 용량 증가)
3. 독립 배포 (샤드별 무중단 배포)
4. 리소스 최적화 (트래픽에 따른 샤드별 스케일링)
```

## 구현 단계

### 1단계: Order Router 구현

#### OrderRouter.java

```java
package com.sotatek.future.router;

import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

public class OrderRouter {

    private static OrderRouter instance;
    private final Map<String, ShardInfo> shardMapping;
    private final Map<String, ShardClient> shardClients;

    private OrderRouter() {
        this.shardMapping = new ConcurrentHashMap<>();
        this.shardClients = new ConcurrentHashMap<>();
        loadShardMapping();
    }

    public static synchronized OrderRouter getInstance() {
        if (instance == null) {
            instance = new OrderRouter();
        }
        return instance;
    }

    /**
     * 심볼을 담당하는 샤드로 주문을 라우팅
     */
    public void routeOrder(Command command) {
        String symbol = command.getSymbol();
        ShardInfo shard = getShardForSymbol(symbol);

        if (shard == null) {
            throw new UnknownSymbolException("Unknown symbol: " + symbol);
        }

        ShardClient client = shardClients.get(shard.getShardId());
        client.sendCommand(command);
    }

    /**
     * 심볼 → 샤드 매핑 조회
     */
    public ShardInfo getShardForSymbol(String symbol) {
        return shardMapping.get(symbol);
    }

    /**
     * 샤드 매핑 로드 (설정 파일 또는 DB에서)
     */
    private void loadShardMapping() {
        // 예시: 설정 파일에서 로드
        // shard-1: BTC/USDT, BTC/BUSD
        // shard-2: ETH/USDT, ETH/BUSD
        // shard-3: 나머지 모든 심볼

        shardMapping.put("BTCUSDT", new ShardInfo("shard-1", "kafka-topic-shard-1"));
        shardMapping.put("BTCBUSD", new ShardInfo("shard-1", "kafka-topic-shard-1"));
        shardMapping.put("ETHUSDT", new ShardInfo("shard-2", "kafka-topic-shard-2"));
        shardMapping.put("ETHBUSD", new ShardInfo("shard-2", "kafka-topic-shard-2"));
        // 기본 샤드 (나머지 심볼)
        // shardMapping.put("*", new ShardInfo("shard-3", "kafka-topic-shard-3"));
    }

    /**
     * 동적 샤드 재배치 (리밸런싱)
     */
    public void rebalanceShard(String symbol, String newShardId) {
        ShardInfo newShard = new ShardInfo(newShardId, "kafka-topic-" + newShardId);
        ShardInfo oldShard = shardMapping.put(symbol, newShard);

        // 기존 샤드에 심볼 제거 명령
        if (oldShard != null) {
            ShardClient oldClient = shardClients.get(oldShard.getShardId());
            oldClient.sendCommand(new Command(CommandCode.REMOVE_SYMBOL, symbol));
        }

        // 새 샤드에 심볼 추가 명령
        ShardClient newClient = shardClients.get(newShardId);
        newClient.sendCommand(new Command(CommandCode.ADD_SYMBOL, symbol));
    }
}
```

#### ShardInfo.java

```java
package com.sotatek.future.router;

import lombok.Data;
import lombok.AllArgsConstructor;

@Data
@AllArgsConstructor
public class ShardInfo {
    private String shardId;
    private String kafkaTopic;
    private String primaryHost;
    private int primaryPort;
    private String standbyHost;
    private int standbyPort;
    private ShardStatus status;

    public ShardInfo(String shardId, String kafkaTopic) {
        this.shardId = shardId;
        this.kafkaTopic = kafkaTopic;
        this.status = ShardStatus.ACTIVE;
    }

    public enum ShardStatus {
        ACTIVE,      // 정상 운영
        DEGRADED,    // Standby로 전환 중
        MAINTENANCE, // 유지보수 중
        OFFLINE      // 오프라인
    }
}
```

#### ShardClient.java

```java
package com.sotatek.future.router;

import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.ProducerRecord;
import java.util.Properties;

public class ShardClient {

    private final String shardId;
    private final KafkaProducer<String, String> producer;
    private final String topic;

    public ShardClient(ShardInfo shardInfo, Properties kafkaProps) {
        this.shardId = shardInfo.getShardId();
        this.topic = shardInfo.getKafkaTopic();
        this.producer = new KafkaProducer<>(kafkaProps);
    }

    /**
     * 샤드로 명령 전송
     */
    public void sendCommand(Command command) {
        String key = command.getSymbol(); // 파티션 키로 심볼 사용
        String value = command.toJson();

        ProducerRecord<String, String> record = new ProducerRecord<>(topic, key, value);

        producer.send(record, (metadata, exception) -> {
            if (exception != null) {
                handleSendError(command, exception);
            }
        });
    }

    /**
     * 전송 실패 처리
     */
    private void handleSendError(Command command, Exception e) {
        // 1. 재시도
        // 2. DLQ(Dead Letter Queue)로 전송
        // 3. 알림 발송
        log.error("Failed to send command to shard {}: {}", shardId, e.getMessage());
    }

    public void close() {
        producer.close();
    }
}
```

### 2단계: 샤드별 매칭 엔진 수정

#### ShardedMatchingEngine.java

```java
package com.sotatek.future.engine;

import java.util.Set;
import java.util.HashSet;

/**
 * 샤드별로 독립 실행되는 매칭 엔진
 */
public class ShardedMatchingEngine extends MatchingEngine {

    private final String shardId;
    private final Set<String> assignedSymbols;
    private final StandbySync standbySync;

    public ShardedMatchingEngine(String shardId, Set<String> symbols) {
        super();
        this.shardId = shardId;
        this.assignedSymbols = new HashSet<>(symbols);
        this.standbySync = new StandbySync(shardId);
    }

    @Override
    protected void onTick() {
        Command command = currentProcCommand;

        // 이 샤드에 할당된 심볼인지 확인
        if (!assignedSymbols.contains(command.getSymbol())) {
            log.warn("Received command for unassigned symbol: {}", command.getSymbol());
            return;
        }

        // 기존 로직 실행
        super.onTick();

        // Standby로 상태 동기화
        standbySync.syncState(command, getAffectedEntities());
    }

    /**
     * 심볼 추가 (동적 재배치)
     */
    public void addSymbol(String symbol) {
        assignedSymbols.add(symbol);
        // 심볼에 대한 Matcher 초기화
        Matcher matcher = new Matcher(symbol, this);
        matchers.put(symbol, matcher);
        log.info("Added symbol {} to shard {}", symbol, shardId);
    }

    /**
     * 심볼 제거 (동적 재배치)
     */
    public void removeSymbol(String symbol) {
        assignedSymbols.remove(symbol);
        Matcher matcher = matchers.remove(symbol);
        if (matcher != null) {
            // 미체결 주문 처리 (취소 또는 마이그레이션)
            matcher.cancelAllOrders();
        }
        log.info("Removed symbol {} from shard {}", symbol, shardId);
    }

    /**
     * 샤드 상태 조회 (헬스체크용)
     */
    public ShardHealthStatus getHealthStatus() {
        return ShardHealthStatus.builder()
            .shardId(shardId)
            .assignedSymbols(assignedSymbols.size())
            .activeOrders(getActiveOrderCount())
            .matcherCount(matchers.size())
            .lastProcessedTime(lastProcessedTime)
            .memoryUsage(Runtime.getRuntime().totalMemory() - Runtime.getRuntime().freeMemory())
            .build();
    }
}
```

### 3단계: Standby 동기화 구현

#### StandbySync.java

```java
package com.sotatek.future.engine;

import java.util.List;

/**
 * Primary → Standby 상태 동기화
 */
public class StandbySync {

    private final String shardId;
    private final KafkaProducer<String, byte[]> syncProducer;
    private final String syncTopic;

    public StandbySync(String shardId) {
        this.shardId = shardId;
        this.syncTopic = "shard-sync-" + shardId;
        this.syncProducer = createSyncProducer();
    }

    /**
     * 상태 변경을 Standby로 동기화
     */
    public void syncState(Command command, List<BaseEntity> affectedEntities) {
        SyncMessage syncMessage = SyncMessage.builder()
            .shardId(shardId)
            .sequenceNumber(getNextSequence())
            .command(command)
            .entities(affectedEntities)
            .timestamp(System.currentTimeMillis())
            .build();

        // 바이너리 직렬화 (성능 최적화)
        byte[] payload = syncMessage.toProtobuf();

        ProducerRecord<String, byte[]> record =
            new ProducerRecord<>(syncTopic, shardId, payload);

        // 동기 전송 (순서 보장)
        try {
            syncProducer.send(record).get();
        } catch (Exception e) {
            handleSyncFailure(syncMessage, e);
        }
    }

    /**
     * 동기화 실패 처리
     */
    private void handleSyncFailure(SyncMessage message, Exception e) {
        log.error("Failed to sync state to standby: {}", e.getMessage());

        // 1. 재시도 큐에 추가
        retryQueue.add(message);

        // 2. 연속 실패 시 알림
        if (consecutiveFailures.incrementAndGet() > MAX_FAILURES) {
            alertService.sendAlert(AlertLevel.CRITICAL,
                "Standby sync failed for shard " + shardId);
        }
    }
}
```

### 4단계: 샤드별 Kafka 토픽 구성

#### Kafka 토픽 설계

```yaml
# kafka-topics.yaml

topics:
  # 샤드별 입력 토픽
  - name: matching-engine-shard-1-input
    partitions: 1  # 단일 파티션 (순서 보장)
    replication-factor: 3
    config:
      retention.ms: 604800000  # 7일

  - name: matching-engine-shard-2-input
    partitions: 1
    replication-factor: 3

  - name: matching-engine-shard-3-input
    partitions: 1
    replication-factor: 3

  # 샤드별 출력 토픽
  - name: matching-engine-shard-1-output
    partitions: 10  # 병렬 처리 가능
    replication-factor: 3

  # 샤드 동기화 토픽 (Primary → Standby)
  - name: shard-sync-shard-1
    partitions: 1
    replication-factor: 3
    config:
      retention.ms: 86400000  # 1일
      min.insync.replicas: 2

  # 글로벌 출력 토픽 (모든 샤드의 결과 통합)
  - name: matching-engine-output-global
    partitions: 20
    replication-factor: 3
```

### 5단계: 배포 구성

#### docker-compose-sharded.yml

```yaml
version: '3.8'

services:
  # 샤드 1: BTC
  matching-engine-shard-1-primary:
    image: exchange/matching-engine:latest
    environment:
      - SHARD_ID=shard-1
      - SHARD_ROLE=primary
      - ASSIGNED_SYMBOLS=BTCUSDT,BTCBUSD
      - KAFKA_INPUT_TOPIC=matching-engine-shard-1-input
      - KAFKA_OUTPUT_TOPIC=matching-engine-shard-1-output
      - STANDBY_SYNC_ENABLED=true
    deploy:
      resources:
        limits:
          cpus: '4'
          memory: 8G
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 5s
      timeout: 3s
      retries: 3

  matching-engine-shard-1-standby:
    image: exchange/matching-engine:latest
    environment:
      - SHARD_ID=shard-1
      - SHARD_ROLE=standby
      - ASSIGNED_SYMBOLS=BTCUSDT,BTCBUSD
      - SYNC_TOPIC=shard-sync-shard-1
    deploy:
      resources:
        limits:
          cpus: '4'
          memory: 8G

  # 샤드 2: ETH
  matching-engine-shard-2-primary:
    image: exchange/matching-engine:latest
    environment:
      - SHARD_ID=shard-2
      - SHARD_ROLE=primary
      - ASSIGNED_SYMBOLS=ETHUSDT,ETHBUSD
      - KAFKA_INPUT_TOPIC=matching-engine-shard-2-input
    # ...

  # 샤드 3: 기타 심볼
  matching-engine-shard-3-primary:
    image: exchange/matching-engine:latest
    environment:
      - SHARD_ID=shard-3
      - SHARD_ROLE=primary
      - ASSIGNED_SYMBOLS=SOLUSDT,XRPUSDT,ADAUSDT,...
    # ...

  # Order Router
  order-router:
    image: exchange/order-router:latest
    environment:
      - SHARD_CONFIG_PATH=/config/shard-mapping.yaml
    volumes:
      - ./config:/config
    ports:
      - "8081:8081"
```

#### Kubernetes 배포 (권장)

```yaml
# k8s/matching-engine-shard.yaml

apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: matching-engine-shard-1
spec:
  serviceName: matching-engine-shard-1
  replicas: 2  # Primary + Standby
  selector:
    matchLabels:
      app: matching-engine
      shard: shard-1
  template:
    metadata:
      labels:
        app: matching-engine
        shard: shard-1
    spec:
      affinity:
        podAntiAffinity:
          requiredDuringSchedulingIgnoredDuringExecution:
            - labelSelector:
                matchExpressions:
                  - key: shard
                    operator: In
                    values:
                      - shard-1
              topologyKey: kubernetes.io/hostname
      containers:
        - name: matching-engine
          image: exchange/matching-engine:latest
          env:
            - name: SHARD_ID
              value: "shard-1"
            - name: POD_NAME
              valueFrom:
                fieldRef:
                  fieldPath: metadata.name
            - name: SHARD_ROLE
              value: "$(POD_NAME == 'matching-engine-shard-1-0' ? 'primary' : 'standby')"
          resources:
            requests:
              cpu: "2"
              memory: "4Gi"
            limits:
              cpu: "4"
              memory: "8Gi"
          livenessProbe:
            httpGet:
              path: /health/live
              port: 8080
            initialDelaySeconds: 30
            periodSeconds: 10
          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 5
```

## 샤드 리밸런싱 전략

### 자동 리밸런싱

```java
public class ShardRebalancer {

    private final Map<String, ShardMetrics> shardMetrics;
    private final double LOAD_THRESHOLD = 0.8; // 80%

    /**
     * 주기적으로 샤드 부하를 체크하고 리밸런싱
     */
    @Scheduled(fixedRate = 60000) // 1분마다
    public void checkAndRebalance() {
        for (Map.Entry<String, ShardMetrics> entry : shardMetrics.entrySet()) {
            String shardId = entry.getKey();
            ShardMetrics metrics = entry.getValue();

            if (metrics.getCpuUsage() > LOAD_THRESHOLD ||
                metrics.getOrderRate() > metrics.getMaxCapacity() * LOAD_THRESHOLD) {

                // 부하가 높은 샤드에서 가장 적은 트래픽 심볼을 다른 샤드로 이동
                String symbolToMove = findLowestTrafficSymbol(shardId);
                String targetShard = findLowestLoadShard();

                if (targetShard != null && !targetShard.equals(shardId)) {
                    rebalanceSymbol(symbolToMove, shardId, targetShard);
                }
            }
        }
    }

    /**
     * 심볼을 다른 샤드로 이동
     */
    private void rebalanceSymbol(String symbol, String fromShard, String toShard) {
        log.info("Rebalancing {} from {} to {}", symbol, fromShard, toShard);

        // 1. 해당 심볼의 신규 주문 일시 중지
        orderRouter.pauseSymbol(symbol);

        // 2. 기존 샤드에서 미체결 주문 내보내기
        List<Order> pendingOrders = shardClients.get(fromShard).exportOrders(symbol);

        // 3. 새 샤드로 심볼 할당 및 주문 복원
        shardClients.get(toShard).importSymbol(symbol, pendingOrders);

        // 4. 라우팅 테이블 업데이트
        orderRouter.updateMapping(symbol, toShard);

        // 5. 기존 샤드에서 심볼 제거
        shardClients.get(fromShard).removeSymbol(symbol);

        // 6. 신규 주문 재개
        orderRouter.resumeSymbol(symbol);
    }
}
```

## 모니터링 및 알림

### 샤드 메트릭

```java
public class ShardMetrics {
    private String shardId;
    private int assignedSymbols;
    private long activeOrders;
    private double ordersPerSecond;
    private double tradesPerSecond;
    private double avgMatchingLatencyMs;
    private double p99MatchingLatencyMs;
    private double cpuUsage;
    private long memoryUsed;
    private long memoryMax;
    private boolean isPrimary;
    private long standbyLag; // Standby의 지연 시간
}
```

### Prometheus 메트릭

```java
// Prometheus 메트릭 노출
public class ShardMetricsExporter {

    private final Counter ordersProcessed = Counter.build()
        .name("matching_engine_orders_processed_total")
        .help("Total orders processed")
        .labelNames("shard_id", "symbol", "side")
        .register();

    private final Histogram matchingLatency = Histogram.build()
        .name("matching_engine_latency_seconds")
        .help("Order matching latency")
        .labelNames("shard_id")
        .buckets(0.001, 0.005, 0.01, 0.025, 0.05, 0.1)
        .register();

    private final Gauge activeOrders = Gauge.build()
        .name("matching_engine_active_orders")
        .help("Current active orders")
        .labelNames("shard_id", "symbol")
        .register();

    private final Gauge standbyLag = Gauge.build()
        .name("matching_engine_standby_lag_seconds")
        .help("Standby replication lag")
        .labelNames("shard_id")
        .register();
}
```

## 체크리스트

### 구현 전 확인사항

- [ ] 현재 심볼별 트래픽 분석 완료
- [ ] 샤드 할당 전략 결정 (볼륨 기준 vs 균등 분배)
- [ ] Kafka 토픽 설계 완료
- [ ] Standby 동기화 전략 결정

### 구현 체크리스트

- [ ] OrderRouter 구현
- [ ] ShardClient 구현
- [ ] ShardedMatchingEngine 수정
- [ ] StandbySync 구현
- [ ] 헬스체크 엔드포인트 추가
- [ ] 메트릭 수집 구현

### 테스트 체크리스트

- [ ] 단일 샤드 기능 테스트
- [ ] 멀티 샤드 라우팅 테스트
- [ ] Standby 전환 테스트
- [ ] 리밸런싱 테스트
- [ ] 부하 테스트 (목표 TPS 달성)
- [ ] 장애 복구 테스트

### 배포 체크리스트

- [ ] Docker 이미지 빌드
- [ ] Kubernetes 매니페스트 작성
- [ ] 모니터링 대시보드 구성
- [ ] 알림 규칙 설정
- [ ] 롤백 절차 문서화

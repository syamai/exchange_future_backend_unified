# 성능 최적화 가이드

## 개요

암호화폐 선물 거래소에서 밀리초 단위의 지연 시간은 트레이더의 수익과 직결됩니다. 이 문서에서는 매칭 엔진, 네트워크, 데이터베이스 등 전체 시스템의 성능 최적화 전략을 다룹니다.

## 성능 목표

| 메트릭 | 현재 (추정) | Phase 1 목표 | Phase 2 목표 | 업계 리더 |
|--------|-------------|--------------|--------------|-----------|
| 매칭 지연시간 | 10-50ms | **<10ms** | **<5ms** | 5ms |
| 주문 처리량 | ~10K/초 | **50K/초** | **100K/초** | 1M/초 |
| WebSocket 지연 | ~200ms | **<100ms** | **<50ms** | 50ms |
| API 응답시간 | ~100ms | **<50ms** | **<20ms** | 10ms |
| 시스템 가동률 | 99% | **99.9%** | **99.99%** | 99.99% |

## 1. 매칭 엔진 최적화

### 1.1 메모리 관리 최적화

#### Object Pooling

```java
// src/main/java/com/exchange/engine/pool/OrderPool.java
package com.exchange.engine.pool;

import com.exchange.engine.model.Order;
import java.util.concurrent.ConcurrentLinkedQueue;
import java.util.concurrent.atomic.AtomicInteger;

/**
 * Object Pool for Order instances to reduce GC pressure.
 * Pre-allocates Order objects and reuses them instead of creating new instances.
 */
public class OrderPool {
    private final ConcurrentLinkedQueue<Order> pool;
    private final AtomicInteger poolSize;
    private final int maxPoolSize;
    private final int initialSize;

    public OrderPool(int initialSize, int maxPoolSize) {
        this.pool = new ConcurrentLinkedQueue<>();
        this.poolSize = new AtomicInteger(0);
        this.maxPoolSize = maxPoolSize;
        this.initialSize = initialSize;

        // Pre-allocate orders
        for (int i = 0; i < initialSize; i++) {
            pool.offer(new Order());
            poolSize.incrementAndGet();
        }
    }

    public Order acquire() {
        Order order = pool.poll();
        if (order != null) {
            poolSize.decrementAndGet();
            return order;
        }
        // Pool exhausted, create new instance
        return new Order();
    }

    public void release(Order order) {
        if (order == null) return;

        // Reset order state
        order.reset();

        // Return to pool if not full
        if (poolSize.get() < maxPoolSize) {
            pool.offer(order);
            poolSize.incrementAndGet();
        }
        // Otherwise let GC collect it
    }

    public int getPoolSize() {
        return poolSize.get();
    }

    public int getAvailable() {
        return pool.size();
    }
}
```

#### Memory-Efficient Order Book

```java
// src/main/java/com/exchange/engine/orderbook/OptimizedOrderBook.java
package com.exchange.engine.orderbook;

import it.unimi.dsi.fastutil.longs.Long2ObjectOpenHashMap;
import it.unimi.dsi.fastutil.objects.ObjectArrayList;
import java.math.BigDecimal;
import java.util.Comparator;
import java.util.TreeMap;

/**
 * Memory-optimized order book using primitive collections.
 * Uses fastutil library for reduced memory footprint and improved cache locality.
 */
public class OptimizedOrderBook {
    // Price levels sorted by price
    private final TreeMap<Long, PriceLevel> bidLevels;  // Descending
    private final TreeMap<Long, PriceLevel> askLevels;  // Ascending

    // Order ID -> Order mapping for O(1) lookup
    private final Long2ObjectOpenHashMap<Order> orderIndex;

    // Price precision (e.g., 8 decimals = 100_000_000)
    private final long pricePrecision;

    public OptimizedOrderBook(int priceDecimals) {
        this.pricePrecision = (long) Math.pow(10, priceDecimals);
        this.bidLevels = new TreeMap<>(Comparator.reverseOrder());
        this.askLevels = new TreeMap<>();
        this.orderIndex = new Long2ObjectOpenHashMap<>(10000);
    }

    /**
     * Add order to the book. O(log N) for tree operations.
     */
    public void addOrder(Order order) {
        long priceKey = toPriceKey(order.getPrice());
        TreeMap<Long, PriceLevel> levels = order.isBuy() ? bidLevels : askLevels;

        PriceLevel level = levels.computeIfAbsent(priceKey, k -> new PriceLevel(priceKey));
        level.addOrder(order);
        orderIndex.put(order.getId(), order);
    }

    /**
     * Remove order from the book. O(log N) for tree operations.
     */
    public Order removeOrder(long orderId) {
        Order order = orderIndex.remove(orderId);
        if (order == null) return null;

        long priceKey = toPriceKey(order.getPrice());
        TreeMap<Long, PriceLevel> levels = order.isBuy() ? bidLevels : askLevels;

        PriceLevel level = levels.get(priceKey);
        if (level != null) {
            level.removeOrder(order);
            if (level.isEmpty()) {
                levels.remove(priceKey);
            }
        }

        return order;
    }

    /**
     * Get best bid price. O(1) with TreeMap.firstKey()
     */
    public BigDecimal getBestBid() {
        if (bidLevels.isEmpty()) return null;
        return fromPriceKey(bidLevels.firstKey());
    }

    /**
     * Get best ask price. O(1) with TreeMap.firstKey()
     */
    public BigDecimal getBestAsk() {
        if (askLevels.isEmpty()) return null;
        return fromPriceKey(askLevels.firstKey());
    }

    /**
     * Match incoming order against the book.
     */
    public MatchResult match(Order incomingOrder) {
        MatchResult result = new MatchResult();
        TreeMap<Long, PriceLevel> oppositeLevels =
            incomingOrder.isBuy() ? askLevels : bidLevels;

        BigDecimal remainingQty = incomingOrder.getRemainingQuantity();

        while (remainingQty.compareTo(BigDecimal.ZERO) > 0 && !oppositeLevels.isEmpty()) {
            Long bestPriceKey = oppositeLevels.firstKey();
            BigDecimal bestPrice = fromPriceKey(bestPriceKey);

            // Check if prices cross
            if (incomingOrder.isBuy()) {
                if (incomingOrder.getPrice().compareTo(bestPrice) < 0) break;
            } else {
                if (incomingOrder.getPrice().compareTo(bestPrice) > 0) break;
            }

            PriceLevel level = oppositeLevels.get(bestPriceKey);

            // Match against orders at this price level
            while (remainingQty.compareTo(BigDecimal.ZERO) > 0 && !level.isEmpty()) {
                Order restingOrder = level.getFirstOrder();
                BigDecimal fillQty = remainingQty.min(restingOrder.getRemainingQuantity());

                // Create trade
                Trade trade = new Trade(
                    incomingOrder.getId(),
                    restingOrder.getId(),
                    bestPrice,
                    fillQty,
                    incomingOrder.isBuy()
                );
                result.addTrade(trade);

                // Update quantities
                remainingQty = remainingQty.subtract(fillQty);
                restingOrder.fill(fillQty);

                // Remove filled order
                if (restingOrder.isFilled()) {
                    level.removeOrder(restingOrder);
                    orderIndex.remove(restingOrder.getId());
                }
            }

            // Remove empty price level
            if (level.isEmpty()) {
                oppositeLevels.remove(bestPriceKey);
            }
        }

        // Update incoming order
        incomingOrder.setRemainingQuantity(remainingQty);
        result.setRemainingQuantity(remainingQty);

        return result;
    }

    private long toPriceKey(BigDecimal price) {
        return price.multiply(BigDecimal.valueOf(pricePrecision)).longValue();
    }

    private BigDecimal fromPriceKey(long priceKey) {
        return BigDecimal.valueOf(priceKey).divide(BigDecimal.valueOf(pricePrecision));
    }

    /**
     * Price level containing orders at the same price.
     * Uses FIFO ordering for price-time priority.
     */
    static class PriceLevel {
        private final long priceKey;
        private final ObjectArrayList<Order> orders;
        private BigDecimal totalQuantity;

        PriceLevel(long priceKey) {
            this.priceKey = priceKey;
            this.orders = new ObjectArrayList<>();
            this.totalQuantity = BigDecimal.ZERO;
        }

        void addOrder(Order order) {
            orders.add(order);
            totalQuantity = totalQuantity.add(order.getRemainingQuantity());
        }

        void removeOrder(Order order) {
            orders.remove(order);
            totalQuantity = totalQuantity.subtract(order.getRemainingQuantity());
        }

        Order getFirstOrder() {
            return orders.isEmpty() ? null : orders.get(0);
        }

        boolean isEmpty() {
            return orders.isEmpty();
        }

        BigDecimal getTotalQuantity() {
            return totalQuantity;
        }
    }
}
```

### 1.2 JVM 튜닝

```bash
# jvm-options.conf
# Memory settings
-Xms8g
-Xmx8g
-XX:MaxDirectMemorySize=2g

# GC settings - Use ZGC for low latency
-XX:+UseZGC
-XX:+ZGenerational
-XX:ZCollectionInterval=0
-XX:ZAllocationSpikeTolerance=5

# For G1GC alternative (Java 11+)
# -XX:+UseG1GC
# -XX:MaxGCPauseMillis=10
# -XX:G1HeapRegionSize=16m
# -XX:+ParallelRefProcEnabled

# JIT Compiler optimization
-XX:+TieredCompilation
-XX:CompileThreshold=1000
-XX:+UseCompressedOops
-XX:+UseCompressedClassPointers

# Thread stack size
-Xss512k

# Large pages for better TLB utilization
-XX:+UseLargePages
-XX:LargePageSizeInBytes=2m

# Disable biased locking (removed in Java 15+)
# -XX:-UseBiasedLocking

# String deduplication
-XX:+UseStringDeduplication

# Native memory tracking (for debugging)
-XX:NativeMemoryTracking=summary

# GC logging
-Xlog:gc*:file=/var/log/exchange/gc.log:time,uptime:filecount=10,filesize=100m
```

### 1.3 Hot Path 최적화

```java
// src/main/java/com/exchange/engine/processor/HotPathProcessor.java
package com.exchange.engine.processor;

import com.exchange.engine.model.Order;
import com.exchange.engine.orderbook.OptimizedOrderBook;
import com.lmax.disruptor.EventHandler;
import com.lmax.disruptor.RingBuffer;
import com.lmax.disruptor.dsl.Disruptor;
import com.lmax.disruptor.util.DaemonThreadFactory;

/**
 * High-performance order processor using LMAX Disruptor.
 * Achieves millions of operations per second with predictable latency.
 */
public class HotPathProcessor implements EventHandler<OrderEvent> {
    private final OptimizedOrderBook orderBook;
    private final RingBuffer<TradeEvent> tradeRingBuffer;

    // Thread-local for avoiding allocation in hot path
    private final ThreadLocal<MatchContext> matchContext =
        ThreadLocal.withInitial(MatchContext::new);

    public HotPathProcessor(
        OptimizedOrderBook orderBook,
        RingBuffer<TradeEvent> tradeRingBuffer
    ) {
        this.orderBook = orderBook;
        this.tradeRingBuffer = tradeRingBuffer;
    }

    @Override
    public void onEvent(OrderEvent event, long sequence, boolean endOfBatch) {
        // Hot path - minimize allocations and branching
        switch (event.getType()) {
            case NEW_ORDER:
                processNewOrder(event.getOrder());
                break;
            case CANCEL_ORDER:
                processCancelOrder(event.getOrderId());
                break;
            case MODIFY_ORDER:
                processModifyOrder(event);
                break;
        }

        // Clear event for reuse
        event.clear();
    }

    private void processNewOrder(Order order) {
        // Get thread-local context to avoid allocation
        MatchContext ctx = matchContext.get();
        ctx.reset();

        // Match order
        MatchResult result = orderBook.match(order);

        // Publish trades (batch if end of batch)
        for (Trade trade : result.getTrades()) {
            publishTrade(trade);
        }

        // Add remaining to book if not fully filled
        if (!order.isFilled() && order.isLimit()) {
            orderBook.addOrder(order);
        }
    }

    private void processCancelOrder(long orderId) {
        Order removed = orderBook.removeOrder(orderId);
        if (removed != null) {
            publishOrderCancelled(removed);
        }
    }

    private void processModifyOrder(OrderEvent event) {
        // Cancel and replace
        Order removed = orderBook.removeOrder(event.getOrderId());
        if (removed != null) {
            Order newOrder = event.getOrder();
            newOrder.setId(removed.getId());
            processNewOrder(newOrder);
        }
    }

    private void publishTrade(Trade trade) {
        long sequence = tradeRingBuffer.next();
        try {
            TradeEvent tradeEvent = tradeRingBuffer.get(sequence);
            tradeEvent.setTrade(trade);
        } finally {
            tradeRingBuffer.publish(sequence);
        }
    }

    private void publishOrderCancelled(Order order) {
        // Implementation for cancel notification
    }

    /**
     * Thread-local context to avoid allocations in hot path.
     */
    static class MatchContext {
        private final ObjectArrayList<Trade> trades = new ObjectArrayList<>();

        void reset() {
            trades.clear();
        }

        void addTrade(Trade trade) {
            trades.add(trade);
        }
    }
}
```

## 2. 네트워크 최적화

### 2.1 Protocol Buffers

```protobuf
// proto/exchange.proto
syntax = "proto3";

package exchange;

option java_package = "com.exchange.proto";
option java_outer_classname = "ExchangeProto";

message Order {
    int64 id = 1;
    int64 user_id = 2;
    string symbol = 3;
    OrderSide side = 4;
    OrderType type = 5;
    string price = 6;       // Decimal as string
    string quantity = 7;    // Decimal as string
    int32 leverage = 8;
    MarginType margin_type = 9;
    bool reduce_only = 10;
    TimeInForce time_in_force = 11;
    int64 timestamp = 12;
}

enum OrderSide {
    SIDE_UNKNOWN = 0;
    BUY = 1;
    SELL = 2;
}

enum OrderType {
    TYPE_UNKNOWN = 0;
    LIMIT = 1;
    MARKET = 2;
    STOP_LIMIT = 3;
    STOP_MARKET = 4;
}

enum MarginType {
    MARGIN_UNKNOWN = 0;
    CROSS = 1;
    ISOLATED = 2;
}

enum TimeInForce {
    TIF_UNKNOWN = 0;
    GTC = 1;  // Good Till Cancel
    IOC = 2;  // Immediate Or Cancel
    FOK = 3;  // Fill Or Kill
    GTX = 4;  // Post Only
}

message Trade {
    int64 id = 1;
    string symbol = 2;
    int64 buyer_order_id = 3;
    int64 seller_order_id = 4;
    string price = 5;
    string quantity = 6;
    bool is_buyer_maker = 7;
    int64 timestamp = 8;
}

message OrderBookLevel {
    string price = 1;
    string quantity = 2;
    int32 order_count = 3;
}

message OrderBookSnapshot {
    string symbol = 1;
    repeated OrderBookLevel bids = 2;
    repeated OrderBookLevel asks = 3;
    int64 last_update_id = 4;
    int64 timestamp = 5;
}

// Batch messages for efficiency
message OrderBatch {
    repeated Order orders = 1;
}

message TradeBatch {
    repeated Trade trades = 1;
}
```

### 2.2 WebSocket 최적화

```typescript
// src/websocket/optimized-gateway.ts
import { Server, Socket } from 'socket.io';
import { createAdapter } from '@socket.io/redis-adapter';
import Redis from 'ioredis';
import * as msgpack from 'msgpack-lite';

interface SubscriptionMap {
  orderbook: Set<string>;  // symbols
  trades: Set<string>;
  ticker: Set<string>;
  kline: Map<string, Set<string>>;  // symbol -> intervals
}

export class OptimizedWebSocketGateway {
  private io: Server;
  private subscriptions: Map<string, SubscriptionMap> = new Map();

  // Message batching
  private batchBuffer: Map<string, any[]> = new Map();
  private batchInterval: NodeJS.Timer;
  private readonly BATCH_INTERVAL_MS = 10;  // 10ms batching

  constructor(
    server: any,
    redisClient: Redis.Cluster
  ) {
    this.io = new Server(server, {
      cors: { origin: '*' },
      transports: ['websocket'],  // WebSocket only, no polling
      pingInterval: 25000,
      pingTimeout: 60000,
      maxHttpBufferSize: 1e6,  // 1MB
    });

    // Redis adapter for horizontal scaling
    const pubClient = redisClient.duplicate();
    const subClient = redisClient.duplicate();
    this.io.adapter(createAdapter(pubClient, subClient));

    this.setupHandlers();
    this.startBatchProcessor();
  }

  private setupHandlers(): void {
    this.io.on('connection', (socket: Socket) => {
      console.log(`Client connected: ${socket.id}`);

      // Initialize subscription map
      this.subscriptions.set(socket.id, {
        orderbook: new Set(),
        trades: new Set(),
        ticker: new Set(),
        kline: new Map(),
      });

      // Handle subscriptions
      socket.on('subscribe', (channels: string[]) => {
        this.handleSubscribe(socket, channels);
      });

      socket.on('unsubscribe', (channels: string[]) => {
        this.handleUnsubscribe(socket, channels);
      });

      socket.on('disconnect', () => {
        this.subscriptions.delete(socket.id);
        console.log(`Client disconnected: ${socket.id}`);
      });
    });
  }

  private handleSubscribe(socket: Socket, channels: string[]): void {
    const subs = this.subscriptions.get(socket.id);
    if (!subs) return;

    for (const channel of channels) {
      // Parse channel: orderbook@BTCUSDT, trades@ETHUSDT, kline@BTCUSDT@1m
      const parts = channel.split('@');
      const type = parts[0];
      const symbol = parts[1];

      switch (type) {
        case 'orderbook':
          subs.orderbook.add(symbol);
          socket.join(`orderbook:${symbol}`);
          break;
        case 'trades':
          subs.trades.add(symbol);
          socket.join(`trades:${symbol}`);
          break;
        case 'ticker':
          subs.ticker.add(symbol);
          socket.join(`ticker:${symbol}`);
          break;
        case 'kline':
          const interval = parts[2];
          if (!subs.kline.has(symbol)) {
            subs.kline.set(symbol, new Set());
          }
          subs.kline.get(symbol)!.add(interval);
          socket.join(`kline:${symbol}:${interval}`);
          break;
      }
    }

    socket.emit('subscribed', { channels });
  }

  private handleUnsubscribe(socket: Socket, channels: string[]): void {
    const subs = this.subscriptions.get(socket.id);
    if (!subs) return;

    for (const channel of channels) {
      const parts = channel.split('@');
      const type = parts[0];
      const symbol = parts[1];

      switch (type) {
        case 'orderbook':
          subs.orderbook.delete(symbol);
          socket.leave(`orderbook:${symbol}`);
          break;
        case 'trades':
          subs.trades.delete(symbol);
          socket.leave(`trades:${symbol}`);
          break;
        case 'ticker':
          subs.ticker.delete(symbol);
          socket.leave(`ticker:${symbol}`);
          break;
        case 'kline':
          const interval = parts[2];
          subs.kline.get(symbol)?.delete(interval);
          socket.leave(`kline:${symbol}:${interval}`);
          break;
      }
    }

    socket.emit('unsubscribed', { channels });
  }

  // Batch processor for efficient broadcasting
  private startBatchProcessor(): void {
    this.batchInterval = setInterval(() => {
      this.flushBatches();
    }, this.BATCH_INTERVAL_MS);
  }

  private flushBatches(): void {
    for (const [room, messages] of this.batchBuffer.entries()) {
      if (messages.length === 0) continue;

      // Use MessagePack for efficient serialization
      const packed = msgpack.encode(messages);
      this.io.to(room).emit('batch', packed);

      messages.length = 0;  // Clear array without reallocating
    }
  }

  // Public methods for broadcasting
  public broadcastOrderBookUpdate(symbol: string, update: any): void {
    const room = `orderbook:${symbol}`;
    this.addToBatch(room, { type: 'orderbook', data: update });
  }

  public broadcastTrade(symbol: string, trade: any): void {
    const room = `trades:${symbol}`;
    this.addToBatch(room, { type: 'trade', data: trade });
  }

  public broadcastTicker(symbol: string, ticker: any): void {
    const room = `ticker:${symbol}`;
    this.addToBatch(room, { type: 'ticker', data: ticker });
  }

  public broadcastKline(symbol: string, interval: string, kline: any): void {
    const room = `kline:${symbol}:${interval}`;
    this.addToBatch(room, { type: 'kline', data: kline });
  }

  private addToBatch(room: string, message: any): void {
    if (!this.batchBuffer.has(room)) {
      this.batchBuffer.set(room, []);
    }
    this.batchBuffer.get(room)!.push(message);
  }

  public getStats(): {
    connections: number;
    rooms: number;
  } {
    return {
      connections: this.io.sockets.sockets.size,
      rooms: this.io.sockets.adapter.rooms.size,
    };
  }
}
```

### 2.3 TCP/Kernel 튜닝

```bash
# /etc/sysctl.d/99-exchange.conf

# Network buffer sizes
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.core.rmem_default = 16777216
net.core.wmem_default = 16777216
net.ipv4.tcp_rmem = 4096 87380 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728

# TCP optimization
net.ipv4.tcp_fastopen = 3
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_keepalive_time = 300
net.ipv4.tcp_keepalive_probes = 5
net.ipv4.tcp_keepalive_intvl = 15

# Connection handling
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_max_tw_buckets = 2000000

# Congestion control
net.ipv4.tcp_congestion_control = bbr
net.core.default_qdisc = fq

# Memory
vm.swappiness = 10
vm.dirty_ratio = 60
vm.dirty_background_ratio = 2
```

## 3. 데이터베이스 최적화

### 3.1 PostgreSQL 튜닝

```ini
# postgresql.conf

# Memory
shared_buffers = 8GB                    # 25% of RAM
effective_cache_size = 24GB             # 75% of RAM
work_mem = 256MB                        # For complex sorts
maintenance_work_mem = 2GB              # For VACUUM, INDEX
wal_buffers = 64MB

# Parallelism
max_worker_processes = 16
max_parallel_workers_per_gather = 4
max_parallel_workers = 16
max_parallel_maintenance_workers = 4

# WAL
wal_level = replica
max_wal_size = 4GB
min_wal_size = 1GB
checkpoint_completion_target = 0.9
checkpoint_timeout = 15min

# Query planner
random_page_cost = 1.1                  # For SSD
effective_io_concurrency = 200          # For SSD
default_statistics_target = 100

# Connections
max_connections = 500
superuser_reserved_connections = 3

# Logging
log_min_duration_statement = 100        # Log queries > 100ms
log_checkpoints = on
log_lock_waits = on
log_temp_files = 0
```

### 3.2 쿼리 최적화

```sql
-- Explain analyze for slow queries
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM orders
WHERE user_id = '...' AND status = 'NEW'
ORDER BY created_at DESC LIMIT 100;

-- Partial indexes for common queries
CREATE INDEX idx_orders_active ON orders(user_id, created_at DESC)
WHERE status IN ('NEW', 'PARTIALLY_FILLED');

-- BRIN index for time-series data
CREATE INDEX idx_trades_time_brin ON trades
USING BRIN(trade_time) WITH (pages_per_range = 128);

-- Covering index to avoid table lookups
CREATE INDEX idx_positions_cover ON positions(user_id, symbol)
INCLUDE (side, size, entry_price, leverage);

-- Optimize VACUUM
ALTER TABLE trades SET (
    autovacuum_vacuum_scale_factor = 0.01,
    autovacuum_analyze_scale_factor = 0.005
);
```

### 3.3 Redis 파이프라이닝

```typescript
// src/cache/pipeline-cache.ts
import Redis from 'ioredis';

export class PipelineCache {
  private cluster: Redis.Cluster;

  constructor(cluster: Redis.Cluster) {
    this.cluster = cluster;
  }

  /**
   * Batch multiple operations for efficiency.
   * Reduces network round trips from N to 1.
   */
  async batchOperations<T>(
    operations: Array<{
      command: string;
      args: any[];
    }>
  ): Promise<T[]> {
    const pipeline = this.cluster.pipeline();

    for (const op of operations) {
      (pipeline as any)[op.command](...op.args);
    }

    const results = await pipeline.exec();

    return results?.map((result, index) => {
      if (result[0]) {
        throw new Error(`Operation ${index} failed: ${result[0].message}`);
      }
      return result[1] as T;
    }) || [];
  }

  /**
   * Efficient multi-key get with pipelining.
   */
  async multiGet(keys: string[]): Promise<Map<string, string | null>> {
    const pipeline = this.cluster.pipeline();

    for (const key of keys) {
      pipeline.get(key);
    }

    const results = await pipeline.exec();
    const map = new Map<string, string | null>();

    results?.forEach((result, index) => {
      map.set(keys[index], result[1] as string | null);
    });

    return map;
  }

  /**
   * Efficient multi-key set with pipelining.
   */
  async multiSet(
    entries: Array<{ key: string; value: string; ttl?: number }>
  ): Promise<void> {
    const pipeline = this.cluster.pipeline();

    for (const entry of entries) {
      if (entry.ttl) {
        pipeline.setex(entry.key, entry.ttl, entry.value);
      } else {
        pipeline.set(entry.key, entry.value);
      }
    }

    await pipeline.exec();
  }

  /**
   * Batch update order book levels.
   */
  async batchUpdateOrderBook(
    symbol: string,
    updates: Array<{
      side: 'bid' | 'ask';
      price: string;
      quantity: string;
    }>
  ): Promise<void> {
    const pipeline = this.cluster.pipeline();

    for (const update of updates) {
      const key = `orderbook:${symbol}:${update.side}s`;
      const price = parseFloat(update.price);

      if (parseFloat(update.quantity) === 0) {
        pipeline.zremrangebyscore(key, price, price);
      } else {
        pipeline.zadd(key, price, JSON.stringify({
          price: update.price,
          quantity: update.quantity
        }));
      }
    }

    await pipeline.exec();
  }
}
```

## 4. 애플리케이션 레벨 최적화

### 4.1 비동기 처리 패턴

```typescript
// src/services/async-order.service.ts
import { Injectable } from '@nestjs/common';
import { InjectQueue } from '@nestjs/bull';
import { Queue } from 'bull';

@Injectable()
export class AsyncOrderService {
  constructor(
    @InjectQueue('orders') private orderQueue: Queue,
    @InjectQueue('notifications') private notificationQueue: Queue,
  ) {}

  /**
   * Process order asynchronously with priority.
   */
  async submitOrder(order: CreateOrderDto): Promise<{ orderId: string }> {
    // Generate order ID immediately
    const orderId = generateOrderId();

    // Add to queue with priority
    await this.orderQueue.add(
      'process',
      { orderId, ...order },
      {
        priority: this.calculatePriority(order),
        attempts: 3,
        backoff: {
          type: 'exponential',
          delay: 1000,
        },
        removeOnComplete: true,
        removeOnFail: false,
      }
    );

    // Return immediately
    return { orderId };
  }

  private calculatePriority(order: CreateOrderDto): number {
    // Market orders get highest priority
    if (order.type === 'MARKET') return 1;
    // Reduce-only orders get high priority (liquidation protection)
    if (order.reduceOnly) return 2;
    // Regular limit orders
    return 3;
  }

  /**
   * Fan-out notifications efficiently.
   */
  async notifyOrderUpdate(orderId: string, update: OrderUpdate): Promise<void> {
    // Non-blocking notification
    await this.notificationQueue.add(
      'order-update',
      { orderId, update },
      {
        priority: 3,
        attempts: 2,
        removeOnComplete: true,
      }
    );
  }
}
```

### 4.2 캐싱 전략

```typescript
// src/cache/multi-tier-cache.ts
import { Injectable } from '@nestjs/common';
import Redis from 'ioredis';
import NodeCache from 'node-cache';

@Injectable()
export class MultiTierCache {
  private localCache: NodeCache;  // L1: In-memory
  private redis: Redis.Cluster;   // L2: Redis

  constructor(redisCluster: Redis.Cluster) {
    this.localCache = new NodeCache({
      stdTTL: 5,          // 5 seconds default
      checkperiod: 1,
      useClones: false,   // Performance: no cloning
    });
    this.redis = redisCluster;
  }

  /**
   * Get with fallback through cache tiers.
   */
  async get<T>(
    key: string,
    fetcher: () => Promise<T>,
    options: {
      localTTL?: number;
      redisTTL?: number;
    } = {}
  ): Promise<T> {
    const { localTTL = 5, redisTTL = 60 } = options;

    // L1: Check local cache
    const localValue = this.localCache.get<T>(key);
    if (localValue !== undefined) {
      return localValue;
    }

    // L2: Check Redis
    const redisValue = await this.redis.get(key);
    if (redisValue) {
      const parsed = JSON.parse(redisValue) as T;
      this.localCache.set(key, parsed, localTTL);
      return parsed;
    }

    // L3: Fetch from source
    const value = await fetcher();

    // Populate caches
    await this.redis.setex(key, redisTTL, JSON.stringify(value));
    this.localCache.set(key, value, localTTL);

    return value;
  }

  /**
   * Invalidate across all tiers.
   */
  async invalidate(key: string): Promise<void> {
    this.localCache.del(key);
    await this.redis.del(key);
  }

  /**
   * Batch invalidation with pattern.
   */
  async invalidatePattern(pattern: string): Promise<void> {
    // Clear local cache (scan is expensive, clear all for simplicity)
    this.localCache.flushAll();

    // Clear Redis with pattern
    const keys = await this.redis.keys(pattern);
    if (keys.length > 0) {
      await this.redis.del(...keys);
    }
  }
}
```

## 5. 모니터링 및 프로파일링

### 5.1 성능 메트릭

```typescript
// src/monitoring/performance-metrics.ts
import { Registry, Histogram, Counter, Gauge } from 'prom-client';

export class PerformanceMetrics {
  private registry: Registry;

  // Latency histograms
  public orderLatency: Histogram;
  public matchingLatency: Histogram;
  public dbQueryLatency: Histogram;
  public wsLatency: Histogram;

  // Throughput counters
  public ordersProcessed: Counter;
  public tradesExecuted: Counter;
  public wsMessages: Counter;

  // System gauges
  public activeConnections: Gauge;
  public queueDepth: Gauge;
  public memoryUsage: Gauge;

  constructor(registry: Registry) {
    this.registry = registry;
    this.initializeMetrics();
  }

  private initializeMetrics(): void {
    this.orderLatency = new Histogram({
      name: 'order_processing_latency_ms',
      help: 'Order processing latency in milliseconds',
      labelNames: ['type', 'symbol'],
      buckets: [0.1, 0.5, 1, 2, 5, 10, 25, 50, 100],
      registers: [this.registry],
    });

    this.matchingLatency = new Histogram({
      name: 'matching_engine_latency_ms',
      help: 'Matching engine latency in milliseconds',
      labelNames: ['symbol'],
      buckets: [0.01, 0.05, 0.1, 0.5, 1, 2, 5, 10],
      registers: [this.registry],
    });

    this.dbQueryLatency = new Histogram({
      name: 'database_query_latency_ms',
      help: 'Database query latency in milliseconds',
      labelNames: ['operation', 'table'],
      buckets: [0.5, 1, 2, 5, 10, 25, 50, 100, 250],
      registers: [this.registry],
    });

    this.ordersProcessed = new Counter({
      name: 'orders_processed_total',
      help: 'Total orders processed',
      labelNames: ['type', 'status'],
      registers: [this.registry],
    });

    this.tradesExecuted = new Counter({
      name: 'trades_executed_total',
      help: 'Total trades executed',
      labelNames: ['symbol'],
      registers: [this.registry],
    });

    this.activeConnections = new Gauge({
      name: 'active_websocket_connections',
      help: 'Number of active WebSocket connections',
      registers: [this.registry],
    });

    this.queueDepth = new Gauge({
      name: 'order_queue_depth',
      help: 'Current depth of order processing queue',
      labelNames: ['queue'],
      registers: [this.registry],
    });
  }

  // Helper methods for timing
  startTimer(): () => number {
    const start = process.hrtime.bigint();
    return () => {
      const end = process.hrtime.bigint();
      return Number(end - start) / 1_000_000;  // Convert to ms
    };
  }
}
```

### 5.2 Grafana 대시보드

```json
{
  "dashboard": {
    "title": "Exchange Performance Dashboard",
    "panels": [
      {
        "title": "Order Latency (p99)",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.99, rate(order_processing_latency_ms_bucket[5m]))",
            "legendFormat": "p99"
          },
          {
            "expr": "histogram_quantile(0.95, rate(order_processing_latency_ms_bucket[5m]))",
            "legendFormat": "p95"
          },
          {
            "expr": "histogram_quantile(0.50, rate(order_processing_latency_ms_bucket[5m]))",
            "legendFormat": "p50"
          }
        ]
      },
      {
        "title": "Orders per Second",
        "type": "graph",
        "targets": [
          {
            "expr": "sum(rate(orders_processed_total[1m]))",
            "legendFormat": "Orders/sec"
          }
        ]
      },
      {
        "title": "Matching Engine Latency",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.99, rate(matching_engine_latency_ms_bucket[5m]))",
            "legendFormat": "{{symbol}} p99"
          }
        ]
      },
      {
        "title": "Active WebSocket Connections",
        "type": "stat",
        "targets": [
          {
            "expr": "sum(active_websocket_connections)",
            "legendFormat": "Connections"
          }
        ]
      },
      {
        "title": "Database Query Latency",
        "type": "heatmap",
        "targets": [
          {
            "expr": "rate(database_query_latency_ms_bucket[5m])",
            "legendFormat": "{{operation}}"
          }
        ]
      }
    ]
  }
}
```

## 6. 성능 테스트

### 6.1 부하 테스트 스크립트

```typescript
// test/load/order-load-test.ts
import autocannon from 'autocannon';

async function runLoadTest(): Promise<void> {
  const result = await autocannon({
    url: 'http://localhost:3000',
    connections: 100,
    duration: 60,
    pipelining: 10,
    requests: [
      {
        method: 'POST',
        path: '/api/v1/orders',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token',
        },
        body: JSON.stringify({
          symbol: 'BTCUSDT',
          side: 'BUY',
          type: 'LIMIT',
          price: '50000',
          quantity: '0.001',
          leverage: 10,
        }),
      },
    ],
  });

  console.log('Load Test Results:');
  console.log(`  Requests/sec: ${result.requests.average}`);
  console.log(`  Latency (avg): ${result.latency.average}ms`);
  console.log(`  Latency (p99): ${result.latency.p99}ms`);
  console.log(`  Errors: ${result.errors}`);
  console.log(`  Timeouts: ${result.timeouts}`);
}

runLoadTest();
```

### 6.2 벤치마크 결과 템플릿

```markdown
## Performance Benchmark Report

### Test Environment
- **Date**: YYYY-MM-DD
- **Hardware**: AWS c5.4xlarge (16 vCPU, 32GB RAM)
- **JVM**: OpenJDK 17 with ZGC
- **Database**: PostgreSQL 15 (db.r5.2xlarge)
- **Redis**: Redis Cluster 7 (3 masters, 3 replicas)

### Results

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Order Latency (p50) | 15ms | 3ms | 80% |
| Order Latency (p99) | 45ms | 8ms | 82% |
| Throughput | 10K/s | 75K/s | 650% |
| WebSocket Latency | 150ms | 35ms | 77% |
| Memory Usage | 12GB | 6GB | 50% |
| GC Pause Time | 50ms | <1ms | 98% |

### Key Optimizations Applied
1. Object pooling for Order instances
2. ZGC for low-latency garbage collection
3. Protocol Buffers for serialization
4. Redis pipelining for batch operations
5. Connection pooling with PgBouncer
```

## 체크리스트

### 구현 우선순위

- [ ] **Phase 1: Critical Path**
  - [ ] JVM 튜닝 (ZGC 설정)
  - [ ] Object pooling 구현
  - [ ] Redis 파이프라이닝 적용

- [ ] **Phase 2: Network**
  - [ ] Protocol Buffers 도입
  - [ ] WebSocket 배치 처리
  - [ ] TCP 커널 튜닝

- [ ] **Phase 3: Database**
  - [ ] PostgreSQL 튜닝
  - [ ] 인덱스 최적화
  - [ ] 쿼리 리팩토링

- [ ] **Phase 4: Monitoring**
  - [ ] 메트릭 수집 구현
  - [ ] Grafana 대시보드 구성
  - [ ] 알림 규칙 설정

## 참고 자료

- [LMAX Disruptor](https://lmax-exchange.github.io/disruptor/)
- [Java Performance: The Definitive Guide](https://www.oreilly.com/library/view/java-performance-the/9781449363512/)
- [High Performance Browser Networking](https://hpbn.co/)
- [PostgreSQL Performance Tuning](https://wiki.postgresql.org/wiki/Performance_Optimization)
- [Redis Best Practices](https://redis.io/docs/management/optimization/)

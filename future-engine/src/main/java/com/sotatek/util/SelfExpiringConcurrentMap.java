package com.sotatek.util;

import com.google.common.primitives.Ints;
import com.sotatek.future.enums.CommandCode;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ConcurrentMap;
import java.util.concurrent.DelayQueue;
import java.util.concurrent.Delayed;
import java.util.concurrent.TimeUnit;
import java.util.function.BiFunction;

public class SelfExpiringConcurrentMap<K, V> {

  private final ConcurrentMap<K, V> map = new ConcurrentHashMap<>();
  private final BlockingQueue<DelayedOrder> insertedKeys = new DelayQueue<>();

  private Long lifeTime = 3600000L; // 60 minutes

  public V compute(K key, BiFunction<? super K, ? super V, ? extends V> remappingFunction) {
    this.removeExpiredKeys();
    V result = map.compute(key, remappingFunction);
    // We need to keep all PLACE ORDER command, only remove CANCEL ORDER command
    if (result == CommandCode.CANCEL_ORDER) {
      this.insertedKeys.add(new DelayedOrder(key, this.lifeTime));
    }
    return result;
  }

  protected void removeExpiredKeys() {
    while (true) {
      DelayedOrder delayedKey = insertedKeys.poll();
      if (delayedKey != null) {
        K key = delayedKey.getData();
        map.compute(key, (k, oldValue) -> null);
      } else {
        break;
      }
    }
  }

  public Long getLifeTime() {
    return lifeTime;
  }

  public void setLifeTime(Long lifeTime) {
    this.lifeTime = lifeTime;
  }

  private class DelayedOrder implements Delayed {

    private final K data;
    private final long expireTime;

    public DelayedOrder(K data, long lifeTime) {
      this.data = data;
      this.expireTime = System.currentTimeMillis() + lifeTime;
    }

    @Override
    public long getDelay(TimeUnit timeUnit) {
      long diff = this.expireTime - System.currentTimeMillis();
      return timeUnit.convert(diff, TimeUnit.MILLISECONDS);
    }

    @Override
    public int compareTo(Delayed o) {
      return Ints.saturatedCast(this.expireTime - ((DelayedOrder) o).expireTime);
    }

    public K getData() {
      return data;
    }
  }
}

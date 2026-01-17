package com.sotatek.future.thread;

import com.sotatek.future.engine.Trigger;
import java.util.Map;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class TriggerThread extends Thread {
  private long interval = 5000;

  private Map<String, Trigger> triggers;

  public TriggerThread(Map<String, Trigger> triggers) {
    this.triggers = triggers;
  }

  @Override
  public void run() {
    try {
      while (true) {
        long startTime = System.currentTimeMillis();
        for (Trigger trigger : triggers.values()) {
          trigger.startTrigger();
        }
        long sleepTime = this.interval - (System.currentTimeMillis() - startTime);
        if (sleepTime > 0) {
          Thread.sleep(sleepTime);
        }
      }
    } catch (Exception e) {
      log.error(e.getMessage(), e);
    }
  }
}

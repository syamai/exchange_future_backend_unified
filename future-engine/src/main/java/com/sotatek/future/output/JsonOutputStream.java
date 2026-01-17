package com.sotatek.future.output;

import com.google.gson.Gson;
import com.sotatek.future.engine.MatchingEngineConfig;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.util.TimeUtil;
import com.sotatek.future.util.json.JsonUtil;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Queue;
import java.util.concurrent.ConcurrentLinkedQueue;
import java.util.concurrent.PriorityBlockingQueue;
import java.util.concurrent.TimeoutException;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public abstract class JsonOutputStream<T> extends BaseOutputStream<T> {

  private final Gson gson = JsonUtil.createGson();
  protected int batchSize = MatchingEngineConfig.OUTPUT_BATCH_SIZE;
  protected long currentId = 0;
  protected long sentId = 0;
  protected int threadCount = 4;
  protected Queue<T> queue = new ConcurrentLinkedQueue<>();
  protected Queue<Batch> batches = new ConcurrentLinkedQueue<>();
  protected PriorityBlockingQueue<Batch> pendingQueue =
      new PriorityBlockingQueue<>(1000, Comparator.comparingLong((Batch o) -> o.id));

  public JsonOutputStream() {}

  public JsonOutputStream(int batchSize) {
    this.batchSize = batchSize;
  }

  @Override
  public boolean connect() throws IOException, TimeoutException {
    List<Thread> checkThreads = new ArrayList<>();
    Thread batch = new BatchingThread();
    batch.setName("Batch");
    checkThreads.add(batch);
    batch.start();
    Thread serialization = new SerializationThread(0);
    serialization.setName("Serialize");
    checkThreads.add(serialization);
    serialization.start();
    Thread publish = new PublishThread();
    publish.setName("Publish");
    checkThreads.add(publish);
    publish.start();
    Thread checking = new CheckingStatusThread(checkThreads);
    checking.setName("Checking");
    checking.start();
    return true;
  }

  @Override
  public void write(T o) {
    queue.add(o);
    log.debug("queue size {}", queue.size());
  }

  @Override
  public void write(List<T> list) {
    queue.addAll(list);
    log.debug("queue size {}", queue.size());
  }

  @Override
  public void flush() {}

  public abstract void publish(String data);

  private class Batch {

    public long id;
    public List<T> data;
    public String serializedData;

    public Batch(long id, List<T> data) {
      this.id = id;
      this.data = data;
    }
  }

  private class BatchingThread extends Thread {
    @Override
    public void run() {
      while (true) {
        try {
          List<T> dataList = new ArrayList<>();
          T data;
          do {
            data = queue.poll();
            if (data != null) {
              dataList.add(data);
            }
          } while (data != null && dataList.size() <= batchSize);

          if (!dataList.isEmpty()) {
            batches.add(new Batch(currentId++, dataList));
          } else {
            TimeUtil.sleep(20);
          }
          if (batches.size() > 0) {
            log.debug("batches size {}", batches.size());
          }
        } catch (Exception e) {
          log.error("BatchingThread error", e);
        }
      }
    }
  }

  private class SerializationThread extends Thread {

    int id;

    public SerializationThread(int id) {
      this.id = id;
    }

    @Override
    public void run() {
      while (true) {
        List<T> batchData = null;
        try {
          Batch batch = batches.poll();
          if (batch != null) {
            batchData = new ArrayList<>(batch.data);
            if (batchSize > 0) {
              batch.serializedData = gson.toJson(batchData);
            } else {
              batch.serializedData = gson.toJson(batchData.get(0));
            }
            pendingQueue.add(batch);
            log.debug("Pending queue batch {}", pendingQueue.size());
          } else {
            TimeUtil.sleep(20);
          }
        } catch (Exception e) {
          log.error("SerializationThread error", e);
          log.error("batchData {}", batchData);
        }
      }
    }
  }

  public static int numOfTradesSentToKafka = 0;
  private class PublishThread extends Thread {
    @Override
    public void run() {
      while (true) {
        try {
          Batch batch = pendingQueue.poll();
          if (batch != null) {
            log.debug("Publish batch data size {} and {}", batch.data.size(), batch.serializedData);
            publish(batch.serializedData);
            sentId++;

            // Count number of trades sent to kafka
            for (T data: batch.data) {
              if (data instanceof CommandOutput) {
                if (((CommandOutput)data).getTrades() != null) {
                  JsonOutputStream.numOfTradesSentToKafka += ((CommandOutput)data).getTrades().size();
                }
              }

            }

          } else {
            TimeUtil.sleep(20);
          }
        } catch (Exception e) {
          log.error("PublishThread error", e);
        }
      }
    }
  }

  private class CheckingStatusThread extends Thread {
    List<Thread> checkingThreads;

    CheckingStatusThread(List<Thread> threads) {
      checkingThreads = threads;
    }

    @Override
    public void run() {
      while (true) {
        checkingThreads.forEach(
            t -> {
              log.debug(
                  "Thread id {} with name {} and status {}", t.getId(), t.getName(), t.getState());
            });
        TimeUtil.sleep(2000);
      }
    }
  }
}

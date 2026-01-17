package com.sotatek.future.input;

import com.google.gson.Gson;
import com.sotatek.future.util.json.JsonUtil;
import java.lang.reflect.Type;
import java.time.Duration;
import java.util.Arrays;
import java.util.Map;
import java.util.Properties;
import java.util.concurrent.atomic.AtomicReference;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.consumer.Consumer;
import org.apache.kafka.clients.consumer.ConsumerConfig;
import org.apache.kafka.clients.consumer.ConsumerRecords;
import org.apache.kafka.clients.consumer.KafkaConsumer;
import org.apache.kafka.common.utils.Utils;

@Slf4j
public class KafkaInputStream<T> extends BaseInputStream<T> {

  private final Map<String, Object> parameters;
  private final Gson gson = JsonUtil.createGson();
  private String topic;
  private Class dataClass;
  private Type type;
  private Consumer<String, String> consumer;

  public KafkaInputStream(Map<String, Object> parameters, String topic, Class dataClass) {
    this.parameters = parameters;
    this.topic = topic;
    this.dataClass = dataClass;
  }

  public KafkaInputStream(Map<String, Object> parameters, String topic, Type type) {
    this.parameters = parameters;
    this.topic = topic;
    this.type = type;
  }

  @Override
  public boolean connect() {

    String[] keys =
        new String[] {
          ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG,
          ConsumerConfig.GROUP_ID_CONFIG,
          ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG,
          ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG
        };
    Properties props = new Properties();
    for (String key : keys) {
      props.put(key, parameters.get(key));
    }
    props.put(ConsumerConfig.ENABLE_AUTO_COMMIT_CONFIG, "false");
    props.put(ConsumerConfig.AUTO_OFFSET_RESET_CONFIG, "earliest");
    //    props.put(ConsumerConfig.MAX_POLL_RECORDS_CONFIG, IKafkaConstants.MAX_POLL_RECORDS);
    //    props.put(ConsumerConfig.AUTO_OFFSET_RESET_CONFIG, IKafkaConstants.OFFSET_RESET_EARLIER);

    this.consumer = new KafkaConsumer<>(props);
    this.consumer.subscribe(Arrays.asList(this.topic));
    new InputThread().start();

    return true;
  }

  private void poll() {
    while (true) {
      if (this.isClosed) {
        break;
      }
      // to write error log
      AtomicReference<String> recordData = new AtomicReference<>();
      try {
        final ConsumerRecords<String, String> consumerRecords =
            consumer.poll(Duration.ofMillis(100));
        consumerRecords.forEach(
            record -> {
              //                log.debug("KafkaInputStream record {}", record.value());
              recordData.set(record.value());
              T command;
              if (this.dataClass != null) {
                command = (T) this.gson.fromJson(record.value(), this.dataClass);
              } else {
                command = (T) this.gson.fromJson(record.value(), this.type);
              }
              if (callback != null) {
                //                    log.debug("KafkaInputStream command {}", command);
                long pendingCount = this.callback.onNewData(command);
//                if (pendingCount > 100000) {
//                  Utils.sleep(50);
//                }
              }
            });
        consumer.commitAsync();
      } catch (Exception e) {
        log.atError().setCause(e).log("KafkaInputStream with data {} has error", recordData);
        consumer.commitAsync();
      }
    }
    consumer.close();
  }

  @Override
  public void close() {
    this.isClosed = true;
  }

  private class InputThread extends Thread {

    @Override
    public void run() {
      poll();
    }
  }
}

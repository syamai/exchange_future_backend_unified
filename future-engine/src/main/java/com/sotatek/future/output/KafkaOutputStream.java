package com.sotatek.future.output;

import java.io.IOException;
import java.util.Map;
import java.util.Properties;
import java.util.concurrent.TimeoutException;

import com.sotatek.future.engine.MatchingEngine;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.Producer;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.clients.producer.ProducerRecord;

@Slf4j
public class KafkaOutputStream<T> extends JsonOutputStream<T> {

  private final Map<String, Object> parameters;
  private Producer<String, String> producer;
  private String topic;

  public KafkaOutputStream(Map<String, Object> parameters, String topic) {
    super();
    this.parameters = parameters;
    this.topic = topic;
  }

  public KafkaOutputStream(Map<String, Object> parameters, String topic, int batchSize) {
    super(batchSize);
    this.parameters = parameters;
    this.topic = topic;
  }

  @Override
  public boolean connect() throws IOException, TimeoutException {
    super.connect();
    String[] keys =
        new String[] {
          ProducerConfig.BOOTSTRAP_SERVERS_CONFIG,
          ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG,
          ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG
        };
    Properties props = new Properties();
    for (String key : keys) {
      props.put(key, parameters.get(key));
    }
    props.put(ProducerConfig.MAX_REQUEST_SIZE_CONFIG, 104857600);
    props.put(ProducerConfig.BUFFER_MEMORY_CONFIG, 504857600);
    this.producer = new KafkaProducer<>(props);
    return true;
  }

  @Override
  public void close() {
    this.producer.close();
  }

  private boolean logStartPublish = true;
  @Override
  public void publish(String data) {
    if (this.logStartPublish) {
      log.info("Start publish");
      this.logStartPublish = false;
    }

    try {
      ProducerRecord<String, String> producerRecord = new ProducerRecord<>(topic, data);
//      producer.send(producerRecord).get();
      producer.send(producerRecord, (metadata, exception) -> {
        if (exception != null) {
          log.error("Kafka send failed", exception);
        }
      });
//      System.out.println("Publish time: " + (MatchingEngine.sumOfTime + (System.currentTimeMillis() - MatchingEngine.endTime)));
    } catch (Throwable e) {
      log.error(e.getMessage(), e);
      throw new RuntimeException(e.getMessage());
    }
  }
}

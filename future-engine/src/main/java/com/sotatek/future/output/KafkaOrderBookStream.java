package com.sotatek.future.output;

import com.google.gson.Gson;
import com.sotatek.future.entity.OrderBookEvent;
import com.sotatek.future.util.json.JsonUtil;
import java.util.Map;
import java.util.Properties;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.Producer;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.clients.producer.ProducerRecord;

@Slf4j
public class KafkaOrderBookStream extends OrderBookOutputStream {

  private final Map<String, Object> parameters;
  private final Gson gson = JsonUtil.createGson();
  private final String topic;
  private Producer<String, String> producer;

  public KafkaOrderBookStream(Map<String, Object> parameters, String topic) {
    this.parameters = parameters;
    this.topic = topic;
  }

  @Override
  public boolean connect() {
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
  public void flush() {
    // nothing to do
  }

  @Override
  public void close() {
    super.close();
  }

  @Override
  protected void publish(OrderBookEvent event) {
    String value = gson.toJson(event);
//    log.debug("KafkaOrderBookStream {}", value);
    ProducerRecord<String, String> producerRecord = new ProducerRecord<>(this.topic, value);
    try {
      this.producer.send(producerRecord).get();
    } catch (Exception e) {
      e.printStackTrace();
      throw new RuntimeException(e.getMessage());
    }
  }
}

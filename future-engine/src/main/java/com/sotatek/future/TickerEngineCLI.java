package com.sotatek.future;

import com.google.gson.reflect.TypeToken;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.Ticker;
import com.sotatek.future.enums.KafkaGroup;
import com.sotatek.future.enums.KafkaTopic;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.KafkaInputStream;
import com.sotatek.future.output.KafkaOutputStream;
import com.sotatek.future.output.OutputStream;
import com.sotatek.future.ticker.TickerEngine;
import java.io.IOException;
import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.TimeoutException;
import org.apache.kafka.clients.consumer.ConsumerConfig;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.common.serialization.StringDeserializer;
import org.apache.kafka.common.serialization.StringSerializer;

public class TickerEngineCLI {

  public static void main(String[] args) {
    System.setProperty("user.timezone", "UTC");
    String kafkaBrokers = args[0];
    Type listType = new TypeToken<ArrayList<CommandOutput>>() {}.getType();
    Map<String, Object> inputParams = new HashMap<>();
    inputParams.put(ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG, kafkaBrokers);
    inputParams.put(ConsumerConfig.GROUP_ID_CONFIG, "ticker_engine");
    inputParams.put(
        ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
    inputParams.put(
        ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
    InputStream<List<CommandOutput>> preloadStream =
        new KafkaInputStream<>(inputParams, KafkaTopic.TICKER_ENGINE_PRELOAD.getValue(), listType);
    InputStream<List<CommandOutput>> inputStream =
        new KafkaInputStream<>(inputParams, KafkaTopic.MATCHING_ENGINE_OUTPUT.getValue(), listType);

    Map<String, Object> outputParams = new HashMap<>();
    outputParams.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, kafkaBrokers);
    outputParams.put(ProducerConfig.CLIENT_ID_CONFIG, KafkaGroup.TICKER_ENGINE.getValue());
    outputParams.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    outputParams.put(
        ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    OutputStream<List<Ticker>> outputStream =
        new KafkaOutputStream<>(outputParams, KafkaTopic.TICKER_ENGINE_OUTPUT.getValue(), 0);
    TickerEngine tickerEngine = new TickerEngine(preloadStream, inputStream, outputStream);
    try {
      tickerEngine.initialize();
      tickerEngine.start();
    } catch (IOException e) {
      e.printStackTrace();
    } catch (TimeoutException e) {
      e.printStackTrace();
    }
  }
}

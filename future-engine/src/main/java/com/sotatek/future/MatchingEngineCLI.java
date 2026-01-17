package com.sotatek.future;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.engine.MatchingEngineConfig;
import com.sotatek.future.enums.InputDriver;
import com.sotatek.future.enums.KafkaGroup;
import com.sotatek.future.enums.KafkaTopic;
import com.sotatek.future.enums.OutputDriver;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.input.InputStreamFactory;
import com.sotatek.future.output.OutputStreamFactory;
import java.util.HashMap;
import java.util.Map;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.consumer.ConsumerConfig;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.common.serialization.StringDeserializer;
import org.apache.kafka.common.serialization.StringSerializer;

@Slf4j
public class MatchingEngineCLI {

  public static void main(String[] args) {
    System.setProperty("user.timezone", "UTC");

    String kafkaBrokers = args[0];

    MatchingEngineConfig config = new MatchingEngineConfig();

    config.setCommandInputDriver(InputDriver.KAFKA);
    config.setCommandPreloadDriver(InputDriver.KAFKA);
    Map<String, Object> inputParams = new HashMap<>();
    inputParams.put(ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG, kafkaBrokers);
    inputParams.put(ConsumerConfig.GROUP_ID_CONFIG, KafkaGroup.MATCHING_ENGINE.getValue());
    inputParams.put(
        ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
    inputParams.put(
        ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
    inputParams.put(
        InputStreamFactory.PRELOAD_QUEUE, KafkaTopic.MATCHING_ENGINE_PRElOAD.getValue());
    inputParams.put(InputStreamFactory.INPUT_QUEUE, KafkaTopic.MATCHING_ENGINE_INPUT.getValue());
    config.setInputParameters(inputParams);

    config.setCommandOutputDriver(OutputDriver.KAFKA);
    config.setOrderBookOutputDriver(OutputDriver.KAFKA);
    Map<String, Object> outputParams = new HashMap<>();
    outputParams.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, kafkaBrokers);
    outputParams.put(ProducerConfig.CLIENT_ID_CONFIG, "MatchingEngine");
    outputParams.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    outputParams.put(
        ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    outputParams.put(
        OutputStreamFactory.OUTPUT_QUEUE, KafkaTopic.MATCHING_ENGINE_OUTPUT.getValue());
    outputParams.put(OutputStreamFactory.ORDERBOOK_QUEUE, KafkaTopic.ORDERBOOK_OUTPUT.getValue());
    config.setOutputParameters(outputParams);

    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    try {
      log.info("Start Matching Engine CLI and Waiting Preload");
      matchingEngine.initialize(config);
      matchingEngine.start();
    } catch (InvalidMatchingEngineConfigException e) {
      e.printStackTrace();
    }
  }
}

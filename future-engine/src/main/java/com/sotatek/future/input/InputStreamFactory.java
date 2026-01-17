package com.sotatek.future.input;

import com.sotatek.future.engine.MatchingEngineConfig;
import com.sotatek.future.entity.Command;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;

public class InputStreamFactory {

  public static final String PRELOAD_QUEUE = "preload_queue";
  public static final String INPUT_QUEUE = "input_queue";

  public static InputStream<Command> createPreloadStream(MatchingEngineConfig config) {
    switch (config.getCommandPreloadDriver()) {
      case KAFKA:
        String topic = (String) config.getInputParameters().get(InputStreamFactory.PRELOAD_QUEUE);
        return new KafkaInputStream(config.getInputParameters(), topic, Command.class);
      case RABBIT_MQ:
        String queue = (String) config.getInputParameters().get(InputStreamFactory.PRELOAD_QUEUE);
        return new RabbitMQInputStream(config.getRabbitMQConnectionFactory(), queue);
      case JAVA_LIST:
      case AUTO_GENERATE:
        return config.getCommandPreloadStream();
      default:
        throw new InvalidMatchingEngineConfigException(
            "Unknown command preload driver: " + config.getCommandInputDriver());
    }
  }

  public static InputStream<Command> createInputStream(MatchingEngineConfig config) {
    switch (config.getCommandInputDriver()) {
      case KAFKA:
        String topic = (String) config.getInputParameters().get(InputStreamFactory.INPUT_QUEUE);
        return new KafkaInputStream(config.getInputParameters(), topic, Command.class);
      case RABBIT_MQ:
        String queue = (String) config.getInputParameters().get(InputStreamFactory.INPUT_QUEUE);
        return new RabbitMQInputStream(config.getRabbitMQConnectionFactory(), queue);
      case JAVA_LIST:
      case AUTO_GENERATE:
        return config.getCommandInputStream();
      default:
        throw new InvalidMatchingEngineConfigException(
            "Unknown command input driver: " + config.getCommandInputDriver());
    }
  }
}

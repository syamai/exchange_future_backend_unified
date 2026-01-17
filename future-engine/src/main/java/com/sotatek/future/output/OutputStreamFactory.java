package com.sotatek.future.output;

import com.sotatek.future.engine.MatchingEngineConfig;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;

public class OutputStreamFactory {

  public static final String OUTPUT_QUEUE = "output_queue";
  public static final String ORDERBOOK_QUEUE = "orderbook_queue";

  public static OutputStream<CommandOutput> createCommandOutputStream(MatchingEngineConfig config)
      throws InvalidMatchingEngineConfigException {
    switch (config.getCommandOutputDriver()) {
      case RABBIT_MQ:
        String queue = (String) config.getOutputParameters().get(OutputStreamFactory.OUTPUT_QUEUE);
        return new RabbitMQOutputStream<>(config.getRabbitMQConnectionFactory(), queue);
      case JAVA_LIST:
        return config.getCommandOutputStream();
      case KAFKA:
        String topic = (String) config.getOutputParameters().get(OutputStreamFactory.OUTPUT_QUEUE);
        return new KafkaOutputStream<>(config.getOutputParameters(), topic);
      default:
        throw new InvalidMatchingEngineConfigException(
            "Unknown order output driver: " + config.getCommandOutputDriver());
    }
  }

  public static OutputStream<OrderBookOutput> createOrderBookOutputStream(
      MatchingEngineConfig config) throws InvalidMatchingEngineConfigException {
    switch (config.getOrderBookOutputDriver()) {
      case RABBIT_MQ:
        String queue =
            (String) config.getOutputParameters().get(OutputStreamFactory.ORDERBOOK_QUEUE);
        return new RabbitMQOutputStream<>(config.getRabbitMQConnectionFactory(), queue);
      case JAVA_LIST:
        return config.getOrderBookOutputStream();
      case KAFKA:
        String topic =
            (String) config.getOutputParameters().get(OutputStreamFactory.ORDERBOOK_QUEUE);
        return new KafkaOrderBookStream(config.getOutputParameters(), topic);
      default:
        throw new InvalidMatchingEngineConfigException(
            "Unknown orderbook output driver: " + config.getOrderBookOutputDriver());
    }
  }
}

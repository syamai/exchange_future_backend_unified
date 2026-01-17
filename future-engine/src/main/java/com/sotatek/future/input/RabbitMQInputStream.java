package com.sotatek.future.input;

import com.google.gson.Gson;
import com.rabbitmq.client.Channel;
import com.rabbitmq.client.Connection;
import com.rabbitmq.client.ConnectionFactory;
import com.rabbitmq.client.DeliverCallback;
import com.sotatek.future.entity.Command;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.TimeoutException;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class RabbitMQInputStream extends BaseInputStream<Command> {

  private final ConnectionFactory connectionFactory;
  private String queueName;

  public RabbitMQInputStream(ConnectionFactory connectionFactory, String queueName) {
    this.connectionFactory = connectionFactory;
    this.queueName = queueName;
  }

  @Override
  public boolean connect() throws IOException, TimeoutException {
    Connection connection = this.connectionFactory.newConnection();
    Channel channel = connection.createChannel();
    Map<String, Object> params = new HashMap<>();
    params.put("x-queue-mode", "default");
    channel.queueDeclare(this.queueName, false, false, false, null);
    DeliverCallback deliverCallback =
        (consumerTag, delivery) -> {
          String message = new String(delivery.getBody(), StandardCharsets.UTF_8);
          this.handleMessage(message);
          channel.basicAck(delivery.getEnvelope().getDeliveryTag(), false);
        };
    channel.basicConsume(this.queueName, false, deliverCallback, consumerTag -> {});
    return true;
  }

  protected void handleMessage(String message) {
    //        log.info(message);
    Command orderInput = new Gson().fromJson(message, Command.class);

    //        log.info(orderInput);
    if (this.callback != null) {
      this.callback.onNewData(orderInput);
    }
  }

  public String getQueueName() {
    return this.queueName;
  }

  public void setQueueName(String queueName) {
    this.queueName = queueName;
  }

  @Override
  public void close() {}
}

package com.sotatek.future.output;

import com.rabbitmq.client.Channel;
import com.rabbitmq.client.Connection;
import com.rabbitmq.client.ConnectionFactory;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.TimeoutException;

public class RabbitMQOutputStream<T> extends JsonOutputStream<T> {

  private final ConnectionFactory connectionFactory;
  private final String queueName;
  private Channel channel;

  public RabbitMQOutputStream(ConnectionFactory connectionFactory, String queueName) {
    super();
    this.connectionFactory = connectionFactory;
    this.queueName = queueName;
  }

  @Override
  public boolean connect() throws IOException, TimeoutException {
    Connection connection = this.connectionFactory.newConnection();
    this.channel = connection.createChannel();
    this.channel.queueDeclare(this.queueName, true, false, false, null);
    return true;
  }

  @Override
  public void publish(String data) {
    try {
      this.channel.basicPublish("", this.queueName, null, data.getBytes(StandardCharsets.UTF_8));
    } catch (IOException e) {
      e.printStackTrace();
    }
  }

  @Override
  public void close() {
    try {
      this.channel.close();
    } catch (IOException e) {
      e.printStackTrace();
    } catch (TimeoutException e) {
      e.printStackTrace();
    }
  }
}

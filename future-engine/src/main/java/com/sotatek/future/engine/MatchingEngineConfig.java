package com.sotatek.future.engine;

import com.rabbitmq.client.ConnectionFactory;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.enums.InputDriver;
import com.sotatek.future.enums.OutputDriver;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.output.OutputStream;
import java.util.HashMap;
import java.util.Map;

public class MatchingEngineConfig {

  public static final int TRADES_PER_MESSAGE = 10;
  public static final int OUTPUT_BATCH_SIZE = 1;

  private boolean isTesting = false;

  private InputDriver commandInputDriver;
  private InputDriver commandPreloadDriver;
  private OutputDriver commandOutputDriver;
  private OutputDriver orderBookOutputDriver;

  private Map<String, Object> inputParameters = new HashMap<>();
  private Map<String, Object> outputParameters = new HashMap<>();

  private InputStream<Command> commandInputStream;
  private InputStream<Command> commandPreloadStream;
  private OutputStream<CommandOutput> orderOutputStream;
  private OutputStream<OrderBookOutput> orderBookOutputStream;

  private ConnectionFactory rabbitMQConnectionFactory;

  public MatchingEngineConfig() {}

  public MatchingEngineConfig(
      InputDriver commandInputDriver,
      OutputDriver commandOutputDriver,
      OutputDriver orderBookOutputDriver) {
    this.commandInputDriver = commandInputDriver;
    this.commandOutputDriver = commandOutputDriver;
    this.orderBookOutputDriver = orderBookOutputDriver;
  }

  public boolean isTesting() {
    return this.isTesting;
  }

  public void setTesting(boolean testing) {
    this.isTesting = testing;
  }

  public InputDriver getCommandInputDriver() {
    return this.commandInputDriver;
  }

  public void setCommandInputDriver(InputDriver commandInputDriver) {
    this.commandInputDriver = commandInputDriver;
  }

  public InputDriver getCommandPreloadDriver() {
    return commandPreloadDriver;
  }

  public void setCommandPreloadDriver(InputDriver commandPreloadDriver) {
    this.commandPreloadDriver = commandPreloadDriver;
  }

  public OutputDriver getCommandOutputDriver() {
    return this.commandOutputDriver;
  }

  public void setCommandOutputDriver(OutputDriver commandOutputDriver) {
    this.commandOutputDriver = commandOutputDriver;
  }

  public OutputDriver getOrderBookOutputDriver() {
    return this.orderBookOutputDriver;
  }

  public void setOrderBookOutputDriver(OutputDriver orderBookOutputDriver) {
    this.orderBookOutputDriver = orderBookOutputDriver;
  }

  public InputStream<Command> getCommandInputStream() {
    return this.commandInputStream;
  }

  public void setCommandInputStream(InputStream<Command> orderInputStream) {
    this.commandInputStream = orderInputStream;
  }

  public InputStream<Command> getCommandPreloadStream() {
    return commandPreloadStream;
  }

  public void setCommandPreloadStream(InputStream<Command> commandPreloadStream) {
    this.commandPreloadStream = commandPreloadStream;
  }

  public OutputStream<CommandOutput> getCommandOutputStream() {
    return this.orderOutputStream;
  }

  public void setCommandOutputStream(OutputStream<CommandOutput> orderOutputStream) {
    this.orderOutputStream = orderOutputStream;
  }

  public OutputStream<OrderBookOutput> getOrderBookOutputStream() {
    return this.orderBookOutputStream;
  }

  public void setOrderBookOutputStream(OutputStream<OrderBookOutput> orderbookOutputStream) {
    this.orderBookOutputStream = orderbookOutputStream;
  }

  public ConnectionFactory getRabbitMQConnectionFactory() {
    return this.rabbitMQConnectionFactory;
  }

  public void setRabbitMQConnectionFactory(ConnectionFactory rabbitMQConnectionFactory) {
    this.rabbitMQConnectionFactory = rabbitMQConnectionFactory;
  }

  public Map<String, Object> getInputParameters() {
    return inputParameters;
  }

  public void setInputParameters(Map<String, Object> inputParameters) {
    this.inputParameters = inputParameters;
  }

  public Map<String, Object> getOutputParameters() {
    return outputParameters;
  }

  public void setOutputParameters(Map<String, Object> outputParameters) {
    this.outputParameters = outputParameters;
  }
}

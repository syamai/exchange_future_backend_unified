package com.sotatek.future;

import com.sotatek.future.engine.ShardedMatchingEngine;
import com.sotatek.future.engine.ShardedMatchingEngineConfig;
import com.sotatek.future.enums.InputDriver;
import com.sotatek.future.enums.KafkaGroup;
import com.sotatek.future.enums.OutputDriver;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.input.InputStreamFactory;
import com.sotatek.future.output.OutputStreamFactory;
import com.sotatek.future.router.ShardHealthServer;
import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.router.ShardMetricsExporter;
import java.io.IOException;
import java.util.Arrays;
import java.util.HashMap;
import java.util.HashSet;
import java.util.Map;
import java.util.Set;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.consumer.ConsumerConfig;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.common.serialization.StringDeserializer;
import org.apache.kafka.common.serialization.StringSerializer;

/**
 * CLI for running a sharded matching engine instance.
 *
 * Usage:
 *   java -jar MatchingEngine.jar --shard-id=shard-1 \
 *       --role=primary \
 *       --symbols=BTCUSDT,BTCBUSD \
 *       --kafka=localhost:9092 \
 *       --health-port=8080 \
 *       --metrics-port=9090
 */
@Slf4j
public class ShardedMatchingEngineCLI {

    public static void main(String[] args) {
        System.setProperty("user.timezone", "UTC");

        // Parse command line arguments
        CliArgs cliArgs = parseArgs(args);

        log.info("Starting Sharded Matching Engine");
        log.info("  Shard ID: {}", cliArgs.shardId);
        log.info("  Role: {}", cliArgs.role);
        log.info("  Symbols: {}", cliArgs.symbols);
        log.info("  Kafka: {}", cliArgs.kafkaBrokers);

        // Build configuration
        ShardedMatchingEngineConfig config = buildConfig(cliArgs);

        // Create engine
        ShardedMatchingEngine engine = new ShardedMatchingEngine(
                cliArgs.shardId,
                cliArgs.symbols,
                cliArgs.role
        );

        ShardHealthServer healthServer = null;
        ShardMetricsExporter metricsExporter = null;

        try {
            // Initialize engine
            engine.initialize(config);

            // Start health server
            healthServer = new ShardHealthServer(engine);
            healthServer.start(cliArgs.healthPort);
            log.info("Health server started on port {}", cliArgs.healthPort);

            // Start metrics exporter
            if (cliArgs.metricsPort > 0) {
                metricsExporter = new ShardMetricsExporter(cliArgs.shardId, engine);
                metricsExporter.start(cliArgs.metricsPort);
                log.info("Metrics server started on port {}", cliArgs.metricsPort);
            }

            // Add shutdown hook
            final ShardHealthServer finalHealthServer = healthServer;
            final ShardMetricsExporter finalMetricsExporter = metricsExporter;
            Runtime.getRuntime().addShutdownHook(new Thread(() -> {
                log.info("Shutdown signal received");
                if (finalHealthServer != null) finalHealthServer.close();
                if (finalMetricsExporter != null) finalMetricsExporter.close();
                engine.shutdown();
            }));

            // Start engine (blocking call)
            log.info("Starting matching engine for shard {}", cliArgs.shardId);
            engine.start();

        } catch (InvalidMatchingEngineConfigException e) {
            log.error("Invalid configuration: {}", e.getMessage());
            System.exit(1);
        } catch (IOException e) {
            log.error("Failed to start servers: {}", e.getMessage());
            System.exit(1);
        }
    }

    private static ShardedMatchingEngineConfig buildConfig(CliArgs args) {
        ShardedMatchingEngineConfig config = ShardedMatchingEngineConfig.builder()
                .shardId(args.shardId)
                .role(args.role)
                .symbols(args.symbols)
                .kafkaBootstrapServers(args.kafkaBrokers)
                .standbySyncEnabled(args.role == ShardRole.PRIMARY)
                .healthCheckPort(args.healthPort)
                .metricsPort(args.metricsPort)
                .build();

        // Configure Kafka input
        config.setCommandInputDriver(InputDriver.KAFKA);
        config.setCommandPreloadDriver(InputDriver.KAFKA);

        Map<String, Object> inputParams = new HashMap<>();
        inputParams.put(ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG, args.kafkaBrokers);
        inputParams.put(ConsumerConfig.GROUP_ID_CONFIG,
                KafkaGroup.MATCHING_ENGINE.getValue() + "-" + args.shardId);
        inputParams.put(ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
        inputParams.put(ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG, StringDeserializer.class.getName());
        inputParams.put(ConsumerConfig.AUTO_OFFSET_RESET_CONFIG, "earliest");

        // Use shard-specific topics
        String inputTopic = "matching-engine-" + args.shardId + "-input";
        String preloadTopic = "matching-engine-" + args.shardId + "-preload";
        inputParams.put(InputStreamFactory.PRELOAD_QUEUE, preloadTopic);
        inputParams.put(InputStreamFactory.INPUT_QUEUE, inputTopic);
        config.setInputParameters(inputParams);

        // Configure Kafka output
        config.setCommandOutputDriver(OutputDriver.KAFKA);
        config.setOrderBookOutputDriver(OutputDriver.KAFKA);

        Map<String, Object> outputParams = new HashMap<>();
        outputParams.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, args.kafkaBrokers);
        outputParams.put(ProducerConfig.CLIENT_ID_CONFIG, "MatchingEngine-" + args.shardId);
        outputParams.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        outputParams.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        outputParams.put(ProducerConfig.ACKS_CONFIG, "all");

        // Use shard-specific output topics
        String outputTopic = "matching-engine-" + args.shardId + "-output";
        String orderbookTopic = "orderbook-" + args.shardId + "-output";
        outputParams.put(OutputStreamFactory.OUTPUT_QUEUE, outputTopic);
        outputParams.put(OutputStreamFactory.ORDERBOOK_QUEUE, orderbookTopic);
        config.setOutputParameters(outputParams);

        return config;
    }

    private static CliArgs parseArgs(String[] args) {
        CliArgs cliArgs = new CliArgs();

        for (String arg : args) {
            if (arg.startsWith("--shard-id=")) {
                cliArgs.shardId = arg.substring("--shard-id=".length());
            } else if (arg.startsWith("--role=")) {
                String role = arg.substring("--role=".length()).toUpperCase();
                cliArgs.role = ShardRole.valueOf(role);
            } else if (arg.startsWith("--symbols=")) {
                String symbols = arg.substring("--symbols=".length());
                cliArgs.symbols = new HashSet<>(Arrays.asList(symbols.split(",")));
            } else if (arg.startsWith("--kafka=")) {
                cliArgs.kafkaBrokers = arg.substring("--kafka=".length());
            } else if (arg.startsWith("--health-port=")) {
                cliArgs.healthPort = Integer.parseInt(arg.substring("--health-port=".length()));
            } else if (arg.startsWith("--metrics-port=")) {
                cliArgs.metricsPort = Integer.parseInt(arg.substring("--metrics-port=".length()));
            } else if (!arg.startsWith("--")) {
                // Legacy positional argument support (kafka brokers)
                cliArgs.kafkaBrokers = arg;
            }
        }

        // Also check environment variables
        if (cliArgs.shardId == null) {
            cliArgs.shardId = System.getenv("SHARD_ID");
        }
        if (cliArgs.role == null) {
            String roleEnv = System.getenv("SHARD_ROLE");
            if (roleEnv != null) {
                cliArgs.role = ShardRole.valueOf(roleEnv.toUpperCase());
            }
        }
        if (cliArgs.symbols.isEmpty()) {
            String symbolsEnv = System.getenv("ASSIGNED_SYMBOLS");
            if (symbolsEnv != null) {
                cliArgs.symbols = new HashSet<>(Arrays.asList(symbolsEnv.split(",")));
            }
        }
        if (cliArgs.kafkaBrokers == null) {
            cliArgs.kafkaBrokers = System.getenv("KAFKA_BOOTSTRAP_SERVERS");
        }

        // Validate required arguments
        if (cliArgs.shardId == null || cliArgs.shardId.isEmpty()) {
            printUsageAndExit("Missing required argument: --shard-id");
        }
        if (cliArgs.kafkaBrokers == null || cliArgs.kafkaBrokers.isEmpty()) {
            printUsageAndExit("Missing required argument: --kafka or KAFKA_BOOTSTRAP_SERVERS env");
        }
        if (cliArgs.symbols.isEmpty()) {
            printUsageAndExit("Missing required argument: --symbols");
        }
        if (cliArgs.role == null) {
            cliArgs.role = ShardRole.PRIMARY;
        }

        return cliArgs;
    }

    private static void printUsageAndExit(String error) {
        System.err.println("Error: " + error);
        System.err.println();
        System.err.println("Usage: java -jar MatchingEngine.jar [options]");
        System.err.println();
        System.err.println("Options:");
        System.err.println("  --shard-id=<id>       Unique shard identifier (required)");
        System.err.println("  --role=<role>         Shard role: PRIMARY or STANDBY (default: PRIMARY)");
        System.err.println("  --symbols=<symbols>   Comma-separated list of symbols (required)");
        System.err.println("  --kafka=<brokers>     Kafka bootstrap servers (required)");
        System.err.println("  --health-port=<port>  Health check HTTP port (default: 8080)");
        System.err.println("  --metrics-port=<port> Prometheus metrics port (default: 9090)");
        System.err.println();
        System.err.println("Environment variables:");
        System.err.println("  SHARD_ID              Alternative to --shard-id");
        System.err.println("  SHARD_ROLE            Alternative to --role");
        System.err.println("  ASSIGNED_SYMBOLS      Alternative to --symbols");
        System.err.println("  KAFKA_BOOTSTRAP_SERVERS  Alternative to --kafka");
        System.err.println();
        System.err.println("Example:");
        System.err.println("  java -jar MatchingEngine.jar \\");
        System.err.println("    --shard-id=shard-1 \\");
        System.err.println("    --role=primary \\");
        System.err.println("    --symbols=BTCUSDT,BTCBUSD \\");
        System.err.println("    --kafka=localhost:9092");
        System.exit(1);
    }

    private static class CliArgs {
        String shardId;
        ShardRole role;
        Set<String> symbols = new HashSet<>();
        String kafkaBrokers;
        int healthPort = 8080;
        int metricsPort = 9090;
    }
}

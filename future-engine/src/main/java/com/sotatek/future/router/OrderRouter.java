package com.sotatek.future.router;

import com.sotatek.future.entity.Command;
import com.sotatek.future.router.ShardInfo.ShardStatus;
import java.io.IOException;
import java.io.InputStream;
import java.util.Collection;
import java.util.Map;
import java.util.Properties;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.CopyOnWriteArraySet;
import lombok.extern.slf4j.Slf4j;

/**
 * Routes orders/commands to the appropriate shard based on symbol mapping.
 * Implements the singleton pattern for global access.
 */
@Slf4j
public class OrderRouter implements AutoCloseable {

    private static volatile OrderRouter instance;
    private static final Object lock = new Object();

    private final Map<String, ShardInfo> symbolToShardMapping;
    private final Map<String, ShardClient> shardClients;
    private final Map<String, ShardInfo> shardInfoMap;
    private final Set<String> pausedSymbols;
    private final String defaultShardId;
    private String bootstrapServers;

    private volatile boolean closed = false;

    /**
     * Private constructor for singleton pattern.
     */
    private OrderRouter() {
        this.symbolToShardMapping = new ConcurrentHashMap<>();
        this.shardClients = new ConcurrentHashMap<>();
        this.shardInfoMap = new ConcurrentHashMap<>();
        this.pausedSymbols = new CopyOnWriteArraySet<>();
        this.defaultShardId = "shard-default";

        // Load default shard mapping in constructor for testing convenience
        loadShardMapping();
    }

    /**
     * Get the singleton instance.
     */
    public static OrderRouter getInstance() {
        if (instance == null) {
            synchronized (lock) {
                if (instance == null) {
                    instance = new OrderRouter();
                }
            }
        }
        return instance;
    }

    /**
     * Reset the singleton instance (for testing purposes).
     */
    public static void resetInstance() {
        synchronized (lock) {
            if (instance != null) {
                instance.close();
                instance = null;
            }
        }
    }

    /**
     * Initialize the router with Kafka bootstrap servers and load shard mapping.
     */
    public void initialize(String bootstrapServers) {
        this.bootstrapServers = bootstrapServers;
        loadShardMapping();
        initializeShardClients();
        log.info("OrderRouter initialized with {} shards", shardInfoMap.size());
    }

    /**
     * Initialize from properties file.
     */
    public void initializeFromProperties(String propertiesPath) throws IOException {
        Properties props = new Properties();
        try (InputStream is = getClass().getClassLoader().getResourceAsStream(propertiesPath)) {
            if (is == null) {
                throw new IOException("Properties file not found: " + propertiesPath);
            }
            props.load(is);
        }

        this.bootstrapServers = props.getProperty("kafka.bootstrap.servers");

        // Load shard configurations from properties
        int shardCount = Integer.parseInt(props.getProperty("shard.count", "3"));
        for (int i = 1; i <= shardCount; i++) {
            String shardId = "shard-" + i;
            String symbols = props.getProperty("shard." + i + ".symbols", "");
            String inputTopic = props.getProperty("shard." + i + ".input.topic",
                    "matching-engine-" + shardId + "-input");

            ShardInfo shardInfo = new ShardInfo(shardId, inputTopic);
            shardInfoMap.put(shardId, shardInfo);

            // Map symbols to shard
            for (String symbol : symbols.split(",")) {
                symbol = symbol.trim();
                if (!symbol.isEmpty()) {
                    symbolToShardMapping.put(symbol, shardInfo);
                }
            }
        }

        initializeShardClients();
        log.info("OrderRouter initialized from properties with {} shards", shardInfoMap.size());
    }

    /**
     * Load default shard mapping (can be overridden with configuration).
     */
    private void loadShardMapping() {
        // Shard 1: BTC pairs (highest volume)
        ShardInfo shard1 = ShardInfo.builder()
                .shardId("shard-1")
                .kafkaInputTopic("matching-engine-shard-1-input")
                .kafkaOutputTopic("matching-engine-shard-1-output")
                .kafkaSyncTopic("shard-sync-shard-1")
                .status(ShardStatus.ACTIVE)
                .build();
        shardInfoMap.put("shard-1", shard1);
        symbolToShardMapping.put("BTCUSDT", shard1);
        symbolToShardMapping.put("BTCBUSD", shard1);

        // Shard 2: ETH pairs
        ShardInfo shard2 = ShardInfo.builder()
                .shardId("shard-2")
                .kafkaInputTopic("matching-engine-shard-2-input")
                .kafkaOutputTopic("matching-engine-shard-2-output")
                .kafkaSyncTopic("shard-sync-shard-2")
                .status(ShardStatus.ACTIVE)
                .build();
        shardInfoMap.put("shard-2", shard2);
        symbolToShardMapping.put("ETHUSDT", shard2);
        symbolToShardMapping.put("ETHBUSD", shard2);

        // Shard 3: Other symbols (default shard)
        ShardInfo shard3 = ShardInfo.builder()
                .shardId("shard-3")
                .kafkaInputTopic("matching-engine-shard-3-input")
                .kafkaOutputTopic("matching-engine-shard-3-output")
                .kafkaSyncTopic("shard-sync-shard-3")
                .status(ShardStatus.ACTIVE)
                .build();
        shardInfoMap.put("shard-3", shard3);
        shardInfoMap.put(defaultShardId, shard3);

        log.info("Loaded shard mapping: {} symbol mappings", symbolToShardMapping.size());
    }

    /**
     * Initialize Kafka clients for each shard.
     */
    private void initializeShardClients() {
        for (ShardInfo shardInfo : shardInfoMap.values()) {
            if (!shardClients.containsKey(shardInfo.getShardId())) {
                ShardClient client = new ShardClient(shardInfo, bootstrapServers);
                shardClients.put(shardInfo.getShardId(), client);
                log.info("Initialized ShardClient for {}", shardInfo.getShardId());
            }
        }
    }

    /**
     * Route a command to the appropriate shard.
     */
    public void routeCommand(Command command) {
        String symbol = extractSymbol(command);

        if (pausedSymbols.contains(symbol)) {
            throw new SymbolPausedException("Symbol is paused for rebalancing: " + symbol);
        }

        ShardInfo shard = getShardForSymbol(symbol);
        if (shard == null) {
            throw new UnknownSymbolException("Unknown symbol: " + symbol);
        }

        if (!shard.isAvailable()) {
            throw new ShardUnavailableException("Shard is not available: " + shard.getShardId());
        }

        ShardClient client = shardClients.get(shard.getShardId());
        if (client == null) {
            throw new ShardUnavailableException("No client for shard: " + shard.getShardId());
        }

        client.sendCommand(command);
        log.debug("Routed command {} for symbol {} to shard {}",
                command.getCode(), symbol, shard.getShardId());
    }

    /**
     * Route a command synchronously and wait for acknowledgment.
     */
    public void routeCommandSync(Command command) throws Exception {
        String symbol = extractSymbol(command);

        if (pausedSymbols.contains(symbol)) {
            throw new SymbolPausedException("Symbol is paused for rebalancing: " + symbol);
        }

        ShardInfo shard = getShardForSymbol(symbol);
        if (shard == null) {
            throw new UnknownSymbolException("Unknown symbol: " + symbol);
        }

        ShardClient client = shardClients.get(shard.getShardId());
        client.sendCommandSync(command);
    }

    /**
     * Extract symbol from command.
     */
    private String extractSymbol(Command command) {
        if (command.isOrderCommand() && command.getOrder() != null) {
            return command.getOrder().getSymbol();
        }
        try {
            if (command.getPosition() != null) {
                return command.getPosition().getSymbol();
            }
        } catch (ClassCastException ignored) {
        }
        try {
            if (command.getInstrument() != null) {
                return command.getInstrument().getSymbol();
            }
        } catch (ClassCastException ignored) {
        }
        return null;
    }

    /**
     * Get the shard responsible for a symbol.
     */
    public ShardInfo getShardForSymbol(String symbol) {
        ShardInfo shard = symbolToShardMapping.get(symbol);
        if (shard == null) {
            // Return default shard for unknown symbols
            return shardInfoMap.get(defaultShardId);
        }
        return shard;
    }

    /**
     * Add a new symbol to shard mapping.
     */
    public void addSymbolMapping(String symbol, String shardId) {
        ShardInfo shard = shardInfoMap.get(shardId);
        if (shard == null) {
            throw new IllegalArgumentException("Unknown shard: " + shardId);
        }
        symbolToShardMapping.put(symbol, shard);
        log.info("Added symbol {} to shard {}", symbol, shardId);
    }

    /**
     * Remove a symbol from shard mapping.
     */
    public void removeSymbolMapping(String symbol) {
        ShardInfo removed = symbolToShardMapping.remove(symbol);
        if (removed != null) {
            log.info("Removed symbol {} from shard {}", symbol, removed.getShardId());
        }
    }

    /**
     * Update shard mapping (for rebalancing).
     */
    public void updateMapping(String symbol, String newShardId) {
        ShardInfo newShard = shardInfoMap.get(newShardId);
        if (newShard == null) {
            throw new IllegalArgumentException("Unknown shard: " + newShardId);
        }
        ShardInfo oldShard = symbolToShardMapping.put(symbol, newShard);
        log.info("Updated symbol {} mapping from {} to {}",
                symbol,
                oldShard != null ? oldShard.getShardId() : "none",
                newShardId);
    }

    /**
     * Pause a symbol (during rebalancing).
     */
    public void pauseSymbol(String symbol) {
        pausedSymbols.add(symbol);
        log.info("Paused symbol {}", symbol);
    }

    /**
     * Resume a symbol (after rebalancing).
     */
    public void resumeSymbol(String symbol) {
        pausedSymbols.remove(symbol);
        log.info("Resumed symbol {}", symbol);
    }

    /**
     * Check if a symbol is paused.
     */
    public boolean isSymbolPaused(String symbol) {
        return pausedSymbols.contains(symbol);
    }

    /**
     * Get all shard information.
     */
    public Collection<ShardInfo> getAllShards() {
        return shardInfoMap.values();
    }

    /**
     * Get shard client by ID.
     */
    public ShardClient getShardClient(String shardId) {
        return shardClients.get(shardId);
    }

    /**
     * Get all symbols mapped to a specific shard.
     */
    public Set<String> getSymbolsForShard(String shardId) {
        Set<String> symbols = ConcurrentHashMap.newKeySet();
        symbolToShardMapping.forEach((symbol, shard) -> {
            if (shard.getShardId().equals(shardId)) {
                symbols.add(symbol);
            }
        });
        return symbols;
    }

    /**
     * Broadcast a command to all shards (e.g., for global configuration updates).
     */
    public void broadcastCommand(Command command) {
        for (ShardClient client : shardClients.values()) {
            try {
                client.sendCommand(command);
            } catch (Exception e) {
                log.error("Failed to broadcast command to shard {}: {}",
                        client.getShardId(), e.getMessage());
            }
        }
        log.info("Broadcasted command {} to all shards", command.getCode());
    }

    @Override
    public void close() {
        if (!closed) {
            closed = true;
            for (ShardClient client : shardClients.values()) {
                try {
                    client.close();
                } catch (Exception e) {
                    log.error("Error closing shard client {}: {}",
                            client.getShardId(), e.getMessage());
                }
            }
            shardClients.clear();
            log.info("OrderRouter closed");
        }
    }

    /**
     * Exception for unknown symbols.
     */
    public static class UnknownSymbolException extends RuntimeException {
        public UnknownSymbolException(String message) {
            super(message);
        }
    }

    /**
     * Exception for unavailable shards.
     */
    public static class ShardUnavailableException extends RuntimeException {
        public ShardUnavailableException(String message) {
            super(message);
        }
    }

    /**
     * Exception for paused symbols.
     */
    public static class SymbolPausedException extends RuntimeException {
        public SymbolPausedException(String message) {
            super(message);
        }
    }
}

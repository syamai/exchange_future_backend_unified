package com.sotatek.future.router;

import com.google.gson.Gson;
import com.sotatek.future.engine.ShardHealthStatus;
import com.sotatek.future.engine.ShardedMatchingEngine;
import com.sotatek.future.util.json.JsonUtil;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.Executors;
import lombok.extern.slf4j.Slf4j;

/**
 * HTTP server for health check and readiness endpoints.
 * Used by Kubernetes for liveness and readiness probes.
 */
@Slf4j
public class ShardHealthServer implements AutoCloseable {

    private final ShardedMatchingEngine engine;
    private final Gson gson = JsonUtil.createGson();
    private HttpServer server;
    private volatile boolean closed = false;

    public ShardHealthServer(ShardedMatchingEngine engine) {
        this.engine = engine;
    }

    /**
     * Start the health server on the specified port.
     */
    public void start(int port) throws IOException {
        server = HttpServer.create(new InetSocketAddress(port), 0);
        server.setExecutor(Executors.newFixedThreadPool(2));

        // Liveness probe - is the process alive?
        server.createContext("/health/live", this::handleLiveness);

        // Readiness probe - is the service ready to accept traffic?
        server.createContext("/health/ready", this::handleReadiness);

        // Detailed health status
        server.createContext("/health", this::handleHealth);

        // Status endpoint for monitoring
        server.createContext("/status", this::handleStatus);

        server.start();
        log.info("Health server started on port {}", port);
    }

    /**
     * Handle liveness probe.
     * Returns 200 if the JVM is running.
     */
    private void handleLiveness(HttpExchange exchange) throws IOException {
        try {
            String response = "{\"status\":\"UP\"}";
            sendResponse(exchange, 200, response);
        } catch (Exception e) {
            log.error("Liveness check failed", e);
            sendResponse(exchange, 500, "{\"status\":\"DOWN\",\"error\":\"" + e.getMessage() + "\"}");
        }
    }

    /**
     * Handle readiness probe.
     * Returns 200 if the engine is ready to process requests.
     */
    private void handleReadiness(HttpExchange exchange) throws IOException {
        try {
            ShardHealthStatus status = engine.getHealthStatus();

            if (status.isReady()) {
                String response = "{\"status\":\"UP\",\"shard\":\"" + engine.getShardId() + "\"}";
                sendResponse(exchange, 200, response);
            } else {
                String response = "{\"status\":\"DOWN\",\"reason\":\"Engine not ready\",\"shard\":\""
                        + engine.getShardId() + "\"}";
                sendResponse(exchange, 503, response);
            }
        } catch (Exception e) {
            log.error("Readiness check failed", e);
            sendResponse(exchange, 500, "{\"status\":\"DOWN\",\"error\":\"" + e.getMessage() + "\"}");
        }
    }

    /**
     * Handle detailed health check.
     */
    private void handleHealth(HttpExchange exchange) throws IOException {
        try {
            ShardHealthStatus status = engine.getHealthStatus();
            boolean healthy = status.isHealthy();

            HealthResponse response = new HealthResponse();
            response.status = healthy ? "UP" : "DOWN";
            response.shardId = engine.getShardId();
            response.role = status.getRole().toString();
            response.assignedSymbols = status.getAssignedSymbols();
            response.activeOrders = status.getActiveOrders();
            response.commandsProcessed = status.getCommandsProcessed();
            response.memoryUsagePercent = status.getMemoryUsagePercent();
            response.avgProcessingTimeMs = status.getAvgProcessingTimeMs();
            response.lastProcessedAgoMs = status.getTimeSinceLastProcessed();

            int statusCode = healthy ? 200 : 503;
            sendResponse(exchange, statusCode, gson.toJson(response));
        } catch (Exception e) {
            log.error("Health check failed", e);
            sendResponse(exchange, 500, "{\"status\":\"DOWN\",\"error\":\"" + e.getMessage() + "\"}");
        }
    }

    /**
     * Handle status endpoint - detailed status for monitoring dashboards.
     */
    private void handleStatus(HttpExchange exchange) throws IOException {
        try {
            ShardHealthStatus status = engine.getHealthStatus();
            sendResponse(exchange, 200, gson.toJson(status));
        } catch (Exception e) {
            log.error("Status check failed", e);
            sendResponse(exchange, 500, "{\"error\":\"" + e.getMessage() + "\"}");
        }
    }

    /**
     * Send HTTP response.
     */
    private void sendResponse(HttpExchange exchange, int statusCode, String response) throws IOException {
        exchange.getResponseHeaders().set("Content-Type", "application/json");
        byte[] bytes = response.getBytes(StandardCharsets.UTF_8);
        exchange.sendResponseHeaders(statusCode, bytes.length);
        try (OutputStream os = exchange.getResponseBody()) {
            os.write(bytes);
        }
    }

    @Override
    public void close() {
        if (!closed && server != null) {
            closed = true;
            server.stop(0);
            log.info("Health server stopped");
        }
    }

    /**
     * Health response structure.
     */
    private static class HealthResponse {
        String status;
        String shardId;
        String role;
        int assignedSymbols;
        long activeOrders;
        long commandsProcessed;
        double memoryUsagePercent;
        double avgProcessingTimeMs;
        long lastProcessedAgoMs;
    }
}

package com.sotatek.future.websocket;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.model.BinanceTradeDataResponse;
import lombok.Getter;
import org.java_websocket.client.WebSocketClient;
import org.java_websocket.handshake.ServerHandshake;

import java.net.URI;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class BinanceWebSocketClientForGetTrades {
    private boolean isConnected = false;
    private String symbol = null;

    @Getter
    private List<BinanceTradeDataResponse> trades = new ArrayList<>();

    public BinanceWebSocketClientForGetTrades(URI serverUri, String symbol) {
        this.symbol = symbol;
//        String binanceWebSocketURL = "wss://stream.binance.com:9443/ws/btcusdt@trade";
        try {
            WebSocketClient client = new WebSocketClient(serverUri) {
                @Override
                public void onOpen(ServerHandshake handshakedata) {
                    System.out.println(symbol + " | Connected to Binance WebSocket");
                    isConnected = true;
                }

                @Override
                public void onMessage(String message) {
                    ObjectMapper objectMapper = new ObjectMapper();
                    try {
                        Map<String, Object> resultMap = objectMapper.readValue(message, Map.class);
                        BinanceTradeDataResponse result = BinanceTradeDataResponse.fromBinanceTradeDataWSResponseMap(resultMap);
                        if (trades.size() == 10) {
                            trades.remove(0);
                        }
                        trades.add(result);
                    } catch (JsonProcessingException e) {
                        e.printStackTrace();
                    }
                    // You can parse the JSON message here and handle it accordingly
                }

                @Override
                public void onClose(int code, String reason, boolean remote) {
                    System.out.println(symbol + " | Disconnected from Binance WebSocket");
                    isConnected = false;
                }

                @Override
                public void onError(Exception ex) {
                    ex.printStackTrace();
                    isConnected = false;
                }
            };

            client.connect();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public boolean isConnected() {
        return isConnected;
    }

}

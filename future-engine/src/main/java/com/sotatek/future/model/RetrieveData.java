package com.sotatek.future.model;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.enums.RetrieveDataType;
import lombok.Getter;
import lombok.Setter;
import lombok.extern.slf4j.Slf4j;

@Getter
@Setter
@Slf4j
public class RetrieveData {
    private RetrieveDataType type;
    private Object dataOfType;

    public OrderQuery getOrderQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, OrderQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public PositionQuery getPositionQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, PositionQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public AccountQuery getAccountQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, AccountQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public InstrumentQuery getInstrumentQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, InstrumentQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public OrderBookQuery getOrderBookQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, OrderBookQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public LmSymbolIndexQuery getLmSymbolIndexQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, LmSymbolIndexQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public LmSymbolIndexDefaultQuery getLmSymbolIndexDefaultQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, LmSymbolIndexDefaultQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }

    public LiquidationClearanceRateIndexQuery getLiquidationClearanceRateIndexQuery() {
        ObjectMapper objectMapper = new ObjectMapper();
        try {
            String dataStr = objectMapper.writeValueAsString(this.dataOfType);
            return objectMapper.readValue(dataStr, LiquidationClearanceRateIndexQuery.class);
        } catch (JsonProcessingException e) {
            log.error(e.toString());
            return null;
        }
    }
}

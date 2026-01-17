package com.sotatek.future.enums;

import lombok.Getter;

public enum ErrorResponse {
    INSUFFICIENT_BALANCE("E001", "Insufficient balance, rollback and cancel."),
    EXCEED_RISK_LIMIT("E002", "Risk limit exceed, rollback and cancel"),
    POST_ONLY("E003", "Cancel post only order"),
    CROSS_LIQUIDATION_PRICE("E004", "Cross over liquidation price"),
    CROSS_BANKRUPT_PRICE("E005", "Cross over bankrupt price"),
    REDUCE_ONLY("E006", "Reduce only order"),
    LOCK_PRICE("E007", "Cannot lock order, rollback and cancel"),
    INSUFFICIENT_QUANTITY("E008", "Cannot fill FOK order, rollback and cancel");

    @Getter
    private String code;
    @Getter
    private String messages;

    ErrorResponse(String code, String messages) {
        this.code = code;
        this.messages = messages;
    }
}

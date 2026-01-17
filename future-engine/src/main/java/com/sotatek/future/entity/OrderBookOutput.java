package com.sotatek.future.entity;

import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.util.MarginBigDecimal;

public record OrderBookOutput(
    OrderSide side, MarginBigDecimal price, MarginBigDecimal quantity, String symbol) {}

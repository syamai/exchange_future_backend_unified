package com.sotatek.future.model;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import lombok.*;

import java.util.Map;

@AllArgsConstructor
@NoArgsConstructor
@Getter
@Setter
@JsonIgnoreProperties(ignoreUnknown = true)
@Builder
public class BinanceTradeDataResponse {
    private long id;
    private String price;
    private String qty;
    private String quoteQty;
    private long time;
    private boolean isBuyerMaker;

    public void setIsBuyerMaker(boolean buyerMaker) {
        isBuyerMaker = buyerMaker;
    }

    public static BinanceTradeDataResponse fromBinanceTradeDataWSResponseMap(Map<String, Object> data) {
        BinanceTradeDataResponse result = BinanceTradeDataResponse.builder()
                .id(data.get("a") instanceof Integer? Long.valueOf((Integer)data.get("a")): (Long) data.get("a"))
                .time(data.get("T") instanceof Integer? Long.valueOf((Integer)data.get("T")): (Long) data.get("T"))
                .qty((String)data.get("q"))
                .isBuyerMaker((boolean) data.get("m"))
                .price((String)data.get("p"))
                .build();
        return result;
    }

}

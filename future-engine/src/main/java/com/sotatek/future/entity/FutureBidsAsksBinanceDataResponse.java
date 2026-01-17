package com.sotatek.future.entity;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.AllArgsConstructor;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

import java.util.List;

@AllArgsConstructor
@NoArgsConstructor
@Getter
@Setter
@JsonIgnoreProperties(ignoreUnknown = true)
public class FutureBidsAsksBinanceDataResponse {
    private long lastUpdateId;
    private long E;
    private long T;
    private List<MarginBigDecimal[]> bids;
    private List<MarginBigDecimal[]> asks;
}

package com.sotatek.future.entity;

import java.io.Serializable;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Setter
@Getter
@ToString
public class AdjustTpSl implements Serializable {
  private Long accountId;
  private String symbol;
  private TpSlOrder tpOrder;
  private TpSlOrder slOrder;

  public String getKey() {
    return accountId + symbol;
  }
}

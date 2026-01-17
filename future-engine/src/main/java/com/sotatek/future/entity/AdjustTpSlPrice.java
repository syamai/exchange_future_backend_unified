package com.sotatek.future.entity;

import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString
public class AdjustTpSlPrice {
  private Long tpOrderId;
  private Long slOrderId;
  private MarginBigDecimal tpOrderChangePrice;
  private OrderTrigger tpOrderTrigger;
  private OrderTrigger slOrderTrigger;
  private MarginBigDecimal slOrderChangePrice;
}

package com.sotatek.future.engine;

import com.sotatek.future.entity.Order;
import java.util.Comparator;

public class OrderComparators {

  // Comparator where lower price order appear first
  static final Comparator<Order> LowPriceComparator = Comparator.comparing(Order::getPrice);

  // Comparator where higher price order appear first
  static final Comparator<Order> HighPriceComparator =
      Comparator.comparing(Order::getPrice).reversed();

  // Comparator where lower TPSL price order appear first
  static final Comparator<Order> LowTpslPriceComparator = Comparator.comparing(Order::getTpSLPrice);

  // Comparator where higher TPSL price order appear first
  static final Comparator<Order> HighTpslPriceComparator =
      Comparator.comparing(Order::getTpSLPrice).reversed();

  // Comparator where lower priority order appear first.
  // Priority is a misnomer, because it's in fact a sequence number,
  // where an order with a lower 'priority' appears before an order with higher 'priority' number.
  // As a result, order with a lower 'priority' value should be processed first
  static final Comparator<Order> LowPriorityComparator = Comparator.comparing(Order::getPriority);
}

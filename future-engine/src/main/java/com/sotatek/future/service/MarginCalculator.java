package com.sotatek.future.service;

import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.NoArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.jetbrains.annotations.NotNull;
import org.jetbrains.annotations.VisibleForTesting;

@Slf4j
@NoArgsConstructor
public class MarginCalculator {
  private Instrument instrument;
  private InstrumentExtraInformation instrumentExtraInfo;
  private boolean isCoinM;
  // avoid get mark price from instrumentExtraInfo multiple times
  // because it can change from other thread
  private MarginBigDecimal oraclePrice;

  private MarginCalculator(@NotNull String symbol) {
    this.instrument = InstrumentService.getInstance().get(symbol);
    this.instrumentExtraInfo = InstrumentService.getInstance().getExtraInfo(symbol);
    this.oraclePrice = this.instrumentExtraInfo.getOraclePrice();
    this.isCoinM = ContractType.COIN_M.equals(instrument.getContractType());
  }

  public static MarginCalculator getCalculatorFor(String symbol) {
    return new MarginCalculator(symbol);
  }

  public MarginBigDecimal getOpenSize(
      @NotNull MarginBigDecimal quantity, @NotNull MarginBigDecimal currentSize) {
    long sign = quantity.gt(0) ? 1 : -1;
    MarginBigDecimal closedPosition =
        currentSize.multiplySign(quantity) >= 0
            ? MarginBigDecimal.ZERO
            : currentSize.abs().min(quantity.abs());
    closedPosition = closedPosition.multiply(sign);
    return quantity.subtract(closedPosition);
  }

  public MarginBigDecimal getCloseSize(
      @NotNull MarginBigDecimal quantity, @NotNull MarginBigDecimal currentSize) {
    long sign = quantity.compareTo(MarginBigDecimal.ZERO) > 0 ? 1 : -1;
    MarginBigDecimal closedSize =
        currentSize.multiplySign(quantity) >= 0
            ? MarginBigDecimal.ZERO
            : currentSize.abs().min(quantity.abs());
    return closedSize.multiply(sign);
  }

  public MarginBigDecimal getOpenValue(MarginBigDecimal price, @NotNull MarginBigDecimal quantity) {
    if (price == null || price.eq(MarginBigDecimal.ZERO)) {
      return MarginBigDecimal.ZERO;
    }
    if (isCoinM) {
      return quantity.divide(price);
    } else {
      return price.multiply(quantity);
    }
  }

  public MarginBigDecimal getCloseValue(Position position, MarginBigDecimal closeSize) {
    // close value is just entry value ratio of close size and current quantity
    return position.getEntryValue().multiplyThenDivide(closeSize, position.getCurrentQty());
  }

  public MarginBigDecimal getEntryPrice(
      @NotNull MarginBigDecimal value, @NotNull MarginBigDecimal quantity) {
    MarginBigDecimal entryPrice;
    if (isCoinM) {
      entryPrice = quantity.divide(value);
    } else {
      entryPrice = value.divide(quantity);
    }
    return entryPrice;
  }

  public MarginBigDecimal getFee(MarginBigDecimal price, MarginBigDecimal size, boolean isTaker) {
    return isTaker ? getTakerFee(price, size) : getMakerFee(price, size);
  }

  public MarginBigDecimal getFeeRate(boolean isTaker) {
    return isTaker ? instrument.getTakerFee() : instrument.getMakerFee();
  }

  public MarginBigDecimal calcUnrealisedPnl(Position position) {
    return MarginCalculator.calcUnrealisedPnl(
        position, oraclePrice, this.instrument.getMultiplier());
  }

  public static MarginBigDecimal calcUnrealisedPnl(
      Position position, MarginBigDecimal oraclePrice, MarginBigDecimal multiplier) {
    MarginBigDecimal sizeWithSide = position.getCurrentQty();
    MarginBigDecimal entryPrice = position.getEntryPrice();
    if (position.isCoinM()) {
      // Unrealized PNL = Size * Contract Multiplier * (1/ Entry price - 1/ Mark Price) * Side
      return sizeWithSide
          .multiply(multiplier)
          .multiply(oraclePrice.subtract(entryPrice).divide(entryPrice.multiply(oraclePrice)));
    } else {
      // Unrealized PNL = Size * (Mark price - Entry Price) * Side
      return sizeWithSide.multiply(oraclePrice.subtract(entryPrice));
    }
  }

  public MarginBigDecimal getRealisedPnl(
      MarginBigDecimal exitPrice,
      MarginBigDecimal closeQuantity,
      MarginBigDecimal closeValue,
      Position position) {
    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal multiplier = null;
    MarginBigDecimal realisedPnl;
    if (isCoinM) {
      multiplier = this.instrument.getMultiplier();
      // Realized PNL = Size * (1/ Entry price - 1/ Exit Price) * Side * Contract Multiplier
      // = Size * ((Exit Price - Entry price)/ Entry price * Exit Price) * Side * Contract
      // Multiplier
      // = (Size * (Exit Price - Entry price) * Side * Contract Multiplier) / Entry price * Exit
      // Price
      realisedPnl =
          closeQuantity
              .multiply(exitPrice.subtract(entryPrice))
              .multiply(multiplier)
              .divide(entryPrice.multiply(exitPrice));
    } else {
      // Realized PNL = Size * (Exit price - Entry Price) * Side
      // =closeQuantity.multiply(exitPrice.subtract(entryPrice));
      realisedPnl = exitPrice.multiply(closeQuantity).add(closeValue);
    }

    log.debug(
        "getRealisedPnl ={} entryPrice={} exitPrice={} closeQuantity={} closeValue={}"
            + " multiplier={}",
        realisedPnl,
        entryPrice,
        exitPrice,
        closeQuantity,
        closeValue,
        multiplier);

    return realisedPnl;
  }

  public MarginBigDecimal getTakerFee(MarginBigDecimal price, MarginBigDecimal size) {
    MarginBigDecimal feeRate = instrument.getTakerFee().divide(MarginBigDecimal.valueOf(100));
    if (isCoinM) {
      // "Trading fee = Size * Contract Multiplier / Matching price * Taker/Maker fee"
      MarginBigDecimal multiplier = this.instrument.getMultiplier();
      return size.multiply(multiplier).multiplyThenDivide(feeRate, price).abs();
    } else {
      // "Trading fee = Size * Matching price * Taker/Maker fee"
      return price.multiply(size).abs().multiply(feeRate);
    }
  }

  public MarginBigDecimal getMakerFee(MarginBigDecimal price, MarginBigDecimal size) {
    MarginBigDecimal feeRate = instrument.getMakerFee().divide(MarginBigDecimal.valueOf(100));
    if (isCoinM) {
      // "Trading fee = Size * Contract Multiplier / Matching price * Taker/Maker fee"
      MarginBigDecimal multiplier = this.instrument.getMultiplier();
      return size.multiply(multiplier).multiplyThenDivide(feeRate, price).abs();
    } else {
      // "Trading fee = Size * Matching price * Taker/Maker fee"
      return price.multiply(size).abs().multiply(feeRate);
    }
  }

  /**
   * Calculate allocated margin of position
   *
   * @param position to calculate
   * @return
   */
  public MarginBigDecimal calcAllocatedMargin(@NotNull Position position) {
    if (position.isIsolated()
        && position.getPositionMargin() != null
        && !position.getPositionMargin().eq(MarginBigDecimal.ZERO)) {
      return position.getPositionMargin().add(position.getAdjustMargin());
    }

    MarginBigDecimal margin;
    if (isCoinM) {
      MarginBigDecimal multiplier = this.instrument.getMultiplier();
      if (position.isCross()) {
        // "Allocated Margin for Cross position
        // = Size * Contract Multiplier / (Leverage * Mark price)
        margin =
            positionMarginCrossCoinM(
                position.getCurrentQty(), multiplier, position.getLeverage(), oraclePrice);
      } else {
        // Allocated Margin for Isolated position = position margin + Added Margin
        margin =
            positionMarginIsolateCoinM(
                position.getCurrentQty(),
                multiplier,
                position.getLeverage(),
                position.getEntryPrice());
      }
    } else {
      if (position.isCross()) {
        // allocated margin cross position = [size * markPrice / leverage]
        margin =
            positionMarginCrossUsdM(position.getCurrentQty(), oraclePrice, position.getLeverage());
      } else {
        // allocated margin isolated position = position margin + Added Margin
        margin =
            positionMarginIsolateUsdM(
                position.getCurrentQty(), position.getEntryPrice(), position.getLeverage());
      }
    }
    position.setPositionMargin(margin);
    return position.isCross() ? margin : margin.add(position.getAdjustMargin());
  }

  public void reCalcPositionMarginIsolateOpen(Position position, MarginBigDecimal openValue) {
    if (position.isCross()) return;

    MarginBigDecimal margin;
    MarginBigDecimal multiplier = this.instrument.getMultiplier();
    if (isCoinM) {
      // New Position Margin =
      // Old Position Margin + Matching amount * Contract Multiplier / (Average price * Leverage)
      // openValue = size / tradePrice
      margin = openValue.abs().multiplyThenDivide(multiplier, position.getLeverage());
    } else {
      // New Position Margin =
      // Old Position Margin + Matching amount * Average price / Current Leverage
      // openValue = tradePrice * size
      margin = openValue.abs().divide(position.getLeverage());
    }
    margin = position.getPositionMargin().add(margin);
    log.atDebug()
        .addKeyValue("isCoinM", position.isCoinM())
        .addKeyValue("marginBefore", position.getPositionMargin())
        .addKeyValue("multiplier", multiplier)
        .addKeyValue("openValue", openValue)
        .addKeyValue("leverage", position.getLeverage())
        .addKeyValue("margin", margin)
        .log("re-calc open position margin isolate");
    position.setPositionMargin(margin);
  }

  @VisibleForTesting
  MarginBigDecimal positionMarginCrossUsdM(
      MarginBigDecimal size, MarginBigDecimal markPrice, MarginBigDecimal leverage) {
    // allocated margin cross position = [size * markPrice / leverage]
    return size.abs().multiplyThenDivide(markPrice, leverage);
  }

  @VisibleForTesting
  MarginBigDecimal positionMarginCrossCoinM(
      MarginBigDecimal size,
      MarginBigDecimal multiplier,
      MarginBigDecimal leverage,
      MarginBigDecimal markPrice) {
    // "Allocated Margin for Cross position
    // = Size * Contract Multiplier / (Leverage * Mark price)
    return size.abs().multiplyThenDivide(multiplier, leverage.multiply(markPrice));
  }

  @VisibleForTesting
  MarginBigDecimal positionMarginIsolateUsdM(
      MarginBigDecimal size, MarginBigDecimal entryPrice, MarginBigDecimal leverage) {
    // position margin = [size * entryPrice / leverage]
    return size.abs().multiplyThenDivide(entryPrice, leverage);
  }

  @VisibleForTesting
  MarginBigDecimal positionMarginIsolateCoinM(
      MarginBigDecimal size,
      MarginBigDecimal multiplier,
      MarginBigDecimal leverage,
      MarginBigDecimal entryPrice) {
    // position margin = Size * Contract Multiplier / (Leverage * Entry price)"
    return size.abs().multiplyThenDivide(multiplier, leverage.multiply(entryPrice));
  }

  public void reCalcPositionMarginIsolateClose(Position position, MarginBigDecimal oldQty) {
    if (position.isCross() || position.getCurrentQty().eq(MarginBigDecimal.ZERO)) return;

    // New Position Margin = Old Position Margin * New size / Old size
    MarginBigDecimal margin =
        position.getPositionMargin().multiplyThenDivide(position.getCurrentQty(), oldQty).abs();
    log.atDebug()
        .addKeyValue("marginBefore", position.getPositionMargin())
        .addKeyValue("oldSize", oldQty)
        .addKeyValue("newSize", position.getCurrentQty())
        .addKeyValue("margin", margin)
        .log("re-calc close position margin isolate");
    position.setPositionMargin(margin);
  }

  public MarginBigDecimal calcOrderMargin(Order order) {
    if (isCoinM) {
      MarginBigDecimal multiplier = instrument.getMultiplier();
      // Margin of each open Buy/Sell order = Size * Contract Multiplier / (Leverage * Input price)
      return order
          .getRemaining()
          .multiply(multiplier)
          .divide(order.getLeverage().multiply(order.getPrice()));
    } else {
      // Margin of each open Buy/Sell order = Input price(of this order) * Size(of this order)/
      // leverage
      if (order.getPrice() == null && order.getLockPrice() != null) {
        return order.getLockPrice().multiplyThenDivide(order.getRemaining(), order.getLeverage());
      }
      if (order.getPrice() == null) {
        log.atError()
            .addKeyValue("order", order)
            .log("Price of order is null, cannot calc order margin, return 0!");
        return MarginBigDecimal.ZERO;
      }
      return order.getPrice().multiplyThenDivide(order.getRemaining(), order.getLeverage());
    }
  }

  public MarginBigDecimal calcMulBuy(MarginBigDecimal inputPrice, MarginBigDecimal leverage) {
    MarginBigDecimal markPrice = this.oraclePrice;
    if (isCoinM) {
      // MulBuy = 1/(Input price * Leverage) + (1/ Mark price - 1/ Input price) * (1 + 1/ Leverage)
      // transform to avoid division
      // MulBuy = (Input price * Leverage + Input price - Mark price * Leverage)
      // % (Input price * Mark price * Leverage)
      MarginBigDecimal numerator =
          inputPrice.multiply(leverage).add(inputPrice).subtract(markPrice.multiply(leverage));
      MarginBigDecimal denominator = inputPrice.multiply(markPrice).multiply(leverage);
      return numerator.divide(denominator);
    } else {
      // MulBuy = Input price/ Leverage + Input price - Mark price
      return inputPrice.divide(leverage).add(inputPrice).subtract(markPrice);
    }
  }

  public MarginBigDecimal calcMulSell(MarginBigDecimal inputPrice, MarginBigDecimal leverage) {
    MarginBigDecimal markPrice = this.oraclePrice;
    if (isCoinM) {
      // MulSel = 1/(Input price * Leverage) + (1/ Input price - 1/ Mark price)
      // = (Mark price + Leverage * Mark price - Leverage * Input price) / (Input price * Mark price
      // * Leverage)
      MarginBigDecimal numerator =
          markPrice.add(leverage.multiply(markPrice)).subtract(leverage.multiply(inputPrice));
      MarginBigDecimal denominator = inputPrice.multiply(markPrice).multiply(leverage);
      return numerator.divide(denominator);
    } else {
      // MulSel = Input price/ Leverage + (Mark price - Input price) * (1 + 1/ Leverage)
      // = ((mark price - input price) * (leverage + 1) + input price) / leverage ->this get better
      // precision
      return markPrice
          .subtract(inputPrice)
          .multiply(leverage.add(MarginBigDecimal.ONE))
          .add(inputPrice)
          .divide(leverage);
    }
  }

  public MarginBigDecimal calculateOrderCostWithoutPosition(
      MarginBigDecimal inputPrice,
      MarginBigDecimal marBuy,
      MarginBigDecimal marSel,
      MarginBigDecimal mulBuy,
      MarginBigDecimal mulSell,
      Order order) {
    MarginBigDecimal multiplier = isCoinM ? this.instrument.getMultiplier() : MarginBigDecimal.ONE;
    MarginBigDecimal markPrice = this.oraclePrice;
    MarginBigDecimal size = order.getRemaining();
    MarginBigDecimal leverage = order.getLeverage();

    MarginBigDecimal orderCost = MarginBigDecimal.ZERO;
    boolean comparePrice = inputPrice.gt(markPrice);
    int compareMarBuySell = marBuy.compareTo(marSel);
    switch (compareMarBuySell) {
      case 1:
        // if marBuy > marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // Sell order cost = max (0,
              // Size * Contract Multiplier / (Leverage * Input price) - MarBuy + MarSel)
              MarginBigDecimal tempVal =
                  size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice))
                      .subtract(marBuy)
                      .add(marSel);
              orderCost = orderCost.max(tempVal);
            } else {
              // Sell order cost = max (0; Input price * Size/ Leverage - MarBuy + MarSel)
              MarginBigDecimal tempVal =
                  inputPrice.multiplyThenDivide(size, leverage).subtract(marBuy).add(marSel);
              orderCost = orderCost.max(tempVal);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size * Contract Multiplier * MulBuy
              orderCost = size.multiply(multiplier).multiply(mulBuy);
            } else {
              // Buy order cost = Size * MulBuy
              orderCost = size.multiply(mulBuy);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // "Sell order cost = Size * Contract Multiplier * MulSel -
              // min(Size *Contract Multiplier / (Leverage * Input price), MarBuy - MarSel)"
              MarginBigDecimal minTempVal =
                  marBuy
                      .subtract(marSel)
                      .min(size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice)));
              orderCost = size.multiply(multiplier).multiply(mulSell).subtract(minTempVal);
            } else {
              // Sell order cost = Size * MulSel - min (Size * Input price/ Leverage; MarBuy)
              MarginBigDecimal minTempVal =
                  marBuy.min(inputPrice.multiplyThenDivide(size, leverage));
              orderCost = size.multiply(mulSell).subtract(minTempVal);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
              orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
            } else {
              // Buy order cost = Input price * Size/ Leverage
              orderCost = inputPrice.multiplyThenDivide(size, leverage);
            }
          }
        }
        break;
      case -1:
        // if marBuy < marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier / (Leverage * Input price)
              orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
            } else {
              // Sell order cost = Input price * Size / Leverage
              orderCost = inputPrice.multiplyThenDivide(size, leverage);
            }
          } else {
            if (isCoinM) {
              // "Buy order cost = Size * Contract Multiplier * MulBuy -
              // min (Size * Contract Multiplier / (Leverage * Input price), MarSel - MarBuy)"
              MarginBigDecimal minTempVal =
                  marSel
                      .subtract(marBuy)
                      .min(size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice)));
              orderCost = size.multiply(multiplier).multiply(mulBuy).subtract(minTempVal);
            } else {
              // Buy order cost = Size * MulBuy - min(Size * Input price/ Leverage; MarSel)
              MarginBigDecimal minTempVal =
                  marSel.min(inputPrice.multiplyThenDivide(size, leverage));
              orderCost = size.multiply(mulBuy).subtract(minTempVal);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier * MulSel
              orderCost = size.multiply(multiplier).multiply(mulSell);
            } else {
              // Sell order cost = Size * MulSel
              orderCost = size.multiply(mulSell);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = max (0, Size * Contract Multiplier / (Leverage * Input price)
              // - MarSel + MarBuy)
              MarginBigDecimal tempVal =
                  size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice))
                      .subtract(marSel)
                      .add(marBuy);
              orderCost = orderCost.max(tempVal);
            } else {
              // Buy order cost = max (0; Input price * Size/ Leverage - MarSel + MarBuy)
              MarginBigDecimal tempVal =
                  inputPrice.multiplyThenDivide(size, leverage).subtract(marSel).add(marBuy);
              orderCost = orderCost.max(tempVal);
            }
          }
        }
        break;
      case 0:
        // if marBuy = marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier / (Leverage * Input price)
              orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
            } else {
              // Sell order cost = Input price * Size / Leverage
              orderCost = inputPrice.multiplyThenDivide(size, leverage);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size * Contract Multiplier * MulBuy
              orderCost = size.multiply(multiplier).multiply(mulBuy);
            } else {
              // Buy order cost = Size * MulBuy
              orderCost = size.multiply(mulBuy);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.isSellOrder()) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier * MulSel
              orderCost = size.multiply(multiplier).multiply(mulSell);
            } else {
              // Sell order cost = Size * MulSel
              orderCost = size.multiply(mulSell);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
              orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
            } else {
              // Buy order cost = Input price * Size/ Leverage
              orderCost = inputPrice.multiplyThenDivide(size, leverage);
            }
          }
        }
        break;
      default:
        throw new RuntimeException();
    }
    return orderCost;
  }

  public MarginBigDecimal calculateOrderCostWithPosition(
      boolean isLongPosition,
      MarginBigDecimal positionMargin,
      MarginBigDecimal positionSize,
      MarginBigDecimal inputPrice,
      MarginBigDecimal marBuy,
      MarginBigDecimal marSel,
      MarginBigDecimal mulBuy,
      MarginBigDecimal mulSell,
      Order order) {
    MarginBigDecimal multiplier = isCoinM ? this.instrument.getMultiplier() : MarginBigDecimal.ONE;
    MarginBigDecimal markPrice = this.oraclePrice;
    MarginBigDecimal size = order.getRemaining();
    MarginBigDecimal leverage = order.getLeverage();

    MarginBigDecimal orderCost = MarginBigDecimal.ZERO;
    boolean comparePrice = inputPrice.gt(markPrice);

    if (isLongPosition) {
      // user hold long position
      if (comparePrice) {
        // if inputPrice > markPrice
        if (order.isSellOrder()) {
          if (isCoinM) {
            // Sell order cost = max (0; Size * Contract Multiplier / (Leverage * Input price)
            // - 2 * Position Margin + MarSel - MarBuy)
            MarginBigDecimal tempVal =
                size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice))
                    .subtract(MarginBigDecimal.valueOf(2L).multiply(positionMargin))
                    .add(marSel)
                    .subtract(marBuy);
            orderCost = orderCost.max(tempVal);
          } else {
            // Sell order cost = max (0; Size * Input price/ Leverage - 2 * Position Margin + MarSel
            // -
            // MarBuy)
            MarginBigDecimal tempVal =
                inputPrice
                    .multiplyThenDivide(size, leverage)
                    .subtract(MarginBigDecimal.valueOf(2L).multiply(positionMargin))
                    .add(marSel)
                    .subtract(marBuy);
            orderCost = orderCost.max(tempVal);
          }
        } else {
          if (isCoinM) {
            // Buy order cost = Size * Contract Multiplier * MulBuy
            orderCost = size.multiply(multiplier).multiply(mulBuy);
          } else {
            // Buy order cost = Size * MulBuy
            orderCost = size.multiply(mulBuy);
          }
        }
      } else {
        // if inputPrice < markPrice
        if (order.isSellOrder()) {
          if (isCoinM) {
            // Sell order cost = max (0; (Order size - Position size) * Contract Multiplier
            // * MulSel - Position Margin + MarSel - MarBuy)
            orderCost =
                orderCost.max(
                    mulSell
                        .multiply(size.subtract(positionSize))
                        .multiply(multiplier)
                        .subtract(positionMargin)
                        .add(marSel)
                        .subtract(marBuy));
          } else {
            // Sell order cost = max (0; (Order size - Position size) * MulSel - Position Margin +
            // MarSel - MarBuy)
            orderCost =
                orderCost.max(
                    mulSell
                        .multiply(size.subtract(positionSize))
                        .subtract(positionMargin)
                        .add(marSel)
                        .subtract(marBuy));
          }
        } else {
          if (isCoinM) {
            // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
            orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
          } else {
            // Buy order cost = Input price * Size/ Leverage
            orderCost = inputPrice.multiplyThenDivide(size, leverage);
          }
        }
      }
    } else {
      // user hold short position
      if (comparePrice) {
        // if inputPrice > markPrice
        if (order.isSellOrder()) {
          if (isCoinM) {
            // Sell order cost = Size *Contract Multiplier / (Leverage * Input price)
            orderCost = size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice));
          } else {
            // Sell order cost = Size * Input price/ Leverage
            orderCost = inputPrice.multiplyThenDivide(size, leverage);
          }
        } else {
          if (isCoinM) {
            // Buy order cost = max (0; (Order size - Position size)
            // * Contract Multiplier * MulBuy - Position Margin + MarBuy - MarSel)
            MarginBigDecimal temVal =
                mulBuy
                    .multiply(size.subtract(positionSize))
                    .multiply(multiplier)
                    .subtract(positionMargin)
                    .add(marBuy)
                    .subtract(marSel);
            orderCost = orderCost.max(temVal);
          } else {
            // Buy order cost = max (0; (Order size - Position size) * MulBuy - Position Margin +
            // MarBuy - MarSel)
            MarginBigDecimal temVal =
                mulBuy
                    .multiply(size.subtract(positionSize))
                    .subtract(positionMargin)
                    .add(marBuy)
                    .subtract(marSel);
            orderCost = orderCost.max(temVal);
          }
        }
      } else {
        // if inputPrice < markPrice
        if (order.isSellOrder()) {
          if (isCoinM) {
            // Sell order cost = Size * Contract Multiplier * MulSel
            orderCost = size.multiply(multiplier).multiply(mulSell);
          } else {
            // Sell order cost = Size * MulSel
            orderCost = size.multiply(mulSell);
          }
        } else {
          if (isCoinM) {
            // Buy order cost = max (0; Size *Contract Multiplier / (Leverage * Input price)
            // - 2 * Position Margin + MarBuy - MarSel)
            MarginBigDecimal temVal =
                size.multiplyThenDivide(multiplier, leverage.multiply(inputPrice))
                    .subtract(MarginBigDecimal.valueOf(2L).multiply(positionMargin))
                    .add(marBuy)
                    .subtract(marSel);
            orderCost = orderCost.max(temVal);
          } else {
            // Buy order cost = max (0; Size * Input price/ Leverage - 2 * Position Margin + MarBuy
            // -
            // MarSel)
            MarginBigDecimal temVal =
                inputPrice
                    .multiplyThenDivide(size, leverage)
                    .subtract(MarginBigDecimal.valueOf(2L).multiply(positionMargin))
                    .add(marBuy)
                    .subtract(marSel);
            orderCost = orderCost.max(temVal);
          }
        }
      }
    }
    return orderCost;
  }

  public MarginBigDecimal getOraclePrice() {
    return this.oraclePrice;
  }

  /**
   * "Max removable = Max (0; min (Allocated Margin for the position; Allocated Margin for the
   * position + Size * (Mark Price - Entry Price) * Side - Mark Price * Size /Leverage))"
   *
   * @param allocatedMargin
   * @param markPrice
   * @param position
   * @return
   */
  public MarginBigDecimal getMaxRemovableAdjustMargin(
      MarginBigDecimal allocatedMargin, MarginBigDecimal markPrice, Position position) {
    MarginBigDecimal sizeWithSide = position.getCurrentQty();
    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal leverage = position.getLeverage();
    if (isCoinM) {
      // "Max removable
      // = Max (0; min (Allocated Margin for the position; Allocated Margin for the position +
      // Size * Contract Multiplier * (1/ Entry Price - 1/ Mark Price) * Side
      // - Size * Contract Multiplier / (Leverage * Mark price)))"
      MarginBigDecimal multiplier = this.instrument.getMultiplier();
      MarginBigDecimal tempVal =
          sizeWithSide
              .multiply(multiplier)
              .multiply(oraclePrice.subtract(entryPrice).divide(entryPrice.multiply(oraclePrice)))
              .subtract(
                  sizeWithSide
                      .abs()
                      .multiplyThenDivide(multiplier, leverage.multiply(oraclePrice)));
      return MarginBigDecimal.ZERO.max(allocatedMargin.min(allocatedMargin.add(tempVal)));
    }
    // "Max removable
    // = Max (0; min (Allocated Margin for the position; Allocated Margin for the position +
    // Size * (Mark Price - Entry Price) * Side - Mark Price *  Size /Leverage))"
    MarginBigDecimal allocatedMarginWithTmpTotalFee = allocatedMargin.add(position.getTmpTotalFee());
    return MarginBigDecimal.ZERO.max(
        allocatedMarginWithTmpTotalFee.min(
            allocatedMarginWithTmpTotalFee
                .add(sizeWithSide.multiply(markPrice.subtract(entryPrice)))
                .subtract(markPrice.multiplyThenDivide(sizeWithSide.abs(), leverage))));
  }

  public MarginBigDecimal calcFundingFee(
      MarginBigDecimal oraclePrice,
      MarginBigDecimal positionQuantity,
      MarginBigDecimal fundingRate) {
    if (isCoinM) {
      // Funding payment = (-1) * Position size * Contract Multiplier * Funding rate * Side / Mark
      // price
      MarginBigDecimal multiplier = this.instrument.getMultiplier();
      return positionQuantity
          .multiply(multiplier)
          .multiply(fundingRate.divide(MarginBigDecimal.valueOf("100")))
          .divide(oraclePrice)
          .negate();
    } else {
      // Funding payment = (-1) * Position size * Mark price * Funding rate * Side
      return oraclePrice
          .multiply(positionQuantity)
          .multiply(fundingRate.divide(MarginBigDecimal.valueOf("100")))
          .negate();
    }
  }

  public MarginBigDecimal calcAvgClosePrice(
      Position position, MarginBigDecimal closeSize, MarginBigDecimal closePrice) {
    // newAvgClosePrice = (oldAvgClosePrice * oldCloseSize
    // + currentAvgClosePrice * currentCloseSize)/
    // (oldCloseSize + currentCloseSize)
    return position
        .getAvgClosePrice()
        .multiply(position.getCloseSize())
        .add(closePrice.multiply(closeSize))
        .divide(position.getCloseSize().add(closeSize));
  }
}

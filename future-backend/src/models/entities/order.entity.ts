import { Expose, Transform } from "class-transformer";
import {
  MarginMode,
  OrderNote,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderTrigger,
  OrderType,
  TpSlType,
} from "src/shares/enums/order.enum";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  Index,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

export const MIN_ORDER_ID = 100000000000;

@Entity({
  name: "orders",
})
@Index("idx_orders_margin_calc", ["accountId", "asset", "status"])
@Index("idx_orders_account_status", ["accountId", "status"])
export class OrderEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  accountId: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  side: OrderSide;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  type: OrderType;

  @Column()
  @Expose()
  quantity: string;

  @Column()
  @Expose()
  price: string;

  @Column()
  @Expose()
  lockPrice: string;

  @Column()
  @Expose()
  orderValue: string;

  @Column()
  @Expose()
  remaining: string;

  @Column()
  @Expose()
  executedPrice: string;

  @Column()
  @Expose()
  tpSLType: TpSlType;

  @Column()
  @Expose()
  tpSLPrice: string;

  @Column()
  @Expose()
  trigger: OrderTrigger;

  @Column()
  @Expose()
  timeInForce: OrderTimeInForce;

  @Column()
  @Expose()
  trailPrice: string;

  @Column()
  @Expose()
  status: OrderStatus;

  @Column()
  @Expose()
  asset: string;

  @Column()
  @Expose()
  isReduceOnly: boolean;

  @Column()
  @Expose()
  isPostOnly: boolean;

  @Column()
  @Expose()
  note: OrderNote;

  @Column()
  @Expose()
  operationId: string;

  @Column()
  @Expose()
  leverage: string;

  @Column()
  @Expose()
  marginMode: MarginMode;

  @Column()
  @Expose()
  takeProfitOrderId: number;

  @Column()
  @Expose()
  stopLossOrderId: number;

  @Column()
  @Expose()
  parentOrderId: number;

  @Column()
  @Expose()
  callbackRate: string;

  @Column()
  @Expose()
  activationPrice: string;

  @Column()
  @Expose()
  stopCondition: string;

  @Column({ default: 0 })
  @Expose()
  cost: string;

  @Column()
  @Expose()
  isClosePositionOrder: boolean;

  @Column()
  @Expose()
  isHidden: boolean;

  @Column()
  @Expose()
  isTriggered: boolean;

  @Column()
  @Expose()
  linkedOrderId: number;

  @Column()
  @Expose()
  isTpSlOrder: boolean;

  @Column()
  @Expose()
  isTpSlTriggered: boolean;

  @Column()
  @Expose()
  contractType: string;

  @Column()
  @Expose()
  userEmail: string;

  @Column({ default: 0 })
  @Expose()
  orderMargin: string;

  @Column({ default: 0 })
  @Expose()
  originalCost: string;

  @Column({ default: 0 })
  @Expose()
  originalOrderMargin: string;

  @Column()
  @Expose()
  tmpId: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  public isLimitOrder() {
    return this.type === OrderType.LIMIT && this.isPostOnly === false;
  }

  public isStopLimitOrder() {
    return this.type === OrderType.LIMIT && this.tpSLType?.toString() === TpSlType.STOP_LIMIT.toString() && this.isPostOnly === false;
  }

  public isStopMarketOrder() {
    return (
      this.type === OrderType.MARKET && this.tpSLPrice?.toString() === OrderType.STOP_MARKET.toString() && this.isPostOnly === false
    );
  }

  public isTrailingStopOrder() {
    return (
      this.type === OrderType.MARKET && this.tpSLPrice?.toString() === OrderType.TRAILING_STOP.toString() && this.isPostOnly === false
    );
  }

  public isPostOnlyOrder() {
    return this.isPostOnly
  }

  public isLiquidationOrder() {
    return this.note != null && this.note === OrderNote.LIQUIDATION;
  }

  public isStopLossOrder() {
    return this.type === OrderType.MARKET && this.tpSLType === TpSlType.STOP_MARKET && this.isTpSlOrder;
  }

  public isTakeProfitMarketOrder() {
    return this.type === OrderType.MARKET && this.tpSLType === TpSlType.TAKE_PROFIT_MARKET;
  }
}

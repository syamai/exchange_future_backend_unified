import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  BaseEntity,
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "trading_rules",
})
export class TradingRulesEntity extends BaseEntity {
  @PrimaryGeneratedColumn()
  id: number;

  // @Column()
  // @Expose()
  // instrumentId: number;

  @Column()
  @Expose()
  minTradeAmount: string;

  @Column()
  @Expose()
  minOrderAmount: string;

  @Column()
  @Expose()
  minPrice: string;

  @Column()
  @Expose()
  limitOrderPrice: string;

  @Column()
  @Expose()
  floorRatio: string;

  @Column()
  @Expose()
  maxMarketOrder: string;

  @Column()
  @Expose()
  limitOrderAmount: string;

  @Column()
  @Expose()
  numberOpenOrders: string;

  @Column()
  @Expose()
  priceProtectionThreshold: string;

  @Column()
  @Expose()
  liqClearanceFee: string;

  @Column()
  @Expose()
  minNotional: string;

  @Column()
  @Expose()
  marketOrderPrice: string;

  @Column()
  @Expose()
  isReduceOnly: boolean;

  @Column()
  @Expose()
  positionsNotional: string;

  @Column()
  @Expose()
  ratioOfPostion: string;

  @Column()
  @Expose()
  liqMarkPrice: string;

  @Column()
  @Expose()
  maxLeverage: number;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  @Expose()
  maxNotinal: string;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  maxOrderAmount: string;
}

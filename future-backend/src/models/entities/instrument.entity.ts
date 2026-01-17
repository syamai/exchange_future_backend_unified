import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  BaseEntity,
  Column,
  CreateDateColumn,
  Entity,
  OneToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";
import { MarketFeeEntity } from "./market_fee.entity";

@Entity({
  name: "instruments",
})
export class InstrumentEntity extends BaseEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  name: string;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  rootSymbol: string;

  @Column()
  @Expose()
  state: string;

  @Column()
  @Expose()
  expiry: Date;

  @Column()
  @Expose()
  baseUnderlying: string;

  @Column()
  @Expose()
  quoteCurrency: string;

  @Column()
  @Expose()
  underlyingSymbol: string;

  @Column()
  @Expose()
  settleCurrency: string;

  @Column()
  @Expose()
  initMargin: string;

  @Column()
  @Expose()
  maintainMargin: string;

  @Column()
  @Expose()
  deleverageable: boolean;

  @Column()
  @Expose()
  makerFee: string;

  @Column()
  @Expose()
  takerFee: string;

  @Column()
  @Expose()
  settlementFee: string;

  @Column()
  @Expose()
  hasLiquidity: boolean;

  @Column()
  @Expose()
  referenceIndex: string;

  @Column()
  @Expose()
  settlementIndex: string;

  @Column()
  @Expose()
  fundingBaseIndex: string;

  @Column()
  @Expose()
  fundingQuoteIndex: string;

  @Column()
  @Expose()
  fundingPremiumIndex: string;

  @Column()
  @Expose()
  fundingInterval: number;

  @Column()
  @Expose()
  tickSize: string;

  @Column()
  @Expose()
  contractSize: string;

  @Column()
  @Expose()
  lotSize: string;

  @Column()
  @Expose()
  maxPrice: string;

  @Column()
  @Expose()
  maxOrderQty: number;

  @Column()
  @Expose()
  multiplier: string;

  @Column()
  @Expose()
  optionStrikePrice: string;

  @Column()
  @Expose()
  optionKoPrice: string;

  @Column()
  @Expose()
  riskStep: string;

  @Column()
  @Expose()
  rank: number;

  @OneToOne(() => MarketFeeEntity, (marketFee) => marketFee.instrument)
  marketFee: MarketFeeEntity;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  @Expose()
  minPriceMovement: string;

  @Column()
  @Expose()
  maxFiguresForSize: string;

  @Column()
  @Expose()
  maxFiguresForPrice: string;

  @Column()
  @Expose()
  impactMarginNotional: string;

  @Column()
  @Expose()
  contractType: string;

  @Column()
  @Expose()
  thumbnail?: string;
}

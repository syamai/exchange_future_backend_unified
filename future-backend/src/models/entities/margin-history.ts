import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "margin_histories",
})
export class MarginHistoryEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  accountId: string;

  @Column()
  @Expose()
  action: string;

  @Column()
  @Expose()
  orderId: string;

  @Column()
  @Expose()
  tradeId: string;

  @Column()
  @Expose()
  positionId: string;

  @Column()
  @Expose()
  leverage: string;

  @Column()
  @Expose()
  leverageAfter: string;

  @Column()
  @Expose()
  entryPrice: string;

  @Column()
  @Expose()
  entryPriceAfter: string;

  @Column()
  @Expose()
  entryValue: string;

  @Column()
  @Expose()
  entryValueAfter: string;

  @Column()
  @Expose()
  currentQty: string;

  @Column()
  @Expose()
  currentQtyAfter: string;

  @Column()
  @Expose()
  liquidationPrice: string;

  @Column()
  @Expose()
  liquidationPriceAfter: string;

  @Column()
  @Expose()
  liquidationProgress: number;

  @Column()
  @Expose()
  liquidationProgressAfter: number;

  @Column()
  @Expose()
  pnlRanking: string;

  @Column()
  @Expose()
  pnlRankingAfter: string;

  @Column()
  @Expose()
  openOrderBuyQtyAfter: string;

  @Column()
  @Expose()
  openOrderSellQtyAfter: string;

  @Column()
  @Expose()
  openOrderBuyValueAfter: string;

  @Column()
  @Expose()
  openOrderSellValueAfter: string;

  @Column()
  @Expose()
  balance: string;

  @Column()
  @Expose()
  balanceAfter: string;

  @Column()
  @Expose()
  orderValue: string;

  @Column()
  @Expose()
  contractMargin: string;

  @Column()
  @Expose()
  operationId: string;

  @Column()
  @Expose()
  tradeUuid: string;

  @Column()
  @Expose()
  realizedPnl: string;

  @Column()
  @Expose()
  tradePrice: string;

  @Column()
  @Expose()
  fee: string;

  @Column()
  @Expose()
  closeFee: string;

  @Column()
  @Expose()
  openFee: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

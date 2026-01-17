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
  name: "positions",
})
export class PositionEntity {
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
  symbol: string;

  @Column()
  @Expose()
  leverage: string;

  @Column()
  @Expose()
  currentQty: string;

  @Column()
  @Expose()
  liquidationPrice: string;

  @Column()
  @Expose()
  bankruptPrice: string;

  @Column()
  @Expose()
  entryPrice: string;

  @Column()
  @Expose()
  entryValue: string;

  @Column()
  @Expose()
  liquidationProgress: number;

  @Column()
  @Expose()
  takeProfitOrderId: number;

  @Column()
  @Expose()
  stopLossOrderId: number;

  @Column()
  @Expose()
  adjustMargin: string;

  @Column()
  @Expose()
  isCross: boolean;

  @Column()
  @Expose()
  pnlRanking: string;

  @Column()
  @Expose()
  operationId: string;

  @Column()
  @Expose()
  asset: string;

  @Column()
  @Expose()
  contractType: string;

  @Column()
  @Expose()
  marBuy: string;

  @Column()
  @Expose()
  marSel: string;

  @Column()
  @Expose()
  orderCost: string;

  @Column()
  @Expose()
  positionMargin: string;

  @Column()
  @Expose()
  closeSize: string;

  @Column()
  @Expose()
  avgClosePrice: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  @Expose()
  tmpTotalFee?: string;

  @Column()
  @Transform(dateTransformer)
  lastOpenTime: Date;
}

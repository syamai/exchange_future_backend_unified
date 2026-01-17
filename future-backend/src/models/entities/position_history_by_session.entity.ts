import { Expose, Transform } from "class-transformer";
import { MarginMode } from "src/shares/enums/order.enum";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "position_history_by_session",
})
export class PositionHistoryBySessionEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  accountId: number;

  @Column()
  @Expose()
  userEmail: string;

  @Column()
  @Expose()
  positionId: number;

  @Column()
  @Expose()
  @Transform(dateTransformer)
  openTime: Date;

  @Column()
  @Expose()
  @Transform(dateTransformer)
  closeTime: Date;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  leverages: string;

  @Column()
  @Expose()
  marginMode: MarginMode;

  @Column()
  @Expose()
  side: string;

  @Column()
  @Expose()
  sumEntryPrice: string;

  @Column()
  @Expose()
  numOfOpenOrders: number;

  @Column()
  @Expose()
  sumClosePrice: string;

  @Column()
  @Expose()
  numOfCloseOrders: number;

  @Column()
  @Expose()
  minMargin: string;

  @Column()
  @Expose()
  maxMargin: string;

  @Column()
  @Expose()
  sumMargin: string;

  @Column()
  @Expose()
  minSize: string;

  @Column()
  @Expose()
  maxSize: string;

  @Column()
  @Expose()
  minValue: string;

  @Column()
  @Expose()
  maxValue: string;

  @Column()
  @Expose()
  pnl: string; // including fees

  @Column()
  @Expose()
  pnlAfterFundingFee?: string; // including funding fees

  @Column()
  @Expose()
  profit?: string; // not including fees

  @Column()
  @Expose()
  fee: string;

  @Column()
  @Expose()
  fundingFee: string;

  @Column()
  @Expose()
  openingFee: string;

  @Column()
  @Expose()
  closingFee: string;

  @Column()
  @Expose()
  pnlRate: string;

  @Column()
  @Expose()
  status: string;

  @Column()
  @Expose()
  checkingStatus?: string;

  @Column()
  @Expose()
  hasUpdatedFundingFee?: boolean;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
} 
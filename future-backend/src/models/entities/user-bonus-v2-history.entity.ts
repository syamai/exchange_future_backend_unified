import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from "typeorm";

export enum BonusChangeType {
  GRANT = "GRANT",
  TRADING_FEE = "TRADING_FEE",
  FUNDING_FEE = "FUNDING_FEE",
  REALIZED_PNL = "REALIZED_PNL",
  LIQUIDATION = "LIQUIDATION",
  REVOKE = "REVOKE",
}

@Entity({
  name: "user_bonus_v2_history",
})
export class UserBonusV2HistoryEntity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: number;

  @Column({ type: "bigint" })
  @Expose()
  userBonusId: number;

  @Column({ type: "bigint" })
  @Expose()
  userId: number;

  @Column({ length: 30 })
  @Expose()
  changeType: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  changeAmount: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  principalBefore: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  principalAfter: string;

  @Column({ length: 100, nullable: true })
  @Expose()
  transactionUuid: string | null;

  @Column({ length: 200, nullable: true })
  @Expose()
  description: string | null;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;
}

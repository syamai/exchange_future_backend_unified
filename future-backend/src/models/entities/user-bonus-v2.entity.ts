import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

export enum BonusStatusV2 {
  ACTIVE = "ACTIVE",
  LIQUIDATED = "LIQUIDATED",
  EXPIRED = "EXPIRED",
  REVOKED = "REVOKED",
}

@Entity({
  name: "user_bonus_v2",
})
export class UserBonusV2Entity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: number;

  @Column({ type: "bigint" })
  @Expose()
  userId: number;

  @Column({ type: "bigint" })
  @Expose()
  accountId: number;

  @Column({ type: "bigint" })
  @Expose()
  eventSettingId: number;

  @Column({ type: "bigint" })
  @Expose()
  transactionId: number;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  bonusAmount: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  originalDeposit: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
  })
  @Expose()
  currentPrincipal: string;

  @Column({
    type: "enum",
    enum: BonusStatusV2,
    default: BonusStatusV2.ACTIVE,
  })
  @Expose()
  status: BonusStatusV2;

  @Column({ type: "datetime" })
  @Expose()
  grantedAt: Date;

  @Column({ type: "datetime", nullable: true })
  @Expose()
  liquidatedAt: Date | null;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

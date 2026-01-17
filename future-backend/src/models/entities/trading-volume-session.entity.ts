import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn
} from "typeorm";

@Entity({
  name: "trading_volume_session",
})
export class TradingVolumeSessionEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  startDate: Date;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalReward: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  currentTradingVolume: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalProfit: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalLoss: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalUsedReward: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  targetTradingVolume: string;

  @Column()
  @Expose()
  sessionUUID: string;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  status: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

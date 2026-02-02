import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

export enum EventStatusV2 {
  ACTIVE = "ACTIVE",
  INACTIVE = "INACTIVE",
}

@Entity({
  name: "event_setting_v2",
})
export class EventSettingV2Entity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: number;

  @Column({ length: 100 })
  @Expose()
  eventName: string;

  @Column({ length: 50, unique: true })
  @Expose()
  eventCode: string;

  @Column({
    type: "enum",
    enum: EventStatusV2,
    default: EventStatusV2.INACTIVE,
  })
  @Expose()
  status: EventStatusV2;

  @Column({
    type: "decimal",
    precision: 10,
    scale: 2,
    default: 100.0,
  })
  @Expose()
  bonusRatePercent: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  minDepositAmount: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  maxBonusAmount: string;

  @Column({ type: "datetime" })
  @Expose()
  startDate: Date;

  @Column({ type: "datetime" })
  @Expose()
  endDate: Date;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

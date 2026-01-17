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
  name: "leverage_margin",
})
export class LeverageMarginEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  tier: number;

  // @Column()
  // @Expose()
  // instrumentId: number;

  @Column()
  @Expose()
  min: number;

  @Column()
  @Expose()
  max: number;

  @Column()
  @Expose()
  maxLeverage: number;

  @Column()
  @Expose()
  maintenanceMarginRate: number;

  @Column()
  @Expose()
  maintenanceAmount: number;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  contractType: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

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
  name: "candles",
})
export class CandlesEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  low: string;

  @Column()
  @Expose()
  high: string;

  @Column()
  @Expose()
  open: string;

  @Column()
  @Expose()
  close: string;

  @Column()
  @Expose()
  minute: number;

  @Column()
  @Expose()
  resolution: number;

  @Column()
  @Expose()
  volume: string;

  @Column()
  @Expose()
  lastTradeTime: number;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

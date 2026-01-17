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
  name: "market_data",
})
export class MarketDataEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  market: string;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  group: string;

  @Column()
  @Expose()
  bid: string;

  @Column()
  @Expose()
  ask: string;

  @Column()
  @Expose()
  index: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

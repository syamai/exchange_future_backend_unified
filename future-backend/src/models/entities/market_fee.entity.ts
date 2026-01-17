import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  OneToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";
import { InstrumentEntity } from "./instrument.entity";

@Entity({
  name: "market_fee",
})
export class MarketFeeEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  instrumentId: number;

  @Column()
  @Expose()
  makerFee: string;

  @Column()
  @Expose()
  takerFee: string;

  @OneToOne(() => InstrumentEntity)
  @JoinColumn({ name: "instrumentId" })
  instrument: InstrumentEntity;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

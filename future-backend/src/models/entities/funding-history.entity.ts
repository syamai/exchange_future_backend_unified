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
  name: "funding_histories",
})
export class FundingHistoryEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  accountId: string;

  @Column()
  @Expose()
  positionId: string;

  @Column()
  @Transform(dateTransformer)
  time: Date;

  @Column()
  @Expose()
  amount: string;

  @Column()
  @Expose()
  fundingRate: string;

  @Column()
  @Expose()
  fundingQuantity: string;

  @Column()
  @Expose()
  operationId: string;

  @Column()
  @Expose()
  asset: string;

  @Column()
  @Expose()
  fundingInterval: string;

  @Column()
  @Expose()
  userId: number;

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

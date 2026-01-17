import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  BaseEntity,
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "transactions",
})
export class TransactionEntity extends BaseEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  accountId: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  amount: string;

  @Column()
  @Expose()
  status: string;

  @Column()
  @Expose()
  type: string;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  asset: string;

  @Column()
  @Expose()
  operationId: number;

  @Column()
  @Expose()
  contractType: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  @Expose()
  uuid?: string;
}

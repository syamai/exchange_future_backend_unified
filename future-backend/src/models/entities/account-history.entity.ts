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
  name: "account_histories",
})
export class AccountHistoryEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  accountId: number;

  @Column()
  @Expose()
  balance: string;

  @Column()
  @Expose()
  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @Column()
  @Expose()
  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

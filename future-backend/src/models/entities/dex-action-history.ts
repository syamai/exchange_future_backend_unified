import { Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "dex_action_histories" })
export class DexActionHistory {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  txid: string;

  @Column({ type: "int" })
  logIndex: number;

  @Column()
  address: string;

  @Column({ type: "bigint" })
  accountId: number;

  @Column()
  action: string;

  @Column({ type: "bigint" })
  actionId: string;

  @Column({ type: "bigint" })
  operationId: string;

  @Column()
  validStatus: string;

  @Column()
  oldMargin: string;

  @Column()
  newMargin: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

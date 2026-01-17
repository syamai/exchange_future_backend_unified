import { Transform } from "class-transformer";
import { DexTransactionStatus } from "src/modules/dex/dex.constant";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "dex_action_transactions" })
export class DexActionTransaction {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  txid?: string;

  @Column()
  matcherAddress?: string;

  @Column({ type: "bigint" })
  nonce?: string;

  @Column()
  rawTx: string;

  @Column()
  status: DexTransactionStatus;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

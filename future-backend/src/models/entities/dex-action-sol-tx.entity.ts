import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "dex_action_sol_txs" })
export class DexActionSolTxEntity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  @Expose()
  txid: string;

  @Column({ type: "bigint" })
  @Expose()
  slot: string;

  @Column()
  @Expose()
  logs: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

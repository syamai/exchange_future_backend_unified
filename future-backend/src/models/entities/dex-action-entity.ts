import { Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "dex_actions" })
export class DexAction {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  action: string;

  @Column({ type: "bigint" })
  actionId: string;

  @Column({ type: "bigint" })
  kafkaOffset: string;

  @Column({ type: "json" })
  rawParameter: any;

  @Column({ type: "json" })
  dexParameter: any;

  @Column({ type: "bigint" })
  dexActionTransactionId: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

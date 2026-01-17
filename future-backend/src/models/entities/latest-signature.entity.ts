import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "latest_signatures" })
export class LatestSignatureEntity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  @Expose()
  signature: string;

  @Column()
  @Expose()
  service: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

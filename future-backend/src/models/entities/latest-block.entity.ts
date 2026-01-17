import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "latest_blocks" })
export class LatestBlockEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  blockNumber: number;

  @Column()
  @Expose()
  status: string;

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

import { Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "api_keys" })
export class ApiKey {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column({ type: "bigint" })
  userId: string;

  @Column()
  key: string;

  @Column()
  type: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

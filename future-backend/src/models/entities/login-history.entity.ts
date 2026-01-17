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
  name: "login_histories",
})
export class LoginHistoryEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  ip: string;

  @Column()
  @Expose()
  device: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  updatedAt: Date;
}

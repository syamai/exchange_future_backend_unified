import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({ name: "settings" })
export class SettingEntity {
  public static MINIMUM_WITHDRAWAL = "MINIMUM_WITHDRAWAL";
  public static WITHDRAW_FEE = "WITHDRAW_FEE";

  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  key: string;

  @Column()
  @Expose()
  value: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

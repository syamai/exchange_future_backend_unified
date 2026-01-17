import { Expose, Transform } from "class-transformer";
import { MarginMode } from "src/shares/enums/order.enum";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "user_margin_mode",
})
export class UserMarginModeEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  instrumentId: number;

  @Column()
  @Expose()
  marginMode: MarginMode;

  @Column()
  @Expose()
  leverage: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

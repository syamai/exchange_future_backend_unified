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
  name: "user_settings",
})
export class UserSettingEntity {
  public static NOTIFICATION = "NOTIFICATION";

  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  key: string;

  @Column()
  @Expose()
  limitOrder: boolean;

  @Column()
  @Expose()
  marketOrder: boolean;

  @Column()
  @Expose()
  stopLimitOrder: boolean;

  @Column()
  @Expose()
  stopMarketOrder: boolean;

  @Column()
  @Expose()
  traillingStopOrder: boolean;

  @Column()
  @Expose()
  takeProfitTrigger: boolean;

  @Column()
  @Expose()
  stopLossTrigger: boolean;

  @Column()
  @Expose()
  fundingFeeTriggerValue: number;

  @Column()
  @Expose()
  fundingFeeTrigger: boolean;

  @Column()
  @Expose()
  isFavorite: boolean;

  @Column()
  @Expose()
  time: Date;

  @Column()
  @Expose()
  notificationQuantity: number;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  @Expose()
  enablePriceChangeFireBase: boolean;

  @UpdateDateColumn()
  favoritedAt: Date;
}

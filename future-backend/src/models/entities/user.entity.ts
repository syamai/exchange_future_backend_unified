import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import { Column, CreateDateColumn, Entity, PrimaryGeneratedColumn, UpdateDateColumn } from "typeorm";
export const ADMIN_ID = 1475;

@Entity({
  name: "users",
})
export class UserEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  email: string;

  @Column()
  @Expose()
  position: string;

  @Column()
  @Expose()
  role: string;

  @Column()
  @Expose()
  status: string;

  @Column()
  @Expose()
  isLocked: string;

  @Column()
  @Expose()
  userType: string;

  @Column()
  @Expose()
  antiPhishingCode?: string;

  @Column()
  @Expose()
  location: string;

  @Column()
  @Expose()
  notification_token: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column({ default: true })
  @Expose()
  allowTrade: boolean;

  @Column({ default: true })
  @Expose()
  enableTradingFee: boolean;

  @Column({ default: false })
  @Expose()
  isMarketMaker: boolean;

  @Column({ nullable: true, type: 'json' })
  @Expose()
  preTradingPair: object;

  @Column()
  @Expose()
  uid: string;

  @Column({ default: false, nullable: true })
  @Expose()
  isBot: boolean;

  @Column({ default: false, nullable: true })
  @Expose()
  hasDeposited: boolean;
}

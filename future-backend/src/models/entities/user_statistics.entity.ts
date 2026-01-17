import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import { Column, CreateDateColumn, Entity, PrimaryColumn, UpdateDateColumn } from "typeorm";

@Entity({
  name: "user_statistics",
})
export class UserStatisticEntity {
  @PrimaryColumn()
  id: number; //userId

  @Column({ default: 0 })
  @Expose()
  totalDeposit: string;

  @Column({ default: 0 })
  @Expose()
  pnlGain: string;

  // @Column({ default: null })
  // @Expose()
  // gainPercent: number; // pnlGain / totalDeposit * 100

  @Column({ default: 0 })
  @Expose()
  peakAssetValue: string;

  @Column({ default: 0 })
  @Expose()
  pnlLoss: string;

  // @Column({ default: null })
  // @Expose()
  // lossPercent: string;

  @Column({ default: 0 })
  @Expose()
  totalWithdrawal: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column({ default: 0 })
  @Expose()
  totalTradeVolume: string;
}

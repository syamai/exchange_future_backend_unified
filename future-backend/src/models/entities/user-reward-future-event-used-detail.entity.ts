import { Expose } from "class-transformer";
import {
  Column,
  Entity,
  PrimaryGeneratedColumn
} from "typeorm";

@Entity({
  name: "user_reward_future_event_used_detail",
})
export class UserRewardFutureEventUsedDetailEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  transactionUuid: string;

  @Column()
  @Expose()
  amount: string;

  @Column()
  @Expose()
  dateUsed: Date;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  transactionType: string;

  @Column()
  @Expose()
  rewardId: number;

  @Column()
  @Expose()
  rewardUsedId: number;

  @Column()
  @Expose()
  rewardAmountBefore: string;

  @Column()
  @Expose()
  rewardAmountAfter: string
}

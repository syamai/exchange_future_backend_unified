import { Expose } from "class-transformer";
import {
  Column,
  Entity,
  PrimaryGeneratedColumn
} from "typeorm";

@Entity({
  name: "user_reward_future_event_used",
})
export class UserRewardFutureEventUsedEntity {
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
  remainingRewardBalance: string;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  transactionType: string;
}

import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn
} from "typeorm";

@Entity({
  name: "user_reward_future_event",
})
export class UserRewardFutureEventEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  userId: number;

  @Column()
  @Expose()
  amount: string;

  @Column()
  @Expose()
  expiredDate: string;

  @Column()
  @Expose()
  eventName: string;

  @Column()
  @Expose()
  isRevoke: boolean;

  @Column()
  @Expose()
  status: string;

  @Column()
  @Expose()
  refId?: number;

  @Column()
  @Expose()
  remaining: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

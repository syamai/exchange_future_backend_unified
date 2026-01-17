import { Global, Module } from "@nestjs/common";
import { BullModule } from "@nestjs/bull";
import { redisConfig } from "src/configs/redis.config";
import { QUEUE_NAMES } from "./constants/queue-name.enum";
import { RevokeRewardBalanceQueue } from "./queues/revoke-reward-balance.queue";
import { FutureEventModule } from "../future-event/future-event.module";

@Global()
@Module({
  imports: [
    BullModule.forRoot({
      redis: redisConfig,
    }),
    BullModule.registerQueue({
      name: QUEUE_NAMES.REVOKE_REWARD_BALANCE,
    }),
    FutureEventModule
  ],
  providers: [...(process.env.IS_REVOKE_BALANCE_WORKER === "true" ? [RevokeRewardBalanceQueue] : [])],
  exports: [BullModule],
})
export class BullMqModule {}

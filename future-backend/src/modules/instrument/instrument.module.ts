import { CacheModule, forwardRef, Logger, Module } from "@nestjs/common";
import { InstrumentController } from "src/modules/instrument/instrument.controller";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import InstrumentSeedCommand from "./instrument.console";
import { redisConfig } from "../../configs/redis.config";
import * as redisStore from "cache-manager-redis-store";
import { FundingModule } from "src/modules/funding/funding.module";
import { OrderModule } from "../order/order.module";

@Module({
  providers: [InstrumentService, Logger, InstrumentSeedCommand],
  controllers: [InstrumentController],
  exports: [InstrumentService],
  imports: [
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
    forwardRef(() => FundingModule),
    forwardRef(() => OrderModule),
  ],
})
export class InstrumentModule {}

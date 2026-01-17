import { CacheModule, Logger, Module, forwardRef } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import { DatabaseCommonModule } from "src/models/database-common";
import { OrderbookConsole } from "src/modules/orderbook/orderbook.console";
import { OrderbookController } from "src/modules/orderbook/orderbook.controller";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";
import { InstrumentService } from "../instrument/instrument.service";
import { InstrumentModule } from "../instrument/instrument.module";
import { AccountsModule } from "../account/account.module";
import { OrderModule } from "../order/order.module";
import { FixUpdatePublishOrderbookToSocketUsecase } from "./usecase/fix-update-publish-orderbook-to-socket.usecase";

@Module({
  providers: [Logger, OrderbookService, OrderbookConsole, FixUpdatePublishOrderbookToSocketUsecase],
  controllers: [OrderbookController],
  imports: [
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
    DatabaseCommonModule,
    forwardRef(() => InstrumentModule),
    forwardRef(() => AccountsModule),
    forwardRef(() => OrderModule),
  ],
  exports: [OrderbookService],
})
export class OrderbookModule {}

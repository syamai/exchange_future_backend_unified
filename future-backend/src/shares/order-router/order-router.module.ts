import { Global, Module } from "@nestjs/common";
import { OrderRouterService } from "./order-router.service";

@Global()
@Module({
  providers: [OrderRouterService],
  exports: [OrderRouterService],
})
export class OrderRouterModule {}

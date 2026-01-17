import { forwardRef, Module } from "@nestjs/common";
import { AccountsModule } from "src/modules/account/account.module";
import { OrderModule } from "src/modules/order/order.module";
import { UsersModule } from "src/modules/user/users.module";
import { OrderToolConsole } from "./order.tool";
import { CommonToolConsole } from "./common.tool";
import { PositionModule } from "src/modules/position/position.module";
import { TradeModule } from "src/modules/trade/trade.module";
import { ToolController } from "./tool.controller";

@Module({
  providers: [CommonToolConsole, OrderToolConsole],
  controllers: [ToolController],
  imports: [
    forwardRef(() => AccountsModule),
    forwardRef(() => OrderModule),
    forwardRef(() => UsersModule),
    forwardRef(() => PositionModule),
    forwardRef(() => TradeModule),
  ],
  exports: [],
})
export class ToolModule {}

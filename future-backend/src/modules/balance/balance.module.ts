import { Module, forwardRef } from "@nestjs/common";
import { BalanceService } from "./balance.service";
import { BalanceController } from "./balance.controller";
import { OrderModule } from "../order/order.module";
import { PositionModule } from "../position/position.module";
import { AccountsModule } from "../account/account.module";
import { IndexModule } from "../index/index.module";

@Module({
  imports: [
    forwardRef(() => OrderModule),
    forwardRef(() => PositionModule),
    forwardRef(() => AccountsModule),
    forwardRef(() => IndexModule),
  ],
  providers: [BalanceService],
  controllers: [BalanceController],
  exports: [BalanceService],
})
export class BalanceModule {}

import { Logger, Module, forwardRef } from "@nestjs/common";
import { DatabaseCommonModule } from "src/models/database-common";
import { AccountsModule } from "src/modules/account/account.module";
import { LatestBlockModule } from "src/modules/latest-block/latest-block.module";
import { TransactionConsole } from "src/modules/transaction/transaction.console";
import { TransactionService } from "src/modules/transaction/transaction.service";
import { TransactionController } from "./transaction.controller";

@Module({
  providers: [Logger, TransactionService, TransactionConsole],
  controllers: [TransactionController],
  imports: [DatabaseCommonModule, forwardRef(() => AccountsModule), LatestBlockModule],
  exports: [TransactionService],
})
export class TransactionModule {}

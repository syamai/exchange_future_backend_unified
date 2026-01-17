import { Logger, Module } from "@nestjs/common";
import { DatabaseCommonModule } from "src/models/database-common";
import { DexConsole } from "src/modules/dex/console/dex.console";
import { SolDexConsole } from "src/modules/dex/console/sol-dex.console";
import { DexService } from "src/modules/dex/service/dex.service";
import { SolDexService } from "src/modules/dex/service/sol-dex.service";
import { LatestBlockModule } from "src/modules/latest-block/latest-block.module";

@Module({
  imports: [DatabaseCommonModule, LatestBlockModule],
  providers: [Logger, DexConsole, SolDexConsole, DexService, SolDexService],
})
export class DexModule {}

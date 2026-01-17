import { Logger, Module } from "@nestjs/common";
import { LatestBlockService } from "src/modules/latest-block/latest-block.service";

@Module({
  providers: [LatestBlockService, Logger],
  exports: [LatestBlockService],
})
export class LatestBlockModule {}

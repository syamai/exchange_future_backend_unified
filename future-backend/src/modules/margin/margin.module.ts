import { Module } from "@nestjs/common";
import { MarginController } from "./margin.controller";
import { MarginService } from "./margin.service";

@Module({
  imports: [],
  controllers: [MarginController],
  providers: [MarginService],
})
export class MarginModule {}

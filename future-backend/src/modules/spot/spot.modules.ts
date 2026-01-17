import { Logger, Module } from "@nestjs/common";
import { SpotConsole } from "./spot.console";

@Module({
  providers: [Logger, SpotConsole],
  controllers: [],
  imports: [],
  exports: [],
})
export class SpotModule {}

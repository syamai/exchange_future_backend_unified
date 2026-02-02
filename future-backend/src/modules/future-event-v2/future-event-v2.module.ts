import { Module } from "@nestjs/common";
import { FutureEventV2Service } from "./future-event-v2.service";
import { FutureEventV2Controller } from "./future-event-v2.controller";
import { FutureEventV2Console } from "./future-event-v2.console";

@Module({
  providers: [FutureEventV2Service, FutureEventV2Console],
  controllers: [FutureEventV2Controller],
  exports: [FutureEventV2Service],
})
export class FutureEventV2Module {}

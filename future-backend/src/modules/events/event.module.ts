import { Logger, Module } from "@nestjs/common";
import { EventGateway } from "src/modules/events/event.gateway";
import { HealthService } from "../health/health.service";
import { JwtModule } from "@nestjs/jwt";
import { jwtConstants } from "../auth/auth.constants";

@Module({
  providers: [EventGateway, HealthService, Logger],
  imports: [
    JwtModule.register({
      secret: jwtConstants.accessTokenSecret,
      signOptions: { expiresIn: jwtConstants.accessTokenExpiry },
    }),
  ],
})
export class EventModule {}

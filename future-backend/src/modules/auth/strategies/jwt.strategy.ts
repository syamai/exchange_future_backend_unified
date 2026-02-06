import { ExtractJwt, Strategy } from "passport-jwt";
import { PassportStrategy } from "@nestjs/passport";
import { Injectable, HttpException, HttpStatus } from "@nestjs/common";
import { UserService } from "src/modules/user/users.service";
import { httpErrors } from "src/shares/exceptions";
import { UserIsLocked, UserStatus } from "src/shares/enums/user.enum";
import { JwtPayload } from "src/modules/auth/strategies/jwt.payload";
import { UserEntity } from "src/models/entities/user.entity";
import * as config from "config";

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  constructor(private userService: UserService) {
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      secretOrKey: config.get("jwt_key.public").toString(),
      algorithms: ["RS256"],
    });
  }

  async validate(payload: JwtPayload): Promise<UserEntity> {
    // Use cached version for high-performance JWT validation
    // This reduces DB queries from ~300/sec to near zero
    const user = await this.userService.findUserByIdCached(+payload.sub);

    if (!user) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    if (user.isLocked == UserIsLocked.LOCKED) {
      throw new HttpException(httpErrors.LOCKED_USER, HttpStatus.FORBIDDEN);
    }
    if (user.status === UserStatus.DEACTIVE) {
      await this.userService.updateStatusUser(user.id, UserStatus.ACTIVE);
    }

    return user;
  }
}

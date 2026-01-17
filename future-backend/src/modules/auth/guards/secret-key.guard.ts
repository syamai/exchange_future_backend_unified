/* eslint-disable @typescript-eslint/no-unused-vars */
import {
  CanActivate,
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import * as config from "config";
import { httpErrors } from "src/shares/exceptions";

@Injectable()
export class JwtSecretGuard implements CanActivate {
  constructor() {}
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const bearerHeader = request.headers.authorization.split(" ")[1];
    console.log(config.get<string>("secret.key"));
    if (!bearerHeader) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }

    // TODO
    if (bearerHeader !== config.get<string>("secret.key")) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    return true;
  }
}

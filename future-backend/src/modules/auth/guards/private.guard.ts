/* eslint-disable @typescript-eslint/no-unused-vars */
import {
  CanActivate,
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { SotaDexHeader } from "src/modules/auth/auth.constants";
import { UserService } from "src/modules/user/users.service";
import { httpErrors } from "src/shares/exceptions";
import { serializeMessage } from "src/shares/helpers/authHelper";

// const signatureIgnore = config.get<boolean>('app.signature_ignore');

@Injectable()
export class PrivateGuard implements CanActivate {
  constructor(private userService: UserService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();

    const [key, signature, timestamp] = [
      request.headers[SotaDexHeader.APIKEY],
      request.headers[SotaDexHeader.SIGNATURE],
      request.headers[SotaDexHeader.TIMESTAMP],
    ];
    if (!key || !signature || !timestamp) {
      return false;
    }
    const currentTimestamp = Math.floor(new Date().getTime());
    if (
      !(
        currentTimestamp - timestamp >= -10000 &&
        currentTimestamp - timestamp <= 30000
      )
    ) {
      throw new HttpException(
        httpErrors.APIKEY_TIMESTAMP_TOO_OLD,
        HttpStatus.BAD_REQUEST
      );
    }

    const user = await this.userService.getUserByApiKey(key);
    request.userId = user.id;

    const message = serializeMessage({
      timestamp,
      method: request.method.toUpperCase(),
      url: request.url,
      data: request.body,
      query: request.params,
    });

    return true;
    // if (signatureIgnore && !(request.headers['enableauth'] == 'enableauth')) {
    //   return true;
    // }

    // return this.userService.checkRecoverSameAddress({
    //   address: key,
    //   message: message,
    //   signature,
    // });
  }
}

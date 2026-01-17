import {
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";
import { httpErrors } from "src/shares/exceptions";

@Injectable()
export class JwtBasicTokenGuard extends AuthGuard("jwt") {
  constructor() {
    super();
  }
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = await context.switchToHttp().getRequest();

    const base64BasicHeader = request.headers.authorization.split(" ")[1];
    if (!base64BasicHeader || base64BasicHeader == "") throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    const basicHeader = Buffer.from(base64BasicHeader, 'base64').toString('utf-8');

    const futureUser = basicHeader.split(':')[0];
    const futurePassword = basicHeader.split(':')[1];

    if (
      futureUser !== process.env.FUTURE_USER ||
      futurePassword !== process.env.FUTURE_PASSWORD
    ) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    
    return true;
  }
}

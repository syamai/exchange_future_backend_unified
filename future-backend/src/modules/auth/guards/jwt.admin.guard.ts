import {
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";
import { InjectConnection } from "@nestjs/typeorm";
import { AccessTokenRepository } from "src/models/repositories/access-token.repository";
import { httpErrors } from "src/shares/exceptions";
import { Connection } from "typeorm";
import fetch from "node-fetch";
@Injectable()
export class JwtAdminGuard extends AuthGuard("jwt") {
  private accessTokenRepository: AccessTokenRepository;
  constructor(@InjectConnection("report") private connection: Connection) {
    super();
    this.accessTokenRepository = this.connection.getCustomRepository(
      AccessTokenRepository
    );
  }
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const bearerHeader = request.headers.authorization;
    if (!bearerHeader) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    const checkAdminToken = await fetch(
      `${process.env.SPOT_URL_API}/admin/auth`,
      {
        method: "post",
        headers: {
          Authorization: bearerHeader,
        },
      }
    );
    console.log("=====================================================");
    console.log(checkAdminToken);
    if (checkAdminToken.status != 200) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    return true;
  }
}

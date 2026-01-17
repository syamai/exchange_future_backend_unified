import {
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";
import { AccessTokenRepository } from "src/models/repositories/access-token.repository";
import { firstValueFrom, isObservable } from "rxjs";
import { httpErrors } from "src/shares/exceptions";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import { Connection } from "typeorm";

// const signatureIgnore = config.get<boolean>('app.signature_ignore');

@Injectable()
export class JwtTokenGuard extends AuthGuard("jwt") {
  private accessTokenRepository: AccessTokenRepository;

  constructor(@InjectConnection("report") private connection: Connection) {
    super();
    this.accessTokenRepository = this.connection.getCustomRepository(
      AccessTokenRepository
    );
  }
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = await context.switchToHttp().getRequest();

    const bearerHeader = request.headers.authorization.split(" ")[1];
    const body = request.body;
    const futureUser = process.env.FUTURE_USER;
    const futurePassword = process.env.FUTURE_PASSWORD;

    if (
      futureUser !== body.futureUser ||
      futurePassword !== body.futurePassword
    ) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    if (!bearerHeader) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    } else {
      return true;
    }

    // const isExistAccessToken = await this.accessTokenRepository.findOne({
    //   where: {
    //     token: bearerHeader,
    //   },
    // });

    // if (!isExistAccessToken || isExistAccessToken.revoked) {
    //   throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    // }
    // console.log('DONEEEEEEE');
    // const result = super.canActivate(context);
    // if (isObservable(result)) {
    //   return firstValueFrom(result);
    // } else {
    //   return result;
    // }
  }
}

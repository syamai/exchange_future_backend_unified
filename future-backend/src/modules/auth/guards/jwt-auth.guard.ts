/* eslint-disable @typescript-eslint/no-unused-vars */
import {
  ExecutionContext,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";
import { AccessTokenRepository } from "src/models/repositories/access-token.repository";
import { Connection } from "typeorm";
import { InjectConnection } from "@nestjs/typeorm";
import { firstValueFrom, isObservable } from "rxjs";
import { httpErrors } from "src/shares/exceptions";
import { RedisService } from "nestjs-redis";
import fetch from "node-fetch";
import { API_KEY_PERMISSION, API_METHOD, EXPIRED } from "../auth.constants";
const crypto = require("crypto");
// const signatureIgnore = config.get<boolean>('app.signature_ignore');

@Injectable()
export class JwtAuthGuard extends AuthGuard("jwt") {
  private accessTokenRepository: AccessTokenRepository;
  constructor(
    @InjectConnection("master") private connection: Connection,
    private readonly redisService: RedisService
  ) {
    super();
    this.accessTokenRepository = this.connection.getCustomRepository(
      AccessTokenRepository
    );
  }
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const apiKey = request.headers.apikey;
    if (apiKey) {
      let data;
      const timestamp = parseFloat(request.headers.timestamp);
      const checkTimestamp = Date.now() / 1000 - timestamp;
      if (checkTimestamp > EXPIRED) {
        throw new HttpException(
          httpErrors.TIMESTAMP_EXPIRED,
          HttpStatus.BAD_REQUEST
        );
      }
      const signature = request.headers.signature;
      if (API_METHOD.includes(request.method)) {
        data = request?.body;
      } else {
        data = request?.query;
      }
      const dataEncrypt = { ...data, timestamp };
      this.handleEncrypt(dataEncrypt, signature);
      const checkApiKey = await fetch(
        `${process.env.SPOT_URL_API}/check-api-key`,
        {
          method: "GET",
          headers: {
            APIKEY: apiKey,
          },
        }
      );
      const resApikey = await checkApiKey?.json();
      const decodedApiKey = this.decodeAPIKEY(apiKey);
      const apiKeyPermissionInRedis = await this.redisService
        .getClient()
        .get(`laravel:${resApikey?.scopes}_${decodedApiKey}`);
      let permission;
      if (apiKeyPermissionInRedis) {
        permission = apiKeyPermissionInRedis;
      } else {
        permission = resApikey.scopes;
      }
      this.checkPermissionRead(permission, request);
      const apiKeyInRedis = await this.redisService
        .getClient()
        .get(`laravel:${decodedApiKey}`);
      if (apiKeyInRedis) {
        request.headers.authorization =
          "Bearer " + apiKeyInRedis?.split(`"`)[1];
      } else {
        request.headers.authorization = resApikey.token;
      }
    } else {
      const bearerHeader = request.headers.authorization?.split(" ")[1];

      if (!bearerHeader) {
        throw new HttpException(
          httpErrors.UNAUTHORIZED,
          HttpStatus.UNAUTHORIZED
        );
      }
    }
    // await new Promise((resolve) => setTimeout(resolve, 1000));
    const result = await super.canActivate(context);

    if (isObservable(result)) {
      return firstValueFrom(result);
    } else {
      return result;
    }
  }

  checkPermissionRead(permission: string, request) {
    if (
      !permission.includes(API_KEY_PERMISSION.ID) &&
      API_METHOD.includes(request.method)
    ) {
      throw new HttpException(
        httpErrors.NOT_HAVE_ACCESS,
        HttpStatus.BAD_REQUEST
      );
    }
  }

  handleEncrypt(data, signature) {
    const encryptData = this.encryptSHA256(JSON.stringify(data));
    if (encryptData !== signature) {
      throw new HttpException(
        httpErrors.SIGNATURE_IS_NOT_VALID,
        HttpStatus.BAD_REQUEST
      );
    }
  }
  encryptSHA256(input) {
    const hash = crypto.createHash("sha256");
    hash.update(input);
    return hash.digest("hex");
  }

  decodeAPIKEY(apiKey: string) {
    const encrypt = "6fe17230cd48b9a5";
    const replacements = "0123456789abcdef";

    let decodedKey = "";

    for (let i = 0; i < apiKey?.length; i++) {
      const char = apiKey[i];
      const index = encrypt.indexOf(char);

      if (index !== -1) {
        decodedKey += replacements[index];
      } else {
        decodedKey += char;
      }
    }
    return decodedKey;
  }
}

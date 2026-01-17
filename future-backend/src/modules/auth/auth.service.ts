import {
  CACHE_MANAGER,
  HttpException,
  HttpStatus,
  Inject,
  Injectable,
} from "@nestjs/common";
import { JwtService } from "@nestjs/jwt";
import { Cache } from "cache-manager";
import { createHash } from "crypto";
import {
  AUTH_CACHE_PREFIX,
  jwtConstants,
} from "src/modules/auth/auth.constants";
import { RefreshAccessTokenDto } from "src/modules/auth/dto/refresh-access-token.dto";
import { ResponseLogin } from "src/modules/auth/dto/response-login.dto";
import { JwtPayload } from "src/modules/auth/strategies/jwt.payload";
import { UserService } from "src/modules/user/users.service";
import { httpErrors } from "src/shares/exceptions";
import { v4 as uuidv4 } from "uuid";

// eslint-disable-next-line
const Web3 = require("web3");

@Injectable()
export class AuthService {
  private web3;

  constructor(
    private userService: UserService,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private jwtService: JwtService
  ) {
    this.web3 = new Web3();
  }

  async refreshAccessToken(
    refreshAccessTokenDto: RefreshAccessTokenDto
  ): Promise<ResponseLogin> {
    const { refreshToken, accessToken } = refreshAccessTokenDto;
    const oldHashAccessToken = await this.cacheManager.get<string>(
      `${AUTH_CACHE_PREFIX}${refreshToken}`
    );
    if (!oldHashAccessToken)
      throw new HttpException(
        httpErrors.REFRESH_TOKEN_EXPIRED,
        HttpStatus.BAD_REQUEST
      );

    const hashAccessToken = createHash("sha256")
      .update(accessToken)
      .digest("hex");
    if (hashAccessToken == oldHashAccessToken) {
      const keyPair = this.web3.eth.accounts.create();
      const key = keyPair.address.toLowerCase();
      const secret = keyPair.privateKey;
      const oldPayload = await this.decodeAccessToken(accessToken);
      oldPayload.key = key;
      delete oldPayload.iat;
      delete oldPayload.exp;
      const newAccessToken = this.generateAccessToken(oldPayload);
      const newRefreshToken = await this.generateRefreshToken(
        newAccessToken.accessToken
      );
      await this.cacheManager.del(`${AUTH_CACHE_PREFIX}${refreshToken}`);
      return {
        secret,
        ...newAccessToken,
        ...newRefreshToken,
      };
    } else
      throw new HttpException(
        httpErrors.REFRESH_TOKEN_EXPIRED,
        HttpStatus.BAD_REQUEST
      );
  }

  generateAccessToken(payload: JwtPayload): { accessToken: string } {
    return {
      accessToken: this.jwtService.sign(payload),
    };
  }

  async generateRefreshToken(
    accessToken: string
  ): Promise<{ refreshToken: string }> {
    const refreshToken = uuidv4();
    const hashedAccessToken = createHash("sha256")
      .update(accessToken)
      .digest("hex");
    await this.cacheManager.set<string>(
      `${AUTH_CACHE_PREFIX}${refreshToken}`,
      hashedAccessToken,
      {
        ttl: jwtConstants.refreshTokenExpiry,
      }
    );
    return {
      refreshToken: refreshToken,
    };
  }

  async verifyAccessToken(
    accessToken: string
  ): Promise<Record<string, unknown>> {
    return this.jwtService.verifyAsync(accessToken);
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async decodeAccessToken(accessToken: string): Promise<JwtPayload | any> {
    return this.jwtService.decode(accessToken);
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async listApiKey(address: string): Promise<any> {
    return this.userService.listApiKey(address);
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async createApiKey(address: string): Promise<any> {
    return this.userService.createApiKey(address);
  }

  async deleteApiKey(apiKey: string): Promise<{ apiKey: string }> {
    return this.userService.deleteApiKey(apiKey);
  }
}

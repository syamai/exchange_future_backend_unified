import { HttpException, HttpStatus, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { AccessToken } from "src/models/entities/access-tokens.entity";
import { AccessTokenRepository } from "src/models/repositories/access-token.repository";
import { httpErrors } from "src/shares/exceptions";

@Injectable()
export class AccessTokenService {
  static DEFAULT_7DAY_MS = 7 * 24 * 60 * 60 * 1000;

  constructor(
    @InjectRepository(AccessTokenRepository, "report")
    public readonly accessTokenRepoReport: AccessTokenRepository,
    @InjectRepository(AccessTokenRepository, "master")
    public readonly accessTokenRepoMaster: AccessTokenRepository
  ) {}

  async addAccessToken(token: string, userId: number): Promise<AccessToken> {
    const isExist: AccessToken = await this.accessTokenRepoMaster.findOne({
      where: { token },
    });
    if (isExist) {
      throw new HttpException(httpErrors.ACCESS_TOKEN_EXIST, HttpStatus.FOUND);
    }
    const accessToken = await this.accessTokenRepoMaster.save({
      userId,
      token,
    });
    return accessToken;
  }

  async removeAccessToken(
    token: string,
    userId: number
  ): Promise<{ message: string }> {
    const isExist: AccessToken = await this.accessTokenRepoMaster.findOne({
      where: { token, userId },
    });
    if (!isExist) {
      throw new HttpException(
        httpErrors.ACCESS_TOKEN_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }
    await this.accessTokenRepoMaster.remove(isExist);
    return { message: "Remove access token success" };
  }

  async revokeAllTokens(userId: number): Promise<{ message: string }> {
    await this.accessTokenRepoMaster.update({ userId }, { revoked: true });

    return { message: "Remove all access token success" };
  }
}

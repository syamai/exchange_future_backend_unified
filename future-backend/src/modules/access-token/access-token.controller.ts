import { Body, Controller, Delete, Post, Put, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { AccessToken } from "src/models/entities/access-tokens.entity";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { JwtTokenGuard } from "../auth/guards/jwt-token.guard";
import { AccessTokenService } from "./access-token.service";
import { AccessTokenDto } from "./dto/add-token.dto";

@Controller("access-token")
@ApiTags("Access-token")
@ApiBearerAuth()
export class AccessTokenController {
  constructor(private readonly accessTokenService: AccessTokenService) {}

  @Post("/v1")
  @UseGuards(JwtTokenGuard)
  async addAccessToken(
    @Body() body: AccessTokenDto,
    @UserID() userId: number
  ): Promise<ResponseDto<AccessToken>> {
    return {
      data: await this.accessTokenService.addAccessToken(body.token, userId),
    };
  }

  @Delete("/")
  @UseGuards(JwtTokenGuard)
  async removeAccessToken(
    @Body() body: AccessTokenDto,
    @UserID() userId: number
  ) {
    return {
      data: await this.accessTokenService.removeAccessToken(body.token, userId),
    };
  }

  @Put("/revoke-tokens")
  @UseGuards(JwtTokenGuard)
  async revokeAllTokens(@UserID() userId: number) {
    return {
      data: await this.accessTokenService.revokeAllTokens(userId),
    };
  }
}

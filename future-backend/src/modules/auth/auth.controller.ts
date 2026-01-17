import { Controller, Post, Body, Get, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiBody, ApiTags } from "@nestjs/swagger";
// import { LoginDto } from 'src/modules/auth/dto/login.dto';
import { AuthService } from "src/modules/auth/auth.service";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { ResponseLogin } from "src/modules/auth/dto/response-login.dto";
import { RefreshAccessTokenDto } from "src/modules/auth/dto/refresh-access-token.dto";
import { UserEntity } from "src/models/entities/user.entity";
import { UserService } from "src/modules/user/users.service";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PrivateGuard } from "src/modules/auth/guards/private.guard";
import { ApiKeyUserID } from "src/shares/decorators/api-key-user-id.decorator";
import { MailService } from "src/modules/mail/mail.service";
import { JwtService } from "@nestjs/jwt";

@Controller("auth")
@ApiTags("Auth")
@ApiBearerAuth()
export class AuthController {
  constructor(
    private readonly authService: AuthService,
    private readonly userService: UserService,
    private readonly mailService: MailService
  ) {}

  @Get("/current")
  @UseGuards(JwtAuthGuard)
  async currentUser(
    @UserID() userId: number
  ): Promise<ResponseDto<UserEntity & { pendingEmail: string }>> {
    const user = await this.userService.findUserById(userId);
    const pendingEmail = await this.mailService.getPendingEmail(user);
    return {
      data: {
        ...user,
        pendingEmail,
      },
    };
  }

  @Post("refresh-access-token")
  @ApiBody({
    type: RefreshAccessTokenDto,
  })
  async refreshAccessToken(
    @Body() refreshAccessTokenDto: RefreshAccessTokenDto
  ): Promise<ResponseDto<Partial<ResponseLogin>>> {
    return {
      data: await this.authService.refreshAccessToken(refreshAccessTokenDto),
    };
  }

  // @Get('api-keys')
  // @UseGuards(EthPrivateGuard)
  // async listApiKey(@Headers(SotaDexHeader.ADDRESS) address: string): Promise<ResponseDto<ApiKey[]>> {
  //   return {
  //     data: await this.authService.listApiKey(address),
  //   };
  // }

  // @Post('api-keys')
  // @UseGuards(EthPrivateGuard)
  // async createApiKey(@Headers(SotaDexHeader.ADDRESS) address: string) {
  //   return {
  //     data: await this.authService.createApiKey(address),
  //   };
  // }

  // @Delete('api-keys')
  // @UseGuards(EthPrivateGuard)
  // async deleteApiKey(@Query() params: DeleteApiKeyDto) {
  //   return {
  //     data: await this.authService.deleteApiKey(params.apiKey),
  //   };
  // }

  @Get("/me")
  @UseGuards(PrivateGuard)
  async me(@ApiKeyUserID() userId: number): Promise<ResponseDto<UserEntity>> {
    return {
      data: await this.userService.findUserById(userId),
    };
  }
}

import {
  Body,
  Controller,
  Get,
  HttpException,
  HttpStatus,
  Param,
  Post,
  Query,
  UseGuards,
} from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { BalanceService } from "./balance.service";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { AssetsDto } from "../account/dto/assets.dto";
import { AccountEntity } from "src/models/entities/account.entity";
import { GetInforBalanceDto } from "./balance.dto";
// import {JwtAdminGuard} from "../auth/guards/jwt.admin.guard";
// import {JwtTokenGuard} from "../auth/guards/jwt-token.guard";
// import {CANCEL_ORDER_TYPE, ContractType} from "../../shares/enums/order.enum";
import { httpErrors } from "../../shares/exceptions";
import { AdminGetListUserBalanceDto } from "./dto/admin-get-list-user-balance.dto";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { AdminGetBalanceByUserDto } from "./dto/admin-balance-by-user.dto";

@Controller("balance")
@ApiTags("balance")
@ApiBearerAuth()
export class BalanceController {
  constructor(private readonly balanceService: BalanceService) {}

  @Get("/")
  @UseGuards(JwtAuthGuard)
  async getAllAccountByOwner(
    @UserID() userId: number
  ): Promise<ResponseDto<AccountEntity[]>> {
    return {
      data: await this.balanceService.getUserBalance(userId),
    };
  }

  @Get("/assets")
  @UseGuards(JwtAuthGuard)
  async getAssets(@UserID() userId: number): Promise<AssetsDto> {
    const assets = await this.balanceService.getAssets(userId);
    return assets;
  }

  @Get("/infor")
  @UseGuards(JwtAuthGuard)
  async getBalanceInfor(
    @UserID() userId: number,
    @Query() query: GetInforBalanceDto
  ) {
    return this.balanceService.getInforBalance(userId, query.symbol);
  }

  @Get("/total-balances/:userId")
  async getBalanceFuture(
    @Param("userId") userId: number,
    @Body("futureUser") futureUser: string,
    @Body("futurePassword") futurePassword: string
  ) {
    const futureUserEnv = process.env.FUTURE_USER;
    const futurePasswordEnv = process.env.FUTURE_PASSWORD;
    if (futureUser !== futureUserEnv || futurePassword !== futurePasswordEnv) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    return {
      data: await this.balanceService.getUserBalance(userId),
    };
  }

  @Get("/user-balances")
  async getTotalBalanceAllUser(
    @Body("futureUser") futureUser: string,
    @Body("futurePassword") futurePassword: string
  ) {
    const futureUserEnv = process.env.FUTURE_USER;
    const futurePasswordEnv = process.env.FUTURE_PASSWORD;
    if (futureUser !== futureUserEnv || futurePassword !== futurePasswordEnv) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }
    return {
      data: await this.balanceService.getTotalUserBalances(),
    };
  }

  @Post("/admin-get-list-user-balances")
  // @UseGuards(JwtAdminGuard)
  async adminGetListUserBalances(@Body() body: AdminGetListUserBalanceDto) {
    return await this.balanceService.adminGetListUserBalances(body.userIds)
  }

  @Get("/admin-get-balance-by-user")
  @UseGuards(JwtAdminGuard)
  async adminGetBalanceByUser(@Query() query: AdminGetBalanceByUserDto) {
    return await this.balanceService.adminGetBalanceByUser(query)
  }

}

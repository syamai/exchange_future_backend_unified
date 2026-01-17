/* eslint-disable @typescript-eslint/no-unused-vars */
import {
  Body,
  Controller,
  Get,
  Param,
  Post,
  Put,
  Query,
  UseGuards,
} from "@nestjs/common";
import { ApiBearerAuth, ApiQuery, ApiTags } from "@nestjs/swagger";
import { AccountHistoryEntity } from "src/models/entities/account-history.entity";
import { AccountEntity } from "src/models/entities/account.entity";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { AccountService } from "src/modules/account/account.service";
import { WithdrawalDto } from "src/modules/account/dto/body-withdraw.dto";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { FromToDto } from "src/shares/dtos/from-to.dto";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { TransactionType } from "src/shares/enums/transaction.enum";
import { BalanceDto } from "./dto/balance.dto";
import { DepositDto } from "./dto/body-deposit.dto";

@Controller("account")
@ApiTags("Account")
@ApiBearerAuth()
export class AccountController {
  constructor(private readonly accountService: AccountService) {}

  @Get("")
  @UseGuards(JwtAuthGuard)
  async getAccountByUserId(
    @UserID() userId: number
  ): Promise<ResponseDto<AccountEntity>> {
    return {
      data: await this.accountService.getFirstAccountByOwnerId(userId),
    };
  }

  @Get("/:symbol")
  @UseGuards(JwtAuthGuard)
  async getAllAccountByOwner(
    @UserID() userId: number,
    @Param("symbol") symbol: string
  ): Promise<ResponseDto<AccountEntity>> {
    return {
      data: await this.accountService.getFirstAccountByOwnerId(userId, symbol),
    };
  }

  @Post("/withdraw")
  @UseGuards(JwtAuthGuard)
  async withdrawal(
    @UserID() ownerId: number,
    @Body() withdrawDto: WithdrawalDto
  ): Promise<ResponseDto<unknown>> {
    return {
      data: await this.accountService.withdraw(ownerId, withdrawDto),
    };
  }

  @Get("/history/balance/:symbol")
  @UseGuards(JwtAuthGuard)
  @ApiQuery({
    name: "from",
    required: false,
    example: new Date().getTime() - 7 * 24 * 60 * 60 * 1000,
  })
  @ApiQuery({
    name: "to",
    required: false,
    example: new Date().getTime(),
  })
  async getAccountBalanceFromTo(
    @UserID() userId: number,
    @Query() ft: FromToDto,
    @Param("symbol") symbol: string
  ): Promise<ResponseDto<AccountHistoryEntity[]>> {
    const account = await this.accountService.getFirstAccountByOwnerId(
      userId,
      symbol
    );
    const balances = await this.accountService.findBalanceFromTo(
      account.id,
      ft
    );
    return {
      data: balances,
    };
  }

  @Get("/balance/:symbol")
  @UseGuards(JwtAuthGuard)
  async getBalance(
    @UserID() userId: number,
    @Param("symbol") symbol: string
  ): Promise<BalanceDto> {
    // const balances = await this.accountService.getBalance(userId);
    const balances = await this.accountService.getBalanceV2(userId, symbol);
    return {
      ...balances,
    };
  }

  @Get("/history/transfer/:symbol")
  @UseGuards(JwtAuthGuard)
  @ApiQuery({
    name: "type",
    required: false,
    example: TransactionType.DEPOSIT,
    enum: [TransactionType.DEPOSIT, TransactionType.WITHDRAWAL],
  })
  async getTransferHistory(
    @UserID() userId: number,
    @Query() paging: PaginationDto,
    @Query("type") type: string,
    @Param("symbol") symbol: string
  ): Promise<ResponseDto<TransactionEntity[]>> {
    const account = await this.accountService.getFirstAccountByOwnerId(
      userId,
      symbol
    );
    const response = await this.accountService.getTransferHistory(
      account.id,
      type,
      paging
    );
    return response;
  }
}

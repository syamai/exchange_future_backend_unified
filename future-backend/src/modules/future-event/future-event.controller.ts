import { Body, Controller, Get, Post, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiOperation, ApiResponse, ApiTags } from "@nestjs/swagger";
import { InjectRepository } from "@nestjs/typeorm";
import { UserRewardFutureEventUsedEntity } from "src/models/entities/user-reward-future-event-used.entity";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { UserRewardFutureEventUsedRepository } from "src/models/repositories/user-reward-future-event-used.repository";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { JwtAuthGuard } from "../auth/guards/jwt-auth.guard";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { AdminRevokeRewardDto } from "./dto/admin-revoke-reward.dto";
import { AdminRewardUsageDto } from "./dto/admin-reward-usage.dto";
import { FutureEventRevokeRewardService } from "./future-event-revoke-reward.service";
import { FutureEventService } from "./future-event.service";

@ApiTags("Future Event")
@Controller("future-event")
@ApiBearerAuth()
export class FutureEventController {
  constructor(
    private readonly futureEventService: FutureEventService,
    private readonly futureEventRevokeRewardService: FutureEventRevokeRewardService,

    @InjectRepository(UserRewardFutureEventUsedRepository, "master")
    private readonly userRewardFutureEventUsedRepoMaster: UserRewardFutureEventUsedRepository,

    private readonly kafkaClient: KafkaClient,

  ) {}

  @Get("rewards")
  @UseGuards(JwtAuthGuard)
  @ApiOperation({ summary: "Get future event rewards with pagination" })
  @ApiResponse({
    status: 200,
    description: "Returns paginated future event rewards",
  })
  async getFutureEventRewards(
    @Query() paginationDto: PaginationDto,
    @UserID() userId: number
  ): Promise<ResponseDto<UserRewardFutureEventEntity[]>> {
    const [rewards, total] = await this.futureEventService.getFutureEventRewardsWithPagination(paginationDto, userId);

    return {
      data: rewards,
      metadata: {
        totalPage: Math.ceil(total / paginationDto.size),
        total,
      },
    };
  }

  @Get("admin/reward-usage")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Admin endpoint to get reward usage history with pagination and filters" })
  @ApiResponse({
    status: 200,
    description: "Returns paginated reward usage history with filters and statistics",
  })
  async getRewardUsageHistoryAdmin(
    @Query() paginationDto: PaginationDto,
    @Query() filters: AdminRewardUsageDto
  ): Promise<ResponseDto<UserRewardFutureEventUsedEntity[]>> {
    const [usageHistory, total, additionalInfo] = await this.futureEventService.getRewardUsageHistoryAdminWithPagination(
      paginationDto,
      filters
    );

    return {
      data: usageHistory,
      metadata: {
        totalPage: Math.ceil(total / paginationDto.size),
        total,
        additionalInfo,
      },
    };
  }

  @Get("admin/dashboard")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Get last 7 days statistics for reward issuance and usage" })
  @ApiResponse({
    status: 200,
    description: "Returns statistics for the last 7 days",
  })
  async getDashboard() {
    const data = await this.futureEventService.getLast7DaysStatistics();
    return {
      data,
    };
  }

  @Post("admin/revoke-reward")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Admin revoke reward balance with amount" })
  @ApiResponse({
    status: 200,
    description: "",
  })
  async adminRevokeReward(@Body() dto: AdminRevokeRewardDto) {
    const data = await this.futureEventRevokeRewardService.adminRevokeRewardBalance(dto.userId, dto.amount);
    return {
      data,
    };
  }

  @Get("current-target-trading-volume")
  @UseGuards(JwtAuthGuard)
  @ApiOperation({ summary: "Get user trading volume and target volume" })
  @ApiResponse({
    status: 200,
    description: "Get user trading volume and target volume",
  })
  async getUserCurrentAndTargetTradingVolume(@UserID() userId: number) {
    const result = await this.futureEventService.getUserCurrentAndTargetTradingVolume(userId);

    return result;
  }

  // @Get("update-voucher-used-detail")
  // async updateVoucherUsedDetail() {
  //   const rewards = await this.userRewardFutureEventUsedRepoMaster.find({ where: { userId: 9045 }, order: { id: "ASC" } });
  //   for (const reward of rewards) {
  //     await this.kafkaClient.send(FutureEventKafkaTopic.reward_balance_used_to_process_used_detail, reward);
  //   }
  // }
}

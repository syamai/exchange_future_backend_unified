import { AdminOrderDto } from "./dto/admin-order.dto";
import { Body, CACHE_MANAGER, Controller, Delete, Get, Inject, Logger, Param, Post, Put, Query, Res, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiOperation, ApiTags } from "@nestjs/swagger";
import { OrderEntity } from "src/models/entities/order.entity";
import { AccountService } from "src/modules/account/account.service";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { OrderService } from "src/modules/order/order.service";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { CANCEL_ORDER_TYPE, ContractType, ORDER_TPSL } from "src/shares/enums/order.enum";
import { CreateOrderDto } from "./dto/create-order.dto";
import { OpenOrderDto } from "./dto/open-order.dto";
import { OrderHistoryDto } from "./dto/order-history.dto";
import { UpdateTpSlOrderDto } from "./dto/update-tpsl-order.dto";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { GetOrderHistoryForPartner } from "./dto/get-order-history-for-partner.dto";
import { JwtTokenGuard } from "../auth/guards/jwt-token.guard";
import { Response } from "express";
import { AdminCancelOrderDto } from "./dto/admin-cancel-order.dto";
import { Cache } from "cache-manager";
import { ENABLE_CREATE_ORDER } from "./order.const";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { GetOpenOrdersByAccountFromRedisUseCase } from "./usecase/get-open-orders-by-account-from-redis.usecase";
import { IsTestingRequest } from "src/shares/decorators/is-testing-request.decorator";

@Controller("order")
@ApiTags("Order")
@ApiBearerAuth()
export class OrderController {
  constructor(
    private readonly orderService: OrderService, 
    private readonly accountService: AccountService, 
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly redisClient: RedisClient,
    private readonly getOpenOrdersByAccountFromRedisUseCase: GetOpenOrdersByAccountFromRedisUseCase
  ) {}

  @Post("/history")
  @UseGuards(JwtAuthGuard)
  async getHistoryOrders(
    @UserID() userId: number,
    @Query() paging: PaginationDto,
    @Body() orderHistoryDto: OrderHistoryDto
  ): Promise<ResponseDto<OrderEntity[]>> {
    //const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const response = await this.orderService.getHistoryOrders(userId, paging, orderHistoryDto);
    return response;
  }

  @Get()
  @UseGuards(JwtAdminGuard)
  async getAllOrderAdmin(@Query() paging: PaginationDto, @Query() queries: AdminOrderDto): Promise<ResponseDto<OrderEntity[]>> {
    const response = await this.orderService.getOrderByAdmin(paging, queries);
    return response;
  }

  @Get("excel")
  @UseGuards(JwtAdminGuard)
  async exportAllOrderAdminExcelFile(@Query() paging: PaginationDto, @Query() queries: AdminOrderDto) {
    return await this.orderService.exportOrderAdminExcelFile(paging, queries);
  }

  @Get("/my-order/:orderId")
  @UseGuards(JwtAuthGuard)
  async getOneOrder(@UserID() userId: number, @Param("orderId") orderId: string): Promise<OrderEntity> {
    const response = await this.orderService.getOneOrderV2(String(orderId), userId);
    return response;
  }

  @Post("/open")
  @UseGuards(JwtAuthGuard)
  async getAllOrder(
    @Query() paging: PaginationDto,
    @UserID() userId: number,
    @Body() openOrderDto: OpenOrderDto
  ): Promise<ResponseDto<OrderEntity[]>> {
    //const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const response = await this.orderService.getOpenOrderByAccountId(paging, userId, openOrderDto);
    // const response = await this.getOpenOrdersByAccountFromRedisUseCase.execute(paging, userId, openOrderDto);
    return response;
  }

  @Post("/backup")
  @UseGuards(JwtAuthGuard)
  @ApiOperation({
    description: `
    When place:
    Limit order: type = "LIMIT", tpSLType = ""
    Market order: type = "MARKET", tpSLType = ""
    Stop limit order: type = "LIMIT", tpSLType = "STOP_LIMIT", tpSLPrice, stopCondition, trigger
    Stop market order: type = "MARKET", tpSLType = "STOP_MARKET", tpSLPrice, stopCondition, trigger
    Trailing stop order: type = "MARKET", tpSLType = "TRAILING_STOP", stopCondition, activationPrice, callbackRate,
    Post only order: isPostOnly = true
    `,
  })
  async createOrder(@Body() createOrderDto: CreateOrderDto, @UserID() userId: number): Promise<ResponseDto<OrderEntity>> {
    // Count number of requests received from client
    const redisClient = (this.cacheManager.store as any).getClient();
    await redisClient.incrby("numOfReqsReceivedFromClient", 1);
    await redisClient.expire("numOfReqsReceivedFromClient", 3600000000000);

    // if status = true => disable create order
    const checkStatusEnableCreateOrder = await this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER);
    if (checkStatusEnableCreateOrder) {
      return;
    }
    const account = await this.accountService.getFirstAccountByOwnerId(userId, createOrderDto.asset);
    const validatedCreateOrder = await this.orderService.validateOrder(createOrderDto, {
      accountId: account.id,
      userId: account.userId,
      email: account.userEmail,
    });

    return {
      data: await this.orderService.createOrder(validatedCreateOrder, {
        accountId: account.id,
        userId,
        email: account.userEmail,
      }),
    };
  }

  @Post('/backup2')
  @UseGuards(JwtAuthGuard)
  @ApiOperation({
    description: `
    When place:
    Limit order: type = "LIMIT", tpSLType = ""
    Market order: type = "MARKET", tpSLType = ""
    Stop limit order: type = "LIMIT", tpSLType = "STOP_LIMIT", tpSLPrice, stopCondition, trigger
    Stop market order: type = "MARKET", tpSLType = "STOP_MARKET", tpSLPrice, stopCondition, trigger
    Trailing stop order: type = "MARKET", tpSLType = "TRAILING_STOP", stopCondition, activationPrice, callbackRate,
    Post only order: isPostOnly = true
    `,
  })
  async createOrderOptimized(@Body() createOrderDto: CreateOrderDto, @UserID() userId: number): Promise<ResponseDto<OrderEntity>> {
    // Count number of requests received from client
    // const redisClient = (this.cacheManager.store as any).getClient();
    // await redisClient.incrby("numOfReqsReceivedFromClient", 1);
    // await redisClient.expire("numOfReqsReceivedFromClient", 3600000000000);

    const account = await this.accountService.getFirstAccountByOwnerIdForCreateOrder(userId, createOrderDto.asset);
    const accountData = {
      accountId: account.id,
      userId: account.userId,
      email: account.userEmail,
      balance: Number(account.balance)
    }
    const validatedCreateOrder = await this.orderService.validateOrder(createOrderDto, accountData);

    return {
      data: await this.orderService.createOrderOptimized({
        createOrderDto: validatedCreateOrder, 
        accountData
      }),
    };
  }

  @Post('/')
  @UseGuards(JwtAuthGuard)
  @ApiOperation({
    description: `
    When place:
    Limit order: type = "LIMIT", tpSLType = ""
    Market order: type = "MARKET", tpSLType = ""
    Stop limit order: type = "LIMIT", tpSLType = "STOP_LIMIT", tpSLPrice, stopCondition, trigger
    Stop market order: type = "MARKET", tpSLType = "STOP_MARKET", tpSLPrice, stopCondition, trigger
    Trailing stop order: type = "MARKET", tpSLType = "TRAILING_STOP", stopCondition, activationPrice, callbackRate,
    Post only order: isPostOnly = true
    `,
  })
  async createOrderOptimizedV2(@Body() createOrderDto: CreateOrderDto, @UserID() userId: number, @IsTestingRequest() isTesting?: boolean): Promise<ResponseDto<OrderEntity>> {
    return {
      data: await this.orderService.createOrderOptimizedV2({
        createOrderDto, 
        userId,
        isTesting
      }),
    };
  }

  @Delete("/cancel-order")
  @UseGuards(JwtAuthGuard)
  async cancelOrderByType(
    @UserID() userId: number,
    @Query("type") type: CANCEL_ORDER_TYPE,
    @Query("contractType") contractType: ContractType
  ): Promise<ResponseDto<OrderEntity[]>> {
    //const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const canceledOrders = await this.orderService.cancelAllOrder(userId, type, contractType);
    return {
      data: canceledOrders,
    };
  }

  @Get("/get-root-order")
  @UseGuards(JwtAuthGuard)
  async getRootOrder(
    @UserID() userId: number,
    @Query("orderId") orderId: number,
    @Query("type") type: ORDER_TPSL
  ): Promise<ResponseDto<OrderEntity>> {
    const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const canceledOrders = await this.orderService.getRootOrder(account.id, orderId, type);
    return {
      data: canceledOrders,
    };
  }
  
  @Delete("/admin-cancel-order")
  @UseGuards(JwtAdminGuard)
  async adminCancelOrder(@Query() query: AdminCancelOrderDto): Promise<ResponseDto<OrderEntity>> {
    
    const canceledOrder = await this.orderService.cancelOrder(String(query.orderId), query.userId);
    return {
      data: canceledOrder,
    };
  }

  @Delete("/:orderId")
  @UseGuards(JwtAuthGuard)
  async cancelOrder(@Param("orderId") orderId: string, @UserID() userId: number): Promise<ResponseDto<OrderEntity>> {
    //const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const canceledOrder = await this.orderService.cancelOrderV2(String(orderId), userId);
    return {
      data: canceledOrder,
    };
  }

  @Get("/tp-sl/:rootOrderId")
  @UseGuards(JwtAuthGuard)
  async getTpSlOrder(@Param("rootOrderId") rootOrderId: string) {
    const orders = await this.orderService.getTpSlOrder(String(rootOrderId));
    return {
      data: orders,
    };
  }

  @Put("/tp-sl/:rootOrderId")
  @UseGuards(JwtAuthGuard)
  async updateTpSlOrder(
    @UserID() userId: number,
    @Body() updateTpSlOrderDto: UpdateTpSlOrderDto[],
    @Param("rootOrderId") rootOrderId: string
  ) {
    // const account = await this.accountService.getFirstAccountByOwnerId(userId);
    const orders = await this.orderService.updateTpSlOrder(userId, updateTpSlOrderDto, String(rootOrderId));
    return {
      data: orders,
    };
  }
}

import { Injectable } from "@nestjs/common";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { OpenOrderDto } from "../dto/open-order.dto";
import { RedisClient } from "src/shares/redis-client/redis-client";
import {
  ContractType,
  ORDER_TPSL,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
} from "src/shares/enums/order.enum";
import { OrderEntity } from "src/models/entities/order.entity";
import { ResponseDto } from "src/shares/dtos/response.dto";

@Injectable()
export class GetOpenOrdersByAccountFromRedisUseCase {
  constructor(private readonly redisClient: RedisClient) {}

  public async execute(
    paging: PaginationDto,
    userId: number,
    openOrderDto: OpenOrderDto
  ): Promise<ResponseDto<OrderEntity[]>> {
    let orders = await this.getOrdersFromRedisAndApplyFilter(
      userId,
      openOrderDto
    );

    // Sort orders by createdAt and id descending
    orders.sort((a, b) => {
      if (new Date(b.createdAt).getTime() === new Date(a.createdAt).getTime()) {
        return b.id - a.id;
      }
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });
    const totalItem = orders.length;

    // Apply pagination
    const start = (paging.page - 1) * paging.size;
    const end = start + paging.size;
    if (!openOrderDto.getAll) {
      orders = orders.slice(start, end);
    }

    return {
      data: orders,
      metadata: {
        totalPage: Math.ceil(totalItem / paging.size),
        totalItem,
      },
    };
  }

  private async getOrdersFromRedisAndApplyFilter(
    userId: number,
    openOrderDto: OpenOrderDto
  ): Promise<OrderEntity[]> {
    const keyWithActiveOrderIds = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${userId}:activeOrderIds`;
    const orderIds = await this.redisClient
      .getInstance()
      .smembers(keyWithActiveOrderIds);

    const batch = 1000;
    const orders: OrderEntity[] = [];
    for (let i = 0; i < orderIds.length; i += batch) {
      const batchOrderIds = orderIds.slice(i, i + batch);

      const pipeline = this.redisClient.getInstance().multi();
      for (const orderId of batchOrderIds) {
        const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${userId}:orderId_${orderId}`;
        pipeline.zrevrange(keyWithOrderId, 0, 0, "WITHSCORES");
      }

      const memberResults = await pipeline.exec();

      for (let j = 0; j < batchOrderIds.length; j++) {
        const members = memberResults[j][1];
        if (!members || members.length < 2) continue;

        const order = JSON.parse(members[members.length - 2]) as OrderEntity;
        if (
          order.status?.toString() !== OrderStatus.ACTIVE.toString() &&
          order.status?.toString() !== OrderStatus.UNTRIGGERED.toString()
        ) {
          continue;
        }

        if (
          order.status?.toString() === OrderStatus.ACTIVE.toString() &&
          order.type?.toString() === OrderType.MARKET.toString() && 
          order.timeInForce?.toString() === OrderTimeInForce.IOC.toString() &&
          order.isTpSlOrder == false 
        ) {
          continue;
        }

        // Apply filters
        if (
          openOrderDto.contractType &&
          openOrderDto.contractType !== ContractType.ALL &&
          order.contractType !== openOrderDto.contractType
        ) {
          continue;
        }

        if (
          openOrderDto.symbol &&
          String(order.symbol) !== String(openOrderDto.symbol)
        ) {
          continue;
        }

        if (
          openOrderDto.side &&
          order.side.toString() !== openOrderDto.side.toString()
        ) {
          continue;
        }

        orders.push(order);
      }
    }

    return orders;
  }
}

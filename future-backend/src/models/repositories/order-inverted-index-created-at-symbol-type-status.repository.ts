import { MIN_ORDER_ID, OrderEntity } from "src/models/entities/order.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { OrderHistoryDto } from "src/modules/order/dto/order-history.dto";
import {
  CANCEL_ORDER_TYPE,
  ContractType,
  OrderStatus,
  OrderType,
} from "src/shares/enums/order.enum";
import { EntityRepository, LessThan, Repository, SelectQueryBuilder } from "typeorm";

import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { OrderInvertedIndexCreatedAtSymbolTypeStatusEntity } from "../entities/order_inverted_index_createdAt_symbol_type_status.entity";
import { InjectRepository } from "@nestjs/typeorm";
// import { OrderStatus } from '@0x/protocol-utils';

@EntityRepository(OrderInvertedIndexCreatedAtSymbolTypeStatusEntity)
export class OrderInvertedIndexCreatedAtSymbolTypeStatusRepository extends BaseRepository<OrderInvertedIndexCreatedAtSymbolTypeStatusEntity>{
  constructor(
    @InjectRepository(OrderInvertedIndexCreatedAtSymbolTypeStatusEntity)
    private readonly repo: Repository<OrderInvertedIndexCreatedAtSymbolTypeStatusEntity>,
  ) {
    super();
  }

  public async getLastId(): Promise<number> {
    return null;
  }

  // public async save(records: OrderInvertedIndexCreatedAtSymbolTypeStatusEntity[]) {
  //   return this.repo.save(records);
  // }

  // public async findOrderBatch(
  //   status: OrderStatus,
  //   fromId: number,
  //   count: number
  // ): Promise<OrderEntity[]> {
  //   return this.createQueryBuilder()
  //     .where("id > :fromId", { fromId })
  //     .andWhere("status = :status", { status })
  //     .orderBy("id", "ASC")
  //     .take(count)
  //     .getMany();
  // }

  // public async findAccountOrderBatch(
  //   userId: number,
  //   status: OrderStatus,
  //   fromId: number,
  //   count: number,
  //   types: string[],
  //   cancelOrderType: CANCEL_ORDER_TYPE,
  //   contractType: ContractType
  // ): Promise<OrderEntity[]> {
  //   const query = this.createQueryBuilder()
  //     .where("id > :fromId", { fromId })
  //     .andWhere("userId = :userId", { userId })
  //     .andWhere("`status` = :status", { status })
  //     .andWhere("contractType = :contractType", { contractType });
  //   switch (cancelOrderType) {
  //     case CANCEL_ORDER_TYPE.ALL:
  //       query.andWhere(`(type in (:types) or tpSLType in (:types))`, { types });
  //       break;
  //     case CANCEL_ORDER_TYPE.LIMIT:
  //       query.andWhere("type = :limitType and tpSLType is null", {
  //         limitType: OrderType.LIMIT,
  //       });
  //       break;
  //     case CANCEL_ORDER_TYPE.STOP:
  //       query.andWhere("tpSLType in (:types)", { types });
  //       break;
  //     default:
  //       break;
  //   }
  //   return await query.orderBy("id", "ASC").take(count).getMany();
  // }

  // public async getLastId(): Promise<number> {
  //   const entity = await this.findOne({
  //     where: { id: LessThan(MIN_ORDER_ID) },
  //     order: { id: "DESC" },
  //   });
  //   if (entity) {
  //     return entity.id;
  //   } else {
  //     return 0;
  //   }
  // }

  // public async genQueryGetOrderHistory(
  //   orderHistoryDto: OrderHistoryDto,
  //   startTime: string,
  //   endTime: string,
  //   userId: number,
  //   offset: number,
  //   limit: number
  // ) {
  //   //const ignoreOrderStatus = `'${OrderStatus.UNTRIGGERED}', '${OrderStatus.PENDING}', '${OrderStatus.ACTIVE}'`;
  //   const query = this.createQueryBuilder("order")
  //     .where("userId = :userId", { userId })
  //     .andWhere("updatedAt BETWEEN :startTime and :endTime ", {
  //       startTime,
  //       endTime,
  //     })
  //     .andWhere("isHidden = false")
  //     .andWhere("contractType = :contractType", {
  //       contractType: orderHistoryDto.contractType,
  //     })
  //     .orderBy("updatedAt", "DESC")
  //     .addOrderBy("id", "DESC")
  //     .limit(limit)
  //     .offset(offset);
  //   if (orderHistoryDto.symbol) {
  //     query.andWhere("symbol = :symbol", { symbol: orderHistoryDto.symbol });
  //   }
  //   if (orderHistoryDto.side) {
  //     query.andWhere("side = :side", { side: orderHistoryDto.side });
  //   }
  //   if (orderHistoryDto.type) {
  //     switch (orderHistoryDto.type) {
  //       case OrderType.LIMIT:
  //       case OrderType.MARKET:
  //         query.andWhere("note is null and type = :type and tpSLType is null", {
  //           type: orderHistoryDto.type,
  //         });
  //         break;
  //       case OrderType.LIQUIDATION:
  //         query.andWhere("note = :note", { note: OrderType.LIQUIDATION });
  //         break;
  //       case OrderType.STOP_LOSS_MARKET:
  //         query.andWhere("tpSLType = :tpSlType and isTpSlOrder = true", {
  //           tpSlType: OrderType.STOP_MARKET,
  //         });
  //         break;
  //       case OrderType.STOP_MARKET:
  //         query.andWhere("tpSLType = :tpSlType and isTpSlOrder = false", {
  //           tpSlType: OrderType.STOP_MARKET,
  //         });
  //         break;
  //       default:
  //         query.andWhere("tpSLType = :type", { type: orderHistoryDto.type });
  //         break;
  //     }
  //   }

  //   if (!orderHistoryDto.status) {
  //     query.andWhere(`status not in ('ACTIVE', 'PENDING', 'UNTRIGGERED')`);
  //     await this.genQueryGetPartiallyFilledOrder(
  //       orderHistoryDto,
  //       startTime,
  //       endTime,
  //       query,
  //       userId
  //     );
  //   } else if (orderHistoryDto.status !== OrderStatus.PARTIALLY_FILLED) {
  //     query.andWhere("status = :status", { status: orderHistoryDto.status });
  //   } else {
  //     query.andWhere(
  //       "remaining > 0 and remaining < quantity and status = :status",
  //       { status: OrderStatus.ACTIVE }
  //     );
  //   }

  //   if (orderHistoryDto.contractType) {
  //     query.andWhere("contractType = :contractType", {
  //       contractType: orderHistoryDto.contractType,
  //     });
  //   }
  //   // if (orderHistoryDto.type) {
  //   //   if ([OrderType.LIMIT, OrderType.MARKET].includes(orderHistoryDto.type)) {
  //   //     query.andWhere('note is null and type = :type and tpSLType is null', { type: orderHistoryDto.type });
  //   //   } else if (orderHistoryDto.type == OrderType.LIQUIDATION) {
  //   //     query.andWhere('note = :note', { note: OrderType.LIQUIDATION });
  //   //   } else if (orderHistoryDto.type == OrderType.STOP_LOSS_MARKET) {
  //   //     query.andWhere(`tpSLType = :tpSlType and isTpSlOrder = true`, { tpSlType: OrderType.STOP_MARKET });
  //   //   } else if (orderHistoryDto.type == OrderType.STOP_MARKET) {
  //   //     query.andWhere('tpSLType = :tpSlType and isTpSlOrder = false', { tpSlType: OrderType.STOP_MARKET });
  //   //   } else {
  //   //     query.andWhere('tpSLType = :type', { type: orderHistoryDto.type });
  //   //   }
  //   // }
  //   // if (!orderHistoryDto.status) {
  //   //   query.andWhere(`status not in ('ACTIVE', 'PENDING', 'UNTRIGGERED')`);
  //   //   await this.genQueryGetPartiallyFilledOrder(orderHistoryDto, startTime, endTime, query, userId);
  //   // }
  //   // if (orderHistoryDto.status && orderHistoryDto.status !== OrderStatus.PARTIALLY_FILLED) {
  //   //   query.andWhere('status  = :status', { status: orderHistoryDto.status });
  //   // }
  //   // if (orderHistoryDto.status == OrderStatus.PARTIALLY_FILLED) {
  //   //   query.andWhere('remaining > 0 and remaining < quantity and status = :status', { status: OrderStatus.ACTIVE });
  //   // }
  //   // if (orderHistoryDto.contractType) {
  //   //   query.andWhere('contractType = :contractType', { contractType: orderHistoryDto.contractType });
  //   // }
  //   const [orders, count] = await Promise.all([
  //     query.getMany(),
  //     query.getCount(),
  //   ]);

  //   return { orders, count };
  // }
  // public async getOrderHistory(
  //   orderHistoryDto: OrderHistoryDto,
  //   startTime: string,
  //   endTime: string,
  //   userId: number,
  //   paging: PaginationDto
  // ) {
  //   const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
  //   const dataOrder = await this.genQueryGetOrderHistory(
  //     orderHistoryDto,
  //     startTime,
  //     endTime,
  //     userId,
  //     offset,
  //     limit
  //   );
  //   return dataOrder;
  // }

  // public async genQueryGetPartiallyFilledOrder(
  //   orderHistoryDto: OrderHistoryDto,
  //   startTime: string,
  //   endTime: string,
  //   query: SelectQueryBuilder<OrderEntity>,
  //   userId: number
  // ) {
  //   const parameters = {
  //     status: OrderStatus.ACTIVE,
  //     startTime,
  //     endTime,
  //     userId,
  //     contractType: orderHistoryDto.contractType,
  //   };

  //   let commonCondition =
  //     "userId = :userId and  updatedAt BETWEEN :startTime and :endTime  and status = :status and (remaining > 0 and remaining < quantity) and contractType = :contractType";

  //   if (orderHistoryDto.type) {
  //     if ([OrderType.LIMIT, OrderType.MARKET].includes(orderHistoryDto.type)) {
  //       commonCondition +=
  //         " and note is null and type = :type and tpSLType is null";
  //     } else if (orderHistoryDto.type == OrderType.LIQUIDATION) {
  //       commonCondition += " and note = :note";
  //       parameters["note"] = OrderType.LIQUIDATION;
  //     } else if (orderHistoryDto.type == OrderType.STOP_LOSS_MARKET) {
  //       commonCondition += " and tpSLType = :tpSlType and isTpSlOrder = true";
  //       parameters["tpSlType"] = OrderType.STOP_MARKET;
  //     } else if (orderHistoryDto.type == OrderType.STOP_MARKET) {
  //       commonCondition += " and tpSLType = :tpSlType and isTpSlOrder = false";
  //       parameters["tpSlType"] = OrderType.STOP_MARKET;
  //     } else {
  //       commonCondition += " and note is null and tpSLType = :type";
  //     }
  //     parameters["type"] = orderHistoryDto.type;
  //   }

  //   if (orderHistoryDto.status) {
  //     commonCondition += " and status = :status";
  //     parameters["status"] = orderHistoryDto.status;
  //   }

  //   if (orderHistoryDto.side) {
  //     commonCondition += " and side = :side";
  //     parameters["side"] = orderHistoryDto.side;
  //   }

  //   if (orderHistoryDto.symbol) {
  //     commonCondition += " and symbol = :symbol";
  //     parameters["symbol"] = orderHistoryDto.symbol;
  //   }
  //   // let commonCondition =
  //   //   'userId = :userId and  updatedAt BETWEEN :startTime and :endTime  and status =:status and( remaining > 0 and remaining < quantity) and contractType = :contractType';
  //   // const parameters = {
  //   //   status: OrderStatus.ACTIVE,
  //   //   startTime,
  //   //   endTime,
  //   //   userId,
  //   //   contractType: orderHistoryDto.contractType,
  //   // };

  //   // if (orderHistoryDto.type) {
  //   //   if ([OrderType.LIMIT, OrderType.MARKET].includes(orderHistoryDto.type)) {
  //   //     commonCondition = `${commonCondition} and note is null and type = :type and tpSLType is null`;
  //   //   } else if (orderHistoryDto.type == OrderType.LIQUIDATION) {
  //   //     commonCondition = `${commonCondition} and note = :note`;
  //   //     parameters['note'] = OrderType.LIQUIDATION;
  //   //   } else if (orderHistoryDto.type == OrderType.STOP_LOSS_MARKET) {
  //   //     commonCondition = `${commonCondition} and tpSLType = :tpSlType and isTpSlOrder = true`;
  //   //     parameters['tpSlType'] = OrderType.STOP_MARKET;
  //   //   } else if (orderHistoryDto.type == OrderType.STOP_MARKET) {
  //   //     commonCondition = `${commonCondition} and tpSLType = :tpSlType and isTpSlOrder = false`;
  //   //     parameters['tpSlType'] = OrderType.STOP_MARKET;
  //   //   } else {
  //   //     commonCondition = `${commonCondition} and note is null and tpSLType = :type `;
  //   //   }
  //   //   parameters['type'] = orderHistoryDto.type;
  //   // }
  //   // if (orderHistoryDto.status) {
  //   //   commonCondition = `${commonCondition} and status =:status`;
  //   //   parameters['status'] = orderHistoryDto.status;
  //   // }
  //   // if (orderHistoryDto.side) {
  //   //   commonCondition = `${commonCondition} and side =:side`;
  //   //   parameters['side'] = orderHistoryDto.side;
  //   // }
  //   // if (orderHistoryDto.symbol) {
  //   //   commonCondition = `${commonCondition} and symbol =:symbol`;
  //   //   parameters['symbol'] = orderHistoryDto.symbol;
  //   // }
  //   query.orWhere(`(${commonCondition})`, parameters);
  // }
}

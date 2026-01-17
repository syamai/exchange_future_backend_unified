import { EntityRepository, Repository } from "typeorm";
import { OrderAverageByTradeEntity } from "../entities/order-average-by-trade.entity";

@EntityRepository(OrderAverageByTradeEntity)
export class OrderAverageByTradeRepository extends Repository<OrderAverageByTradeEntity> {}

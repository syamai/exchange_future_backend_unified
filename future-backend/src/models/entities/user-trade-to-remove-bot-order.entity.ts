import { Expose } from "class-transformer";
import { Column, Entity, PrimaryColumn } from "typeorm";

@Entity({ name: "user_trade_to_remove_bot_order" })
export class UserTradeToRemoveBotOrderEntity {
  @PrimaryColumn({ name: "id", type: "bigint", unsigned: true })
  @Expose()
  id: number; // tradeId

  @Column({ name: "sellOrderId", type: "bigint", unsigned: true })
  @Expose()
  sellOrderId: number;

  @Column({ name: "buyOrderId", type: "bigint", unsigned: true })
  @Expose()
  buyOrderId: number;
}

import { Expose } from "class-transformer";
import {
  Column,
  Entity,
  PrimaryGeneratedColumn,
} from "typeorm";

@Entity({
  name: "order_average_by_trade",
})
export class OrderAverageByTradeEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  orderId: number;

  @Column({
    type: "enum",
    enum: ["BUY", "SELL"],
  })
  @Expose()
  type: string;

  @Column()
  @Expose()
  symbol: string;

  @Column({ default: false })
  @Expose()
  isCoinM: boolean;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalQuantityMulOrDivPrice: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  totalQuantity: string;

  @Column({
    type: "decimal",
    precision: 30,
    scale: 15,
    default: 0,
  })
  @Expose()
  average: string;
}

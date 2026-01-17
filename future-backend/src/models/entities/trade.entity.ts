import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import { Column, Entity, PrimaryGeneratedColumn } from "typeorm";

@Entity({ name: "trades" })
export class TradeEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  buyOrderId: number;

  @Column()
  @Expose()
  sellOrderId: number;

  @Column()
  @Expose()
  buyAccountId: number;

  @Column()
  @Expose()
  sellAccountId: number;

  @Column()
  @Expose()
  buyUserId: number;

  @Column()
  @Expose()
  sellUserId: number;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Expose()
  price: string;

  @Column()
  @Expose()
  quantity: string;

  @Column()
  @Expose()
  buyFee: string;

  @Column()
  @Expose()
  sellFee: string;

  @Column()
  @Expose()
  realizedPnlOrderBuy: string;

  @Column()
  @Expose()
  realizedPnlOrderSell: string;

  @Column()
  @Expose()
  buyerIsTaker: boolean;

  @Column()
  @Expose()
  note: string;

  @Column()
  @Expose()
  operationId: string;

  @Column()
  @Expose()
  contractType: string;

  @Column()
  @Expose()
  buyEmail: string;

  @Column()
  @Expose()
  sellEmail: string;

  @Column()
  @Expose()
  @Transform(dateTransformer)
  createdAt: Date;

  @Column()
  @Expose()
  @Transform(dateTransformer)
  updatedAt: Date;

  public equals(o: TradeEntity): boolean {
    return this.id === o.id;
  }
}

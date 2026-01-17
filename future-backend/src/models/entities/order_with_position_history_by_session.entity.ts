import { Expose } from "class-transformer";
import { Entity, PrimaryGeneratedColumn, Column } from "typeorm";

@Entity({ name: "order_with_position_history_by_session" })
export class OrderWithPositionHistoryBySessionEntity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  @Expose()
  id: string;

  @Column({ type: "bigint" })
  @Expose()
  orderId: string;

  @Column({ type: "bigint" })
  @Expose()
  positionHistoryBySessionId: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  orderMarginAfter?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  entryPriceAfter?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  currentQtyAfter?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  entryValueAfter?: string;

  @Column({ type: "boolean", default: true })
  @Expose()
  isOpenOrder: boolean;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  fee?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  closeFee?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  openFee?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  profit?: string;

  @Column({ type: "decimal", precision: 22, scale: 8, nullable: true })
  @Expose()
  tradePriceAfter?: string;
} 
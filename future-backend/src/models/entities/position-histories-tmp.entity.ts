import { Column, Entity, PrimaryColumn } from "typeorm";

@Entity({
  name: "position_histories_tmp",
})
export class PositionHistoriesTmpEntity {
  @PrimaryColumn({ type: "bigint" })
  id: string;

  @Column({ type: "bigint" })
  userId: string;

  @Column({ type: "timestamp" })
  openTime: Date;

  @Column({ type: "varchar", length: 64 })
  pair: string;

  @Column({ type: "varchar", length: 16 })
  side: string;

  @Column({ type: "decimal" })
  maxSize: string;

  @Column({ type: "varchar", length: 32 })
  openOrClosedOrParClosedPosition: string;

  @Column({ type: "decimal" })
  pnl: string;

  @Column({ type: "decimal" })
  uPnl: string;

  @Column({ type: "decimal" })
  entryPrice: string;

  @Column({ type: "decimal", nullable: true })
  closedPrice: string;

  @Column({ type: "decimal", nullable: true })
  avgClosedPrice: string;

  @Column({ type: "varchar" })
  leverage: number;

  @Column({ type: "decimal" })
  maxMargin: string;

  @Column({ type: "timestamp", nullable: true })
  closeTime: Date;

  @Column({ type: "decimal", nullable: true })
  sumClosedPrice: string;

  @Column({ type: "int", nullable: true })
  totalClosedOrder: number;

  @Column({ type: "varchar", length: 128 })
  email: string;
}

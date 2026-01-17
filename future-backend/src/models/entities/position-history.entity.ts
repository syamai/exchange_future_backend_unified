import { Expose, Transform } from "class-transformer";
import { dateTransformer } from "src/shares/helpers/transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";

@Entity({
  name: "position_histories",
})
export class PositionHistoryEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  action: string;

  @Column()
  @Expose()
  positionId: string;

  @Column()
  @Expose()
  entryPrice: string;

  @Column()
  @Expose()
  entryPriceAfter: string;

  @Column()
  @Expose()
  entryValue: string;

  @Column()
  @Expose()
  asset: string;

  @Column()
  @Expose()
  entryValueAfter: string;

  @Column()
  @Expose()
  currentQty: string;

  @Column()
  @Expose()
  currentQtyAfter: string;

  @Column()
  @Expose()
  operationId: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

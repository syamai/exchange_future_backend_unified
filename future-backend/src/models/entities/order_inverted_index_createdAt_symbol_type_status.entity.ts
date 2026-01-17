import {
  Column,
  Entity,
  PrimaryColumn,
} from "typeorm";

@Entity({
  name: "orders_inverted_index_createdAt_symbol_type_status",
})
export class OrderInvertedIndexCreatedAtSymbolTypeStatusEntity {
  public id: number;

  @PrimaryColumn()
  public createdAt: string;

  @Column({ type: 'longtext' })
  public value: string;

  public valueObj: object;
}

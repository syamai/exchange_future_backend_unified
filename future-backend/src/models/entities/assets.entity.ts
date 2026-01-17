import { Transform } from "class-transformer";
import { AssetType } from "src/modules/transaction/transaction.const";
import { dateTransformer } from "src/shares/helpers/transformer";
import { Column, CreateDateColumn, Entity, PrimaryGeneratedColumn, UpdateDateColumn } from "typeorm";

@Entity({ name: "assets" })
export class AssetsEntity {
  @PrimaryGeneratedColumn({ type: "bigint" })
  id: string;

  @Column()
  asset: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;

  @Column()
  assetType: AssetType;
}

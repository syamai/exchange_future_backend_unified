import { Transform } from "class-transformer";
import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from "typeorm";
import { dateTransformer } from "../../shares/helpers/transformer";

@Entity({
  name: "coin_info",
})
export class CoinInfoEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  fullName: string;

  @Column({ unique: true })
  baseId: string;

  @Column()
  symbol: string;

  @Column({ unsigned: true })
  rank: number;

  @Column()
  marketCap: string;

  @Column()
  cirSupply: string;

  @Column()
  maxSupply: string;

  @Column()
  totalSupply: string;

  @Column()
  issueDate: Date;

  @Column()
  issuePrice: string;

  @Column()
  explorer: string;

  @Column()
  coin_image: string;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

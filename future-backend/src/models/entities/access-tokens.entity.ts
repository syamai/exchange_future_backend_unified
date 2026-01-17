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
  name: "access-tokens",
})
export class AccessToken {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({
    name: "token",
    type: "text",
    nullable: false,
  })
  @Expose()
  token: string;

  @Column({
    name: "user_id",
    type: "int",
    nullable: false,
  })
  @Expose()
  userId: number;

  @Column()
  @Expose()
  revoked: boolean;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

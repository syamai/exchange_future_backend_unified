import { Expose } from "class-transformer";
import { Column, Entity, PrimaryGeneratedColumn } from "typeorm";

@Entity({ name: "settings" })
export class ConfigEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  key: string;

  @Column()
  @Expose()
  value: string;

  @Column()
  @Expose()
  createdAt: Date;

  @Column()
  @Expose()
  updatedAt: Date;
}

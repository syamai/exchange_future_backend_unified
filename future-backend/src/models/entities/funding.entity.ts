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
  name: "fundings",
})
export class FundingEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  @Expose()
  symbol: string;

  @Column()
  @Transform(dateTransformer)
  time: Date; // Thời điểm dữ liệu được ghi hoặc cập nhật.

  @Column()
  @Expose()
  fundingInterval: string; // Khoảng thời gian giữa các lần tài trợ.

  @Column()
  @Expose()
  fundingRateDaily: string; // Tỷ lệ tài trợ hàng ngày.

  @Column()
  @Expose()
  fundingRate: string; // Tỷ lệ tài trợ hiện tại.

  @Column()
  @Expose()
  oraclePrice: string; // Giá oracle, giá được xác định bởi một nguồn thông tin bên ngoài.

  @Column()
  @Expose()
  paid: boolean; // Trạng thái thanh toán của khoản tài trợ.

  @Column()
  @Expose()
  nextFunding: number;

  @CreateDateColumn()
  @Transform(dateTransformer)
  createdAt: Date;

  @UpdateDateColumn()
  @Transform(dateTransformer)
  updatedAt: Date;
}

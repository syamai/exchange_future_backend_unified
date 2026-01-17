import { ApiProperty } from "@nestjs/swagger";
import { Transform } from "class-transformer";
import { IsEnum, IsOptional, IsString } from "class-validator";
import { ContractType, OrderSide, OrderStatus } from "src/shares/enums/order.enum";

export class AdminPositionDto {
  @ApiProperty({
    required: false,
    example: "2023-01-21",
  })
  @IsString()
  @IsOptional()
  from: string;

  @ApiProperty({
    required: false,
    example: "2023-02-21",
  })
  @IsString()
  @IsOptional()
  to: string;

  @ApiProperty({
    required: false,
    example: "BTCUSDT",
  })
  @IsString()
  @IsOptional()
  symbol: string;

  @ApiProperty({
    required: false,
    example: "USD_M",
  })
  @IsString()
  @IsOptional()
  contractType: ContractType;

  @ApiProperty({
    required: false,
    description: "Search by ID",
  })
  @IsString()
  @IsOptional()
  search_key: string;

  @ApiProperty({
    required: false,
    example: OrderSide.BUY,
  })
  @IsOptional()
  @IsEnum(OrderSide)
  side: OrderSide;

  @ApiProperty({
    example: OrderStatus.PENDING,
    enum: OrderStatus,
    required: false,
  })
  @IsOptional()
  @IsEnum([OrderStatus.PENDING, OrderStatus.PARTIALLY_FILLED])
  status: OrderStatus;

  @ApiProperty({
    required: false,
    example: false,
  })
  @IsOptional()
  @Transform(({ value }) => (value === "true" ? true : false))
  calcPnl: string;
}

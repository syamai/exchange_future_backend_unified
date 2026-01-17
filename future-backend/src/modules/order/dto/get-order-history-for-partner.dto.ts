import { ContractType, EDirection, EOrderBy, OrderSide, OrderStatus } from "src/shares/enums/order.enum";
import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsNumberString, IsOptional, IsString } from "class-validator";
import { Transform } from "class-transformer";

export class GetOrderHistoryForPartner {
  @ApiProperty({
    required: true,
    example: "2023-01-21",
  })
  @IsString()
  @IsOptional()
  from: string;

  @ApiProperty({
    required: true,
    example: "2023-02-21",
  })
  @IsString()
  @IsOptional()
  to: string;

  @ApiProperty({
    required: false,
    description: "Search by UID",
  })
  @IsOptional()
  @IsNumberString()
  userId: string;

  @ApiProperty({
    required: false,
    description: "Search by Order ID",
  })
  orderId: string;

  @ApiProperty({
    required: true,
    example: "USDT",
  })
  @IsString()
  @IsOptional()
  currency: string;
}

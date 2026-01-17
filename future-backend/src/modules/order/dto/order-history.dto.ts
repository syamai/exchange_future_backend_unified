import { ApiProperty } from "@nestjs/swagger";
import {
  IsIn,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
} from "class-validator";
import {
  ContractType,
  OrderSide,
  OrderStatus,
  OrderType,
} from "src/shares/enums/order.enum";

export class OrderHistoryDto {
  @ApiProperty({
    required: true,
    example: 1672194339532,
  })
  @IsNumber()
  @IsNotEmpty()
  startTime: number;

  @ApiProperty({
    required: true,
    example: 1682194339532,
  })
  @IsNumber()
  @IsNotEmpty()
  endTime: number;

  @ApiProperty({
    required: false,
    example: "BUY",
  })
  @IsString()
  @IsOptional()
  side: OrderSide;

  @ApiProperty({
    required: false,
    example: "LIMIT",
  })
  @IsString()
  @IsOptional()
  type: OrderType;

  @ApiProperty({
    required: false,
    example: "ADABTC_DELIVERY",
  })
  @IsString()
  @IsOptional()
  symbol: string;

  @ApiProperty({
    required: false,
    example: true,
  })
  @IsOptional()
  isActive: boolean;

  @ApiProperty({
    required: false,
    example: "FILLED",
  })
  @IsString()
  @IsOptional()
  status: OrderStatus;

  @ApiProperty({
    required: false,
    example: "USD_M",
  })
  @IsString()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;
}

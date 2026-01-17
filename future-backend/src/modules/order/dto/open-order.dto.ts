import { ApiProperty } from "@nestjs/swagger";
import { IsBoolean, IsIn, IsOptional, IsString } from "class-validator";
import { ContractType } from "src/shares/enums/order.enum";

export class OpenOrderDto {
  @ApiProperty({
    required: false,
    example: "BUY",
    description: "Side of order want to get",
  })
  @IsString()
  @IsOptional()
  side: string;

  @ApiProperty({
    required: false,
    example: "STOP_LIMIT",
    description: "Type of order",
    enum: [
      "LIMIT",
      "STOP_LIMIT",
      "STOP_MARKET",
      "TRAILING_STOP",
      "TAKE_PROFIT",
      "STOP_LOSS",
    ],
  })
  @IsString()
  @IsOptional()
  type: string;

  @ApiProperty({
    required: false,
    example: "ADABTC",
    description: "Get from /api/v1/ticker/24h",
  })
  @IsString()
  @IsOptional()
  symbol: string;

  @ApiProperty({
    required: false,
    example: "USD_M",
    enum: ["USD_M", "COIN_M"],
  })
  @IsString()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;

  @ApiProperty({
    required: false,
    description: "Get all (ignore pagination) if true",
  })
  @IsBoolean()
  @IsOptional()
  getAll: boolean;
}

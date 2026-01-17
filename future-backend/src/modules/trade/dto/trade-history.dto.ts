import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsNumber, IsOptional, IsString } from "class-validator";

export class TradeHistoryDto {
  @ApiProperty({
    required: true,
    example: 1671123600000,
    description: "Timestamp",
  })
  @IsNumber()
  @IsNotEmpty()
  startTime: number;

  @ApiProperty({
    required: true,
    example: 1681814800000,
    description: "Timestamp",
  })
  @IsNumber()
  @IsNotEmpty()
  endTime: number;

  @ApiProperty({
    required: true,
    example: "BUY",
    enum: ["BUY", "SELL", "ALL"],
  })
  @IsString()
  @IsNotEmpty()
  side: string;

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
  @IsNotEmpty()
  contractType: string;
}

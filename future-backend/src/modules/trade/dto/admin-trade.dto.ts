import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsOptional, IsString } from "class-validator";

export class AdminTradeDto {
  @ApiProperty({
    required: true,
    example: "2023-01-21",
  })
  @IsString()
  @IsNotEmpty()
  from: string;

  @ApiProperty({
    required: true,
    example: "2023-02-21",
  })
  @IsString()
  @IsNotEmpty()
  to: string;

  @ApiProperty({
    required: false,
    example: "BTCUSDT",
  })
  @IsString()
  @IsOptional()
  symbol: string;

  @ApiProperty({
    description: "Search tradeID, accountID, buyID, sellID",
    required: false,
  })
  @IsOptional()
  @IsString()
  search_key: string;

  @ApiProperty({
    required: false,
    example: "1",
  })
  @IsString()
  @IsOptional()
  userId: string;
}

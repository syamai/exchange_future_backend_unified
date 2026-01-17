import { ApiProperty } from "@nestjs/swagger";
import { IsArray, IsBoolean, IsNotEmpty, IsNumber, IsOptional, IsString } from "class-validator";

export class SetUserTradeSettingDto {
  @IsNotEmpty()
  @IsNumber()
  @ApiProperty({
    required: true,
    example: 1,
  })
  userId: number;

  @IsOptional()
  @IsBoolean()
  @ApiProperty({
    required: false,
    example: true,
  })
  allowTrade: boolean;

  @IsOptional()
  @IsBoolean()
  @ApiProperty({
    required: false,
    example: true,
  })
  enableTradingFee: boolean;

  @IsOptional()
  @IsBoolean()
  @ApiProperty({
    required: false,
    example: false,
  })
  isMarketMaker: boolean;

  @IsArray()
  @IsOptional()
  @IsString({ each: true })
  @ApiProperty({
    required: false,
    example: ["BTCUSDT", "BNBUSDM"],
  })
  preTradingPair: string[];
}

import { ApiProperty } from "@nestjs/swagger";
import { IsIn, IsOptional } from "class-validator";
import { ContractType } from "src/shares/enums/order.enum";

export class TradingRulesModeDto {
  @ApiProperty()
  @IsOptional()
  symbol: string;

  @ApiProperty()
  @IsOptional()
  minTradeAmount: string;

  @ApiProperty()
  @IsOptional()
  minOrderAmount: string;

  @ApiProperty()
  @IsOptional()
  minPrice: string;

  @ApiProperty()
  @IsOptional()
  limitOrderPrice: string;

  @ApiProperty()
  @IsOptional()
  floorRatio: string;

  @ApiProperty()
  @IsOptional()
  maxMarketOrder: string;

  @ApiProperty()
  @IsOptional()
  limitOrderAmount: string;

  @ApiProperty()
  @IsOptional()
  numberOpenOrders: string;

  @ApiProperty()
  @IsOptional()
  priceProtectionThreshold: string;

  @ApiProperty()
  @IsOptional()
  liqClearanceFee: string;

  @ApiProperty()
  @IsOptional()
  minNotional: string;

  @ApiProperty()
  @IsOptional()
  marketOrderPrice: string;

  @ApiProperty({ default: false })
  @IsOptional()
  isReduceOnly: boolean;

  @ApiProperty()
  @IsOptional()
  positionsNotional: string;

  @ApiProperty()
  @IsOptional()
  ratioOfPostion: string;

  @ApiProperty()
  @IsOptional()
  liqMarkPrice: string;

  @ApiProperty()
  @IsOptional()
  maxLeverage: number;

  @ApiProperty()
  @IsIn(Object.keys(ContractType))
  contractType: ContractType;

  @IsOptional()
  maxOrderAmount: string;
}

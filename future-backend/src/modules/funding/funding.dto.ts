import { IsNotEmpty } from "class-validator";

export class ImpactPrice {
  @IsNotEmpty()
  impactBidPrice: number;
  @IsNotEmpty()
  impactAskPrice: number;
  @IsNotEmpty()
  interestRate: number;
  maintainMargin?: number;
}

export class MarketIndex {
  @IsNotEmpty()
  symbol: string;
  @IsNotEmpty()
  price: number;
}

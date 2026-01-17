import { IsNotEmpty } from "class-validator";

export class CreateMarketFeeDto {
  @IsNotEmpty()
  instrumentId: number;

  @IsNotEmpty()
  makerFee: string;

  @IsNotEmpty()
  takerFee: string;
}

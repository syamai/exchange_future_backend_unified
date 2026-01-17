import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty } from "class-validator";

export class UpdateEnablePriceChangeFirebaseNotiDto {
  @ApiProperty({
    required: true,
    example: "BTCUSD",
  })
  @IsNotEmpty()
  symbol: string;

  @ApiProperty({
    required: true,
    example: true,
  })
  @IsNotEmpty()
  enable: boolean;
}

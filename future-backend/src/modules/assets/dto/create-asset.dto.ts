import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty } from "class-validator";
import { AssetType } from "src/modules/transaction/transaction.const";

export class CreateAssetDto {
  @ApiProperty({
    required: true,
    description: "Value to be created",
    example: "BTCUSDT",
  })
  @IsNotEmpty()
  asset: string;

  @ApiProperty({
    required: true,
    description: "Value to be created",
    example: "USD_M",
  })
  @IsNotEmpty()
  assetType: AssetType;
}

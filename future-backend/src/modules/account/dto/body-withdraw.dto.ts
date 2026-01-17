import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsNotEmpty } from "class-validator";
import { IsPositiveBigNumber } from "src/shares/decorators/positive-bignumber.decorator";
import { AssetType } from "src/shares/enums/transaction.enum";

export class WithdrawalDto {
  @ApiProperty()
  @IsNotEmpty()
  @IsPositiveBigNumber()
  amount: string;

  @ApiProperty()
  @IsNotEmpty()
  @IsEnum(AssetType)
  assetType: AssetType;
}

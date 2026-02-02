import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsNumber, IsOptional, IsString } from "class-validator";

export class GrantBonusV2Dto {
  @ApiProperty({ example: 1, description: "User ID" })
  @IsNotEmpty()
  @IsNumber()
  userId: number;

  @ApiProperty({ example: 1, description: "Account ID" })
  @IsNotEmpty()
  @IsNumber()
  accountId: number;

  @ApiProperty({ example: 1, description: "Event Setting ID" })
  @IsNotEmpty()
  @IsNumber()
  eventSettingId: number;

  @ApiProperty({ example: "1000", description: "Deposit amount (principal)" })
  @IsNotEmpty()
  @IsString()
  depositAmount: string;

  @ApiProperty({ example: "1000", description: "Bonus amount (optional, calculated from rate if not provided)" })
  @IsOptional()
  @IsString()
  bonusAmount?: string;

  @ApiProperty({ example: 123, description: "Transaction ID (optional for manual grant)" })
  @IsOptional()
  @IsNumber()
  transactionId?: number;
}

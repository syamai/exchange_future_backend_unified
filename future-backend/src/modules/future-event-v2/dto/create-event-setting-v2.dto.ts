import { ApiProperty } from "@nestjs/swagger";
import { IsDateString, IsNotEmpty, IsOptional, IsString } from "class-validator";

export class CreateEventSettingV2Dto {
  @ApiProperty({ example: "신규 입금 보너스 100%" })
  @IsNotEmpty()
  @IsString()
  eventName: string;

  @ApiProperty({ example: "DEPOSIT_BONUS_100" })
  @IsNotEmpty()
  @IsString()
  eventCode: string;

  @ApiProperty({ example: "100.00", description: "Bonus rate percentage" })
  @IsNotEmpty()
  @IsString()
  bonusRatePercent: string;

  @ApiProperty({ example: "100", description: "Minimum deposit amount" })
  @IsOptional()
  @IsString()
  minDepositAmount?: string;

  @ApiProperty({ example: "10000", description: "Maximum bonus amount" })
  @IsOptional()
  @IsString()
  maxBonusAmount?: string;

  @ApiProperty({ example: "2026-01-01T00:00:00Z" })
  @IsNotEmpty()
  @IsDateString()
  startDate: string;

  @ApiProperty({ example: "2026-12-31T23:59:59Z" })
  @IsNotEmpty()
  @IsDateString()
  endDate: string;
}

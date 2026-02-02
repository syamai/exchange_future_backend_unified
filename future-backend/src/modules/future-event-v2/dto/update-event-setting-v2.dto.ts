import { ApiProperty } from "@nestjs/swagger";
import { IsDateString, IsEnum, IsOptional, IsString } from "class-validator";
import { EventStatusV2 } from "../constants/event-status-v2.enum";

export class UpdateEventSettingV2Dto {
  @ApiProperty({ example: "신규 입금 보너스 100%", required: false })
  @IsOptional()
  @IsString()
  eventName?: string;

  @ApiProperty({ example: "ACTIVE", enum: EventStatusV2, required: false })
  @IsOptional()
  @IsEnum(EventStatusV2)
  status?: EventStatusV2;

  @ApiProperty({ example: "100.00", required: false })
  @IsOptional()
  @IsString()
  bonusRatePercent?: string;

  @ApiProperty({ example: "100", required: false })
  @IsOptional()
  @IsString()
  minDepositAmount?: string;

  @ApiProperty({ example: "10000", required: false })
  @IsOptional()
  @IsString()
  maxBonusAmount?: string;

  @ApiProperty({ example: "2026-01-01T00:00:00Z", required: false })
  @IsOptional()
  @IsDateString()
  startDate?: string;

  @ApiProperty({ example: "2026-12-31T23:59:59Z", required: false })
  @IsOptional()
  @IsDateString()
  endDate?: string;
}

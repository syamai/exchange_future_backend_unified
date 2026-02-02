import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsOptional, IsString } from "class-validator";
import { BonusStatusV2 } from "../constants/bonus-status-v2.enum";

export class AdminBonusV2QueryDto {
  @ApiProperty({ required: false, description: "Filter by user ID" })
  @IsOptional()
  userId?: number;

  @ApiProperty({ required: false, description: "Filter by account ID" })
  @IsOptional()
  accountId?: number;

  @ApiProperty({ required: false, description: "Filter by event setting ID" })
  @IsOptional()
  eventSettingId?: number;

  @ApiProperty({ required: false, enum: BonusStatusV2, description: "Filter by status" })
  @IsOptional()
  @IsEnum(BonusStatusV2)
  status?: BonusStatusV2;

  @ApiProperty({ required: false, description: "Start date filter (ISO 8601)" })
  @IsOptional()
  @IsString()
  startDate?: string;

  @ApiProperty({ required: false, description: "End date filter (ISO 8601)" })
  @IsOptional()
  @IsString()
  endDate?: string;

  @ApiProperty({ required: false, description: "Search by user email or UID" })
  @IsOptional()
  @IsString()
  search?: string;
}

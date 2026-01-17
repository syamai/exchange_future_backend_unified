import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsOptional, IsString } from "class-validator";
import { TransactionType } from "src/shares/enums/transaction.enum";

export class AdminRewardUsageDto {
  @ApiProperty({ required: false })
  @IsOptional()
  userId?: number;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  startDate?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  endDate?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  symbol?: string;

  @ApiProperty({ 
    required: false,
    description: "Search by transaction UUID, UUID, or user's email"
  })
  @IsOptional()
  @IsString()
  search?: string;

  @ApiProperty({ required: false, enum: Object.values(TransactionType) })
  @IsOptional()
  @IsString()
  transactionType?: string;
} 